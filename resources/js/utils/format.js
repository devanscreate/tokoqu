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
 * Compress & resize an uploaded image file on the client into a lightweight WebP/JPEG Data URL (~15-30 KB).
 * Keeps the POS fast, avoids multipart upload overhead, and works 100% offline in IndexedDB.
 */
export function compressImageFile(file, maxSize = 320, quality = 0.82) {
    return new Promise((resolve, reject) => {
        if (!file || !file.type.startsWith('image/')) {
            reject(new Error('File yang dipilih bukan gambar yang valid.'));
            return;
        }
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('Gagal membaca file gambar.'));
        reader.onload = (e) => {
            const img = new Image();
            img.onerror = () => reject(new Error('Format gambar tidak didukung.'));
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;

                if (width > height) {
                    if (width > maxSize) {
                        height = Math.round((height * maxSize) / width);
                        width = maxSize;
                    }
                } else if (height > maxSize) {
                    width = Math.round((width * maxSize) / height);
                    height = maxSize;
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, width, height);
                ctx.drawImage(img, 0, 0, width, height);

                let dataUrl = canvas.toDataURL('image/webp', quality);
                if (!dataUrl.startsWith('data:image/webp')) {
                    dataUrl = canvas.toDataURL('image/jpeg', quality);
                }
                resolve(dataUrl);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

/**
 * Clean, lightweight SVG fallback thumbnail when a product has no custom photo yet.
 */
export function getProductImage(product) {
    if (product?.image_url && String(product.image_url).trim() !== '') {
        return product.image_url;
    }
    const name = String(product?.name || 'Produk').trim();
    const words = name.split(/\s+/).filter(Boolean);
    const initials =
        words.length >= 2
            ? `${words[0][0]}${words[1][0]}`.toUpperCase()
            : name.slice(0, 2).toUpperCase();

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
        <rect width="200" height="200" rx="24" fill="#F9F5EB"/>
        <circle cx="100" cy="82" r="38" fill="#FFFFFF" stroke="#E2DFD7" stroke-width="2"/>
        <text x="100" y="92" text-anchor="middle" font-family="Inter, system-ui, sans-serif" font-size="26" font-weight="700" fill="#B8923A">${initials}</text>
        <rect x="44" y="138" width="112" height="22" rx="11" fill="#0F0F0F"/>
        <text x="100" y="153" text-anchor="middle" font-family="monospace" font-size="11" font-weight="600" fill="#D8BE7E">${String(product?.sku || 'ITEM').slice(0, 12)}</text>
    </svg>`;

    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
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

