<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\InvoiceSequence;
use App\Models\Payment;
use App\Models\Register;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function openShift(User $cashier, int $registerId, int $openingCash): array
    {
        if ($openingCash < 0) {
            throw ValidationException::withMessages([
                'opening_cash' => ['Modal awal kas tidak boleh negatif.'],
            ]);
        }

        return DB::transaction(function () use ($cashier, $registerId, $openingCash) {
            $register = Register::query()->lockForUpdate()->findOrFail($registerId);

            if (! $register->is_active) {
                throw ValidationException::withMessages([
                    'register_id' => ['Register ini sedang tidak aktif.'],
                ]);
            }

            $existingOnRegister = Shift::query()
                ->with('cashier')
                ->where('register_id', $register->id)
                ->where('status', 'open')
                ->first();

            if ($existingOnRegister) {
                throw ValidationException::withMessages([
                    'register_id' => ["Register {$register->code} masih memiliki shift terbuka oleh {$existingOnRegister->cashier->name}."],
                ]);
            }

            $existingForCashier = Shift::query()
                ->with('register')
                ->where('cashier_id', $cashier->id)
                ->where('status', 'open')
                ->first();

            if ($existingForCashier) {
                throw ValidationException::withMessages([
                    'cashier_id' => ["Anda masih memiliki shift terbuka di register {$existingForCashier->register->code}. Tutup shift tersebut terlebih dahulu."],
                ]);
            }

            $shift = Shift::create([
                'register_id' => $register->id,
                'cashier_id' => $cashier->id,
                'opened_at' => now(),
                'opening_cash' => $openingCash,
                'status' => 'open',
            ]);

            $todayYymmdd = now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('ymd');
            $seq = DB::table('invoice_sequences')
                ->where('register_id', $register->id)
                ->where('date_yymmdd', $todayYymmdd)
                ->value('last_seq') ?? 0;

            return [
                'shift' => $shift->load(['register', 'cashier']),
                'last_invoice_seq' => (int) $seq,
            ];
        });
    }

    /**
     * Expected cash formula (W9.2):
     * opening_cash + cash payments applied on non-voided sales in the shift + cash_in - cash_out - cash refunds paid in the shift
     */
    public function computeShiftMetrics(Shift $shift): array
    {
        $nonVoidedSales = $shift->sales()->where('status', '!=', 'voided')->get();
        $voidedSalesCount = $shift->sales()->where('status', 'voided')->count();

        $salesIds = $nonVoidedSales->pluck('id');

        $paymentsByMethod = Payment::query()
            ->whereIn('sale_id', $salesIds)
            ->selectRaw('method, SUM(amount) as total_amount')
            ->groupBy('method')
            ->pluck('total_amount', 'method')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $cashSalesApplied = (int) ($paymentsByMethod['cash'] ?? 0);

        $cashIn = (int) CashMovement::query()
            ->where('shift_id', $shift->id)
            ->where('type', 'in')
            ->sum('amount');

        $cashOut = (int) CashMovement::query()
            ->where('shift_id', $shift->id)
            ->where('type', 'out')
            ->sum('amount');

        $cashRefunds = (int) SaleReturn::query()
            ->where('shift_id', $shift->id)
            ->where('refund_method', 'cash')
            ->sum('refund_total');

        $totalReturnsAmount = (int) SaleReturn::query()
            ->where('shift_id', $shift->id)
            ->sum('refund_total');

        $expectedCash = (int) $shift->opening_cash + $cashSalesApplied + $cashIn - $cashOut - $cashRefunds;

        return [
            'opening_cash' => (int) $shift->opening_cash,
            'sales_count' => $nonVoidedSales->count(),
            'voided_count' => $voidedSalesCount,
            'gross_sales' => (int) $nonVoidedSales->sum('subtotal'),
            'discounts_total' => (int) $nonVoidedSales->sum('discount_total'),
            'tax_total' => (int) $nonVoidedSales->sum('tax_total'),
            'net_sales' => (int) $nonVoidedSales->sum('grand_total'),
            'payments_by_method' => [
                'cash' => $cashSalesApplied,
                'qris' => (int) ($paymentsByMethod['qris'] ?? 0),
                'transfer' => (int) ($paymentsByMethod['transfer'] ?? 0),
                'card' => (int) ($paymentsByMethod['card'] ?? 0),
            ],
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'cash_refunds' => $cashRefunds,
            'total_returns' => $totalReturnsAmount,
            'expected_cash' => $expectedCash,
        ];
    }

    public function closeShift(Shift $shift, User $actor, int $countedCash, ?string $note = null): array
    {
        return DB::transaction(function () use ($shift, $actor, $countedCash, $note) {
            $lockedShift = Shift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($lockedShift->status !== 'open') {
                throw ValidationException::withMessages([
                    'shift' => ['Shift ini sudah ditutup sebelumnya (R9).'],
                ]);
            }

            if ($lockedShift->cashier_id !== $actor->id && ! $actor->isManagerOrOwner()) {
                throw ValidationException::withMessages([
                    'shift' => ['Anda tidak memiliki akses untuk menutup shift kasir lain.'],
                ]);
            }

            $metrics = $this->computeShiftMetrics($lockedShift);
            $expectedCash = $metrics['expected_cash'];
            $difference = $countedCash - $expectedCash;

            if ($difference !== 0 && empty(trim((string) $note))) {
                throw ValidationException::withMessages([
                    'close_note' => ['Terdapat selisih kas (Rp '.number_format($difference, 0, ',', '.').'). Catatan wajib diisi (W9.3).'],
                ]);
            }

            $lockedShift->update([
                'closed_at' => now(),
                'counted_cash' => $countedCash,
                'expected_cash' => $expectedCash,
                'difference' => $difference,
                'status' => 'closed',
                'close_note' => $note,
            ]);

            return [
                'shift' => $lockedShift->fresh(['register', 'cashier']),
                'z_report' => array_merge($metrics, [
                    'counted_cash' => $countedCash,
                    'difference' => $difference,
                ]),
            ];
        });
    }

    public function forceCloseShift(Shift $shift, User $manager, int $countedCash, string $reason): array
    {
        return DB::transaction(function () use ($shift, $manager, $countedCash, $reason) {
            $lockedShift = Shift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($lockedShift->status !== 'open') {
                throw ValidationException::withMessages([
                    'shift' => ['Shift ini sudah berstatus tertutup.'],
                ]);
            }

            $metrics = $this->computeShiftMetrics($lockedShift);
            $expectedCash = $metrics['expected_cash'];
            $difference = $countedCash - $expectedCash;

            $lockedShift->update([
                'closed_at' => now(),
                'counted_cash' => $countedCash,
                'expected_cash' => $expectedCash,
                'difference' => $difference,
                'status' => 'closed',
                'forced_by' => $manager->id,
                'force_reason' => $reason,
                'close_note' => 'Force closed: '.$reason,
            ]);

            AuditLog::create([
                'user_id' => $manager->id,
                'action' => 'shift_force_closed',
                'entity' => 'shift',
                'entity_id' => $lockedShift->id,
                'after' => [
                    'expected_cash' => $expectedCash,
                    'counted_cash' => $countedCash,
                    'difference' => $difference,
                ],
                'reason' => $reason,
                'approved_by' => $manager->id,
                'created_at' => now(),
            ]);

            return [
                'shift' => $lockedShift->fresh(['register', 'cashier', 'forcedBy']),
                'z_report' => array_merge($metrics, [
                    'counted_cash' => $countedCash,
                    'difference' => $difference,
                ]),
            ];
        });
    }
}

