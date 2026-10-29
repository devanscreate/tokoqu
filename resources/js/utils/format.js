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
 * Render & compress an image into a standardized 200x200 square thumbnail (~4-10 KB).
 * Supports layout adjustment:
 * - fitMode: 'cover' (penuh kotak seragam) or 'contain' (utuh tanpa terpotong di tengah latar putih)
 * - zoom: 1.0 to 2.0
 * - offsetX / offsetY: -50 to +50 (%)
 */
export function processProductImage(sourceImgOrDataUrl, options = {}) {
    const targetSize = options.size || 200;
    const fitMode = options.fitMode || 'cover'; // 'cover' | 'contain'
    const zoom = Math.max(0.5, Math.min(2.5, Number(options.zoom || 1)));
    const offsetX = Math.max(-50, Math.min(50, Number(options.offsetX || 0)));
    const offsetY = Math.max(-50, Math.min(50, Number(options.offsetY || 0)));

    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onerror = () => reject(new Error('Format gambar tidak didukung.'));
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = targetSize;
            canvas.height = targetSize;
            const ctx = canvas.getContext('2d');

            // Clean white background so transparent PNGs or 'contain' mode look crisp and uniform
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, targetSize, targetSize);

            const iw = img.width || 1;
            const ih = img.height || 1;

            let baseScale =
                fitMode === 'contain'
                    ? Math.min(targetSize / iw, targetSize / ih)
                    : Math.max(targetSize / iw, targetSize / ih);

            const finalScale = baseScale * zoom;
            const drawW = iw * finalScale;
            const drawH = ih * finalScale;

            const shiftX = (offsetX / 100) * targetSize;
            const shiftY = (offsetY / 100) * targetSize;

            const dx = (targetSize - drawW) / 2 + shiftX;
            const dy = (targetSize - drawH) / 2 + shiftY;

            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(img, dx, dy, drawW, drawH);

            // Ultra-lightweight compression: aim for <= 12 KB
            let quality = 0.68;
            let dataUrl = canvas.toDataURL('image/webp', quality);
            if (!dataUrl.startsWith('data:image/webp')) {
                dataUrl = canvas.toDataURL('image/jpeg', quality);
            }

            // Step down quality if still above ~15 KB
            while (dataUrl.length > 20000 && quality > 0.4) {
                quality -= 0.1;
                dataUrl = canvas.toDataURL('image/webp', quality);
                if (!dataUrl.startsWith('data:image/webp')) {
                    dataUrl = canvas.toDataURL('image/jpeg', quality);
                }
            }

            const sizeBytes = Math.round((dataUrl.length * 3) / 4);
            const sizeKb = (sizeBytes / 1024).toFixed(1);

            resolve({
                dataUrl,
                sizeKb,
                width: targetSize,
                height: targetSize,
            });
        };
        img.src = sourceImgOrDataUrl;
    });
}

export function readRawImageFile(file) {
    return new Promise((resolve, reject) => {
        if (!file || !file.type.startsWith('image/')) {
            reject(new Error('File yang dipilih bukan gambar yang valid.'));
            return;
        }
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('Gagal membaca file gambar.'));
        reader.onload = (e) => resolve(e.target.result);
        reader.readAsDataURL(file);
    });
}

export async function compressImageFile(file, maxSize = 200, quality = 0.68) {
    const raw = await readRawImageFile(file);
    const res = await processProductImage(raw, { size: maxSize, fitMode: 'cover', zoom: 1, quality });
    return res.dataUrl;
}

export const AVAILABLE_THEMES = [
    {
        id: 'white-blue',
        label: 'Putih & Biru',
        primaryHex: '#1877F2',
        darkHex: '#1877F2',
        bgHex: '#E7F3FF',
        borderHex: '#93C5FD',
    },
    {
        id: 'white-green',
        label: 'Putih & Hijau',
        primaryHex: '#059669',
        darkHex: '#065F46',
        bgHex: '#D1FAE5',
        borderHex: '#6EE7B7',
    },
    {
        id: 'white-red',
        label: 'Putih & Merah',
        primaryHex: '#DC2626',
        darkHex: '#991B1B',
        bgHex: '#FEE2E2',
        borderHex: '#FCA5A5',
    },
    {
        id: 'white-yellow',
        label: 'Putih & Kuning',
        primaryHex: '#CA8A04',
        darkHex: '#713F12',
        bgHex: '#FEF9C3',
        borderHex: '#FACC15',
    },
    {
        id: 'gold-black',
        label: 'Putih, Emas & Hitam',
        primaryHex: '#B8923A',
        darkHex: '#0F0F0F',
        bgHex: '#F9F3E1',
        borderHex: '#D8CFB8',
    },
];

/**
 * Clean, lightweight SVG fallback thumbnail when a product has no custom photo yet.
 * Adapts automatically to the active color theme.
 */
export function getProductImage(product, themeId = null) {
    if (product?.image_url && String(product.image_url).trim() !== '') {
        return product.image_url;
    }
    const activeThemeId =
        themeId ||
        (typeof document !== 'undefined' ? document.documentElement.getAttribute('data-theme') : null) ||
        'white-blue';

    const theme = AVAILABLE_THEMES.find((t) => t.id === activeThemeId) || AVAILABLE_THEMES[0];

    const name = String(product?.name || 'Produk').trim();
    const words = name.split(/\s+/).filter(Boolean);
    const initials =
        words.length >= 2
            ? `${words[0][0]}${words[1][0]}`.toUpperCase()
            : name.slice(0, 2).toUpperCase();

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
        <rect width="200" height="200" rx="24" fill="${theme.bgHex}"/>
        <circle cx="100" cy="82" r="40" fill="#FFFFFF" stroke="${theme.borderHex}" stroke-width="3"/>
        <text x="100" y="92" text-anchor="middle" font-family="Inter, system-ui, sans-serif" font-size="28" font-weight="800" fill="${theme.primaryHex}">${initials}</text>
        <rect x="38" y="138" width="124" height="24" rx="12" fill="${theme.darkHex}"/>
        <text x="100" y="154" text-anchor="middle" font-family="monospace" font-size="11" font-weight="700" fill="#FFFFFF">${String(product?.sku || 'ITEM').slice(0, 12)}</text>
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

