<template>
    <div v-if="receipt" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="bg-pos-white border border-pos-black w-full max-w-md flex flex-col max-h-[90vh]">
            <!-- Modal Header (Hidden on Print) -->
            <div class="no-print flex items-center justify-between px-5 py-3.5 bg-pos-black text-pos-white border-b border-pos-gold">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 bg-pos-gold inline-block"></span>
                    <h3 class="font-semibold text-sm uppercase tracking-wider">
                        {{ receipt.type === 'z_report' ? 'Laporan Tutup Shift (Z-Report)' : 'Struk Transaksi' }}
                    </h3>
                </div>
                <button @click="$emit('close')" class="text-pos-white hover:text-pos-gold text-sm font-mono px-2 py-1">
                    [ESC] TUTUP
                </button>
            </div>

            <!-- Printable Thermal Area (58mm/80mm compatible) -->
            <div class="overflow-y-auto p-6 flex-1 bg-pos-alabaster flex justify-center">
                <div
                    id="printable-receipt"
                    class="bg-white text-black border border-pos-border p-5 w-[310px] font-mono text-xs leading-relaxed"
                >
                    <!-- Store Header -->
                    <div class="text-center border-b border-dashed border-black pb-3 mb-3">
                        <div class="font-bold text-sm uppercase tracking-wider">{{ settings.store_name || 'TOKOQU RETAIL' }}</div>
                        <div v-if="settings.store_address" class="text-[11px] mt-0.5">{{ settings.store_address }}</div>
                        <div v-if="settings.store_phone" class="text-[11px]">Telp: {{ settings.store_phone }}</div>
                        <div
                            v-if="receipt.is_copy"
                            class="mt-2 inline-block border border-black px-2 py-0.5 font-bold text-[11px] uppercase tracking-widest"
                        >
                            *** SALINAN (COPY) ***
                        </div>
                        <div
                            v-if="receipt.is_offline"
                            class="mt-1 text-[10px] uppercase"
                        >
                            [MODE OFFLINE]
                        </div>
                    </div>

                    <!-- Z-REPORT LAYOUT -->
                    <template v-if="receipt.type === 'z_report'">
                        <div class="border-b border-dashed border-black pb-2 mb-2 space-y-0.5 text-[11px]">
                            <div class="font-bold text-center uppercase mb-1">Z-REPORT TUTUP SHIFT</div>
                            <div class="flex justify-between">
                                <span>Register:</span>
                                <span>{{ receipt.shift?.register?.code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Kasir:</span>
                                <span>{{ receipt.shift?.cashier?.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Buka:</span>
                                <span>{{ formatDateTime(receipt.shift?.opened_at) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Tutup:</span>
                                <span>{{ formatDateTime(receipt.shift?.closed_at) }}</span>
                            </div>
                        </div>

                        <div class="border-b border-dashed border-black pb-2 mb-2 space-y-1 tabular-nums text-[11px]">
                            <div class="flex justify-between">
                                <span>Jml Transaksi:</span>
                                <span>{{ receipt.z_report.sales_count }} ({{ receipt.z_report.voided_count }} void)</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Penjualan Kotor:</span>
                                <span>{{ formatRupiah(receipt.z_report.gross_sales) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Diskon:</span>
                                <span>-{{ formatRupiah(receipt.z_report.discounts_total) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Pajak:</span>
                                <span>{{ formatRupiah(receipt.z_report.tax_total) }}</span>
                            </div>
                            <div class="flex justify-between font-bold">
                                <span>Penjualan Bersih:</span>
                                <span>{{ formatRupiah(receipt.z_report.net_sales) }}</span>
                            </div>
                        </div>

                        <div class="border-b border-dashed border-black pb-2 mb-2 space-y-1 tabular-nums text-[11px]">
                            <div class="font-bold uppercase">Per Metode Bayar:</div>
                            <div class="flex justify-between">
                                <span>- Tunai (Cash):</span>
                                <span>{{ formatRupiah(receipt.z_report.payments_by_method?.cash) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>- QRIS:</span>
                                <span>{{ formatRupiah(receipt.z_report.payments_by_method?.qris) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>- Transfer:</span>
                                <span>{{ formatRupiah(receipt.z_report.payments_by_method?.transfer) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>- Kartu (Card):</span>
                                <span>{{ formatRupiah(receipt.z_report.payments_by_method?.card) }}</span>
                            </div>
                        </div>

                        <div class="space-y-1 tabular-nums text-[11px]">
                            <div class="font-bold uppercase">Rekonsiliasi Laci Kas:</div>
                            <div class="flex justify-between">
                                <span>Modal Awal:</span>
                                <span>{{ formatRupiah(receipt.z_report.opening_cash) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Kas Masuk (Cash In):</span>
                                <span>+{{ formatRupiah(receipt.z_report.cash_in) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Kas Keluar (Cash Out):</span>
                                <span>-{{ formatRupiah(receipt.z_report.cash_out) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Retur Tunai:</span>
                                <span>-{{ formatRupiah(receipt.z_report.cash_refunds) }}</span>
                            </div>
                            <div class="flex justify-between font-bold border-t border-dashed border-black pt-1">
                                <span>Kas Sistem (Expected):</span>
                                <span>{{ formatRupiah(receipt.z_report.expected_cash) }}</span>
                            </div>
                            <div class="flex justify-between font-bold">
                                <span>Kas Fisik (Counted):</span>
                                <span>{{ formatRupiah(receipt.z_report.counted_cash) }}</span>
                            </div>
                            <div class="flex justify-between font-bold">
                                <span>Selisih (Difference):</span>
                                <span>{{ formatRupiah(receipt.z_report.difference) }}</span>
                            </div>
                        </div>
                    </template>

                    <!-- SALE RECEIPT LAYOUT -->
                    <template v-else>
                        <div class="border-b border-dashed border-black pb-2 mb-2 space-y-0.5 text-[11px]">
                            <div class="flex justify-between">
                                <span>No Nota:</span>
                                <span class="font-bold">{{ receipt.invoice_no }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Tanggal:</span>
                                <span>{{ formatDateTime(receipt.created_at) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Kasir:</span>
                                <span>{{ receipt.cashier?.name || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Register:</span>
                                <span>{{ receipt.register?.code || '-' }}</span>
                            </div>
                        </div>

                        <!-- Line Items -->
                        <div class="border-b border-dashed border-black pb-2 mb-2 space-y-1.5 text-[11px] tabular-nums">
                            <div v-for="(item, idx) in receipt.items" :key="idx">
                                <div class="font-semibold">{{ item.name }}</div>
                                <div class="flex justify-between">
                                    <span>{{ formatQty(item.qty) }} x {{ formatRupiah(item.unit_price) }}</span>
                                    <span>{{ formatRupiah(Math.round(item.qty * item.unit_price) - (item.line_discount || 0)) }}</span>
                                </div>
                                <div v-if="item.line_discount > 0" class="flex justify-between text-[10px]">
                                    <span>  Diskon Baris</span>
                                    <span>-{{ formatRupiah(item.line_discount) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="border-b border-dashed border-black pb-2 mb-2 space-y-1 text-[11px] tabular-nums">
                            <div class="flex justify-between">
                                <span>Subtotal</span>
                                <span>{{ formatRupiah(receipt.subtotal) }}</span>
                            </div>
                            <div v-if="receipt.order_discount_amount > 0" class="flex justify-between">
                                <span>Diskon Order</span>
                                <span>-{{ formatRupiah(receipt.order_discount_amount) }}</span>
                            </div>
                            <div v-if="receipt.tax_total > 0" class="flex justify-between">
                                <span>PPN ({{ receipt.tax_mode === 'inclusive' ? 'Termasuk' : 'Eksklusif' }} {{ receipt.tax_rate }}%)</span>
                                <span>{{ formatRupiah(receipt.tax_total) }}</span>
                            </div>
                            <div class="flex justify-between font-bold text-sm pt-1 border-t border-dashed border-black">
                                <span>TOTAL</span>
                                <span>{{ formatRupiah(receipt.grand_total) }}</span>
                            </div>
                        </div>

                        <!-- Payments -->
                        <div class="border-b border-dashed border-black pb-2 mb-3 space-y-1 text-[11px] tabular-nums">
                            <div v-for="(p, pIdx) in receipt.payments" :key="pIdx">
                                <div class="flex justify-between uppercase">
                                    <span>Bayar ({{ p.method }})</span>
                                    <span>{{ formatRupiah(p.method === 'cash' && p.tendered ? p.tendered : p.amount) }}</span>
                                </div>
                                <div v-if="p.change_amount > 0" class="flex justify-between font-bold">
                                    <span>KEMBALI</span>
                                    <span>{{ formatRupiah(p.change_amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-center text-[11px] space-y-1">
                            <div>{{ settings.receipt_footer || 'Terima kasih telah berbelanja.' }}</div>
                            <div class="text-[10px] tracking-widest pt-1">* {{ receipt.invoice_no }} *</div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Modal Footer (Hidden on Print) -->
            <div class="no-print px-5 py-3.5 bg-pos-white border-t border-pos-border flex justify-end gap-3">
                <button @click="$emit('close')" class="btn-outline">
                    Tutup
                </button>
                <button @click="triggerPrint" class="btn-gold">
                    Cetak Struk (Enter)
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { formatDateTime, formatQty, formatRupiah } from '../utils/format';

defineProps({
    receipt: { type: Object, default: null },
    settings: { type: Object, default: () => ({}) },
});

defineEmits(['close']);

function triggerPrint() {
    window.print();
}
</script>

