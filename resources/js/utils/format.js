/**
 * Format integer Rupiah with Indonesian thousands separators (R1, Section 10).
 */
export function formatRupiah(amount) {
    const val = Math.round(Number(amount || 0));
    const formatted = Math.abs(val)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return val < 0 ? `-Rp ${formatted}` : `Rp ${formatted}`;
}

export function formatQty(qty) {
    const num = Number(qty || 0);
    if (Number.isInteger(num)) {
        return num.toString();
    }
    return num.toFixed(3).replace(/\.?0+$/, '');
}

export function formatDateTime(isoString, timezone = 'Asia/Jakarta') {
    if (!isoString) return '-';
    try {
        return new Intl.DateTimeFormat('id-ID', {
            timeZone: timezone,
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(isoString));
    } catch {
        return isoString;
    }
}

export function generateUuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export function getTodayYymmdd(timezone = 'Asia/Jakarta') {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone,
        year: '2-digit',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const map = {};
    parts.forEach((p) => {
        map[p.type] = p.value;
    });
    return `${map.year}${map.month}${map.day}`;
}

/**
 * Client-side implementation of Domain Rule R5 (identical to App\Services\SaleCalculator).
 * Ensures instant local cart totals and accurate offline sale snapshots.
 */
export function calculateCartTotals(lines, options = {}) {
    const computedLines = [];
    let subtotal = 0;
    let lineDiscountSum = 0;

    lines.forEach((line, idx) => {
        const qty = Number(line.qty || 0);
        const unitPrice = Math.round(Number(line.unit_price || 0));
        const unitCost = Math.round(Number(line.unit_cost || 0));
        const grossLine = Math.round(qty * unitPrice);
        const lineDiscount = Math.min(grossLine, Math.max(0, Math.round(Number(line.line_discount || 0))));
        const lineTotal = grossLine - lineDiscount;

        subtotal += lineTotal;
        lineDiscountSum += lineDiscount;

        computedLines.push({
            ...line,
            index: idx,
            qty: Number(qty.toFixed(3)),
            unit_price: unitPrice,
            unit_cost: unitCost,
            gross_line: grossLine,
            line_discount: lineDiscount,
            line_total: lineTotal,
            order_discount_alloc: 0,
            net_total: lineTotal,
        });
    });

    const discountType = options.order_discount_type || 'amount';
    const discountVal = Number(options.order_discount_value || 0);

    let orderDiscountAmount = 0;
    if (discountType === 'percent') {
        orderDiscountAmount = Math.round(subtotal * (Math.min(100, Math.max(0, discountVal)) / 100));
    } else {
        orderDiscountAmount = Math.max(0, Math.round(discountVal));
    }
    orderDiscountAmount = Math.min(subtotal, orderDiscountAmount);

    // Largest Remainder Method allocation across lines (R5)
    if (orderDiscountAmount > 0 && subtotal > 0) {
        const quotas = [];
        let allocatedSum = 0;

        computedLines.forEach((cl, i) => {
            const exact = (cl.line_total / subtotal) * orderDiscountAmount;
            const floor = Math.floor(exact);
            quotas.push({
                index: i,
                floor,
                remainder: exact - floor,
            });
            allocatedSum += floor;
        });

        const remainderToDistribute = orderDiscountAmount - allocatedSum;
        quotas.sort((a, b) => b.remainder - a.remainder || a.index - b.index);

        for (let k = 0; k < remainderToDistribute; k++) {
            quotas[k].floor += 1;
        }

        quotas.forEach((q) => {
            const target = computedLines[q.index];
            target.order_discount_alloc = q.floor;
            target.net_total = target.line_total - q.floor;
        });
    }

    const taxableBase = subtotal - orderDiscountAmount;
    const taxMode = options.tax_mode || 'off';
    const taxRatePct = Math.max(0, Number(options.tax_rate || 0));
    const r = taxRatePct / 100;

    let taxTotal = 0;
    let grandTotal = taxableBase;

    if (taxMode === 'inclusive' && r > 0) {
        taxTotal = Math.round((taxableBase * r) / (1 + r));
        grandTotal = taxableBase;
    } else if (taxMode === 'exclusive' && r > 0) {
        taxTotal = Math.round(taxableBase * r);
        grandTotal = taxableBase + taxTotal;
    }

    return {
        lines: computedLines,
        subtotal,
        line_discount_total: lineDiscountSum,
        order_discount_amount: orderDiscountAmount,
        discount_total: lineDiscountSum + orderDiscountAmount,
        taxable_base: taxableBase,
        tax_mode: r > 0 ? taxMode : 'off',
        tax_rate: taxRatePct,
        tax_total: taxTotal,
        grand_total: grandTotal,
    };
}

