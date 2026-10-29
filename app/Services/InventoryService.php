<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * W10. Receive stock (confirmed in a single step, immutable afterwards).
     */
    public function createStockReceipt(User $actor, array $payload): StockReceipt
    {
        return DB::transaction(function () use ($actor, $payload) {
            $items = $payload['items'] ?? [];
            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => ['Daftar barang masuk tidak boleh kosong.'],
                ]);
            }

            $productIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $lockedProducts = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $type = in_array($payload['type'] ?? 'purchase', ['opening', 'purchase'], true)
                ? $payload['type']
                : 'purchase';

            $receiptNo = 'RCV-'.now()->format('ymd').'-'.str_pad((string) (StockReceipt::count() + 1), 4, '0', STR_PAD_LEFT);

            $receipt = StockReceipt::create([
                'receipt_no' => $receiptNo,
                'type' => $type,
                'supplier_name' => $payload['supplier_name'] ?? null,
                'reference' => $payload['reference'] ?? null,
                'note' => $payload['note'] ?? null,
                'user_id' => $actor->id,
                'created_at' => now(),
            ]);

            foreach ($items as $idx => $item) {
                $qty = round((float) $item['qty'], 3);
                $unitCost = (int) $item['unit_cost'];

                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.qty" => ['Kuantitas penerimaan stok harus lebih dari 0 (W10).'],
                    ]);
                }

                if ($unitCost < 0) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.unit_cost" => ['Harga beli (unit cost) tidak boleh negatif.'],
                    ]);
                }

                /** @var Product|null $product */
                $product = $lockedProducts->get((int) $item['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.product_id" => ['Produk tidak ditemukan.'],
                    ]);
                }

                $receipt->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                ]);

                if ($product->track_stock) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => $type,
                        'qty_delta' => $qty,
                        'ref_type' => 'stock_receipt',
                        'ref_id' => $receipt->id,
                        'user_id' => $actor->id,
                        'note' => "Penerimaan {$receiptNo}",
                        'created_at' => now(),
                    ]);

                    $product->update([
                        'stock_qty' => round((float) $product->stock_qty + $qty, 3),
                        'cost' => $unitCost,
                    ]);
                } else {
                    $product->update(['cost' => $unitCost]);
                }
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'stock_receipt_created',
                'entity' => 'stock_receipt',
                'entity_id' => $receipt->id,
                'after' => ['receipt_no' => $receiptNo, 'type' => $type],
                'reason' => $payload['note'] ?? 'Penerimaan stok barang',
                'created_at' => now(),
            ]);

            return $receipt->load(['items.product', 'user']);
        });
    }

    /**
     * W11. Stock adjustment and opname.
     */
    public function createStockAdjustment(User $actor, array $payload): StockAdjustment
    {
        return DB::transaction(function () use ($actor, $payload) {
            $items = $payload['items'] ?? [];
            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => ['Pilih minimal satu produk untuk penyesuaian stok / opname.'],
                ]);
            }

            $reason = (string) ($payload['reason'] ?? 'opname');
            if (! in_array($reason, ['opname', 'damaged', 'expired', 'lost', 'correction'], true)) {
                throw ValidationException::withMessages([
                    'reason' => ['Alasan penyesuaian stok tidak valid.'],
                ]);
            }

            $productIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $lockedProducts = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $movementConflicts = [];
            foreach ($items as $idx => $item) {
                /** @var Product|null $product */
                $product = $lockedProducts->get((int) $item['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.product_id" => ['Produk tidak ditemukan.'],
                    ]);
                }

                // W11.3: If the product had any movement since the screen was opened (system_qty mismatch), flag for recount
                if (isset($item['expected_system_qty'])) {
                    $expectedSys = round((float) $item['expected_system_qty'], 3);
                    $actualSys = round((float) $product->stock_qty, 3);
                    if (abs($expectedSys - $actualSys) > 0.0001) {
                        $movementConflicts["items.{$idx}"] = "Stok {$product->name} berubah saat layar dibuka (awal: {$expectedSys}, sekarang: {$actualSys}). Harap hitung ulang (W11.3).";
                    }
                }
            }

            if (! empty($movementConflicts)) {
                throw ValidationException::withMessages($movementConflicts);
            }

            $adjustmentNo = 'ADJ-'.now()->format('ymd').'-'.str_pad((string) (StockAdjustment::count() + 1), 4, '0', STR_PAD_LEFT);

            $adjustment = StockAdjustment::create([
                'adjustment_no' => $adjustmentNo,
                'reason' => $reason,
                'note' => $payload['note'] ?? null,
                'user_id' => $actor->id,
                'created_at' => now(),
            ]);

            foreach ($items as $item) {
                /** @var Product $product */
                $product = $lockedProducts->get((int) $item['product_id']);
                $systemQty = round((float) $product->stock_qty, 3);
                $countedQty = round((float) $item['counted_qty'], 3);
                $delta = round($countedQty - $systemQty, 3);

                $adjustment->items()->create([
                    'product_id' => $product->id,
                    'system_qty' => $systemQty,
                    'counted_qty' => $countedQty,
                    'delta' => $delta,
                ]);

                if (abs($delta) > 0.0001 && $product->track_stock) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'adjustment',
                        'qty_delta' => $delta,
                        'ref_type' => 'stock_adjustment',
                        'ref_id' => $adjustment->id,
                        'user_id' => $actor->id,
                        'note' => "Penyesuaian {$adjustmentNo} ({$reason})",
                        'created_at' => now(),
                    ]);

                    $product->update([
                        'stock_qty' => $countedQty,
                    ]);
                }
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'stock_adjusted',
                'entity' => 'stock_adjustment',
                'entity_id' => $adjustment->id,
                'after' => ['adjustment_no' => $adjustmentNo, 'reason' => $reason],
                'reason' => $payload['note'] ?? $reason,
                'created_at' => now(),
            ]);

            return $adjustment->load(['items.product', 'user']);
        });
    }
}

