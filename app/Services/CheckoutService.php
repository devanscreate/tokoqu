<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected SaleCalculator $calculator,
        protected ApprovalService $approvalService,
        protected ShiftService $shiftService,
    ) {}

    /**
     * Confirm a sale (online or offline sync) inside a single ACID transaction (W4, W12, W13).
     */
    public function processSale(User $actor, array $payload, bool $isOfflineSync = false): Sale
    {
        $clientUuid = (string) $payload['client_uuid'];

        // Fast idempotency check before entering lock (R8)
        $existing = Sale::query()->with(['items', 'payments', 'register', 'cashier'])->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($actor, $payload, $isOfflineSync, $clientUuid) {
            // Re-check idempotency inside transaction
            $existingInside = Sale::query()->with(['items', 'payments', 'register', 'cashier'])->where('client_uuid', $clientUuid)->first();
            if ($existingInside) {
                return $existingInside;
            }

            $shift = Shift::query()->lockForUpdate()->findOrFail($payload['shift_id']);
            $register = Register::query()->findOrFail($shift->register_id);

            if (! $isOfflineSync) {
                if ($shift->status !== 'open') {
                    throw ValidationException::withMessages([
                        'shift_id' => ['Transaksi ditolak: tidak ada shift terbuka pada register ini (W4).'],
                    ]);
                }
                if ($shift->cashier_id !== $actor->id && ! $actor->isManagerOrOwner()) {
                    throw ValidationException::withMessages([
                        'shift_id' => ['Shift ini bukan milik akun kasir Anda.'],
                    ]);
                }
            }

            $settings = Setting::getMap();
            $allowNegativeStock = ($settings['allow_negative_stock'] ?? '0') === '1';
            $cashierDiscountLimitPct = (float) ($settings['cashier_discount_limit_pct'] ?? 10);
            $taxMode = $isOfflineSync
                ? ($payload['tax_mode'] ?? ($settings['tax_mode'] ?? 'off'))
                : ($settings['tax_mode'] ?? 'off');
            $taxRate = $isOfflineSync
                ? (float) ($payload['tax_rate'] ?? ($settings['tax_rate'] ?? 0))
                : (float) ($settings['tax_rate'] ?? 0);

            // Lock products in ascending ID order to prevent deadlocks (W4.6.3)
            $requestedLines = $payload['items'] ?? [];
            $productIds = collect($requestedLines)->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

            $lockedProducts = Product::query()
                ->withTrashed()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $stockConflicts = [];
            $outOfStockErrors = [];
            $calculatorLines = [];

            foreach ($requestedLines as $idx => $reqLine) {
                $productId = (int) $reqLine['product_id'];
                /** @var Product|null $product */
                $product = $lockedProducts->get($productId);

                if (! $product || (! $isOfflineSync && (! $product->is_active || $product->trashed()))) {
                    throw ValidationException::withMessages([
                        "items.{$idx}" => ['Produk tidak ditemukan atau sudah tidak aktif.'],
                    ]);
                }

                $qty = (float) $reqLine['qty'];

                // Stock check (R11)
                if ($product->track_stock) {
                    $newStock = (float) $product->stock_qty - $qty;
                    if ($newStock < -0.0001) {
                        if ($isOfflineSync) {
                            $stockConflicts[] = $product->id;
                        } elseif (! $allowNegativeStock) {
                            $outOfStockErrors["items.{$idx}"] = "Stok {$product->name} tidak mencukupi (tersedia: {$product->stock_qty}, diminta: {$qty}).";
                        }
                    }
                }

                // Price authority (R12): online re-prices from DB; offline uses snapshot
                $unitPrice = $isOfflineSync
                    ? (int) $reqLine['unit_price']
                    : (int) $product->price;

                $unitCost = $isOfflineSync
                    ? (int) ($reqLine['unit_cost'] ?? $product->cost)
                    : (int) $product->cost;

                $calculatorLines[] = [
                    'product_id' => $product->id,
                    'name' => $isOfflineSync ? (string) ($reqLine['name'] ?? $product->name) : $product->name,
                    'sku' => $isOfflineSync ? (string) ($reqLine['sku'] ?? $product->sku) : $product->sku,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'line_discount' => (int) ($reqLine['line_discount'] ?? 0),
                    'allow_decimal_qty' => (bool) $product->allow_decimal_qty,
                ];
            }

            if (! empty($outOfStockErrors)) {
                throw ValidationException::withMessages($outOfStockErrors);
            }

            $calc = $this->calculator->calculate($calculatorLines, [
                'order_discount_type' => $payload['order_discount_type'] ?? 'amount',
                'order_discount_value' => $payload['order_discount_value'] ?? ($payload['order_discount_amount'] ?? 0),
                'tax_mode' => $taxMode,
                'tax_rate' => $taxRate,
            ]);

            // Online price authority check (R12): if client sent expected_grand_total and DB re-pricing differs, return 409 with fresh totals
            if (! $isOfflineSync && isset($payload['expected_grand_total'])) {
                $expectedGrandTotal = (int) $payload['expected_grand_total'];
                if ($expectedGrandTotal !== $calc['grand_total']) {
                    abort(response()->json([
                        'message' => 'Harga produk telah berubah di server. Silakan tinjau ulang total terbaru (R12).',
                        'code' => 'PRICE_CHANGED',
                        'fresh_totals' => $calc,
                    ], 409));
                }
            }

            // Discount limit verification (W4.3)
            $approvedBy = null;
            if (! $isOfflineSync) {
                $exceedsLimit = false;
                foreach ($calc['lines'] as $line) {
                    if ($line['gross_line'] > 0) {
                        $linePct = ($line['line_discount'] / $line['gross_line']) * 100.0;
                        if ($linePct > $cashierDiscountLimitPct + 0.0001) {
                            $exceedsLimit = true;
                        }
                    }
                }
                if ($calc['subtotal'] > 0) {
                    $orderPct = ($calc['order_discount_amount'] / $calc['subtotal']) * 100.0;
                    if ($orderPct > $cashierDiscountLimitPct + 0.0001) {
                        $exceedsLimit = true;
                    }
                }

                if ($exceedsLimit) {
                    $approvedBy = $this->approvalService->resolveApproverId(
                        $actor,
                        'discount',
                        $payload['approval_token'] ?? null
                    );
                }
            }

            $normalizedPayments = $this->calculator->validatePayments($payload['payments'] ?? [], $calc['grand_total']);

            // Invoice Number generation (R7: {REGISTER}-{yymmdd}-{seq})
            $saleCreatedAt = isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : now();
            $dateYymmdd = $saleCreatedAt->copy()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('ymd');

            if ($isOfflineSync && ! empty($payload['invoice_no'])) {
                $invoiceNo = (string) $payload['invoice_no'];
                if (Sale::query()->where('invoice_no', $invoiceNo)->exists()) {
                    throw ValidationException::withMessages([
                        'invoice_no' => ["Nomor nota {$invoiceNo} duplikat dan memerlukan tinjauan Manager."],
                    ]);
                }
                // Sync sequence counter if offline invoice has higher sequence
                if (preg_match('/-(\d+)$/', $invoiceNo, $matches)) {
                    $offlineSeq = (int) $matches[1];
                    $this->bumpInvoiceSequence($register->id, $dateYymmdd, $offlineSeq);
                }
            } else {
                $nextSeq = $this->nextInvoiceSequence($register->id, $dateYymmdd);
                $invoiceNo = sprintf('%s-%s-%04d', $register->code, $dateYymmdd, $nextSeq);
            }

            $sale = Sale::create([
                'client_uuid' => $clientUuid,
                'invoice_no' => $invoiceNo,
                'shift_id' => $shift->id,
                'register_id' => $register->id,
                'cashier_id' => $shift->cashier_id,
                'status' => 'completed',
                'subtotal' => $calc['subtotal'],
                'discount_total' => $calc['discount_total'],
                'order_discount_amount' => $calc['order_discount_amount'],
                'tax_mode' => $calc['tax_mode'],
                'tax_rate' => $calc['tax_rate'],
                'tax_total' => $calc['tax_total'],
                'grand_total' => $calc['grand_total'],
                'is_offline' => $isOfflineSync,
                'stock_conflict' => ! empty($stockConflicts),
                'approved_by' => $approvedBy,
                'created_at' => $saleCreatedAt,
                'received_at' => now(),
            ]);

            foreach ($calc['lines'] as $line) {
                $sale->items()->create([
                    'product_id' => $line['product_id'],
                    'name' => $line['name'],
                    'sku' => $line['sku'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'unit_cost' => $line['unit_cost'],
                    'line_discount' => $line['line_discount'],
                    'order_discount_alloc' => $line['order_discount_alloc'],
                    'net_total' => $line['net_total'],
                    'returned_qty' => 0,
                ]);

                /** @var Product $product */
                $product = $lockedProducts->get($line['product_id']);
                if ($product->track_stock) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'sale',
                        'qty_delta' => -$line['qty'],
                        'ref_type' => 'sale',
                        'ref_id' => $sale->id,
                        'user_id' => $shift->cashier_id,
                        'note' => "Penjualan {$invoiceNo}",
                        'created_at' => now(),
                    ]);

                    $product->update([
                        'stock_qty' => round((float) $product->stock_qty - $line['qty'], 3),
                    ]);
                }
            }

            foreach ($normalizedPayments as $payment) {
                $sale->payments()->create($payment);
            }

            // W12: If a sale from a force-closed shift arrives later via offline sync, attach it and mark shift late_adjusted
            if ($isOfflineSync && $shift->status === 'closed') {
                $metrics = $this->shiftService->computeShiftMetrics($shift);
                $shift->update([
                    'late_adjusted' => true,
                    'expected_cash' => $metrics['expected_cash'],
                    'difference' => (int) $shift->counted_cash - $metrics['expected_cash'],
                ]);

                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => 'late_offline_sale_attached',
                    'entity' => 'shift',
                    'entity_id' => $shift->id,
                    'after' => ['sale_id' => $sale->id, 'invoice_no' => $sale->invoice_no],
                    'reason' => 'Penjualan offline masuk setelah shift ditutup paksa (W12)',
                    'created_at' => now(),
                ]);
            }

            return $sale->load(['items', 'payments', 'register', 'cashier']);
        });
    }

    protected function nextInvoiceSequence(int $registerId, string $dateYymmdd): int
    {
        $row = DB::table('invoice_sequences')
            ->where('register_id', $registerId)
            ->where('date_yymmdd', $dateYymmdd)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            DB::table('invoice_sequences')->insert([
                'register_id' => $registerId,
                'date_yymmdd' => $dateYymmdd,
                'last_seq' => 1,
            ]);

            return 1;
        }

        $next = ((int) $row->last_seq) + 1;
        DB::table('invoice_sequences')
            ->where('id', $row->id)
            ->update(['last_seq' => $next]);

        return $next;
    }

    protected function bumpInvoiceSequence(int $registerId, string $dateYymmdd, int $seq): void
    {
        $row = DB::table('invoice_sequences')
            ->where('register_id', $registerId)
            ->where('date_yymmdd', $dateYymmdd)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            DB::table('invoice_sequences')->insert([
                'register_id' => $registerId,
                'date_yymmdd' => $dateYymmdd,
                'last_seq' => $seq,
            ]);
        } elseif ((int) $row->last_seq < $seq) {
            DB::table('invoice_sequences')
                ->where('id', $row->id)
                ->update(['last_seq' => $seq]);
        }
    }
}

