<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectionService
{
    public function __construct(
        protected ApprovalService $approvalService
    ) {}

    /**
     * W6. Void a sale (same open shift only, no prior returns).
     */
    public function voidSale(Sale $sale, User $actor, string $reason, ?string $approvalToken = null): Sale
    {
        return DB::transaction(function () use ($sale, $actor, $reason, $approvalToken) {
            $lockedSale = Sale::query()->with(['items', 'payments', 'shift'])->lockForUpdate()->findOrFail($sale->id);

            if ($lockedSale->status !== 'completed') {
                throw ValidationException::withMessages([
                    'sale' => ['Hanya transaksi berstatus selesai (tanpa retur) yang dapat di-void (W6).'],
                ]);
            }

            if ($lockedSale->returns()->exists()) {
                throw ValidationException::withMessages([
                    'sale' => ['Transaksi yang sudah memiliki retur tidak dapat di-void. Gunakan fitur Retur (W6).'],
                ]);
            }

            $shift = Shift::query()->lockForUpdate()->findOrFail($lockedSale->shift_id);
            if ($shift->status !== 'open') {
                throw ValidationException::withMessages([
                    'shift' => ['Shift untuk transaksi ini sudah ditutup. Void ditolak agar laporan shift tetap immutable; silakan gunakan Retur (W6).'],
                ]);
            }

            $approvedBy = $this->approvalService->resolveApproverId($actor, 'void', $approvalToken);

            // Lock products in ascending order
            $productIds = $lockedSale->items->pluck('product_id')->unique()->sort()->values()->all();
            $lockedProducts = Product::query()
                ->withTrashed()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($lockedSale->items as $item) {
                /** @var Product|null $product */
                $product = $lockedProducts->get($item->product_id);
                if ($product && $product->track_stock) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'void',
                        'qty_delta' => (float) $item->qty,
                        'ref_type' => 'sale_void',
                        'ref_id' => $lockedSale->id,
                        'user_id' => $actor->id,
                        'note' => "Void nota {$lockedSale->invoice_no}: {$reason}",
                        'created_at' => now(),
                    ]);

                    $product->update([
                        'stock_qty' => round((float) $product->stock_qty + (float) $item->qty, 3),
                    ]);
                }
            }

            // Non-cash payments marked for manual reversal outside system (W6.4)
            $lockedSale->payments()->where('method', '!=', 'cash')->update([
                'needs_manual_reversal' => true,
            ]);

            $lockedSale->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
                'approved_by' => $approvedBy,
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'sale_voided',
                'entity' => 'sale',
                'entity_id' => $lockedSale->id,
                'before' => ['status' => 'completed', 'grand_total' => $lockedSale->grand_total],
                'after' => ['status' => 'voided', 'void_reason' => $reason],
                'reason' => $reason,
                'approved_by' => $approvedBy,
                'created_at' => now(),
            ]);

            return $lockedSale->fresh(['items', 'payments', 'register', 'cashier']);
        });
    }

    /**
     * W7. Return and refund.
     */
    public function returnSale(Sale $sale, User $actor, array $payload): SaleReturn
    {
        return DB::transaction(function () use ($sale, $actor, $payload) {
            $lockedSale = Sale::query()->with(['items'])->lockForUpdate()->findOrFail($sale->id);

            if (! in_array($lockedSale->status, ['completed', 'partially_returned'], true)) {
                throw ValidationException::withMessages([
                    'sale' => ['Transaksi ini tidak dapat diretur (status: '.$lockedSale->status.').'],
                ]);
            }

            $approvedBy = $this->approvalService->resolveApproverId(
                $actor,
                'return',
                $payload['approval_token'] ?? null
            );

            // Check return window (W7 Precondition)
            $returnWindowDays = (int) Setting::getValue('return_window_days', 7);
            $daysSinceSale = $lockedSale->created_at->diffInDays(now());
            if ($daysSinceSale > $returnWindowDays && ! $actor->isManagerOrOwner() && empty($payload['override_window'])) {
                throw ValidationException::withMessages([
                    'sale' => ["Batas waktu retur ({$returnWindowDays} hari) telah terlewati."],
                ]);
            }

            $refundMethod = strtolower((string) ($payload['refund_method'] ?? 'cash'));
            $currentShiftId = $payload['shift_id'] ?? null;

            if ($refundMethod === 'cash') {
                $shift = $currentShiftId
                    ? Shift::query()->lockForUpdate()->find($currentShiftId)
                    : Shift::query()->lockForUpdate()->where('cashier_id', $actor->id)->where('status', 'open')->first();

                if (! $shift || $shift->status !== 'open') {
                    throw ValidationException::withMessages([
                        'shift_id' => ['Retur tunai memerlukan shift aktif yang sedang terbuka (W7).'],
                    ]);
                }
                $currentShiftId = $shift->id;
            }

            $requestedItems = $payload['items'] ?? [];
            if (empty($requestedItems)) {
                throw ValidationException::withMessages([
                    'items' => ['Pilih minimal satu item untuk diretur.'],
                ]);
            }

            $saleItemsById = $lockedSale->items->keyBy('id');
            $productIds = $lockedSale->items->pluck('product_id')->unique()->sort()->values()->all();
            $lockedProducts = Product::query()
                ->withTrashed()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $returnLinesToCreate = [];
            $refundTotal = 0;

            foreach ($requestedItems as $idx => $req) {
                $saleItemId = (int) $req['sale_item_id'];
                $returnQty = round((float) $req['qty'], 3);
                $restock = (bool) ($req['restock'] ?? true);

                if ($returnQty <= 0) {
                    continue;
                }

                $saleItem = $saleItemsById->get($saleItemId);
                if (! $saleItem) {
                    throw ValidationException::withMessages([
                        "items.{$idx}" => ['Item penjualan tidak valid.'],
                    ]);
                }

                $remainingReturnableQty = round((float) $saleItem->qty - (float) $saleItem->returned_qty, 3);
                if ($returnQty > $remainingReturnableQty + 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$idx}" => ["Kuantitas retur untuk {$saleItem->name} melebihi sisa yang dapat diretur ({$remainingReturnableQty})."],
                    ]);
                }

                // Cumulative refunds on a line never exceed its net_total (W7.4 & Section 13)
                $alreadyRefundedOnLine = (int) SaleReturnItem::query()
                    ->where('sale_item_id', $saleItem->id)
                    ->sum('refund_amount');

                $maxRemainingRefund = max(0, (int) $saleItem->net_total - $alreadyRefundedOnLine);
                $isFinalRemainingQty = abs($returnQty - $remainingReturnableQty) < 0.0001;

                if ($isFinalRemainingQty) {
                    $lineRefund = $maxRemainingRefund;
                } else {
                    $proportional = (int) round(((int) $saleItem->net_total) * ($returnQty / (float) $saleItem->qty));
                    $lineRefund = min($maxRemainingRefund, $proportional);
                }

                $refundTotal += $lineRefund;

                $returnLinesToCreate[] = [
                    'sale_item' => $saleItem,
                    'qty' => $returnQty,
                    'refund_amount' => $lineRefund,
                    'restock' => $restock,
                ];
            }

            if (empty($returnLinesToCreate)) {
                throw ValidationException::withMessages([
                    'items' => ['Kuantitas retur harus lebih besar dari 0.'],
                ]);
            }

            $returnNo = 'RET-'.now()->format('ymd').'-'.str_pad((string) (SaleReturn::count() + 1), 4, '0', STR_PAD_LEFT);

            $saleReturn = SaleReturn::create([
                'sale_id' => $lockedSale->id,
                'shift_id' => $currentShiftId,
                'return_no' => $returnNo,
                'refund_method' => $refundMethod,
                'refund_total' => $refundTotal,
                'reason' => (string) $payload['reason'],
                'user_id' => $actor->id,
                'approved_by' => $approvedBy,
                'created_at' => now(),
            ]);

            foreach ($returnLinesToCreate as $lineData) {
                $saleItem = $lineData['sale_item'];

                $saleReturn->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'qty' => $lineData['qty'],
                    'refund_amount' => $lineData['refund_amount'],
                    'restock' => $lineData['restock'],
                ]);

                $saleItem->update([
                    'returned_qty' => round((float) $saleItem->returned_qty + $lineData['qty'], 3),
                ]);

                if ($lineData['restock']) {
                    /** @var Product|null $product */
                    $product = $lockedProducts->get($saleItem->product_id);
                    if ($product && $product->track_stock) {
                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => 'return',
                            'qty_delta' => $lineData['qty'],
                            'ref_type' => 'sale_return',
                            'ref_id' => $saleReturn->id,
                            'user_id' => $actor->id,
                            'note' => "Retur {$returnNo} dari {$lockedSale->invoice_no}",
                            'created_at' => now(),
                        ]);

                        $product->update([
                            'stock_qty' => round((float) $product->stock_qty + $lineData['qty'], 3),
                        ]);
                    }
                }
            }

            // Update sale status: partially_returned or returned
            $freshItems = $lockedSale->items()->get();
            $allReturned = $freshItems->every(fn ($i) => abs((float) $i->qty - (float) $i->returned_qty) < 0.0001);

            $lockedSale->update([
                'status' => $allReturned ? 'returned' : 'partially_returned',
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'sale_returned',
                'entity' => 'sale_return',
                'entity_id' => $saleReturn->id,
                'after' => [
                    'sale_id' => $lockedSale->id,
                    'return_no' => $returnNo,
                    'refund_total' => $refundTotal,
                    'refund_method' => $refundMethod,
                ],
                'reason' => (string) $payload['reason'],
                'approved_by' => $approvedBy,
                'created_at' => now(),
            ]);

            return $saleReturn->load(['items', 'sale', 'user']);
        });
    }
}

