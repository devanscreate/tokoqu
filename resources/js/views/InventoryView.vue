<template>
    <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
        <!-- Header & Friendly Sub-Navigation -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-pos-border pb-4">
            <div>
                <h1 class="text-xl font-bold text-pos-black">Kelola Produk, Foto & Stok Gudang</h1>
                <p class="text-xs text-pos-muted mt-0.5">
                    Tambahkan produk beserta fotonya agar kasir mudah mengenali barang, catat barang masuk, atau lakukan cek stok fisik.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'min-h-[42px] px-4 py-2 rounded-xl text-xs font-semibold border transition-all',
                        activeTab === tab.id
                            ? 'bg-pos-black text-pos-gold border-pos-black shadow-sm'
                            : 'bg-pos-white text-pos-black border-pos-border hover:border-pos-gold',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div v-if="errorMsg" class="p-3.5 rounded-xl bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>
        <div v-if="successMsg" class="p-3.5 rounded-xl bg-pos-gold-tint border border-pos-gold text-xs text-pos-black font-semibold">
            {{ successMsg }}
        </div>

        <!-- TAB 1: PRODUCTS, PHOTOS & CATEGORIES -->
        <div v-if="activeTab === 'products'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Product Form with Photo Upload -->
            <form @submit.prevent="saveProduct" class="lg:col-span-4 card-pos p-5 space-y-4 self-start">
                <div class="border-b border-pos-border pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-pos-black">
                            {{ productForm.id ? 'Ubah Data & Foto Produk' : 'Tambah Produk Baru' }}
                        </h2>
                        <p class="text-[11px] text-pos-muted">Lengkapi informasi barang dan unggah foto produk.</p>
                    </div>
                    <button
                        v-if="productForm.id"
                        type="button"
                        @click="resetProductForm"
                        class="text-xs text-pos-danger font-medium hover:underline"
                    >
                        Batal Edit
                    </button>
                </div>

                <!-- PHOTO UPLOADER, UNIFORM 200x200 PREVIEW & LAYOUT CONTROLS -->
                <div class="p-4 rounded-2xl bg-pos-alabaster border border-pos-border space-y-3">
                    <div class="flex items-center gap-4">
                        <div class="w-24 h-24 aspect-square rounded-xl overflow-hidden bg-pos-white border-2 border-pos-border shrink-0 shadow-sm">
                            <img
                                :src="getProductImage(productForm, store.theme)"
                                alt="Preview Produk"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <div class="space-y-2 flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-xs font-bold text-pos-black">Foto Produk (Seragam 1:1)</span>
                                <span
                                    v-if="compressedSizeKb"
                                    class="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-[10px] font-mono font-bold text-emerald-800"
                                >
                                    {{ compressedSizeKb }} KB • 200×200px
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <label class="cursor-pointer px-3 py-1.5 rounded-xl bg-pos-black text-pos-white text-xs font-semibold hover:bg-pos-charcoal transition-colors">
                                    Pilih Foto
                                    <input
                                        type="file"
                                        accept="image/*"
                                        @change="handleProductImageUpload"
                                        class="hidden"
                                    />
                                </label>
                                <button
                                    v-if="productForm.image_url"
                                    type="button"
                                    @click="clearProductImage"
                                    class="px-2.5 py-1.5 rounded-xl border border-pos-border bg-pos-white text-xs font-semibold text-pos-danger"
                                >
                                    Hapus Foto
                                </button>
                            </div>
                            <p class="text-[11px] text-pos-muted leading-snug">
                                Ukuran foto diseragamkan 200×200px & dikompres super kecil (~5–10 KB) agar aplikasi tetap kencang.
                            </p>
                        </div>
                    </div>

                    <!-- Interactive Photo Layout Adjuster (Visible when a photo is uploaded) -->
                    <div v-if="rawImageSource" class="pt-3 border-t border-pos-border space-y-2.5 text-xs">
                        <div class="font-bold text-pos-black flex items-center justify-between">
                            <span>Atur Layout & Posisi Foto:</span>
                            <button
                                type="button"
                                @click="resetImageAdjustments"
                                class="text-[11px] font-semibold text-pos-muted hover:text-pos-black underline"
                            >
                                Reset Posisi
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="setImageFitMode('cover')"
                                :class="[
                                    'py-1.5 px-2.5 rounded-xl border text-xs font-semibold transition-all',
                                    imageAdjust.fitMode === 'cover'
                                        ? 'bg-pos-black text-pos-white border-pos-black'
                                        : 'bg-pos-white text-pos-black border-pos-border',
                                ]"
                            >
                                Penuh Kotak (Rapi)
                            </button>
                            <button
                                type="button"
                                @click="setImageFitMode('contain')"
                                :class="[
                                    'py-1.5 px-2.5 rounded-xl border text-xs font-semibold transition-all',
                                    imageAdjust.fitMode === 'contain'
                                        ? 'bg-pos-black text-pos-white border-pos-black'
                                        : 'bg-pos-white text-pos-black border-pos-border',
                                ]"
                            >
                                Utuh (Tanpa Terpotong)
                            </button>
                        </div>

                        <div class="grid grid-cols-3 gap-2 pt-1">
                            <div>
                                <label class="block text-[10px] font-semibold text-pos-muted mb-0.5">
                                    Perbesar ({{ imageAdjust.zoom.toFixed(1) }}x)
                                </label>
                                <input
                                    v-model.number="imageAdjust.zoom"
                                    @input="reprocessUploadedImage"
                                    type="range"
                                    min="0.8"
                                    max="2.0"
                                    step="0.05"
                                    class="w-full accent-pos-gold"
                                />
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-pos-muted mb-0.5">
                                    Geser Kiri/Kanan
                                </label>
                                <input
                                    v-model.number="imageAdjust.offsetX"
                                    @input="reprocessUploadedImage"
                                    type="range"
                                    min="-40"
                                    max="40"
                                    step="2"
                                    class="w-full accent-pos-gold"
                                />
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-pos-muted mb-0.5">
                                    Geser Atas/Bawah
                                </label>
                                <input
                                    v-model.number="imageAdjust.offsetY"
                                    @input="reprocessUploadedImage"
                                    type="range"
                                    min="-40"
                                    max="40"
                                    step="2"
                                    class="w-full accent-pos-gold"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Nama Produk *</label>
                    <input
                        v-model="productForm.name"
                        type="text"
                        required
                        placeholder="Contoh: Kopi Arabika Gayo 250g"
                        class="input-pos"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Kode Barang (SKU) *</label>
                        <input
                            v-model="productForm.sku"
                            type="text"
                            required
                            placeholder="MNM-001"
                            class="input-pos font-mono"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Nomor Barcode</label>
                        <input
                            v-model="productForm.barcode"
                            type="text"
                            placeholder="Scan / ketik barcode"
                            class="input-pos font-mono"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Kategori Produk</label>
                    <div class="flex gap-2">
                        <select v-model="productForm.category_id" class="input-pos flex-1">
                            <option :value="null">-- Pilih Kategori --</option>
                            <option v-for="c in store.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <button
                            type="button"
                            @click="promptNewCategory"
                            class="btn-outline px-3.5"
                            title="Buat kategori baru"
                        >
                            + Kategori
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Harga Jual Kasir (Rp) *</label>
                        <input
                            v-model.number="productForm.price"
                            type="number"
                            min="0"
                            required
                            class="input-pos font-mono font-semibold"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Harga Modal / Beli (Rp)</label>
                        <input
                            v-model.number="productForm.cost"
                            type="number"
                            min="0"
                            class="input-pos font-mono"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 items-start pt-1">
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Batas Pengingat Stok Tipis</label>
                        <input
                            v-model.number="productForm.min_stock"
                            type="number"
                            min="0"
                            step="0.001"
                            class="input-pos font-mono"
                        />
                    </div>
                    <div class="space-y-2 text-xs pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="productForm.track_stock" type="checkbox" class="rounded" />
                            <span>Hitung Stok Otomatis</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="productForm.allow_decimal_qty" type="checkbox" class="rounded" />
                            <span>Bisa Beli Desimal / Timbangan (Kg)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="productForm.is_active" type="checkbox" class="rounded" />
                            <span>Tampilkan di Layar Kasir</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-gold w-full">
                    {{ productForm.id ? 'Simpan Perubahan Produk' : 'Tambahkan ke Daftar Produk' }}
                </button>
            </form>

            <!-- Product List Table with Thumbnails -->
            <div class="lg:col-span-8 card-pos overflow-hidden flex flex-col">
                <div class="px-5 py-4 bg-pos-black text-pos-white border-b border-pos-gold flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold">Daftar Katalog Barang ({{ store.products.length }} Produk)</h2>
                        <p class="text-[11px] text-pos-stone">Klik tombol "Ubah / Foto" pada baris barang untuk mengganti foto atau harga.</p>
                    </div>
                    <label class="cursor-pointer px-3.5 py-2 rounded-xl bg-pos-charcoal border border-pos-gold text-pos-gold text-xs font-semibold hover:bg-pos-gold hover:text-white transition-colors self-start sm:self-auto">
                        Impor Produk dari Excel/CSV
                        <input type="file" accept=".csv" @change="handleCsvFile" class="hidden" />
                    </label>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-pos-stone border-b border-pos-border text-[11px] font-semibold text-pos-muted">
                                <th class="p-3">Produk & Foto</th>
                                <th class="p-3">Kode / Barcode</th>
                                <th class="p-3 text-right">Harga Jual</th>
                                <th class="p-3 text-right">Harga Modal</th>
                                <th class="p-3 text-right">Sisa Stok</th>
                                <th class="p-3 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pos-border tabular-nums">
                            <tr v-for="p in store.products" :key="p.id" class="hover:bg-pos-alabaster">
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <img
                                            :src="getProductImage(p, store.theme)"
                                            :alt="p.name"
                                            loading="lazy"
                                            class="w-11 h-11 rounded-xl object-cover bg-pos-alabaster border border-pos-border shrink-0"
                                        />
                                        <div>
                                            <div class="font-semibold text-sm text-pos-black">{{ p.name }}</div>
                                            <span
                                                v-if="!p.is_active"
                                                class="inline-block mt-0.5 px-2 py-0.5 rounded bg-red-50 text-pos-danger text-[10px] font-semibold"
                                            >
                                                Disembunyikan
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 font-mono">
                                    <div class="font-bold text-pos-black">{{ p.sku }}</div>
                                    <div class="text-[11px] text-pos-muted">{{ p.barcode || 'Tanpa barcode' }}</div>
                                </td>
                                <td class="p-3 text-right font-mono font-semibold text-pos-black">{{ formatRupiah(p.price) }}</td>
                                <td class="p-3 text-right font-mono text-pos-muted">{{ formatRupiah(p.cost) }}</td>
                                <td class="p-3 text-right font-mono font-bold">
                                    <span
                                        v-if="p.track_stock"
                                        :class="Number(p.stock_qty) <= Number(p.min_stock) ? 'text-pos-danger' : 'text-pos-black'"
                                    >
                                        {{ formatQty(p.stock_qty) }}
                                    </span>
                                    <span v-else class="text-pos-muted">Tanpa Stok</span>
                                </td>
                                <td class="p-3 text-right space-x-1.5 whitespace-nowrap">
                                    <button
                                        @click="editProduct(p)"
                                        class="px-3 py-1.5 rounded-xl border border-pos-border bg-pos-white hover:border-pos-gold font-medium"
                                    >
                                        Ubah / Foto
                                    </button>
                                    <button
                                        @click="openStockCard(p)"
                                        class="px-3 py-1.5 rounded-xl bg-pos-black text-pos-gold font-medium"
                                    >
                                        Riwayat Stok
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: RECEIVE STOCK -->
        <div v-if="activeTab === 'receipt'" class="card-pos p-6 space-y-5">
            <div class="border-b border-pos-border pb-3">
                <h2 class="text-base font-bold text-pos-black">Catat Barang Masuk dari Supplier / Gudang</h2>
                <p class="text-xs text-pos-muted mt-0.5">
                    Gunakan menu ini setiap kali toko menerima kiriman barang baru agar jumlah stok di layar kasir langsung bertambah.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3.5">
                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Jenis Barang Masuk</label>
                    <select v-model="receiptForm.type" class="input-pos">
                        <option value="purchase">Belanja dari Supplier</option>
                        <option value="opening">Stok Awal Toko</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Nama Supplier / Pemasok</label>
                    <input v-model="receiptForm.supplier_name" type="text" placeholder="Contoh: Agen Sembako Jaya" class="input-pos" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Nomor Nota Supplier (Opsional)</label>
                    <input v-model="receiptForm.reference" type="text" placeholder="Contoh: INV-001" class="input-pos font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Catatan Tambahan</label>
                    <input v-model="receiptForm.note" type="text" placeholder="Keterangan barang masuk" class="input-pos" />
                </div>
            </div>

            <div class="space-y-2.5">
                <div
                    v-for="(row, rIdx) in receiptForm.items"
                    :key="rIdx"
                    class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end bg-pos-alabaster p-3.5 rounded-2xl border border-pos-border"
                >
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-pos-black mb-1">Pilih Barang</label>
                        <select v-model="row.product_id" @change="onReceiptProductChange(row)" class="input-pos">
                            <option value="" disabled>-- Pilih Produk --</option>
                            <option v-for="p in store.products" :key="p.id" :value="p.id">
                                {{ p.sku }} — {{ p.name }} (Stok saat ini: {{ formatQty(p.stock_qty) }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Jumlah Barang Masuk</label>
                        <input v-model.number="row.qty" type="number" min="0.001" step="0.001" class="input-pos font-mono font-bold" />
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-pos-black mb-1">Harga Beli per Satuan (Rp)</label>
                            <input v-model.number="row.unit_cost" type="number" min="0" class="input-pos font-mono" />
                        </div>
                        <button
                            v-if="receiptForm.items.length > 1"
                            type="button"
                            @click="receiptForm.items.splice(rIdx, 1)"
                            class="btn-outline text-pos-danger px-3"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap justify-between gap-3">
                <button
                    type="button"
                    @click="receiptForm.items.push({ product_id: '', qty: 1, unit_cost: 0 })"
                    class="btn-outline text-xs"
                >
                    + Tambah Baris Barang Lain
                </button>
                <button type="button" @click="submitStockReceipt" class="btn-gold">
                    Simpan Barang Masuk & Tambah Stok
                </button>
            </div>
        </div>

        <!-- TAB 3: STOCK ADJUSTMENT & OPNAME -->
        <div v-if="activeTab === 'adjustment'" class="card-pos p-6 space-y-5">
            <div class="border-b border-pos-border pb-3">
                <h2 class="text-base font-bold text-pos-black">Cek Fisik & Penyesuaian Stok (Stock Opname)</h2>
                <p class="text-xs text-pos-muted mt-0.5">
                    Masukkan jumlah barang nyata yang ada di rak toko. Sistem akan otomatis menyesuaikan selisihnya.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Alasan Penyesuaian Stok *</label>
                    <select v-model="adjustmentForm.reason" class="input-pos">
                        <option value="opname">Hitung Stok Rutin (Stock Opname)</option>
                        <option value="damaged">Barang Rusak / Tidak Layak Jual</option>
                        <option value="expired">Barang Kedaluwarsa</option>
                        <option value="lost">Barang Hilang / Terselip</option>
                        <option value="correction">Koreksi Salah Catat Sebelumnya</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Catatan Keterangan</label>
                    <input v-model="adjustmentForm.note" type="text" placeholder="Contoh: Cek rak mingguan" class="input-pos" />
                </div>
            </div>

            <div class="space-y-2.5">
                <div
                    v-for="(row, aIdx) in adjustmentForm.items"
                    :key="aIdx"
                    class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end bg-pos-alabaster p-3.5 rounded-2xl border border-pos-border"
                >
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-pos-black mb-1">Pilih Barang</label>
                        <select v-model="row.product_id" @change="onAdjustmentProductSelect(row)" class="input-pos">
                            <option value="" disabled>-- Pilih Produk --</option>
                            <option v-for="p in store.products.filter((x) => x.track_stock)" :key="p.id" :value="p.id">
                                {{ p.sku }} — {{ p.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-pos-muted mb-1">Stok Tercatat di Sistem</label>
                        <input :value="row.expected_system_qty" type="text" disabled class="input-pos font-mono bg-pos-stone" />
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-pos-black mb-1">Jumlah Fisik Sebenarnya</label>
                            <input v-model.number="row.counted_qty" type="number" min="0" step="0.001" class="input-pos font-mono font-bold" />
                        </div>
                        <button
                            v-if="adjustmentForm.items.length > 1"
                            type="button"
                            @click="adjustmentForm.items.splice(aIdx, 1)"
                            class="btn-outline text-pos-danger px-3"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap justify-between gap-3">
                <button
                    type="button"
                    @click="adjustmentForm.items.push({ product_id: '', expected_system_qty: 0, counted_qty: 0 })"
                    class="btn-outline text-xs"
                >
                    + Tambah Barang Lain
                </button>
                <button type="button" @click="submitStockAdjustment" class="btn-gold">
                    Simpan Hasil Cek Stok
                </button>
            </div>
        </div>

        <!-- STOCK CARD MODAL -->
        <div v-if="stockCardData" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
            <div class="bg-pos-white border border-pos-border rounded-3xl w-full max-w-3xl flex flex-col max-h-[88vh] overflow-hidden shadow-2xl">
                <div class="px-6 py-4 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-sm">
                            Riwayat Keluar-Masuk Stok: {{ stockCardData.product.name }}
                        </h3>
                        <div class="text-xs font-mono text-pos-gold mt-0.5">
                            Kode: {{ stockCardData.product.sku }} · Stok Saat Ini: {{ stockCardData.cached_stock_qty }}
                        </div>
                    </div>
                    <button @click="stockCardData = null" class="text-xs text-pos-white hover:text-pos-gold">Tutup [ESC]</button>
                </div>

                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-pos-stone border-b border-pos-border text-[11px] font-semibold text-pos-muted">
                                <th class="p-3">Tanggal & Jam</th>
                                <th class="p-3">Keterangan Aktivitas</th>
                                <th class="p-3 text-right">Perubahan Stok</th>
                                <th class="p-3">Petugas</th>
                                <th class="p-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pos-border tabular-nums">
                            <tr v-for="m in stockCardData.movements" :key="m.id">
                                <td class="p-3 font-mono">{{ formatDateTime(m.created_at) }}</td>
                                <td class="p-3 font-semibold capitalize">{{ movementLabel(m.type) }}</td>
                                <td
                                    class="p-3 text-right font-mono font-bold"
                                    :class="m.qty_delta > 0 ? 'text-pos-success' : 'text-pos-danger'"
                                >
                                    {{ m.qty_delta > 0 ? '+' + formatQty(m.qty_delta) : formatQty(m.qty_delta) }}
                                </td>
                                <td class="p-3">{{ m.user?.name || 'Sistem' }}</td>
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
import {
    formatDateTime,
    formatQty,
    formatRupiah,
    getProductImage,
    processProductImage,
    readRawImageFile,
} from '../utils/format';

const store = usePosStore();
const activeTab = ref('products');
const errorMsg = ref('');
const successMsg = ref('');
const stockCardData = ref(null);
const rawImageSource = ref('');
const compressedSizeKb = ref('');
const imageAdjust = ref({
    fitMode: 'cover',
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
});

const tabs = [
    { id: 'products', label: '1. Daftar Produk & Foto' },
    { id: 'receipt', label: '2. Catat Barang Masuk' },
    { id: 'adjustment', label: '3. Cek Fisik / Opname Stok' },
];

const productForm = ref({
    id: null,
    sku: '',
    barcode: '',
    name: '',
    image_url: '',
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

function movementLabel(type) {
    const map = {
        opening: 'Stok Awal Toko',
        purchase: 'Barang Masuk Supplier',
        sale: 'Terjual di Kasir',
        void: 'Pembatalan Nota (Void)',
        return: 'Retur Pelanggan',
        adjustment: 'Penyesuaian / Opname',
    };
    return map[type] || type;
}

function resetProductForm() {
    rawImageSource.value = '';
    compressedSizeKb.value = '';
    productForm.value = {
        id: null,
        sku: '',
        barcode: '',
        name: '',
        image_url: '',
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
    rawImageSource.value = p.image_url || '';
    compressedSizeKb.value = p.image_url
        ? ((Math.round((p.image_url.length * 3) / 4)) / 1024).toFixed(1)
        : '';
    imageAdjust.value = { fitMode: 'cover', zoom: 1, offsetX: 0, offsetY: 0 };
    productForm.value = {
        id: p.id,
        sku: p.sku,
        barcode: p.barcode || '',
        name: p.name,
        image_url: p.image_url || '',
        category_id: p.category_id,
        price: p.price,
        cost: p.cost,
        min_stock: p.min_stock,
        track_stock: Boolean(p.track_stock),
        allow_decimal_qty: Boolean(p.allow_decimal_qty),
        is_active: Boolean(p.is_active),
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function clearProductImage() {
    productForm.value.image_url = '';
    rawImageSource.value = '';
    compressedSizeKb.value = '';
}

function setImageFitMode(mode) {
    imageAdjust.value.fitMode = mode;
    reprocessUploadedImage();
}

function resetImageAdjustments() {
    imageAdjust.value = { fitMode: 'cover', zoom: 1, offsetX: 0, offsetY: 0 };
    reprocessUploadedImage();
}

async function reprocessUploadedImage() {
    if (!rawImageSource.value) return;
    try {
        const res = await processProductImage(rawImageSource.value, {
            size: 200,
            fitMode: imageAdjust.value.fitMode,
            zoom: imageAdjust.value.zoom,
            offsetX: imageAdjust.value.offsetX,
            offsetY: imageAdjust.value.offsetY,
        });
        productForm.value.image_url = res.dataUrl;
        compressedSizeKb.value = res.sizeKb;
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function handleProductImageUpload(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    errorMsg.value = '';
    try {
        rawImageSource.value = await readRawImageFile(file);
        imageAdjust.value = { fitMode: 'cover', zoom: 1, offsetX: 0, offsetY: 0 };
        await reprocessUploadedImage();
    } catch (err) {
        errorMsg.value = err.message;
    } finally {
        event.target.value = '';
    }
}

async function promptNewCategory() {
    const name = window.prompt('Masukkan nama kategori barang baru:');
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
            successMsg.value = 'Data dan foto produk berhasil diperbarui.';
        } else {
            await apiRequest('/products', {
                method: 'POST',
                body: productForm.value,
            });
            successMsg.value = 'Produk baru berhasil disimpan. Gunakan tab "Catat Barang Masuk" untuk mengisi stok awalnya.';
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
        errorMsg.value = 'File CSV harus memiliki baris judul kolom dan minimal 1 baris produk.';
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
        successMsg.value = `Barang masuk (${res.receipt.receipt_no}) berhasil disimpan dan stok telah bertambah.`;
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
        successMsg.value = `Hasil cek stok (${res.adjustment.adjustment_no}) berhasil disimpan.`;
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
