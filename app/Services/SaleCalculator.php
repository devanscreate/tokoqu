<?php

namespace App\Services;

use InvalidArgumentException;

class SaleCalculator
{
    /**
     * Calculate order totals and per-line allocations according to R1, R2, R5, R6.
     *
     * @param  array<int, array{product_id:int, name:string, sku:string, qty:float|int, unit_price:int, unit_cost:int, line_discount?:int, allow_decimal_qty?:bool}>  $lines
     * @param  array{order_discount_type?:string, order_discount_value?:int|float, tax_mode?:string, tax_rate?:float|int}  $options
     */
    public function calculate(array $lines, array $options = []): array
    {
        if (empty($lines)) {
            throw new InvalidArgumentException('Keranjang belanja tidak boleh kosong.');
        }

        $computedLines = [];
        $subtotal = 0;
        $lineDiscountSum = 0;

        foreach ($lines as $idx => $line) {
            $qty = (float) $line['qty'];
            if ($qty <= 0) {
                throw new InvalidArgumentException("Kuantitas item baris ke-".($idx + 1)." harus lebih dari 0.");
            }

            $allowDecimal = (bool) ($line['allow_decimal_qty'] ?? false);
            if (! $allowDecimal && abs($qty - round($qty)) > 0.0001) {
                throw new InvalidArgumentException("Produk {$line['name']} tidak mengizinkan kuantitas desimal (R2).");
            }

            $unitPrice = (int) $line['unit_price'];
            $unitCost = (int) ($line['unit_cost'] ?? 0);
            $grossLine = (int) round($qty * $unitPrice);
            $lineDiscount = max(0, (int) ($line['line_discount'] ?? 0));

            if ($lineDiscount > $grossLine) {
                throw new InvalidArgumentException("Diskon baris untuk {$line['name']} melebihi harga baris.");
            }

            $lineTotal = $grossLine - $lineDiscount;
            $subtotal += $lineTotal;
            $lineDiscountSum += $lineDiscount;

            $computedLines[] = [
                'index' => $idx,
                'product_id' => (int) $line['product_id'],
                'name' => (string) $line['name'],
                'sku' => (string) $line['sku'],
                'qty' => round($qty, 3),
                'unit_price' => $unitPrice,
                'unit_cost' => $unitCost,
                'gross_line' => $grossLine,
                'line_discount' => $lineDiscount,
                'line_total' => $lineTotal,
                'order_discount_alloc' => 0,
                'net_total' => $lineTotal,
            ];
        }

        // Order discount calculation
        $discountType = $options['order_discount_type'] ?? 'amount'; // amount | percent
        $discountVal = (float) ($options['order_discount_value'] ?? 0);

        if ($discountType === 'percent') {
            if ($discountVal < 0 || $discountVal > 100) {
                throw new InvalidArgumentException('Persentase diskon order harus di antara 0 dan 100.');
            }
            $orderDiscountAmount = (int) round($subtotal * ($discountVal / 100));
        } else {
            $orderDiscountAmount = max(0, (int) round($discountVal));
        }

        if ($orderDiscountAmount > $subtotal) {
            throw new InvalidArgumentException('Diskon order tidak boleh melebihi subtotal.');
        }

        // Allocate order discount across lines proportionally using Largest Remainder Method (R5)
        if ($orderDiscountAmount > 0 && $subtotal > 0) {
            $quotas = [];
            $allocatedSum = 0;

            foreach ($computedLines as $i => $cl) {
                $exact = ($cl['line_total'] / $subtotal) * $orderDiscountAmount;
                $floor = (int) floor($exact);
                $quotas[$i] = [
                    'index' => $i,
                    'floor' => $floor,
                    'remainder' => $exact - $floor,
                ];
                $allocatedSum += $floor;
            }

            $remainderToDistribute = $orderDiscountAmount - $allocatedSum;

            usort($quotas, fn ($a, $b) => $b['remainder'] <=> $a['remainder'] ?: $a['index'] <=> $b['index']);

            for ($k = 0; $k < $remainderToDistribute; $k++) {
                $quotas[$k]['floor'] += 1;
            }

            foreach ($quotas as $q) {
                $idx = $q['index'];
                $computedLines[$idx]['order_discount_alloc'] = $q['floor'];
                $computedLines[$idx]['net_total'] = $computedLines[$idx]['line_total'] - $q['floor'];
            }
        }

        $taxableBase = $subtotal - $orderDiscountAmount;
        $taxMode = $options['tax_mode'] ?? 'off';
        $taxRatePct = max(0.0, (float) ($options['tax_rate'] ?? 0));
        $r = $taxRatePct / 100.0;

        if ($taxMode === 'inclusive' && $r > 0) {
            $taxTotal = (int) round($taxableBase * $r / (1.0 + $r));
            $grandTotal = $taxableBase;
        } elseif ($taxMode === 'exclusive' && $r > 0) {
            $taxTotal = (int) round($taxableBase * $r);
            $grandTotal = $taxableBase + $taxTotal;
        } else {
            $taxMode = 'off';
            $taxTotal = 0;
            $grandTotal = $taxableBase;
        }

        return [
            'lines' => $computedLines,
            'subtotal' => $subtotal,
            'line_discount_total' => $lineDiscountSum,
            'order_discount_amount' => $orderDiscountAmount,
            'discount_total' => $lineDiscountSum + $orderDiscountAmount,
            'taxable_base' => $taxableBase,
            'tax_mode' => $taxMode,
            'tax_rate' => $taxRatePct,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Validate and normalize payments according to R6.
     *
     * @param  array<int, array{method:string, amount:int, tendered?:int|null, reference?:string|null}>  $payments
     */
    public function validatePayments(array $payments, int $grandTotal): array
    {
        if (empty($payments)) {
            throw new InvalidArgumentException('Metode pembayaran harus diisi.');
        }

        $normalized = [];
        $appliedSum = 0;
        $remainingDue = $grandTotal;

        foreach ($payments as $idx => $p) {
            $method = strtolower((string) ($p['method'] ?? 'cash'));
            if (! in_array($method, ['cash', 'qris', 'transfer', 'card'], true)) {
                throw new InvalidArgumentException("Metode pembayaran '{$method}' tidak valid.");
            }

            $amount = (int) ($p['amount'] ?? 0);
            if ($amount <= 0 && $grandTotal > 0) {
                throw new InvalidArgumentException('Nominal pembayaran harus lebih besar dari 0 (R6).');
            }

            if ($method !== 'cash' && $amount > $remainingDue) {
                throw new InvalidArgumentException('Pembayaran non-tunai tidak boleh melebihi sisa tagihan (R6).');
            }

            if ($method === 'cash') {
                $tendered = isset($p['tendered']) ? (int) $p['tendered'] : $amount;
                if ($tendered < $amount) {
                    throw new InvalidArgumentException('Uang tunai yang diterima kurang dari nominal pembayaran tunai.');
                }
                $change = $tendered - $amount;
            } else {
                $tendered = $amount;
                $change = 0;
            }

            $appliedSum += $amount;
            $remainingDue -= $amount;

            $normalized[] = [
                'method' => $method,
                'amount' => $amount,
                'tendered' => $tendered,
                'change_amount' => $change,
                'reference' => isset($p['reference']) ? substr(trim((string) $p['reference']), 0, 100) : null,
            ];
        }

        if ($appliedSum !== $grandTotal) {
            throw new InvalidArgumentException("Total pembayaran (Rp {$appliedSum}) harus sama persis dengan Grand Total (Rp {$grandTotal}) sesuai aturan R6.");
        }

        return $normalized;
    }
}

