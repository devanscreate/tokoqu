<template>
    <div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">
        <!-- Header & Date Filter -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-pos-border pb-4">
            <div>
                <h1 class="text-xl font-bold text-pos-black">Laporan Penjualan & Keuntungan Toko</h1>
                <p class="text-xs text-pos-muted mt-0.5">
                    Pantau omzet penjualan, keuntungan bersih (margin), rincian pembayaran, dan pengingat barang yang stoknya menipis.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input v-model="dateFrom" type="date" class="input-pos w-40 font-mono text-xs" />
                <span class="text-xs text-pos-muted">sampai</span>
                <input v-model="dateTo" type="date" class="input-pos w-40 font-mono text-xs" />
                <button @click="loadReports" class="btn-primary text-xs">Tampilkan</button>
                <button @click="exportProductsCsv" class="btn-gold text-xs">Unduh Laporan (CSV)</button>
            </div>
        </div>

        <div v-if="errorMsg" class="p-3.5 rounded-xl bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>

        <!-- SUMMARY CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 tabular-nums">
            <div class="card-pos p-5">
                <div class="text-xs font-semibold text-pos-muted">Total Penjualan Kotor ({{ report.kpis?.transactions_count || 0 }} Struk)</div>
                <div class="text-xl font-mono font-bold text-pos-black mt-1.5">{{ formatRupiah(report.kpis?.gross_sales) }}</div>
                <div class="text-[11px] text-pos-muted mt-1">Total Diskon Diberikan: -{{ formatRupiah(report.kpis?.discounts_total) }}</div>
            </div>

            <div class="card-pos p-5">
                <div class="text-xs font-semibold text-pos-muted">Omzet Bersih (Setelah Retur)</div>
                <div class="text-xl font-mono font-bold text-pos-black mt-1.5">{{ formatRupiah(report.kpis?.net_sales) }}</div>
                <div class="text-[11px] text-pos-danger mt-1">Total Retur Pelanggan: -{{ formatRupiah(report.kpis?.returns_total) }}</div>
            </div>

            <div class="card-pos p-5">
                <div class="text-xs font-semibold text-pos-muted">Total Modal Barang Terjual</div>
                <div class="text-xl font-mono font-bold text-pos-black mt-1.5">{{ formatRupiah(report.kpis?.cogs_total) }}</div>
                <div class="text-[11px] text-pos-muted mt-1">PPN Terkumpul: {{ formatRupiah(report.kpis?.tax_total) }}</div>
            </div>

            <div class="card-pos p-5 bg-pos-gold-tint border-pos-gold">
                <div class="text-xs font-bold text-pos-black">Keuntungan Kotor (Margin)</div>
                <div class="text-xl font-mono font-bold text-pos-black mt-1.5">{{ formatRupiah(report.kpis?.gross_margin) }}</div>
                <div class="text-[11px] text-pos-muted mt-1">Selisih harga jual & modal barang</div>
            </div>
        </div>

        <!-- REVIEW QUEUE IF ANY -->
        <div
            v-if="
                (report.review_queue?.stock_conflict_sales?.length || 0) > 0 ||
                (report.review_queue?.late_adjusted_shifts?.length || 0) > 0 ||
                store.needsAttentionOutbox.length > 0
            "
            class="card-pos border-2 border-pos-gold p-5 space-y-4"
        >
            <div class="flex items-center justify-between border-b border-pos-border pb-2.5">
                <div>
                    <h2 class="text-sm font-bold text-pos-black">Daftar Transaksi yang Perlu Dicek Manajer</h2>
                    <p class="text-xs text-pos-muted">Transaksi yang terjadi saat mode offline dan membutuhkan pengecekan stok atau laci.</p>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-pos-gold text-white text-xs font-bold">Perlu Dicek</span>
            </div>

            <div v-if="report.review_queue?.stock_conflict_sales?.length" class="space-y-2">
                <div class="text-xs font-bold text-pos-danger">
                    Penjualan Saat Offline yang Melebihi Catatan Stok Sistem:
                </div>
                <div
                    v-for="sc in report.review_queue.stock_conflict_sales"
                    :key="sc.id"
                    class="p-3.5 rounded-xl bg-pos-alabaster border border-pos-border flex items-center justify-between text-xs"
                >
                    <div>
                        <span class="font-mono font-bold">{{ sc.invoice_no }}</span> · Kasir: {{ sc.cashier?.name }} · Total: {{ formatRupiah(sc.grand_total) }}
                    </div>
                    <button
                        @click="resolveConflict(sc.id)"
                        class="px-3 py-1.5 rounded-xl bg-pos-black text-pos-gold font-semibold"
                    >
                        Tandai Sudah Dicek
                    </button>
                </div>
            </div>

            <div v-if="report.review_queue?.late_adjusted_shifts?.length" class="space-y-2">
                <div class="text-xs font-bold text-pos-warning">
                    Shift yang Menerima Sinkronisasi Nota Offline Susulan:
                </div>
                <div
                    v-for="ls in report.review_queue.late_adjusted_shifts"
                    :key="ls.id"
                    class="p-3.5 rounded-xl bg-pos-alabaster border border-pos-border text-xs font-mono"
                >
                    Meja Kasir {{ ls.register?.code }} ({{ ls.cashier?.name }}) · Selisih Laci Terbaru: {{ formatRupiah(ls.difference) }}
                </div>
            </div>

            <div v-if="store.needsAttentionOutbox.length > 0" class="space-y-2">
                <div class="text-xs font-bold text-pos-danger">
                    Transaksi Offline di Perangkat Ini yang Gagal Terkirim:
                </div>
                <div
                    v-for="item in store.needsAttentionOutbox"
                    :key="item.client_uuid"
                    class="p-3.5 rounded-xl bg-red-50 border border-pos-danger flex items-center justify-between text-xs"
                >
                    <div>
                        <div class="font-mono font-bold">{{ item.invoice_no }} · {{ formatRupiah(item.grand_total) }}</div>
                        <div class="text-pos-danger">Keterangan: {{ item.error_reason }}</div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="store.retryRejectedOutboxItem(item.client_uuid)"
                            class="px-3 py-1.5 rounded-xl bg-pos-black text-white text-xs"
                        >
                            Kirim Ulang
                        </button>
                        <button
                            @click="store.removeResolvedOutboxItem(item.client_uuid)"
                            class="px-3 py-1.5 rounded-xl border border-pos-danger text-pos-danger text-xs"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- BREAKDOWN TABLES -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- By Product -->
            <div class="lg:col-span-2 card-pos overflow-hidden">
                <div class="px-5 py-3.5 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold">
                    Rincian Penjualan & Keuntungan per Barang
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-pos-stone border-b border-pos-border text-[11px] font-semibold text-pos-muted">
                                <th class="p-3">Nama Produk</th>
                                <th class="p-3 text-right">Terjual</th>
                                <th class="p-3 text-right">Penjualan Bersih</th>
                                <th class="p-3 text-right">Modal Barang</th>
                                <th class="p-3 text-right">Keuntungan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pos-border tabular-nums">
                            <tr v-for="row in report.by_product" :key="row.product_id">
                                <td class="p-3">
                                    <div class="font-semibold text-pos-black">{{ row.name }}</div>
                                    <div class="font-mono text-[10px] text-pos-muted">{{ row.sku }}</div>
                                </td>
                                <td class="p-3 text-right font-mono">{{ formatQty(row.net_qty) }}</td>
                                <td class="p-3 text-right font-mono">{{ formatRupiah(row.revenue) }}</td>
                                <td class="p-3 text-right font-mono text-pos-muted">{{ formatRupiah(row.cogs) }}</td>
                                <td class="p-3 text-right font-mono font-bold text-pos-black">{{ formatRupiah(row.gross_margin) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column Summaries -->
            <div class="space-y-6">
                <div class="card-pos overflow-hidden">
                    <div class="px-4 py-3 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold">
                        Penerimaan per Cara Bayar
                    </div>
                    <div class="divide-y divide-pos-border text-xs tabular-nums">
                        <div
                            v-for="pm in report.by_payment_method"
                            :key="pm.method"
                            class="p-3.5 flex justify-between"
                        >
                            <span class="uppercase font-semibold">{{ pm.method }} ({{ pm.tx_count }} transaksi)</span>
                            <span class="font-mono font-bold">{{ formatRupiah(pm.total_amount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="card-pos overflow-hidden">
                    <div class="px-4 py-3 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold">
                        Penjualan per Petugas Kasir
                    </div>
                    <div class="divide-y divide-pos-border text-xs tabular-nums">
                        <div
                            v-for="c in report.by_cashier"
                            :key="c.cashier_id"
                            class="p-3.5 flex justify-between"
                        >
                            <span class="font-medium">{{ c.cashier_name }} ({{ c.tx_count }} struk)</span>
                            <span class="font-mono font-bold">{{ formatRupiah(c.total_sales) }}</span>
                        </div>
                    </div>
                </div>

                <div class="card-pos overflow-hidden">
                    <div class="px-4 py-3 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold">
                        Barang yang Stoknya Menipis ({{ report.low_stock_products?.length || 0 }})
                    </div>
                    <div class="divide-y divide-pos-border text-xs tabular-nums max-h-60 overflow-y-auto">
                        <div v-if="!report.low_stock_products?.length" class="p-4 text-center text-pos-muted">
                            Semua stok barang masih aman.
                        </div>
                        <div
                            v-for="lp in report.low_stock_products"
                            :key="lp.id"
                            class="p-3.5 flex items-center justify-between"
                        >
                            <div>
                                <div class="font-semibold text-pos-black">{{ lp.name }}</div>
                                <div class="text-[11px] text-pos-muted">Batas minimal: {{ formatQty(lp.min_stock) }}</div>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full bg-red-50 border border-red-200 font-mono font-bold text-pos-danger">
                                Sisa {{ formatQty(lp.stock_qty) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AUDIT LOGS -->
        <div class="card-pos overflow-hidden">
            <div class="px-5 py-3.5 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold">
                Catatan Aktivitas Penting & Persetujuan PIN Manajer
            </div>
            <div class="overflow-x-auto max-h-72">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-pos-stone border-b border-pos-border text-[11px] font-semibold text-pos-muted">
                            <th class="p-3">Waktu</th>
                            <th class="p-3">Aktivitas</th>
                            <th class="p-3">Dilakukan Oleh</th>
                            <th class="p-3">Disetujui Manajer</th>
                            <th class="p-3">Alasan / Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pos-border">
                        <tr v-for="log in report.audit_logs" :key="log.id">
                            <td class="p-3 font-mono text-[11px]">{{ formatDateTime(log.created_at) }}</td>
                            <td class="p-3 font-semibold">{{ log.action }}</td>
                            <td class="p-3">{{ log.user?.name || 'Sistem' }}</td>
                            <td class="p-3">{{ log.approver?.name || '—' }}</td>
                            <td class="p-3 text-pos-muted">{{ log.reason || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { usePosStore } from '../stores/posStore';
import { apiRequest } from '../utils/api';
import { formatDateTime, formatQty, formatRupiah } from '../utils/format';

const store = usePosStore();
const today = new Date().toISOString().slice(0, 10);
const dateFrom = ref(today);
const dateTo = ref(today);
const errorMsg = ref('');

const report = ref({
    kpis: {},
    by_product: [],
    by_category: [],
    by_payment_method: [],
    by_cashier: [],
    low_stock_products: [],
    review_queue: { stock_conflict_sales: [], late_adjusted_shifts: [] },
    audit_logs: [],
});

async function loadReports() {
    errorMsg.value = '';
    try {
        report.value = await apiRequest(
            `/reports/summary?from=${encodeURIComponent(dateFrom.value)}&to=${encodeURIComponent(dateTo.value)}`
        );
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function resolveConflict(saleId) {
    try {
        await apiRequest(`/reports/stock-conflicts/${saleId}/resolve`, {
            method: 'POST',
            body: { note: 'Sudah diperiksa oleh Manajer' },
        });
        await loadReports();
    } catch (err) {
        errorMsg.value = err.message;
    }
}

function exportProductsCsv() {
    const rows = [
        ['SKU', 'Nama Produk', 'Qty Terjual Bersih', 'Omzet Bersih (Rp)', 'Modal HPP (Rp)', 'Keuntungan Kotor (Rp)'],
        ...(report.value.by_product || []).map((r) => [
            r.sku,
            `"${(r.name || '').replace(/"/g, '""')}"`,
            r.net_qty,
            r.revenue,
            r.cogs,
            r.gross_margin,
        ]),
    ];
    const csvContent = rows.map((e) => e.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `laporan_toko_${dateFrom.value}_${dateTo.value}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

onMounted(() => {
    loadReports();
});
</script>
