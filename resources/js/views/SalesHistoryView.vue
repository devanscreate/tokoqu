<template>
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-pos-border pb-4">
            <div>
                <h1 class="text-xl font-bold uppercase tracking-wider text-pos-black">Riwayat Nota, Void & Retur</h1>
                <p class="text-xs text-pos-muted mt-0.5">
                    Cari atau scan nomor nota untuk cetak salinan (COPY), Void transaksi (W6), atau Retur barang (W7).
                </p>
            </div>
            <div class="flex gap-2">
                <input
                    v-model="invoiceSearch"
                    @keydown.enter.prevent="loadSales"
                    type="text"
                    placeholder="Cari / Scan No. Nota (misal: KSR-01-...)"
                    class="input-pos w-64 font-mono"
                />
                <button @click="loadSales" class="btn-primary">Cari</button>
            </div>
        </div>

        <div v-if="errorMsg" class="p-3.5 bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>
        <div v-if="successMsg" class="p-3.5 bg-pos-gold-tint border border-pos-gold text-xs text-pos-black font-semibold">
            {{ successMsg }}
        </div>

        <div class="card-pos overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-pos-black text-pos-white uppercase text-[11px] tracking-wider border-b border-pos-gold">
                        <th class="p-3">No. Nota</th>
                        <th class="p-3">Waktu</th>
                        <th class="p-3">Register / Kasir</th>
                        <th class="p-3 text-right">Subtotal</th>
                        <th class="p-3 text-right">Diskon</th>
                        <th class="p-3 text-right">Grand Total</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pos-border tabular-nums">
                    <tr v-for="sale in sales" :key="sale.id" class="hover:bg-pos-alabaster">
                        <td class="p-3 font-mono font-bold text-pos-black">
                            {{ sale.invoice_no }}
                            <span v-if="sale.is_offline" class="ml-1 px-1.5 py-0.5 bg-pos-stone text-[10px]">OFFLINE</span>
                        </td>
                        <td class="p-3 font-mono">{{ formatDateTime(sale.created_at) }}</td>
                        <td class="p-3">
                            <span class="font-mono font-semibold">{{ sale.register?.code }}</span> · {{ sale.cashier?.name }}
                        </td>
                        <td class="p-3 text-right font-mono">{{ formatRupiah(sale.subtotal) }}</td>
                        <td class="p-3 text-right font-mono text-pos-danger">
                            {{ sale.discount_total > 0 ? '-' + formatRupiah(sale.discount_total) : 'Rp 0' }}
                        </td>
                        <td class="p-3 text-right font-mono font-bold">{{ formatRupiah(sale.grand_total) }}</td>
                        <td class="p-3">
                            <span
                                :class="[
                                    'px-2 py-0.5 text-[10px] font-mono font-bold uppercase',
                                    sale.status === 'completed'
                                        ? 'bg-pos-gold-tint text-pos-black border border-pos-gold'
                                        : sale.status === 'voided'
                                          ? 'bg-red-50 text-pos-danger border border-red-300'
                                          : 'bg-pos-stone text-pos-black border border-pos-border',
                                ]"
                            >
                                {{ sale.status }}
                            </span>
                        </td>
                        <td class="p-3 text-right space-x-1.5 whitespace-nowrap">
                            <button
                                @click="reprintCopy(sale)"
                                class="px-2.5 py-1.5 border border-pos-border bg-pos-white hover:border-pos-gold text-xs font-medium"
                            >
                                Cetak COPY
                            </button>
                            <button
                                v-if="sale.status === 'completed' && sale.shift?.status === 'open'"
                                @click="initiateVoid(sale)"
                                class="px-2.5 py-1.5 bg-pos-danger text-white text-xs font-medium"
                            >
                                Void (W6)
                            </button>
                            <button
                                v-if="['completed', 'partially_returned'].includes(sale.status)"
                                @click="openReturnModal(sale)"
                                class="px-2.5 py-1.5 bg-pos-black text-pos-gold border border-pos-gold text-xs font-semibold"
                            >
                                Retur (W7)
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- RETURN & REFUND MODAL (W7) -->
        <div v-if="returnTargetSale" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
            <div class="bg-pos-white border-2 border-pos-black w-full max-w-2xl flex flex-col max-h-[90vh]">
                <div class="px-5 py-3.5 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-sm uppercase tracking-wider">
                            Retur & Refund Nota {{ returnTargetSale.invoice_no }} (W7)
                        </h3>
                        <p class="text-[11px] text-pos-stone">
                            Pilih kuantitas item yang diretur serta status Restock (masuk stok kembali) atau Write-Off (rusak/buang).
                        </p>
                    </div>
                    <button @click="returnTargetSale = null" class="text-xs font-mono text-pos-white hover:text-pos-gold">[ESC]</button>
                </div>

                <div class="p-5 overflow-y-auto space-y-4 flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-pos-stone border-b border-pos-border uppercase text-[11px] text-pos-muted">
                                <th class="p-2.5">Produk</th>
                                <th class="p-2.5 text-right">Terjual</th>
                                <th class="p-2.5 text-right">Sudah Diretur</th>
                                <th class="p-2.5 text-right">Net Total Baris</th>
                                <th class="p-2.5 text-center">Qty Retur</th>
                                <th class="p-2.5 text-center">Perlakuan Stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pos-border tabular-nums">
                            <tr v-for="(item, idx) in returnForm.items" :key="item.sale_item_id">
                                <td class="p-2.5 font-medium">{{ item.name }}</td>
                                <td class="p-2.5 text-right font-mono">{{ item.sold_qty }}</td>
                                <td class="p-2.5 text-right font-mono">{{ item.already_returned_qty }}</td>
                                <td class="p-2.5 text-right font-mono">{{ formatRupiah(item.net_total) }}</td>
                                <td class="p-2.5 text-center">
                                    <input
                                        v-model.number="item.qty"
                                        type="number"
                                        min="0"
                                        :max="item.max_returnable"
                                        step="1"
                                        class="w-20 min-h-[38px] px-2 text-center font-mono border border-pos-border"
                                    />
                                </td>
                                <td class="p-2.5 text-center">
                                    <select v-model="item.restock" class="min-h-[38px] px-2 border border-pos-border text-xs">
                                        <option :value="true">Restock (+Stok)</option>
                                        <option :value="false">Write-Off (Rusak)</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Metode Pengembalian Dana</label>
                            <select v-model="returnForm.refund_method" class="input-pos">
                                <option value="cash">Tunai (Diambil dari Laci Shift Aktif)</option>
                                <option value="transfer">Transfer Manual</option>
                                <option value="qris">Refund QRIS Manual</option>
                                <option value="card">Reversal Kartu Manual</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Alasan Retur</label>
                            <input
                                v-model="returnForm.reason"
                                type="text"
                                required
                                placeholder="Contoh: Kemasan rusak / tukar ukuran"
                                class="input-pos"
                            />
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3.5 bg-pos-white border-t border-pos-border flex justify-end gap-2">
                    <button @click="returnTargetSale = null" class="btn-outline">Batal</button>
                    <button @click="initiateReturnSubmit" class="btn-gold">Proses Retur & Refund</button>
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
        const reason = window.prompt(`Masukkan alasan Void untuk nota ${sale.invoice_no} (W6):`, 'Salah input item oleh kasir');
        if (reason) {
            executeVoid(sale.id, reason, null);
        }
    } else {
        pinModal.value = {
            open: true,
            action: 'void',
            reason: `Void nota ${sale.invoice_no}`,
        };
    }
}

async function executeVoid(saleId, reason, approvalToken) {
    try {
        await apiRequest(`/sales/${saleId}/void`, {
            method: 'POST',
            body: { reason, approval_token: approvalToken },
        });
        successMsg.value = 'Transaksi berhasil di-void dan stok telah dikembalikan ke buku besar (W6).';
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
        errorMsg.value = 'Masukkan kuantitas retur minimal pada 1 baris produk.';
        return;
    }
    if (!returnForm.value.reason.trim()) {
        errorMsg.value = 'Alasan retur wajib diisi.';
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
        successMsg.value = `Retur ${res.sale_return.return_no} berhasil diproses. Total Refund: ${formatRupiah(res.sale_return.refund_total)} (W7).`;
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

