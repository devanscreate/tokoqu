<template>
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <!-- Header & Date Filter -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-pos-border pb-4">
            <div>
                <h1 class="text-xl font-bold uppercase tracking-wider text-pos-black">Laporan Penjualan, Margin & Antrean Tinjauan</h1>
                <p class="text-xs text-pos-muted mt-0.5">
                    Rekapitulasi harian dalam zona waktu toko, margin kotor dari snapshot HPP, stok menipis, serta antrean audit (W14).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input v-model="dateFrom" type="date" class="input-pos w-40 font-mono text-xs" />
                <span class="text-xs text-pos-muted">s/d</span>
                <input v-model="dateTo" type="date" class="input-pos w-40 font-mono text-xs" />
                <button @click="loadReports" class="btn-primary text-xs">Terapkan</button>
                <button @click="exportProductsCsv" class="btn-gold text-xs">Ekspor CSV (W14)</button>
            </div>
        </div>

        <div v-if="errorMsg" class="p-3.5 bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>

        <!-- KPI CARDS -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 tabular-nums">
            <div class="card-pos p-4 border-t-4 border-t-pos-black">
                <div class="text-[11px] uppercase tracking-wider font-semibold text-pos-muted">Penjualan Kotor ({{ report.kpis?.transactions_count || 0 }} Nota)</div>
                <div class="text-lg font-mono font-bold text-pos-black mt-1">{{ formatRupiah(report.kpis?.gross_sales) }}</div>
                <div class="text-[11px] text-pos-muted mt-1">Diskon: -{{ formatRupiah(report.kpis?.discounts_total) }}</div>
            </div>

            <div class="card-pos p-4 border-t-4 border-t-pos-gold">
                <div class="text-[11px] uppercase tracking-wider font-semibold text-pos-muted">Penjualan Bersih (Setelah Retur)</div>
                <div class="text-lg font-mono font-bold text-pos-black mt-1">{{ formatRupiah(report.kpis?.net_sales) }}</div>
                <div class="text-[11px] text-pos-danger mt-1">Retur Periode Ini: -{{ formatRupiah(report.kpis?.returns_total) }}</div>
            </div>

            <div class="card-pos p-4 border-t-4 border-t-pos-black">
                <div class="text-[11px] uppercase tracking-wider font-semibold text-pos-muted">Total HPP (Snapshot Cost)</div>
                <div class="text-lg font-mono font-bold text-pos-black mt-1">{{ formatRupiah(report.kpis?.cogs_total) }}</div>
                <div class="text-[11px] text-pos-muted mt-1">Pajak Terkumpul: {{ formatRupiah(report.kpis?.tax_total) }}</div>
            </div>

            <div class="card-pos p-4 border-t-4 border-t-pos-gold bg-pos-gold-tint">
                <div class="text-[11px] uppercase tracking-wider font-bold text-pos-black">Margin Kotor (Gross Margin)</div>
                <div class="text-lg font-mono font-bold text-pos-black mt-1">{{ formatRupiah(report.kpis?.gross_margin) }}</div>
                <div class="text-[11px] text-pos-muted mt-1">Dihitung dari snapshot saat transaksi (R4)</div>
            </div>
        </div>

        <!-- NEEDS-REVIEW QUEUE (W14: stock_conflict, late_adjusted, rejected sync) -->
        <div
            v-if="
                (report.review_queue?.stock_conflict_sales?.length || 0) > 0 ||
                (report.review_queue?.late_adjusted_shifts?.length || 0) > 0 ||
                store.needsAttentionOutbox.length > 0
            "
            class="card-pos border-2 border-pos-gold p-5 space-y-4"
        >
            <div class="flex items-center justify-between border-b border-pos-border pb-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-pos-black">
                    Antrean Perlu Tinjauan Manager (Needs-Review Queue - W14)
                </h2>
                <span class="px-2 py-0.5 bg-pos-gold text-white text-xs font-mono font-bold">ACTION REQUIRED</span>
            </div>

            <!-- Offline Sales with Stock Conflict (R11) -->
            <div v-if="report.review_queue?.stock_conflict_sales?.length" class="space-y-2">
                <div class="text-xs font-bold uppercase text-pos-danger">
                    Penjualan Offline Melebihi Stok (stock_conflict - R11):
                </div>
                <div
                    v-for="sc in report.review_queue.stock_conflict_sales"
                    :key="sc.id"
                    class="p-3 bg-pos-alabaster border border-pos-border flex items-center justify-between text-xs"
                >
                    <div>
                        <span class="font-mono font-bold">{{ sc.invoice_no }}</span> · Kasir: {{ sc.cashier?.name }} · Total: {{ formatRupiah(sc.grand_total) }}
                    </div>
                    <button
                        @click="resolveConflict(sc.id)"
                        class="px-3 py-1 bg-pos-black text-pos-gold font-semibold uppercase"
                    >
                        Tandai Selesai Ditinjau
                    </button>
                </div>
            </div>

            <!-- Late Adjusted Shifts (W12) -->
            <div v-if="report.review_queue?.late_adjusted_shifts?.length" class="space-y-2">
                <div class="text-xs font-bold uppercase text-pos-warning">
                    Shift Force-Closed dengan Transaksi Offline Susulan (late_adjusted - W12):
                </div>
                <div
                    v-for="ls in report.review_queue.late_adjusted_shifts"
                    :key="ls.id"
                    class="p-3 bg-pos-alabaster border border-pos-border text-xs font-mono"
                >
                    Register {{ ls.register?.code }} ({{ ls.cashier?.name }}) · Selisih Baru: {{ formatRupiah(ls.difference) }}
                </div>
            </div>

            <!-- Rejected Outbox Sync Items on This Device (W13.4) -->
            <div v-if="store.needsAttentionOutbox.length > 0" class="space-y-2">
                <div class="text-xs font-bold uppercase text-pos-danger">
                    Transaksi Outbox Offline Ditolak Server pada Perangkat Ini (W13.4):
                </div>
                <div
                    v-for="item in store.needsAttentionOutbox"
                    :key="item.client_uuid"
                    class="p-3 bg-red-50 border border-pos-danger flex items-center justify-between text-xs"
                >
                    <div>
                        <div class="font-mono font-bold">{{ item.invoice_no }} · {{ formatRupiah(item.grand_total) }}</div>
                        <div class="text-pos-danger">Alasan: {{ item.error_reason }}</div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="store.retryRejectedOutboxItem(item.client_uuid)"
                            class="px-2.5 py-1 bg-pos-black text-white text-xs"
                        >
                            Coba Kirim Ulang
                        </button>
                        <button
                            @click="store.removeResolvedOutboxItem(item.client_uuid)"
                            class="px-2.5 py-1 border border-pos-danger text-pos-danger text-xs"
                        >
                            Arsipkan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- BREAKDOWN TABLES -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- By Product -->
            <div class="lg:col-span-2 card-pos">
                <div class="px-4 py-3 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold uppercase tracking-wider">
                    Penjualan & Margin per Produk
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-pos-stone border-b border-pos-border uppercase text-[11px] text-pos-muted">
                                <th class="p-2.5">Produk</th>
                                <th class="p-2.5 text-right">Qty Bersih</th>
                                <th class="p-2.5 text-right">Omzet Bersih</th>
                                <th class="p-2.5 text-right">HPP</th>
                                <th class="p-2.5 text-right">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pos-border tabular-nums">
                            <tr v-for="row in report.by_product" :key="row.product_id">
                                <td class="p-2.5">
                                    <div class="font-semibold">{{ row.name }}</div>
                                    <div class="font-mono text-[10px] text-pos-muted">{{ row.sku }}</div>
                                </td>
                                <td class="p-2.5 text-right font-mono">{{ formatQty(row.net_qty) }}</td>
                                <td class="p-2.5 text-right font-mono">{{ formatRupiah(row.revenue) }}</td>
                                <td class="p-2.5 text-right font-mono text-pos-muted">{{ formatRupiah(row.cogs) }}</td>
                                <td class="p-2.5 text-right font-mono font-bold">{{ formatRupiah(row.gross_margin) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- By Payment Method, Category, Cashier & Low Stock -->
            <div class="space-y-6">
                <div class="card-pos">
                    <div class="px-4 py-2.5 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold uppercase">
                        Per Metode Pembayaran
                    </div>
                    <div class="divide-y divide-pos-border text-xs tabular-nums">
                        <div
                            v-for="pm in report.by_payment_method"
                            :key="pm.method"
                            class="p-3 flex justify-between"
                        >
                            <span class="uppercase font-semibold">{{ pm.method }} ({{ pm.tx_count }}x)</span>
                            <span class="font-mono font-bold">{{ formatRupiah(pm.total_amount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="card-pos">
                    <div class="px-4 py-2.5 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold uppercase">
                        Per Kasir
                    </div>
                    <div class="divide-y divide-pos-border text-xs tabular-nums">
                        <div
                            v-for="c in report.by_cashier"
                            :key="c.cashier_id"
                            class="p-3 flex justify-between"
                        >
                            <span class="font-medium">{{ c.cashier_name }} ({{ c.tx_count }} nota)</span>
                            <span class="font-mono font-bold">{{ formatRupiah(c.total_sales) }}</span>
                        </div>
                    </div>
                </div>

                <div class="card-pos">
                    <div class="px-4 py-2.5 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold uppercase">
                        Peringatan Stok Menipis ({{ report.low_stock_products?.length || 0 }})
                    </div>
                    <div class="divide-y divide-pos-border text-xs tabular-nums max-h-60 overflow-y-auto">
                        <div
                            v-for="lp in report.low_stock_products"
                            :key="lp.id"
                            class="p-3 flex items-center justify-between"
                        >
                            <div>
                                <div class="font-semibold">{{ lp.name }}</div>
                                <div class="text-[10px] font-mono text-pos-muted">Min: {{ formatQty(lp.min_stock) }}</div>
                            </div>
                            <span class="font-mono font-bold text-pos-danger">{{ formatQty(lp.stock_qty) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AUDIT LOGS -->
        <div class="card-pos">
            <div class="px-4 py-3 bg-pos-black text-pos-white border-b border-pos-gold text-xs font-bold uppercase tracking-wider">
                Log Audit Sensitif (Append-Only Audit Trail)
            </div>
            <div class="overflow-x-auto max-h-72">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-pos-stone border-b border-pos-border uppercase text-[11px] text-pos-muted">
                            <th class="p-2.5">Waktu</th>
                            <th class="p-2.5">Aksi</th>
                            <th class="p-2.5">Entitas</th>
                            <th class="p-2.5">Pelaksana</th>
                            <th class="p-2.5">Disetujui Oleh</th>
                            <th class="p-2.5">Alasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pos-border">
                        <tr v-for="log in report.audit_logs" :key="log.id">
                            <td class="p-2.5 font-mono text-[11px]">{{ formatDateTime(log.created_at) }}</td>
                            <td class="p-2.5 font-mono font-bold">{{ log.action }}</td>
                            <td class="p-2.5 font-mono text-[11px]">{{ log.entity }} #{{ log.entity_id }}</td>
                            <td class="p-2.5">{{ log.user?.name || 'System' }}</td>
                            <td class="p-2.5">{{ log.approver?.name || '—' }}</td>
                            <td class="p-2.5 text-pos-muted">{{ log.reason || '—' }}</td>
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
            body: { note: 'Diselesaikan oleh Manager melalui Review Queue' },
        });
        await loadReports();
    } catch (err) {
        errorMsg.value = err.message;
    }
}

function exportProductsCsv() {
    const rows = [
        ['SKU', 'Nama Produk', 'Qty Terjual Bersih', 'Omzet Bersih (Rp)', 'HPP (Rp)', 'Margin Kotor (Rp)'],
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
    link.setAttribute('download', `laporan_pos_${dateFrom.value}_${dateTo.value}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

onMounted(() => {
    loadReports();
});
</script>

