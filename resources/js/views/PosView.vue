<template>
    <div class="max-w-[1600px] mx-auto h-[calc(100vh-112px)] lg:h-[calc(100vh-64px)] flex flex-col lg:flex-row overflow-hidden">
        <!-- LEFT AREA: Search Bar, Category Pills & Product Grid with Photos -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden p-3 sm:p-5">
            <!-- Friendly Shift Reminder Banner -->
            <div
                v-if="!store.currentShift"
                class="mb-4 p-4 rounded-2xl bg-pos-gold-tint border border-pos-gold flex flex-col sm:flex-row sm:items-center justify-between gap-3"
            >
                <div>
                    <div class="font-bold text-sm text-pos-black">Laci Kasir Belum Dibuka</div>
                    <div class="text-xs text-pos-muted mt-0.5">
                        Sebelum menerima pembayaran pelanggan, silakan buka shift terlebih dahulu dan masukkan jumlah uang modal di laci.
                    </div>
                </div>
                <button @click="$emit('navigate', 'shift')" class="btn-gold shrink-0">
                    Buka Shift Kasir
                </button>
            </div>

            <!-- Search Bar & Held Carts Button -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 mb-3.5">
                <div class="relative flex-1">
                    <svg
                        class="w-4 h-4 text-pos-muted absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        ref="searchInput"
                        v-model="searchQuery"
                        @keydown.enter.prevent="handleBarcodeOrSearchEnter"
                        type="text"
                        placeholder="Ketik nama barang, kode SKU, atau scan barcode [Tekan F2]..."
                        class="input-pos pl-10 pr-20 bg-pos-white shadow-sm"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-pos-muted hover:text-pos-black px-1.5 py-0.5"
                    >
                        Hapus
                    </button>
                </div>

                <button
                    @click="showHeldModal = true"
                    class="btn-outline bg-pos-white shrink-0"
                    title="Lihat daftar pesanan pelanggan yang disimpan sementara"
                >
                    <span>Pesanan Disimpan</span>
                    <span
                        v-if="store.heldCarts.length > 0"
                        class="px-2 py-0.5 text-xs font-bold rounded-full bg-pos-gold text-white"
                    >
                        {{ store.heldCarts.length }}
                    </span>
                </button>
            </div>

            <!-- Category Filter Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2.5 mb-2 scrollbar-none">
                <button
                    @click="selectedCategoryId = null"
                    :class="[
                        'min-h-[40px] px-4 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap flex items-center gap-2 border',
                        selectedCategoryId === null
                            ? 'bg-pos-black text-pos-white border-pos-black shadow-sm'
                            : 'bg-pos-white text-pos-black border-pos-border hover:border-pos-gold',
                    ]"
                >
                    <span>Semua Menu</span>
                    <span
                        class="px-1.5 py-0.5 rounded-md text-[10px]"
                        :class="selectedCategoryId === null ? 'bg-pos-gold text-white' : 'bg-pos-alabaster text-pos-muted'"
                    >
                        {{ store.activeProducts.length }}
                    </span>
                </button>

                <button
                    v-for="cat in store.categories"
                    :key="cat.id"
                    @click="selectedCategoryId = cat.id"
                    :class="[
                        'min-h-[40px] px-4 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap flex items-center gap-2 border',
                        selectedCategoryId === cat.id
                            ? 'bg-pos-black text-pos-white border-pos-black shadow-sm'
                            : 'bg-pos-white text-pos-black border-pos-border hover:border-pos-gold',
                    ]"
                >
                    <span>{{ cat.name }}</span>
                    <span
                        class="px-1.5 py-0.5 rounded-md text-[10px]"
                        :class="selectedCategoryId === cat.id ? 'bg-pos-gold text-white' : 'bg-pos-alabaster text-pos-muted'"
                    >
                        {{ countByCategory(cat.id) }}
                    </span>
                </button>
            </div>

            <!-- Product Cards Grid with Photos -->
            <div class="flex-1 overflow-y-auto pr-1 pb-20 lg:pb-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="onTapProduct(product)"
                        class="text-left bg-pos-white rounded-2xl border transition-all p-2.5 flex flex-col justify-between group hover:shadow-md active:scale-[0.99]"
                        :class="
                            qtyInCart(product.id) > 0
                                ? 'border-pos-gold ring-2 ring-pos-gold/25'
                                : 'border-pos-border hover:border-pos-gold'
                        "
                    >
                        <div>
                            <!-- Product Photo Container -->
                            <div class="relative w-full h-28 sm:h-32 rounded-xl overflow-hidden bg-pos-alabaster border border-pos-border/60 mb-2.5">
                                <img
                                    :src="getProductImage(product)"
                                    :alt="product.name"
                                    loading="lazy"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                />

                                <!-- Selected Qty Badge -->
                                <span
                                    v-if="qtyInCart(product.id) > 0"
                                    class="absolute top-2 left-2 px-2.5 py-0.5 rounded-full bg-pos-black text-pos-gold font-bold text-[11px] shadow"
                                >
                                    {{ formatQty(qtyInCart(product.id)) }}x dipilih
                                </span>

                                <!-- Stock Badge -->
                                <span
                                    v-if="product.track_stock"
                                    :class="[
                                        'absolute top-2 right-2 px-2 py-0.5 rounded-lg text-[10px] font-semibold backdrop-blur-sm',
                                        Number(product.stock_qty) <= 0
                                            ? 'bg-red-600 text-white'
                                            : Number(product.stock_qty) <= Number(product.min_stock)
                                              ? 'bg-amber-100 text-amber-900 border border-amber-300'
                                              : 'bg-pos-white/95 text-pos-black border border-pos-border',
                                    ]"
                                >
                                    Stok {{ formatQty(product.stock_qty) }}
                                </span>
                            </div>

                            <!-- Product Title & SKU -->
                            <div class="px-0.5">
                                <div class="text-[11px] text-pos-muted font-mono truncate">
                                    {{ product.sku }}
                                    <span v-if="product.allow_decimal_qty" class="text-pos-gold font-sans font-medium">• Bisa Timbang/Desimal</span>
                                </div>
                                <div class="font-semibold text-xs sm:text-sm text-pos-black mt-0.5 line-clamp-2 leading-snug">
                                    {{ product.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Price & Quick Add Icon -->
                        <div class="mt-2.5 pt-2 border-t border-pos-border/70 px-0.5 flex items-center justify-between">
                            <span class="font-mono font-bold text-sm text-pos-black tabular-nums">
                                {{ formatRupiah(product.price) }}
                            </span>
                            <span class="w-7 h-7 rounded-lg bg-pos-alabaster group-hover:bg-pos-gold group-hover:text-white text-pos-black flex items-center justify-center font-bold text-sm transition-colors">
                                +
                            </span>
                        </div>
                    </button>
                </div>

                <div
                    v-if="filteredProducts.length === 0"
                    class="py-16 text-center bg-pos-white rounded-2xl border border-pos-border p-8"
                >
                    <div class="font-semibold text-sm text-pos-black">Produk Tidak Ditemukan</div>
                    <p class="text-xs text-pos-muted mt-1">
                        Tidak ada barang yang cocok dengan kata kunci "{{ searchQuery }}". Coba kata kunci lain atau pilih kategori "Semua Menu".
                    </p>
                </div>
            </div>
        </div>

        <!-- MOBILE FLOATING CART BAR (< 1024px) -->
        <div
            v-if="!mobileCartOpen"
            class="lg:hidden fixed bottom-0 inset-x-0 z-30 p-3 bg-pos-white/95 border-t border-pos-border shadow-lg"
        >
            <button
                @click="mobileCartOpen = true"
                class="w-full min-h-[50px] rounded-2xl bg-pos-black text-pos-white px-4 py-3 flex items-center justify-between font-semibold text-sm border border-pos-gold"
            >
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-pos-gold text-pos-white text-xs font-bold">
                        {{ store.cart.items.length }} Item
                    </span>
                    <span>Lihat Detail Pesanan</span>
                </div>
                <span class="font-mono font-bold text-pos-gold tabular-nums">
                    {{ formatRupiah(totals.grand_total) }}
                </span>
            </button>
        </div>

        <!-- RIGHT PANEL: Order Details & Checkout (Desktop Sidebar + Mobile Drawer) -->
        <div
            :class="[
                'lg:w-[410px] xl:w-[440px] bg-pos-white border-l border-pos-border flex flex-col shrink-0 transition-all',
                mobileCartOpen
                    ? 'fixed inset-0 z-40 flex'
                    : 'hidden lg:flex h-full',
            ]"
        >
            <!-- Order Header -->
            <div class="px-5 py-4 border-b border-pos-border flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-base text-pos-black">Detail Pesanan</h2>
                    <p class="text-xs text-pos-muted">
                        {{
                            store.cart.items.length > 0
                                ? `${store.cart.items.length} jenis barang di keranjang`
                                : 'Ketuk produk di sebelah kiri untuk menambahkan'
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        v-if="store.cart.items.length > 0"
                        @click="promptHoldCart"
                        class="px-3 py-1.5 rounded-xl border border-pos-border bg-pos-alabaster hover:border-pos-gold text-xs font-semibold text-pos-black"
                        title="Simpan sementara pesanan ini jika pelanggan ingin mengambil barang lain [F4]"
                    >
                        Simpan Sementara
                    </button>
                    <button
                        v-if="store.cart.items.length > 0"
                        @click="store.clearCart()"
                        class="px-2.5 py-1.5 rounded-xl border border-pos-border text-xs text-pos-danger hover:bg-red-50"
                        title="Kosongkan keranjang"
                    >
                        Kosongkan
                    </button>
                    <button
                        v-if="mobileCartOpen"
                        @click="mobileCartOpen = false"
                        class="lg:hidden px-3 py-1.5 rounded-xl bg-pos-black text-pos-white text-xs font-semibold"
                    >
                        Tutup
                    </button>
                </div>
            </div>

            <!-- Alerts (Resume Notices / Price Change) -->
            <div v-if="store.resumeNotices.length > 0" class="mx-4 mt-3 p-3 rounded-xl bg-pos-gold-tint border border-pos-gold text-xs space-y-1">
                <div class="font-bold text-pos-black">Info Pesanan yang Dilanjutkan:</div>
                <div v-for="(n, i) in store.resumeNotices" :key="i" class="text-pos-black">• {{ n }}</div>
            </div>

            <div v-if="store.priceChangeAlert" class="mx-4 mt-3 p-3 rounded-xl bg-amber-50 border border-amber-300 text-xs text-amber-900 font-medium">
                {{ store.priceChangeAlert }}
            </div>

            <!-- Cart Items List -->
            <div class="flex-1 overflow-y-auto p-4 space-y-2.5">
                <div
                    v-if="store.cart.items.length === 0"
                    class="h-full flex flex-col items-center justify-center p-8 text-center text-pos-muted"
                >
                    <div class="w-14 h-14 rounded-2xl bg-pos-alabaster border border-pos-border flex items-center justify-center text-pos-gold mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m12-9l2 9m-9-4h4" />
                        </svg>
                    </div>
                    <div class="font-semibold text-sm text-pos-black">Belum Ada Barang Dipilih</div>
                    <p class="text-xs text-pos-muted mt-1 max-w-[240px]">
                        Pilih produk pada daftar menu atau scan barcode barang untuk mulai menghitung belanjaan.
                    </p>
                </div>

                <div
                    v-for="(item, idx) in store.cart.items"
                    :key="item.product_id"
                    class="p-3 rounded-2xl bg-pos-alabaster border border-pos-border/80 space-y-2.5"
                >
                    <div class="flex items-center gap-3">
                        <!-- Mini Thumbnail -->
                        <img
                            :src="getProductImage(item)"
                            :alt="item.name"
                            class="w-12 h-12 rounded-xl object-cover bg-pos-white border border-pos-border shrink-0"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-xs sm:text-sm text-pos-black truncate">{{ item.name }}</div>
                            <div class="text-xs font-mono text-pos-muted tabular-nums">
                                {{ formatRupiah(item.unit_price) }} / item
                                <span
                                    v-if="item.track_stock && item.qty > item.stock_qty"
                                    class="block text-[11px] font-sans text-pos-danger font-semibold"
                                >
                                    Stok tersedia sisa {{ item.stock_qty }}
                                </span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono font-bold text-sm text-pos-black tabular-nums">
                                {{ formatRupiah(Math.round(item.qty * item.unit_price) - (item.line_discount || 0)) }}
                            </div>
                            <div v-if="item.line_discount > 0" class="text-[10px] font-mono text-pos-danger">
                                Hemat {{ formatRupiah(item.line_discount) }}
                            </div>
                        </div>
                    </div>

                    <!-- Qty Stepper & Item Discount -->
                    <div class="flex items-center justify-between gap-2 pt-1">
                        <div class="inline-flex items-center bg-pos-white rounded-xl border border-pos-border p-0.5">
                            <button
                                @click="store.updateCartLineQty(idx, Number(item.qty) - 1)"
                                class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-base hover:bg-pos-stone active:bg-pos-black active:text-white transition-colors"
                                title="Kurangi jumlah"
                            >
                                −
                            </button>
                            <input
                                :value="item.qty"
                                @change="store.updateCartLineQty(idx, $event.target.value)"
                                type="number"
                                :step="item.allow_decimal_qty ? '0.001' : '1'"
                                min="0.001"
                                class="w-14 h-9 text-center font-mono text-xs font-bold bg-transparent focus:outline-none tabular-nums"
                            />
                            <button
                                @click="store.updateCartLineQty(idx, Number(item.qty) + 1)"
                                class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-base hover:bg-pos-stone active:bg-pos-black active:text-white transition-colors"
                                title="Tambah jumlah"
                            >
                                +
                            </button>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] text-pos-muted">Diskon Item (Rp):</span>
                            <input
                                :value="item.line_discount"
                                @change="store.updateCartLineDiscount(idx, $event.target.value)"
                                type="number"
                                min="0"
                                step="500"
                                placeholder="0"
                                class="w-24 h-9 px-2.5 rounded-xl text-right font-mono text-xs border border-pos-border bg-pos-white focus:border-pos-gold focus:outline-none tabular-nums"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary & Checkout Footer -->
            <div class="border-t border-pos-border bg-pos-white p-4 space-y-3">
                <!-- Order Discount Input -->
                <div class="flex items-center justify-between gap-2 bg-pos-alabaster px-3 py-2 rounded-xl border border-pos-border">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-medium text-pos-black">Diskon Total Belanja</span>
                        <select
                            v-model="store.cart.order_discount_type"
                            @change="clearDiscountApproval"
                            class="h-8 px-2 rounded-lg text-xs border border-pos-border bg-pos-white font-semibold"
                        >
                            <option value="amount">Rupiah (Rp)</option>
                            <option value="percent">Persen (%)</option>
                        </select>
                    </div>
                    <input
                        v-model.number="store.cart.order_discount_value"
                        @input="clearDiscountApproval"
                        type="number"
                        min="0"
                        placeholder="0"
                        class="w-28 h-8 px-2.5 rounded-lg text-right font-mono text-xs border border-pos-border bg-pos-white focus:border-pos-gold focus:outline-none tabular-nums"
                    />
                </div>

                <!-- Manager PIN Prompt if Discount > Limit -->
                <div
                    v-if="store.requiresDiscountPin"
                    class="p-3 rounded-xl bg-pos-gold-tint border border-pos-gold flex items-center justify-between text-xs"
                >
                    <div>
                        <span class="font-semibold text-pos-black">Diskon di atas {{ store.settings.cashier_discount_limit_pct }}%:</span>
                        <span v-if="store.cart.approved_by_name" class="ml-1 text-pos-success font-semibold">
                            Disetujui oleh {{ store.cart.approved_by_name }}
                        </span>
                        <span v-else class="ml-1 text-pos-warning">Perlu izin PIN Manajer</span>
                    </div>
                    <button
                        v-if="!store.cart.approved_by_name"
                        @click="showPinModal = true"
                        class="px-3 py-1.5 rounded-lg bg-pos-black text-pos-gold text-xs font-semibold"
                    >
                        Masukkan PIN
                    </button>
                </div>

                <!-- Subtotal / Discount / Tax Rows -->
                <div class="space-y-1 text-xs tabular-nums">
                    <div class="flex justify-between text-pos-muted">
                        <span>Subtotal Barang</span>
                        <span class="font-mono font-medium text-pos-black">{{ formatRupiah(totals.subtotal) }}</span>
                    </div>
                    <div v-if="totals.order_discount_amount > 0" class="flex justify-between text-pos-muted">
                        <span>Potongan Diskon Belanja</span>
                        <span class="font-mono text-pos-danger">-{{ formatRupiah(totals.order_discount_amount) }}</span>
                    </div>
                    <div v-if="totals.tax_mode !== 'off'" class="flex justify-between text-pos-muted">
                        <span>PPN ({{ totals.tax_mode === 'inclusive' ? 'Sudah Termasuk' : 'Tambahan' }} {{ totals.tax_rate }}%)</span>
                        <span class="font-mono text-pos-black">{{ formatRupiah(totals.tax_total) }}</span>
                    </div>
                </div>

                <div class="flex items-baseline justify-between pt-2 border-t border-pos-border">
                    <span class="text-sm font-bold text-pos-black">Total Tagihan</span>
                    <span class="font-mono text-2xl font-bold text-pos-black tabular-nums">
                        {{ formatRupiah(totals.grand_total) }}
                    </span>
                </div>

                <button
                    @click="openPaymentDialog"
                    :disabled="store.cart.items.length === 0 || !store.currentShift"
                    class="w-full min-h-[52px] rounded-2xl bg-pos-black hover:bg-pos-charcoal text-pos-white border border-pos-gold font-bold text-sm flex items-center justify-between px-5 disabled:opacity-40 disabled:pointer-events-none transition-all shadow-sm"
                >
                    <span>Proses Pembayaran [F8]</span>
                    <span class="font-mono text-pos-gold text-base tabular-nums">{{ formatRupiah(totals.grand_total) }} →</span>
                </button>
            </div>
        </div>

        <!-- PAYMENT DIALOG -->
        <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
            <div class="bg-pos-white border border-pos-border rounded-3xl w-full max-w-2xl flex flex-col max-h-[92vh] overflow-hidden shadow-2xl">
                <div class="px-6 py-4 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                    <div>
                        <div class="text-xs text-pos-gold font-medium">Penyelesaian Pembayaran</div>
                        <div class="text-xl font-mono font-bold tabular-nums mt-0.5">
                            Total Tagihan: {{ formatRupiah(totals.grand_total) }}
                        </div>
                    </div>
                    <button @click="showPayModal = false" class="px-3 py-1.5 rounded-xl bg-pos-charcoal text-xs text-pos-white hover:text-pos-gold">
                        Tutup [ESC]
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-5 flex-1">
                    <div v-if="payError" class="p-3.5 rounded-xl bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
                        {{ payError }}
                    </div>

                    <!-- Payment Method Selection -->
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-2">1. Pilih Cara Pembayaran Pelanggan</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <button
                                v-for="m in paymentMethods"
                                :key="m.id"
                                type="button"
                                @click="selectMethodTab(m.id)"
                                :class="[
                                    'min-h-[48px] rounded-xl font-semibold text-xs border transition-all px-3',
                                    activePayMethod === m.id
                                        ? 'bg-pos-black text-pos-gold border-pos-black shadow-sm'
                                        : 'bg-pos-alabaster text-pos-black border-pos-border hover:border-pos-gold',
                                ]"
                            >
                                {{ m.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Quick Cash Buttons -->
                    <div v-if="activePayMethod === 'cash'" class="space-y-2">
                        <label class="block text-xs font-semibold text-pos-black">
                            2. Pilih Cepat Pecahan Uang Diterima
                        </label>
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                            <button
                                type="button"
                                @click="setQuickCash(remainingDue > 0 ? remainingDue : totals.grand_total)"
                                class="btn-outline text-xs font-bold"
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

                    <!-- Payment Input Fields -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-pos-alabaster p-4 rounded-2xl border border-pos-border">
                        <div>
                            <label class="block text-xs font-semibold text-pos-black mb-1">
                                Nominal Dibayar (Rp)
                            </label>
                            <input
                                v-model.number="payDraft.amount"
                                type="number"
                                min="1"
                                class="input-pos font-mono font-bold tabular-nums"
                            />
                        </div>
                        <div v-if="activePayMethod === 'cash'">
                            <label class="block text-xs font-semibold text-pos-black mb-1">
                                Uang Fisik Diterima (Rp)
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
                            <label class="block text-xs font-semibold text-pos-black mb-1">
                                Nomor Referensi / Bukti (Opsional)
                            </label>
                            <input
                                v-model="payDraft.reference"
                                @keydown.enter.prevent="submitPaymentConfirm"
                                type="text"
                                placeholder="Contoh: No. Ref QRIS / EDC"
                                class="input-pos font-mono"
                            />
                        </div>
                        <div class="flex items-end">
                            <button
                                type="button"
                                @click="addSplitPaymentRow"
                                class="btn-outline w-full text-xs font-semibold"
                                title="Gunakan jika pelanggan membayar dengan 2 metode berbeda (misal sebagian Tunai, sebagian QRIS)"
                            >
                                + Gabung 2 Cara Bayar
                            </button>
                        </div>
                    </div>

                    <!-- Split Payments List -->
                    <div v-if="splitPayments.length > 0" class="rounded-2xl border border-pos-border overflow-hidden divide-y divide-pos-border">
                        <div class="px-4 py-2.5 bg-pos-stone text-xs font-semibold">
                            Daftar Pembayaran Gabungan
                        </div>
                        <div
                            v-for="(sp, sIdx) in splitPayments"
                            :key="sIdx"
                            class="px-4 py-2.5 flex items-center justify-between text-xs font-mono tabular-nums bg-pos-white"
                        >
                            <div>
                                <span class="font-bold uppercase">{{ sp.method }}</span>
                                <span v-if="sp.reference" class="ml-2 text-pos-muted">Ref: {{ sp.reference }}</span>
                                <span v-if="sp.method === 'cash'" class="ml-2 text-pos-muted">
                                    (Uang diterima: {{ formatRupiah(sp.tendered) }})
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-bold">{{ formatRupiah(sp.amount) }}</span>
                                <button @click="splitPayments.splice(sIdx, 1)" class="text-pos-danger hover:underline font-sans">
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Change Box -->
                    <div class="grid grid-cols-2 gap-4 p-4 rounded-2xl bg-pos-black text-pos-white border border-pos-gold tabular-nums">
                        <div>
                            <div class="text-xs text-pos-stone">Kurang Bayar</div>
                            <div class="text-lg font-mono font-bold mt-0.5">
                                {{ formatRupiah(Math.max(0, remainingDueAfterDraft)) }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-pos-gold font-semibold">Uang Kembalian Pelanggan</div>
                            <div class="text-2xl font-mono font-bold text-pos-gold mt-0.5">
                                {{ formatRupiah(liveChangeAmount) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-pos-white border-t border-pos-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <span class="text-xs text-pos-muted">
                        {{ store.isOnline ? 'Terhubung ke server' : 'Mode Offline: Nota tetap dicetak & disinkronkan otomatis saat online' }}
                    </span>
                    <div class="flex gap-2.5 justify-end">
                        <button type="button" @click="showPayModal = false" class="btn-outline">
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="submitPaymentConfirm"
                            :disabled="submittingPay"
                            class="btn-gold px-6"
                        >
                            {{ submittingPay ? 'Menyimpan...' : 'Selesaikan & Cetak Struk [Enter]' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- HELD CARTS MODAL -->
        <div v-if="showHeldModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div class="bg-pos-white border border-pos-border rounded-3xl w-full max-w-lg overflow-hidden shadow-xl">
                <div class="px-5 py-4 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-sm">Daftar Pesanan yang Disimpan Sementara</h3>
                        <p class="text-[11px] text-pos-stone">Pilih pesanan untuk melanjutkan transaksi pelanggan.</p>
                    </div>
                    <button @click="showHeldModal = false" class="text-xs text-pos-white hover:text-pos-gold">Tutup [ESC]</button>
                </div>
                <div class="p-5 max-h-[70vh] overflow-y-auto divide-y divide-pos-border">
                    <div v-if="store.heldCarts.length === 0" class="py-10 text-center text-xs text-pos-muted">
                        Belum ada pesanan yang disimpan sementara.
                    </div>
                    <div
                        v-for="h in store.heldCarts"
                        :key="h.id"
                        class="py-3.5 flex items-center justify-between gap-3"
                    >
                        <div>
                            <div class="font-bold text-sm text-pos-black">{{ h.label }}</div>
                            <div class="text-xs text-pos-muted">
                                Disimpan pukul {{ formatDateTime(h.created_at) }} · {{ h.cart_snapshot.items.length }} jenis barang
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="onResumeHeld(h.id)"
                                class="btn-gold text-xs py-1.5 px-3.5 min-h-[38px]"
                            >
                                Lanjutkan
                            </button>
                            <button
                                @click="store.discardHeldCart(h.id)"
                                class="btn-outline text-xs py-1.5 px-3 min-h-[38px] text-pos-danger"
                            >
                                Hapus
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
            default-reason="Diskon belanja di atas batas kasir"
            @close="showPinModal = false"
            @approved="onDiscountPinApproved"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';
import ManagerPinModal from '../components/ManagerPinModal.vue';
import { usePosStore } from '../stores/posStore';
import { formatDateTime, formatQty, formatRupiah, getProductImage } from '../utils/format';

defineEmits(['navigate']);

const store = usePosStore();
const searchInput = ref(null);
const tenderedInput = ref(null);
const searchQuery = ref('');
const selectedCategoryId = ref(null);
const mobileCartOpen = ref(false);

const showHeldModal = ref(false);
const showPinModal = ref(false);
const showPayModal = ref(false);
const submittingPay = ref(false);
const payError = ref('');

const paymentMethods = [
    { id: 'cash', label: 'Uang Tunai' },
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

function countByCategory(categoryId) {
    return store.activeProducts.filter((p) => p.category_id === categoryId).length;
}

function qtyInCart(productId) {
    const found = store.cart.items.find((i) => i.product_id === productId);
    return found ? Number(found.qty) : 0;
}

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
    const label = window.prompt(
        'Beri nama/catatan untuk pesanan yang disimpan sementara ini:',
        `Pelanggan #${store.heldCarts.length + 1}`
    );
    if (label !== null) {
        await store.holdCurrentCart(label);
        mobileCartOpen.value = false;
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
        payError.value = 'Nominal pembayaran harus lebih dari Rp 0.';
        return;
    }
    if (amount > remainingDue.value) {
        payError.value = 'Nominal yang dimasukkan melebihi sisa tagihan.';
        return;
    }

    const tendered = activePayMethod.value === 'cash' ? Math.round(Number(payDraft.value.tendered || amount)) : amount;
    if (activePayMethod.value === 'cash' && tendered < amount) {
        payError.value = 'Uang tunai yang diterima kurang dari nominal pembayaran.';
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
            payError.value = 'Uang tunai yang diterima kurang dari nominal tagihan.';
            return;
        }
        if (activePayMethod.value !== 'cash' && draftAmt > remainingDue.value) {
            payError.value = 'Pembayaran non-tunai tidak boleh melebihi sisa tagihan.';
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
        payError.value = `Total pembayaran (${formatRupiah(sumApplied)}) belum sesuai dengan Total Tagihan (${formatRupiah(totals.value.grand_total)}).`;
        return;
    }

    submittingPay.value = true;
    try {
        await store.confirmCheckout(finalPayments);
        showPayModal.value = false;
        mobileCartOpen.value = false;
    } catch (err) {
        payError.value = err.message || 'Gagal menyimpan transaksi.';
    } finally {
        submittingPay.value = false;
    }
}

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
        mobileCartOpen.value = false;
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
