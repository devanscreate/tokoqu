<template>
    <div class="h-[calc(100vh-56px)] flex flex-col lg:flex-row overflow-hidden bg-pos-alabaster">
        <!-- LEFT AREA: Search/Scan Bar, Category Chips, Product Grid (Section 9) -->
        <div class="flex-1 flex flex-col min-w-0 border-r border-pos-border">
            <!-- Top Search & Barcode Bar -->
            <div class="p-3 bg-pos-white border-b border-pos-border flex items-center gap-2">
                <div class="relative flex-1">
                    <input
                        ref="searchInput"
                        v-model="searchQuery"
                        @keydown.enter.prevent="handleBarcodeOrSearchEnter"
                        type="text"
                        placeholder="[F2] Scan barcode atau cari nama produk / SKU..."
                        class="input-pos pr-24 font-medium"
                    />
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-mono text-pos-muted border border-pos-border px-1.5 py-0.5 bg-pos-alabaster">
                        F2 / SCAN
                    </span>
                </div>
                <button
                    @click="showHeldModal = true"
                    class="btn-outline relative"
                    title="Keranjang Ditahan (F4)"
                >
                    <span>Hold [F4]</span>
                    <span
                        v-if="store.heldCarts.length > 0"
                        class="ml-1 px-1.5 py-0.5 text-xs font-mono font-bold bg-pos-gold text-white"
                    >
                        {{ store.heldCarts.length }}
                    </span>
                </button>
            </div>

            <!-- Category Chips -->
            <div class="px-3 py-2 bg-pos-white border-b border-pos-border flex items-center gap-2 overflow-x-auto">
                <button
                    @click="selectedCategoryId = null"
                    :class="[
                        'min-h-[40px] px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wider border transition-colors whitespace-nowrap',
                        selectedCategoryId === null
                            ? 'bg-pos-black text-pos-white border-pos-black'
                            : 'bg-pos-alabaster text-pos-black border-pos-border hover:border-pos-gold',
                    ]"
                >
                    Semua Produk ({{ store.activeProducts.length }})
                </button>
                <button
                    v-for="cat in store.categories"
                    :key="cat.id"
                    @click="selectedCategoryId = cat.id"
                    :class="[
                        'min-h-[40px] px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wider border transition-colors whitespace-nowrap',
                        selectedCategoryId === cat.id
                            ? 'bg-pos-black text-pos-white border-pos-black'
                            : 'bg-pos-alabaster text-pos-black border-pos-border hover:border-pos-gold',
                    ]"
                >
                    {{ cat.name }}
                </button>
            </div>

            <!-- Product Grid -->
            <div class="flex-1 overflow-y-auto p-3">
                <div v-if="!store.currentShift" class="mb-3 p-4 bg-pos-gold-tint border border-pos-gold flex items-center justify-between">
                    <div>
                        <div class="font-bold text-sm text-pos-black">Shift Kasir Belum Dibuka (W3)</div>
                        <div class="text-xs text-pos-muted">Setiap penjualan wajib memiliki shift terbuka. Silakan buka shift terlebih dahulu.</div>
                    </div>
                    <button @click="$emit('navigate', 'shift')" class="btn-gold">
                        Buka Shift Sekarang
                    </button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5">
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="onTapProduct(product)"
                        class="text-left bg-pos-white border border-pos-border hover:border-pos-gold active:bg-pos-gold-tint p-3 flex flex-col justify-between min-h-[112px] transition-colors group"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-1 text-[11px] font-mono text-pos-muted">
                                <span>{{ product.sku }}</span>
                                <span
                                    v-if="product.track_stock"
                                    :class="[
                                        'px-1.5 py-0.5 font-semibold',
                                        Number(product.stock_qty) <= Number(product.min_stock)
                                            ? 'bg-red-50 text-pos-danger border border-red-200'
                                            : 'bg-pos-stone text-pos-black',
                                    ]"
                                >
                                    Stok: {{ formatQty(product.stock_qty) }}
                                </span>
                                <span v-else class="px-1.5 py-0.5 bg-pos-stone text-pos-muted">Non-Stok</span>
                            </div>
                            <div class="font-semibold text-sm text-pos-black mt-1.5 line-clamp-2 group-hover:text-pos-gold-hover">
                                {{ product.name }}
                            </div>
                        </div>

                        <div class="mt-3 pt-2 border-t border-pos-border flex items-baseline justify-between">
                            <span class="font-mono font-bold text-sm text-pos-black tabular-nums">
                                {{ formatRupiah(product.price) }}
                            </span>
                            <span v-if="product.allow_decimal_qty" class="text-[10px] font-mono uppercase text-pos-muted">
                                Desimal/Kg
                            </span>
                        </div>
                    </button>
                </div>

                <div v-if="filteredProducts.length === 0" class="py-16 text-center text-pos-muted text-sm">
                    Tidak ada produk yang cocok dengan pencarian "{{ searchQuery }}".
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Cart, Discounts, Totals & Large Pay Button (Section 9) -->
        <div class="w-full lg:w-[430px] xl:w-[460px] bg-pos-white flex flex-col h-[50vh] lg:h-full">
            <!-- Cart Header -->
            <div class="px-4 py-3 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-widest text-pos-gold font-semibold">Keranjang Aktif</div>
                    <div class="text-[11px] font-mono text-pos-stone truncate max-w-[230px]">
                        UUID: {{ store.cart.client_uuid ? store.cart.client_uuid.slice(0, 18) + '...' : 'Belum ada item' }}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="store.cart.items.length > 0"
                        @click="promptHoldCart"
                        class="min-h-[38px] px-3 py-1 bg-pos-charcoal border border-pos-gold text-pos-gold text-xs font-semibold uppercase hover:bg-pos-gold hover:text-white transition-colors"
                    >
                        Tahan [F4]
                    </button>
                    <button
                        v-if="store.cart.items.length > 0"
                        @click="store.clearCart()"
                        class="min-h-[38px] px-2.5 py-1 bg-pos-charcoal border border-pos-border text-pos-white text-xs hover:border-pos-danger"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Alerts (Resume Notices / Price Change / Stock Warning) -->
            <div v-if="store.resumeNotices.length > 0" class="p-3 bg-pos-gold-tint border-b border-pos-gold text-xs space-y-1">
                <div class="font-bold uppercase text-pos-black">Pembaruan Keranjang Hold (W5.2):</div>
                <div v-for="(n, i) in store.resumeNotices" :key="i" class="text-pos-black">• {{ n }}</div>
            </div>

            <div v-if="store.priceChangeAlert" class="p-3 bg-amber-50 border-b border-amber-400 text-xs text-amber-900 font-medium">
                {{ store.priceChangeAlert }}
            </div>

            <!-- Cart Line Items -->
            <div class="flex-1 overflow-y-auto divide-y divide-pos-border">
                <div v-if="store.cart.items.length === 0" class="h-full flex flex-col items-center justify-center p-8 text-center text-pos-muted">
                    <div class="text-sm font-medium text-pos-black mb-1">Keranjang Kosong</div>
                    <div class="text-xs">Scan barcode produk atau ketuk produk di sebelah kiri untuk memulai transaksi.</div>
                </div>

                <div
                    v-for="(item, idx) in store.cart.items"
                    :key="item.product_id"
                    class="p-3 hover:bg-pos-alabaster transition-colors"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold text-sm text-pos-black truncate">{{ item.name }}</div>
                            <div class="text-xs font-mono text-pos-muted tabular-nums">
                                {{ item.sku }} · {{ formatRupiah(item.unit_price) }}
                                <span
                                    v-if="item.track_stock && item.qty > item.stock_qty"
                                    class="ml-1 text-pos-danger font-bold"
                                >
                                    [Stok lokal kurang: {{ item.stock_qty }}]
                                </span>
                            </div>
                        </div>
                        <div class="font-mono font-bold text-sm text-pos-black tabular-nums whitespace-nowrap">
                            {{ formatRupiah(Math.round(item.qty * item.unit_price) - (item.line_discount || 0)) }}
                        </div>
                    </div>

                    <!-- Stepper & Line Discount -->
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <div class="flex items-center border border-pos-border bg-pos-white">
                            <button
                                @click="store.updateCartLineQty(idx, Number(item.qty) - 1)"
                                class="min-w-[44px] min-h-[44px] flex items-center justify-center font-mono font-bold text-base hover:bg-pos-stone active:bg-pos-gold active:text-white"
                            >
                                −
                            </button>
                            <input
                                :value="item.qty"
                                @change="store.updateCartLineQty(idx, $event.target.value)"
                                type="number"
                                :step="item.allow_decimal_qty ? '0.001' : '1'"
                                min="0.001"
                                class="w-16 min-h-[44px] text-center font-mono text-sm font-semibold border-x border-pos-border focus:outline-none tabular-nums"
                            />
                            <button
                                @click="store.updateCartLineQty(idx, Number(item.qty) + 1)"
                                class="min-w-[44px] min-h-[44px] flex items-center justify-center font-mono font-bold text-base hover:bg-pos-stone active:bg-pos-gold active:text-white"
                            >
                                +
                            </button>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] text-pos-muted uppercase">Potongan Rp:</span>
                            <input
                                :value="item.line_discount"
                                @change="store.updateCartLineDiscount(idx, $event.target.value)"
                                type="number"
                                min="0"
                                step="500"
                                class="w-24 min-h-[44px] px-2 text-right font-mono text-xs border border-pos-border bg-pos-white focus:border-pos-gold focus:outline-none tabular-nums"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Discount & Totals Summary (R5) -->
            <div class="border-t-2 border-pos-black bg-pos-alabaster p-4 space-y-2.5">
                <!-- Order Discount Row -->
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1">
                        <span class="text-xs font-semibold uppercase tracking-wider text-pos-muted">Diskon Order</span>
                        <select
                            v-model="store.cart.order_discount_type"
                            @change="clearDiscountApproval"
                            class="min-h-[36px] px-2 text-xs border border-pos-border bg-pos-white font-mono"
                        >
                            <option value="amount">Rp</option>
                            <option value="percent">%</option>
                        </select>
                    </div>
                    <input
                        v-model.number="store.cart.order_discount_value"
                        @input="clearDiscountApproval"
                        type="number"
                        min="0"
                        class="w-32 min-h-[38px] px-2.5 text-right font-mono text-xs border border-pos-border bg-pos-white focus:border-pos-gold focus:outline-none tabular-nums"
                    />
                </div>

                <!-- PIN Required Indicator for Discount > Cashier Limit (W4.3) -->
                <div
                    v-if="store.requiresDiscountPin"
                    class="p-2.5 bg-pos-gold-tint border border-pos-gold flex items-center justify-between text-xs"
                >
                    <div>
                        <span class="font-bold text-pos-black">Diskon &gt; {{ store.settings.cashier_discount_limit_pct }}%:</span>
                        <span v-if="store.cart.approved_by_name" class="ml-1 text-pos-success font-semibold">
                            Disetujui ({{ store.cart.approved_by_name }})
                        </span>
                        <span v-else class="ml-1 text-pos-warning">Butuh PIN Manager</span>
                    </div>
                    <button
                        v-if="!store.cart.approved_by_name"
                        @click="showPinModal = true"
                        class="px-2.5 py-1 bg-pos-black text-pos-gold text-xs font-semibold uppercase"
                    >
                        Input PIN
                    </button>
                </div>

                <div class="space-y-1 text-xs tabular-nums pt-1 border-t border-pos-border">
                    <div class="flex justify-between text-pos-muted">
                        <span>Subtotal ({{ store.cart.items.length }} baris)</span>
                        <span class="font-mono text-pos-black">{{ formatRupiah(totals.subtotal) }}</span>
                    </div>
                    <div v-if="totals.order_discount_amount > 0" class="flex justify-between text-pos-muted">
                        <span>Diskon Order</span>
                        <span class="font-mono text-pos-danger">-{{ formatRupiah(totals.order_discount_amount) }}</span>
                    </div>
                    <div v-if="totals.tax_mode !== 'off'" class="flex justify-between text-pos-muted">
                        <span>PPN ({{ totals.tax_mode === 'inclusive' ? 'Inklusif' : 'Eksklusif' }} {{ totals.tax_rate }}%)</span>
                        <span class="font-mono text-pos-black">{{ formatRupiah(totals.tax_total) }}</span>
                    </div>
                </div>

                <div class="flex items-baseline justify-between pt-2 border-t border-pos-border">
                    <span class="text-sm font-bold uppercase tracking-wider text-pos-black">Grand Total</span>
                    <span class="font-mono text-2xl font-bold text-pos-black tabular-nums">
                        {{ formatRupiah(totals.grand_total) }}
                    </span>
                </div>

                <!-- Large Pay Button -->
                <button
                    @click="openPaymentDialog"
                    :disabled="store.cart.items.length === 0 || !store.currentShift"
                    class="w-full min-h-[54px] bg-pos-black hover:bg-pos-charcoal text-pos-white border-2 border-pos-gold font-bold text-base uppercase tracking-wider flex items-center justify-between px-5 disabled:opacity-40 disabled:pointer-events-none transition-colors"
                >
                    <span>BAYAR SEKARANG [F8]</span>
                    <span class="font-mono text-pos-gold tabular-nums">{{ formatRupiah(totals.grand_total) }}</span>
                </button>
            </div>
        </div>

        <!-- PAYMENT DIALOG (Section 9 & W4.5) -->
        <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
            <div class="bg-pos-white border-2 border-pos-black w-full max-w-2xl flex flex-col max-h-[92vh]">
                <div class="px-5 py-4 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-widest text-pos-gold font-semibold">Pembayaran Transaksi</div>
                        <div class="text-xl font-mono font-bold tabular-nums mt-0.5">
                            Tagihan: {{ formatRupiah(totals.grand_total) }}
                        </div>
                    </div>
                    <button @click="showPayModal = false" class="text-xs font-mono text-pos-white hover:text-pos-gold px-2 py-1">
                        [ESC] BATAL
                    </button>
                </div>

                <div class="p-5 overflow-y-auto space-y-5 flex-1">
                    <div v-if="payError" class="p-3 bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
                        {{ payError }}
                    </div>

                    <!-- Payment Method Tabs -->
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="m in paymentMethods"
                            :key="m.id"
                            type="button"
                            @click="selectMethodTab(m.id)"
                            :class="[
                                'min-h-[48px] font-semibold text-xs uppercase tracking-wider border transition-colors',
                                activePayMethod === m.id
                                    ? 'bg-pos-black text-pos-gold border-pos-black'
                                    : 'bg-pos-alabaster text-pos-black border-pos-border hover:border-pos-gold',
                            ]"
                        >
                            {{ m.label }}
                        </button>
                    </div>

                    <!-- Quick Cash Denominations (W4.5) -->
                    <div v-if="activePayMethod === 'cash'" class="space-y-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-pos-muted">
                            Uang Tunai Cepat (Rupiah)
                        </label>
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                            <button
                                type="button"
                                @click="setQuickCash(remainingDue > 0 ? remainingDue : totals.grand_total)"
                                class="btn-outline text-xs font-mono font-bold"
                            >
                                Uang Pas
                            </button>
                            <button
                                v-for="denom in [10000, 20000, 50000, 100000, 200000]"
                                :key="denom"
                                type="button"
                                @click="setQuickCash(denom)"
                                class="btn-outline text-xs font-mono tabular-nums"
                            >
                                {{ formatRupiah(denom) }}
                            </button>
                        </div>
                    </div>

                    <!-- Current Payment Entry Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-pos-alabaster p-3.5 border border-pos-border">
                        <div>
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">
                                Nominal Diterapkan (Rp)
                            </label>
                            <input
                                v-model.number="payDraft.amount"
                                type="number"
                                min="1"
                                class="input-pos font-mono font-bold tabular-nums"
                            />
                        </div>
                        <div v-if="activePayMethod === 'cash'">
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">
                                Uang Diterima / Tendered (Rp)
                            </label>
                            <input
                                ref="tenderedInput"
                                v-model.number="payDraft.tendered"
                                @keydown.enter.prevent="submitPaymentConfirm"
                                type="number"
                                min="1"
                                class="input-pos font-mono font-bold tabular-nums"
                            />
                        </div>
                        <div v-else>
                            <label class="block text-[11px] font-semibold uppercase text-pos-muted mb-1">
                                No. Referensi / Approval EDC
                            </label>
                            <input
                                v-model="payDraft.reference"
                                @keydown.enter.prevent="submitPaymentConfirm"
                                type="text"
                                placeholder="Opsional (No. Ref / RRN)"
                                class="input-pos font-mono"
                            />
                        </div>
                        <div class="flex items-end">
                            <button
                                type="button"
                                @click="addSplitPaymentRow"
                                class="btn-outline w-full text-xs uppercase font-semibold"
                            >
                                + Tambah Split Bayar
                            </button>
                        </div>
                    </div>

                    <!-- Split Payment Rows List (R6) -->
                    <div v-if="splitPayments.length > 0" class="border border-pos-border divide-y divide-pos-border">
                        <div class="px-3 py-2 bg-pos-stone text-xs font-semibold uppercase tracking-wider">
                            Rincian Pembayaran Terdaftar (Split Payment)
                        </div>
                        <div
                            v-for="(sp, sIdx) in splitPayments"
                            :key="sIdx"
                            class="px-3 py-2.5 flex items-center justify-between text-xs font-mono tabular-nums"
                        >
                            <div>
                                <span class="font-bold uppercase">{{ sp.method }}</span>
                                <span v-if="sp.reference" class="ml-2 text-pos-muted">Ref: {{ sp.reference }}</span>
                                <span v-if="sp.method === 'cash'" class="ml-2 text-pos-muted">
                                    (Diterima: {{ formatRupiah(sp.tendered) }})
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-bold">{{ formatRupiah(sp.amount) }}</span>
                                <button @click="splitPayments.splice(sIdx, 1)" class="text-pos-danger hover:underline">
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Change & Remaining Summary -->
                    <div class="grid grid-cols-2 gap-3 p-4 bg-pos-black text-pos-white border border-pos-gold tabular-nums">
                        <div>
                            <div class="text-xs uppercase text-pos-stone">Sisa Tagihan</div>
                            <div class="text-lg font-mono font-bold">
                                {{ formatRupiah(Math.max(0, remainingDueAfterDraft)) }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs uppercase text-pos-gold">Kembalian Tunai (Change)</div>
                            <div class="text-xl font-mono font-bold text-pos-gold">
                                {{ formatRupiah(liveChangeAmount) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4 bg-pos-white border-t border-pos-border flex items-center justify-between">
                    <span class="text-xs text-pos-muted">
                        {{ store.isOnline ? 'Mode Online: Stok & harga diverifikasi server' : 'Mode Offline: Disimpan ke antrean sinkronisasi (W13)' }}
                    </span>
                    <div class="flex gap-3">
                        <button type="button" @click="showPayModal = false" class="btn-outline">
                            Kembali
                        </button>
                        <button
                            type="button"
                            @click="submitPaymentConfirm"
                            :disabled="submittingPay"
                            class="btn-gold px-6"
                        >
                            {{ submittingPay ? 'Memproses...' : 'Konfirmasi & Cetak Struk [Enter]' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- HELD CARTS MODAL (W5) -->
        <div v-if="showHeldModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div class="bg-pos-white border border-pos-black w-full max-w-lg">
                <div class="px-5 py-3.5 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                    <h3 class="font-semibold text-sm uppercase tracking-wider">Daftar Keranjang Ditahan (Hold - W5)</h3>
                    <button @click="showHeldModal = false" class="text-xs font-mono text-pos-white hover:text-pos-gold">[ESC]</button>
                </div>
                <div class="p-5 max-h-[70vh] overflow-y-auto divide-y divide-pos-border">
                    <div v-if="store.heldCarts.length === 0" class="py-8 text-center text-xs text-pos-muted">
                        Belum ada keranjang yang ditahan pada perangkat ini.
                    </div>
                    <div
                        v-for="h in store.heldCarts"
                        :key="h.id"
                        class="py-3 flex items-center justify-between gap-3"
                    >
                        <div>
                            <div class="font-bold text-sm text-pos-black">{{ h.label }}</div>
                            <div class="text-xs text-pos-muted font-mono">
                                {{ formatDateTime(h.created_at) }} · {{ h.cart_snapshot.items.length }} produk
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="onResumeHeld(h.id)"
                                class="btn-gold text-xs py-1.5 px-3 min-h-[38px]"
                            >
                                Lanjutkan (Resume)
                            </button>
                            <button
                                @click="store.discardHeldCart(h.id)"
                                class="btn-outline text-xs py-1.5 px-3 min-h-[38px] text-pos-danger"
                            >
                                Buang
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MANAGER PIN MODAL -->
        <ManagerPinModal
            :open="showPinModal"
            action="discount"
            default-reason="Diskon transaksi melebihi batas kasir"
            @close="showPinModal = false"
            @approved="onDiscountPinApproved"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';
import ManagerPinModal from '../components/ManagerPinModal.vue';
import { usePosStore } from '../stores/posStore';
import { formatDateTime, formatQty, formatRupiah } from '../utils/format';

defineEmits(['navigate']);

const store = usePosStore();
const searchInput = ref(null);
const tenderedInput = ref(null);
const searchQuery = ref('');
const selectedCategoryId = ref(null);

const showHeldModal = ref(false);
const showPinModal = ref(false);
const showPayModal = ref(false);
const submittingPay = ref(false);
const payError = ref('');

const paymentMethods = [
    { id: 'cash', label: 'Tunai (Cash)' },
    { id: 'qris', label: 'QRIS' },
    { id: 'transfer', label: 'Transfer Bank' },
    { id: 'card', label: 'Kartu Debit/Kredit' },
];

const activePayMethod = ref('cash');
const splitPayments = ref([]);
const payDraft = ref({
    amount: 0,
    tendered: 0,
    reference: '',
});

const totals = computed(() => store.cartTotals);

const filteredProducts = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return store.activeProducts.filter((p) => {
        if (selectedCategoryId.value !== null && p.category_id !== selectedCategoryId.value) {
            return false;
        }
        if (!q) return true;
        return (
            p.name.toLowerCase().includes(q) ||
            p.sku.toLowerCase().includes(q) ||
            (p.barcode && p.barcode.toLowerCase().includes(q))
        );
    });
});

const appliedSplitTotal = computed(() =>
    splitPayments.value.reduce((sum, p) => sum + Math.round(Number(p.amount || 0)), 0)
);

const remainingDue = computed(() => Math.max(0, totals.value.grand_total - appliedSplitTotal.value));

const remainingDueAfterDraft = computed(() =>
    Math.max(0, remainingDue.value - Math.round(Number(payDraft.value.amount || 0)))
);

const liveChangeAmount = computed(() => {
    let change = splitPayments.value.reduce((sum, p) => sum + Number(p.change_amount || 0), 0);
    if (activePayMethod.value === 'cash') {
        const amt = Math.round(Number(payDraft.value.amount || 0));
        const tend = Math.round(Number(payDraft.value.tendered || 0));
        if (tend > amt) {
            change += tend - amt;
        }
    }
    return change;
});

function handleBarcodeOrSearchEnter() {
    const q = searchQuery.value.trim();
    if (!q) return;

    // Exact barcode or SKU match for USB scanner
    const exact = store.activeProducts.find(
        (p) => (p.barcode && p.barcode === q) || p.sku.toLowerCase() === q.toLowerCase()
    );
    if (exact) {
        onTapProduct(exact);
        searchQuery.value = '';
    } else if (filteredProducts.value.length === 1) {
        onTapProduct(filteredProducts.value[0]);
        searchQuery.value = '';
    }
}

function onTapProduct(product) {
    store.addToCart(product, 1);
}

function clearDiscountApproval() {
    store.cart.approval_token = null;
    store.cart.approved_by_name = null;
}

function onDiscountPinApproved({ approval_token, manager_name }) {
    store.cart.approval_token = approval_token;
    store.cart.approved_by_name = manager_name;
    showPinModal.value = false;
}

async function promptHoldCart() {
    const label = window.prompt('Masukkan label/nama pelanggan untuk keranjang yang ditahan (W5):', `Pelanggan #${store.heldCarts.length + 1}`);
    if (label !== null) {
        await store.holdCurrentCart(label);
    }
}

async function onResumeHeld(heldId) {
    await store.resumeHeldCart(heldId);
    showHeldModal.value = false;
}

async function openPaymentDialog() {
    if (!store.cart.items.length || !store.currentShift) return;
    if (store.requiresDiscountPin && !store.cart.approval_token) {
        showPinModal.value = true;
        return;
    }
    payError.value = '';
    splitPayments.value = [];
    activePayMethod.value = 'cash';
    payDraft.value = {
        amount: totals.value.grand_total,
        tendered: totals.value.grand_total,
        reference: '',
    };
    showPayModal.value = true;
    await nextTick();
    tenderedInput.value?.focus();
    tenderedInput.value?.select();
}

function selectMethodTab(methodId) {
    activePayMethod.value = methodId;
    const rem = remainingDue.value;
    payDraft.value = {
        amount: rem,
        tendered: rem,
        reference: '',
    };
}

function setQuickCash(tenderedAmount) {
    activePayMethod.value = 'cash';
    const rem = remainingDue.value > 0 ? remainingDue.value : totals.value.grand_total;
    payDraft.value.amount = rem;
    payDraft.value.tendered = tenderedAmount;
}

function addSplitPaymentRow() {
    payError.value = '';
    const amount = Math.round(Number(payDraft.value.amount || 0));
    if (amount <= 0) {
        payError.value = 'Nominal pembayaran harus lebih dari 0.';
        return;
    }
    if (amount > remainingDue.value) {
        payError.value = 'Nominal yang diterapkan tidak boleh melebihi sisa tagihan.';
        return;
    }

    const tendered = activePayMethod.value === 'cash' ? Math.round(Number(payDraft.value.tendered || amount)) : amount;
    if (activePayMethod.value === 'cash' && tendered < amount) {
        payError.value = 'Uang tunai diterima kurang dari nominal pembayaran.';
        return;
    }

    splitPayments.value.push({
        method: activePayMethod.value,
        amount,
        tendered,
        change_amount: activePayMethod.value === 'cash' ? tendered - amount : 0,
        reference: payDraft.value.reference || null,
    });

    const nextRem = remainingDue.value;
    payDraft.value = {
        amount: nextRem,
        tendered: nextRem,
        reference: '',
    };
}

async function submitPaymentConfirm() {
    payError.value = '';
    const finalPayments = [...splitPayments.value];

    const draftAmt = Math.round(Number(payDraft.value.amount || 0));
    if (draftAmt > 0 && remainingDue.value > 0) {
        const tendered =
            activePayMethod.value === 'cash'
                ? Math.round(Number(payDraft.value.tendered || draftAmt))
                : draftAmt;

        if (activePayMethod.value === 'cash' && tendered < draftAmt) {
            payError.value = 'Uang tunai yang diterima kurang dari nominal tagihan (R6).';
            return;
        }
        if (activePayMethod.value !== 'cash' && draftAmt > remainingDue.value) {
            payError.value = 'Pembayaran non-tunai tidak boleh melebihi sisa tagihan (R6).';
            return;
        }

        finalPayments.push({
            method: activePayMethod.value,
            amount: draftAmt,
            tendered,
            change_amount: activePayMethod.value === 'cash' ? tendered - draftAmt : 0,
            reference: payDraft.value.reference || null,
        });
    }

    const sumApplied = finalPayments.reduce((s, p) => s + p.amount, 0);
    if (sumApplied !== totals.value.grand_total) {
        payError.value = `Total pembayaran (${formatRupiah(sumApplied)}) harus sama persis dengan Grand Total (${formatRupiah(totals.value.grand_total)}) (R6).`;
        return;
    }

    submittingPay.value = true;
    try {
        await store.confirmCheckout(finalPayments);
        showPayModal.value = false;
    } catch (err) {
        payError.value = err.message || 'Gagal menyimpan transaksi.';
    } finally {
        submittingPay.value = false;
    }
}

// Section 9 Keyboard Shortcuts: F2 focus search, F4 hold, F8 pay, Esc back
function onGlobalKeydown(e) {
    if (e.key === 'F2') {
        e.preventDefault();
        searchInput.value?.focus();
        searchInput.value?.select();
    } else if (e.key === 'F4') {
        e.preventDefault();
        if (store.cart.items.length > 0) {
            promptHoldCart();
        } else {
            showHeldModal.value = !showHeldModal.value;
        }
    } else if (e.key === 'F8') {
        e.preventDefault();
        openPaymentDialog();
    } else if (e.key === 'Escape') {
        showPayModal.value = false;
        showHeldModal.value = false;
        showPinModal.value = false;
    }
}

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);
    searchInput.value?.focus();
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
});
</script>

