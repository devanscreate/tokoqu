<template>
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <!-- Header & Sub-Navigation -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-pos-border pb-4">
            <div>
                <h1 class="text-xl font-bold uppercase tracking-wider text-pos-black">Katalog Produk & Buku Besar Stok</h1>
                <p class="text-xs text-pos-muted mt-0.5">
                    Setiap perubahan stok dicatat sebagai baris append-only pada <code class="font-mono">stock_movements</code> (R3, W10, W11).
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'min-h-[40px] px-4 py-2 text-xs font-semibold uppercase tracking-wider border transition-colors',
                        activeTab === tab.id
                            ? 'bg-pos-black text-pos-gold border-pos-black'
                            : 'bg-pos-white text-pos-black border-pos-border hover:border-pos-gold',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div v-if="errorMsg" class="p-3.5 bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>
        <div v-if="successMsg" class="p-3.5 bg-pos-gold-tint border border-pos-gold text-xs text-pos-black font-semibold">
            {{ successMsg }}
        </div>

        <!-- TAB 1: PRODUCTS & CATEGORIES -->
        <div v-if="activeTab === 'products'" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Product Form -->
                <form @submit.prevent="saveProduct" class="card-pos p-5 space-y-3">
                    <div class="border-b border-pos-border pb-2 flex items-center justify-between">
                        <h2 class="text-xs font-bold uppercase tracking-wider">
                            {{ productForm.id ? 'Edit Produk' : 'Tambah Produk Baru (W1.5)' }}
                        </h2>
                        <button v-if="productForm.id" type="button" @click="resetProductForm" class="text-xs text-pos-muted underline">
                            Batal Edit
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">SKU *</label>
                            <input v-model="productForm.sku" type="text" required class="input-pos font-mono" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Barcode</label>
                            <input v-model="productForm.barcode" type="text" class="input-pos font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Nama Produk *</label>
                        <input v-model="productForm.name" type="text" required class="input-pos" />
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Kategori</label>
                        <div class="flex gap-2">
                            <select v-model="productForm.category_id" class="input-pos flex-1">
                                <option :value="null">-- Tanpa Kategori --</option>
                                <option v-for="c in store.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <button type="button" @click="promptNewCategory" class="btn-outline px-3" title="Tambah Kategori">+</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Harga Jual (Rp) *</label>
                            <input v-model.number="productForm.price" type="number" min="0" required class="input-pos font-mono" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Harga Modal / HPP (Rp)</label>
                            <input v-model.number="productForm.cost" type="number" min="0" required class="input-pos font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Stok Minimum Alert</label>
                            <input v-model.number="productForm.min_stock" type="number" min="0" step="0.001" class="input-pos font-mono" />
                        </div>
                        <div class="flex flex-col justify-end space-y-1 text-xs">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="productForm.track_stock" type="checkbox" />
                                <span>Lacak Stok (Ledger)</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input v-model="productForm.allow_decimal_qty" type="checkbox" />
                                <span>Izinkan Desimal (Kg)</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input v-model="productForm.is_active" type="checkbox" />
                                <span>Produk Aktif</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn-gold w-full">
                        {{ productForm.id ? 'Simpan Perubahan Produk' : 'Simpan Produk Baru' }}
                    </button>
                </form>

                <!-- Product Table & CSV Import -->
                <div class="lg:col-span-2 card-pos flex flex-col">
                    <div class="px-4 py-3 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider">Daftar Katalog Produk ({{ store.products.length }})</span>
                        <label class="cursor-pointer px-3 py-1 bg-pos-charcoal border border-pos-gold text-pos-gold text-xs font-semibold uppercase">
                            Impor CSV (W1.5)
                            <input type="file" accept=".csv" @change="handleCsvFile" class="hidden" />
                        </label>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-pos-stone border-b border-pos-border uppercase text-[11px] text-pos-muted">
                                    <th class="p-2.5">SKU / Barcode</th>
                                    <th class="p-2.5">Nama Produk</th>
                                    <th class="p-2.5 text-right">Harga Jual</th>
                                    <th class="p-2.5 text-right">HPP</th>
                                    <th class="p-2.5 text-right">Stok (R3)</th>
                                    <th class="p-2.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-pos-border tabular-nums">
                                <tr v-for="p in store.products" :key="p.id" class="hover:bg-pos-alabaster">
                                    <td class="p-2.5 font-mono">
                                        <div class="font-bold">{{ p.sku }}</div>
                                        <div class="text-[10px] text-pos-muted">{{ p.barcode || '-' }}</div>
                                    </td>
                                    <td class="p-2.5">
                                        <div class="font-semibold">{{ p.name }}</div>
                                        <span v-if="!p.is_active" class="text-[10px] text-pos-danger font-bold uppercase">[NONAKTIF]</span>
                                    </td>
                                    <td class="p-2.5 text-right font-mono">{{ formatRupiah(p.price) }}</td>
                                    <td class="p-2.5 text-right font-mono text-pos-muted">{{ formatRupiah(p.cost) }}</td>
                                    <td class="p-2.5 text-right font-mono font-bold">
                                        {{ p.track_stock ? formatQty(p.stock_qty) : '∞' }}
                                    </td>
                                    <td class="p-2.5 text-right space-x-1.5 whitespace-nowrap">
                                        <button
                                            @click="editProduct(p)"
                                            class="px-2 py-1 border border-pos-border bg-pos-white hover:border-pos-gold"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="openStockCard(p)"
                                            class="px-2 py-1 bg-pos-black text-pos-gold font-mono text-[11px]"
                                        >
                                            Kartu Stok
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: RECEIVE STOCK (W10) -->
        <div v-if="activeTab === 'receipt'" class="card-pos p-5 space-y-4">
            <div class="border-b border-pos-border pb-3">
                <h2 class="text-sm font-bold uppercase tracking-wider">Penerimaan Stok Barang Masuk (W10)</h2>
                <p class="text-xs text-pos-muted">
                    Penerimaan stok yang sudah dikonfirmasi bersifat permanen (immutable) dan langsung menambah buku besar stok serta memperbarui HPP.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Tipe Penerimaan</label>
                    <select v-model="receiptForm.type" class="input-pos">
                        <option value="purchase">Pembelian Supplier (Purchase)</option>
                        <option value="opening">Saldo Stok Awal (Opening - W1.6)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Nama Supplier</label>
                    <input v-model="receiptForm.supplier_name" type="text" placeholder="PT Sumber Makmur" class="input-pos" />
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">No. Surat Jalan / Referensi</label>
                    <input v-model="receiptForm.reference" type="text" placeholder="SJ-2026-001" class="input-pos font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Catatan</label>
                    <input v-model="receiptForm.note" type="text" class="input-pos" />
                </div>
            </div>

            <div class="space-y-2">
                <div
                    v-for="(row, rIdx) in receiptForm.items"
                    :key="rIdx"
                    class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end bg-pos-alabaster p-3 border border-pos-border"
                >
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Produk</label>
                        <select v-model="row.product_id" @change="onReceiptProductChange(row)" class="input-pos">
                            <option value="" disabled>-- Pilih Produk --</option>
                            <option v-for="p in store.products" :key="p.id" :value="p.id">
                                {{ p.sku }} — {{ p.name }} (Stok: {{ formatQty(p.stock_qty) }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Qty Masuk (&gt; 0)</label>
                        <input v-model.number="row.qty" type="number" min="0.001" step="0.001" class="input-pos font-mono" />
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Harga Beli / Unit (Rp)</label>
                            <input v-model.number="row.unit_cost" type="number" min="0" class="input-pos font-mono" />
                        </div>
                        <button
                            v-if="receiptForm.items.length > 1"
                            type="button"
                            @click="receiptForm.items.splice(rIdx, 1)"
                            class="btn-outline text-pos-danger px-3"
                        >
                            ×
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex justify-between">
                <button
                    type="button"
                    @click="receiptForm.items.push({ product_id: '', qty: 1, unit_cost: 0 })"
                    class="btn-outline text-xs"
                >
                    + Tambah Baris Barang
                </button>
                <button type="button" @click="submitStockReceipt" class="btn-gold">
                    Konfirmasi Penerimaan Stok (W10)
                </button>
            </div>
        </div>

        <!-- TAB 3: STOCK ADJUSTMENT & OPNAME (W11) -->
        <div v-if="activeTab === 'adjustment'" class="card-pos p-5 space-y-4">
            <div class="border-b border-pos-border pb-3">
                <h2 class="text-sm font-bold uppercase tracking-wider">Penyesuaian Stok & Stock Opname (W11)</h2>
                <p class="text-xs text-pos-muted">
                    Masukkan jumlah stok fisik hasil hitung (Counted Qty). Jika stok produk bergerak saat layar dibuka, sistem akan meminta hitung ulang (W11.3).
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Alasan Penyesuaian *</label>
                    <select v-model="adjustmentForm.reason" class="input-pos">
                        <option value="opname">Stock Opname Rutin</option>
                        <option value="damaged">Barang Rusak (Damaged)</option>
                        <option value="expired">Barang Kedaluwarsa (Expired)</option>
                        <option value="lost">Barang Hilang / Selisih (Lost)</option>
                        <option value="correction">Koreksi Input Penerimaan (Correction)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Catatan Audit</label>
                    <input v-model="adjustmentForm.note" type="text" placeholder="Catatan stock opname..." class="input-pos" />
                </div>
            </div>

            <div class="space-y-2">
                <div
                    v-for="(row, aIdx) in adjustmentForm.items"
                    :key="aIdx"
                    class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end bg-pos-alabaster p-3 border border-pos-border"
                >
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Produk</label>
                        <select v-model="row.product_id" @change="onAdjustmentProductSelect(row)" class="input-pos">
                            <option value="" disabled>-- Pilih Produk --</option>
                            <option v-for="p in store.products.filter((x) => x.track_stock)" :key="p.id" :value="p.id">
                                {{ p.sku }} — {{ p.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Stok Sistem Saat Dibuka</label>
                        <input :value="row.expected_system_qty" type="text" disabled class="input-pos font-mono bg-pos-stone" />
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">Stok Fisik Dihitung</label>
                            <input v-model.number="row.counted_qty" type="number" min="0" step="0.001" class="input-pos font-mono font-bold" />
                        </div>
                        <button
                            v-if="adjustmentForm.items.length > 1"
                            type="button"
                            @click="adjustmentForm.items.splice(aIdx, 1)"
                            class="btn-outline text-pos-danger px-3"
                        >
                            ×
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex justify-between">
                <button
                    type="button"
                    @click="adjustmentForm.items.push({ product_id: '', expected_system_qty: 0, counted_qty: 0 })"
                    class="btn-outline text-xs"
                >
                    + Tambah Baris Opname
                </button>
                <button type="button" @click="submitStockAdjustment" class="btn-gold">
                    Simpan Penyesuaian Stok (W11)
                </button>
            </div>
        </div>

        <!-- STOCK CARD MODAL (W14) -->
        <div v-if="stockCardData" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
            <div class="bg-pos-white border-2 border-pos-black w-full max-w-3xl flex flex-col max-h-[88vh]">
                <div class="px-5 py-3.5 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-sm uppercase tracking-wider">
                            Kartu Stok (Stock Ledger): {{ stockCardData.product.name }}
                        </h3>
                        <div class="text-xs font-mono text-pos-gold">
                            SKU: {{ stockCardData.product.sku }} · Cached Stok: {{ stockCardData.cached_stock_qty }} · Sum Ledger: {{ stockCardData.ledger_sum }}
                            ({{ stockCardData.is_reconciled ? 'RECONCILED ✓' : 'DRIFT !' }})
                        </div>
                    </div>
                    <button @click="stockCardData = null" class="text-xs font-mono text-pos-white hover:text-pos-gold">[ESC]</button>
                </div>

                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-pos-stone border-b border-pos-border uppercase text-[11px] text-pos-muted">
                                <th class="p-3">Waktu</th>
                                <th class="p-3">Tipe Mutasi</th>
                                <th class="p-3 text-right">Qty Delta</th>
                                <th class="p-3">Referensi</th>
                                <th class="p-3">Petugas</th>
                                <th class="p-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pos-border tabular-nums">
                            <tr v-for="m in stockCardData.movements" :key="m.id">
                                <td class="p-3 font-mono">{{ formatDateTime(m.created_at) }}</td>
                                <td class="p-3 font-mono uppercase font-bold">{{ m.type }}</td>
                                <td
                                    class="p-3 text-right font-mono font-bold"
                                    :class="m.qty_delta > 0 ? 'text-pos-success' : 'text-pos-danger'"
                                >
                                    {{ m.qty_delta > 0 ? '+' + formatQty(m.qty_delta) : formatQty(m.qty_delta) }}
                                </td>
                                <td class="p-3 font-mono text-[11px]">{{ m.ref_type }} #{{ m.ref_id }}</td>
                                <td class="p-3">{{ m.user?.name || 'System' }}</td>
                                <td class="p-3 text-pos-muted">{{ m.note }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { usePosStore } from '../stores/posStore';
import { apiRequest } from '../utils/api';
import { formatDateTime, formatQty, formatRupiah } from '../utils/format';

const store = usePosStore();
const activeTab = ref('products');
const errorMsg = ref('');
const successMsg = ref('');
const stockCardData = ref(null);

const tabs = [
    { id: 'products', label: 'Produk & Kategori' },
    { id: 'receipt', label: ' Barang Masuk (W10)' },
    { id: 'adjustment', label: 'Opname & Penyesuaian (W11)' },
];

const productForm = ref({
    id: null,
    sku: '',
    barcode: '',
    name: '',
    category_id: null,
    price: 0,
    cost: 0,
    min_stock: 5,
    track_stock: true,
    allow_decimal_qty: false,
    is_active: true,
});

const receiptForm = ref({
    type: 'purchase',
    supplier_name: '',
    reference: '',
    note: '',
    items: [{ product_id: '', qty: 10, unit_cost: 0 }],
});

const adjustmentForm = ref({
    reason: 'opname',
    note: '',
    items: [{ product_id: '', expected_system_qty: 0, counted_qty: 0 }],
});

function resetProductForm() {
    productForm.value = {
        id: null,
        sku: '',
        barcode: '',
        name: '',
        category_id: null,
        price: 0,
        cost: 0,
        min_stock: 5,
        track_stock: true,
        allow_decimal_qty: false,
        is_active: true,
    };
}

function editProduct(p) {
    productForm.value = {
        id: p.id,
        sku: p.sku,
        barcode: p.barcode || '',
        name: p.name,
        category_id: p.category_id,
        price: p.price,
        cost: p.cost,
        min_stock: p.min_stock,
        track_stock: Boolean(p.track_stock),
        allow_decimal_qty: Boolean(p.allow_decimal_qty),
        is_active: Boolean(p.is_active),
    };
}

async function promptNewCategory() {
    const name = window.prompt('Masukkan nama kategori baru:');
    if (!name) return;
    try {
        await apiRequest('/categories', {
            method: 'POST',
            body: { name, sort_order: store.categories.length + 1 },
        });
        await store.syncCatalog(true);
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function saveProduct() {
    errorMsg.value = '';
    successMsg.value = '';
    try {
        if (productForm.value.id) {
            await apiRequest(`/products/${productForm.value.id}`, {
                method: 'PUT',
                body: productForm.value,
            });
            successMsg.value = 'Produk berhasil diperbarui.';
        } else {
            await apiRequest('/products', {
                method: 'POST',
                body: productForm.value,
            });
            successMsg.value = 'Produk baru berhasil ditambahkan (stok awal 0, gunakan Penerimaan Stok untuk menambah saldo).';
        }
        resetProductForm();
        await store.syncCatalog(true);
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function handleCsvFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    errorMsg.value = '';
    successMsg.value = '';

    const text = await file.text();
    const lines = text.split(/\r?\n/).filter((l) => l.trim());
    if (lines.length < 2) {
        errorMsg.value = 'File CSV harus memiliki baris header dan minimal 1 baris data.';
        return;
    }

    const headers = lines[0].split(',').map((h) => h.trim().toLowerCase());
    const rows = lines.slice(1).map((line) => {
        const cols = line.split(',').map((c) => c.trim());
        const obj = {};
        headers.forEach((h, i) => {
            obj[h] = cols[i] ?? '';
        });
        return {
            sku: obj.sku,
            barcode: obj.barcode || null,
            name: obj.name,
            category: obj.category || null,
            price: Number(obj.price || 0),
            cost: Number(obj.cost || 0),
            track_stock: obj.track_stock !== '0' && obj.track_stock !== 'false',
            min_stock: Number(obj.min_stock || 0),
        };
    });

    try {
        const res = await apiRequest('/products/import-csv', {
            method: 'POST',
            body: { rows },
        });
        successMsg.value = res.message;
        await store.syncCatalog(true);
    } catch (err) {
        errorMsg.value = err.message;
    }
}

function onReceiptProductChange(row) {
    const p = store.products.find((x) => x.id === row.product_id);
    if (p) {
        row.unit_cost = p.cost;
    }
}

async function submitStockReceipt() {
    errorMsg.value = '';
    successMsg.value = '';
    try {
        const res = await apiRequest('/stock-receipts', {
            method: 'POST',
            body: receiptForm.value,
        });
        successMsg.value = `Penerimaan stok ${res.receipt.receipt_no} berhasil disimpan ke buku besar (W10).`;
        receiptForm.value.items = [{ product_id: '', qty: 10, unit_cost: 0 }];
        await store.syncCatalog(true);
    } catch (err) {
        errorMsg.value = err.message;
    }
}

function onAdjustmentProductSelect(row) {
    const p = store.products.find((x) => x.id === row.product_id);
    if (p) {
        row.expected_system_qty = Number(p.stock_qty);
        row.counted_qty = Number(p.stock_qty);
    }
}

async function submitStockAdjustment() {
    errorMsg.value = '';
    successMsg.value = '';
    try {
        const res = await apiRequest('/stock-adjustments', {
            method: 'POST',
            body: adjustmentForm.value,
        });
        successMsg.value = `Penyesuaian stok ${res.adjustment.adjustment_no} berhasil dicatat (W11).`;
        adjustmentForm.value.items = [{ product_id: '', expected_system_qty: 0, counted_qty: 0 }];
        await store.syncCatalog(true);
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function openStockCard(product) {
    errorMsg.value = '';
    try {
        stockCardData.value = await apiRequest(`/products/${product.id}/stock-card`);
    } catch (err) {
        errorMsg.value = err.message;
    }
}
</script>

