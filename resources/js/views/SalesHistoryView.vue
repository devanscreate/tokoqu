<template>
    <div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-pos-border pb-4">
            <div>
                <h1 class="text-xl font-bold text-pos-black">Riwayat Struk, Pembatalan & Retur Barang</h1>
                <p class="text-xs text-pos-muted mt-0.5">
                    Cari nomor struk belanja pelanggan untuk mencetak ulang struk, membatalkan salah input, atau memproses pengembalian barang (retur).
                </p>
            </div>
            <div class="flex gap-2">
                <input
                    v-model="invoiceSearch"
                    @keydown.enter.prevent="loadSales"
                    type="text"
                    placeholder="Ketik / Scan Nomor Struk..."
                    class="input-pos w-full sm:w-64 font-mono"
                />
                <button @click="loadSales" class="btn-primary shrink-0">Cari Struk</button>
            </div>
        </div>

        <div v-if="errorMsg" class="p-3.5 rounded-xl bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>
        <div v-if="successMsg" class="p-3.5 rounded-xl bg-pos-gold-tint border border-pos-gold text-xs text-pos-black font-semibold">
            {{ successMsg }}
        </div>

        <div class="card-pos overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-pos-black text-pos-white text-[11px] font-semibold border-b border-pos-gold">
                            <th class="p-3.5">Nomor Struk</th>
                            <th class="p-3.5">Tanggal & Jam</th>
                            <th class="p-3.5">Meja Kasir & Petugas</th>
                            <th class="p-3.5 text-right">Subtotal</th>
                            <th class="p-3.5 text-right">Diskon</th>
                            <th class="p-3.5 text-right">Total Bayar</th>
                            <th class="p-3.5">Status</th>
                            <th class="p-3.5 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pos-border tabular-nums">
                        <tr v-for="sale in sales" :key="sale.id" class="hover:bg-pos-alabaster">
                            <td class="p-3.5 font-mono font-bold text-pos-black">
                                {{ sale.invoice_no }}
                                <span v-if="sale.is_offline" class="ml-1 px-2 py-0.5 rounded bg-pos-stone text-[10px]">Offline</span>
                            </td>
                            <td class="p-3.5 font-mono">{{ formatDateTime(sale.created_at) }}</td>
                            <td class="p-3.5">
                                <span class="font-mono font-semibold">{{ sale.register?.code }}</span> · {{ sale.cashier?.name }}
                            </td>
                            <td class="p-3.5 text-right font-mono">{{ formatRupiah(sale.subtotal) }}</td>
                            <td class="p-3.5 text-right font-mono text-pos-danger">
                                {{ sale.discount_total > 0 ? '-' + formatRupiah(sale.discount_total) : 'Rp 0' }}
                            </td>
                            <td class="p-3.5 text-right font-mono font-bold">{{ formatRupiah(sale.grand_total) }}</td>
                            <td class="p-3.5">
                                <span
                                    :class="[
                                        'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase',
                                        sale.status === 'completed'
                                            ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                            : sale.status === 'voided'
                                              ? 'bg-red-50 text-pos-danger border border-red-200'
                                              : 'bg-amber-50 text-amber-900 border border-amber-300',
                                    ]"
                                >
                                    {{ statusLabel(sale.status) }}
                                </span>
                            </td>
                            <td class="p-3.5 text-right space-x-1.5 whitespace-nowrap">
                                <button
                                    @click="reprintCopy(sale)"
                                    class="px-3 py-1.5 rounded-xl border border-pos-border bg-pos-white hover:border-pos-gold text-xs font-medium"
                                >
                                    Cetak Salinan
                                </button>
                                <button
                                    v-if="sale.status === 'completed' && sale.shift?.status === 'open'"
                                    @click="initiateVoid(sale)"
                                    class="px-3 py-1.5 rounded-xl bg-pos-danger text-white text-xs font-medium"
                                    title="Batalkan transaksi yang baru dibuat di shift ini"
                                >
                                    Batalkan (Void)
                                </button>
                                <button
                                    v-if="['completed', 'partially_returned'].includes(sale.status)"
                                    @click="openReturnModal(sale)"
                                    class="px-3 py-1.5 rounded-xl bg-pos-black text-pos-gold border border-pos-gold text-xs font-semibold"
                                >
                                    Retur Barang
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RETURN & REFUND MODAL -->
        <div v-if="returnTargetSale" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
            <div class="bg-pos-white border border-pos-border rounded-3xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden shadow-2xl">
                <div class="px-6 py-4 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-sm">
                            Pengembalian Barang (Retur) — Struk {{ returnTargetSale.invoice_no }}
                        </h3>
                        <p class="text-[11px] text-pos-stone">
                            Isi jumlah barang yang dikembalikan pelanggan dan pilih apakah barang dimasukkan kembali ke stok atau rusak.
                        </p>
                    </div>
                    <button @click="returnTargetSale = null" class="text-xs text-pos-white hover:text-pos-gold">Tutup [ESC]</button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4 flex-1">
                    <div class="rounded-2xl border border-pos-border overflow-hidden">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-pos-stone border-b border-pos-border text-[11px] font-semibold text-pos-muted">
                                    <th class="p-3">Nama Barang</th>
                                    <th class="p-3 text-right">Dibeli</th>
                                    <th class="p-3 text-right">Sudah Diretur</th>
                                    <th class="p-3 text-right">Nilai Bersih</th>
                                    <th class="p-3 text-center">Jumlah Diretur</th>
                                    <th class="p-3 text-center">Kondisi Barang</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-pos-border tabular-nums">
                                <tr v-for="item in returnForm.items" :key="item.sale_item_id">
                                    <td class="p-3 font-medium">{{ item.name }}</td>
                                    <td class="p-3 text-right font-mono">{{ item.sold_qty }}</td>
                                    <td class="p-3 text-right font-mono">{{ item.already_returned_qty }}</td>
                                    <td class="p-3 text-right font-mono">{{ formatRupiah(item.net_total) }}</td>
                                    <td class="p-3 text-center">
                                        <input
                                            v-model.number="item.qty"
                                            type="number"
                                            min="0"
                                            :max="item.max_returnable"
                                            step="1"
                                            class="w-20 min-h-[38px] px-2 rounded-xl text-center font-mono border border-pos-border"
                                        />
                                    </td>
                                    <td class="p-3 text-center">
                                        <select v-model="item.restock" class="min-h-[38px] px-2.5 rounded-xl border border-pos-border text-xs">
                                            <option :value="true">Bagus (Masuk Stok Lagi)</option>
                                            <option :value="false">Rusak (Jangan Masuk Stok)</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-pos-black mb-1">Cara Pengembalian Uang</label>
                            <select v-model="returnForm.refund_method" class="input-pos">
                                <option value="cash">Uang Tunai (Diambil dari Laci Kasir)</option>
                                <option value="transfer">Transfer Bank Manual</option>
                                <option value="qris">Pengembalian QRIS Manual</option>
                                <option value="card">Pengembalian Kartu Manual</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-pos-black mb-1">Alasan Pengembalian Barang *</label>
                            <input
                                v-model="returnForm.reason"
                                type="text"
                                required
                                placeholder="Contoh: Barang cacat / salah beli ukuran"
                                class="input-pos"
                            />
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-pos-white border-t border-pos-border flex justify-end gap-2.5">
                    <button @click="returnTargetSale = null" class="btn-outline">Batal</button>
                    <button @click="initiateReturnSubmit" class="btn-gold">Konfirmasi Retur Barang</button>
                </div>
            </div>
        </div>

        <ManagerPinModal
            :open="pinModal.open"
            :action="pinModal.action"
            :default-reason="pinModal.reason"
            @close="pinModal.open = false"
            @approved="onPinApproved"
        />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import ManagerPinModal from '../components/ManagerPinModal.vue';
import { usePosStore } from '../stores/posStore';
import { apiRequest } from '../utils/api';
import { formatDateTime, formatRupiah } from '../utils/format';

const store = usePosStore();
const invoiceSearch = ref('');
const sales = ref([]);
const errorMsg = ref('');
const successMsg = ref('');

const voidTargetSale = ref(null);
const returnTargetSale = ref(null);
const returnForm = ref({
    refund_method: 'cash',
    reason: '',
    items: [],
});

const pinModal = ref({
    open: false,
    action: 'void',
    reason: '',
});

function statusLabel(status) {
    const map = {
        completed: 'Selesai',
        voided: 'Dibatalkan',
        partially_returned: 'Retur Sebagian',
        returned: 'Diretur Penuh',
    };
    return map[status] || status;
}

async function loadSales() {
    errorMsg.value = '';
    try {
        const q = invoiceSearch.value.trim() ? `?invoice=${encodeURIComponent(invoiceSearch.value.trim())}` : '';
        const res = await apiRequest(`/sales${q}`);
        sales.value = res.sales || [];
    } catch (err) {
        errorMsg.value = err.message;
    }
}

function reprintCopy(sale) {
    store.activeReceipt = {
        ...sale,
        is_copy: true,
    };
}

function initiateVoid(sale) {
    errorMsg.value = '';
    successMsg.value = '';
    voidTargetSale.value = sale;

    if (['manager', 'owner'].includes(store.user?.role)) {
        const reason = window.prompt(
            `Masukkan alasan pembatalan (Void) untuk struk ${sale.invoice_no}:`,
            'Salah input barang oleh kasir'
        );
        if (reason) {
            executeVoid(sale.id, reason, null);
        }
    } else {
        pinModal.value = {
            open: true,
            action: 'void',
            reason: `Pembatalan struk ${sale.invoice_no}`,
        };
    }
}

async function executeVoid(saleId, reason, approvalToken) {
    try {
        await apiRequest(`/sales/${saleId}/void`, {
            method: 'POST',
            body: { reason, approval_token: approvalToken },
        });
        successMsg.value = 'Struk berhasil dibatalkan dan stok barang telah dikembalikan.';
        await loadSales();
        await store.syncCatalog(true);
    } catch (err) {
        errorMsg.value = err.message;
    }
}

function openReturnModal(sale) {
    errorMsg.value = '';
    successMsg.value = '';
    returnTargetSale.value = sale;
    returnForm.value = {
        refund_method: 'cash',
        reason: '',
        items: sale.items.map((i) => ({
            sale_item_id: i.id,
            name: i.name,
            sold_qty: Number(i.qty),
            already_returned_qty: Number(i.returned_qty || 0),
            max_returnable: Number((Number(i.qty) - Number(i.returned_qty || 0)).toFixed(3)),
            net_total: i.net_total,
            qty: 0,
            restock: true,
        })),
    };
}

function initiateReturnSubmit() {
    const selected = returnForm.value.items.filter((i) => i.qty > 0);
    if (!selected.length) {
        errorMsg.value = 'Isi jumlah barang yang diretur minimal pada 1 barang.';
        return;
    }
    if (!returnForm.value.reason.trim()) {
        errorMsg.value = 'Alasan pengembalian barang wajib diisi.';
        return;
    }

    if (['manager', 'owner'].includes(store.user?.role)) {
        executeReturn(null);
    } else {
        pinModal.value = {
            open: true,
            action: 'return',
            reason: returnForm.value.reason,
        };
    }
}

async function executeReturn(approvalToken) {
    try {
        const selected = returnForm.value.items
            .filter((i) => i.qty > 0)
            .map((i) => ({
                sale_item_id: i.sale_item_id,
                qty: i.qty,
                restock: i.restock,
            }));

        const res = await apiRequest(`/sales/${returnTargetSale.value.id}/returns`, {
            method: 'POST',
            body: {
                shift_id: store.currentShift?.id || null,
                refund_method: returnForm.value.refund_method,
                reason: returnForm.value.reason,
                approval_token: approvalToken,
                items: selected,
            },
        });

        returnTargetSale.value = null;
        successMsg.value = `Retur barang (${res.sale_return.return_no}) berhasil disimpan. Uang dikembalikan: ${formatRupiah(res.sale_return.refund_total)}.`;
        await loadSales();
        await store.syncCatalog(true);
    } catch (err) {
        errorMsg.value = err.message;
    }
}

function onPinApproved({ approval_token, reason }) {
    pinModal.value.open = false;
    if (pinModal.value.action === 'void' && voidTargetSale.value) {
        executeVoid(voidTargetSale.value.id, reason, approval_token);
    } else if (pinModal.value.action === 'return' && returnTargetSale.value) {
        executeReturn(approval_token);
    }
}

onMounted(() => {
    loadSales();
});
</script>
