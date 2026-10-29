<template>
    <div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">
        <div class="border-b border-pos-border pb-4">
            <h1 class="text-xl font-bold text-pos-black">Pengaturan Toko, Meja Kasir & Akun Karyawan</h1>
            <p class="text-xs text-pos-muted mt-0.5">
                Atur identitas toko pada struk belanja, pajak PPN, batas diskon kasir, daftar meja kasir, serta akun karyawan dan PIN Manajer.
            </p>
        </div>

        <div v-if="errorMsg" class="p-3.5 rounded-xl bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>
        <div v-if="successMsg" class="p-3.5 rounded-xl bg-pos-gold-tint border border-pos-gold text-xs text-pos-black font-semibold">
            {{ successMsg }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- STORE SETTINGS FORM -->
            <form @submit.prevent="saveSettings" class="card-pos p-6 space-y-4 self-start">
                <div class="border-b border-pos-border pb-3">
                    <h2 class="text-base font-bold text-pos-black">Profil Toko & Aturan Kasir</h2>
                    <p class="text-xs text-pos-muted">Informasi ini akan tampil pada bagian atas dan bawah struk belanja pelanggan.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Nama Toko *</label>
                    <input v-model="settingsForm.store_name" type="text" required class="input-pos" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Nomor Telepon Toko</label>
                        <input v-model="settingsForm.store_phone" type="text" class="input-pos font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Zona Waktu Toko</label>
                        <select v-model="settingsForm.timezone" class="input-pos">
                            <option value="Asia/Jakarta">WIB (Asia/Jakarta)</option>
                            <option value="Asia/Makassar">WITA (Asia/Makassar)</option>
                            <option value="Asia/Jayapura">WIT (Asia/Jayapura)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Alamat Lengkap Toko</label>
                    <input v-model="settingsForm.store_address" type="text" class="input-pos" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1">Pesan Ucapan di Bawah Struk</label>
                    <input v-model="settingsForm.receipt_footer" type="text" class="input-pos" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Pengaturan Pajak (PPN)</label>
                        <select v-model="settingsForm.tax_mode" class="input-pos">
                            <option value="off">Tidak Pakai Pajak (Nonaktif)</option>
                            <option value="inclusive">Harga Barang Sudah Termasuk PPN</option>
                            <option value="exclusive">PPN Ditambahkan di Akhir Struk</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Besaran Pajak PPN (%)</label>
                        <input v-model.number="settingsForm.tax_rate" type="number" min="0" max="100" step="0.1" class="input-pos font-mono" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Batas Diskon Kasir (%)</label>
                        <input
                            v-model.number="settingsForm.cashier_discount_limit_pct"
                            type="number"
                            min="0"
                            max="100"
                            class="input-pos font-mono"
                            title="Diskon di atas angka ini membutuhkan PIN Manajer"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Batas Waktu Retur (Hari)</label>
                        <input v-model.number="settingsForm.return_window_days" type="number" min="1" max="365" class="input-pos font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Jual Saat Stok 0 (Online)</label>
                        <select v-model="settingsForm.allow_negative_stock" class="input-pos">
                            <option value="0">Tidak Boleh (Tolak)</option>
                            <option value="1">Boleh (Stok Minus)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-gold w-full">Simpan Pengaturan Toko</button>
            </form>

            <!-- REGISTERS & USERS MANAGEMENT -->
            <div class="space-y-6">
                <!-- Registers -->
                <div class="card-pos p-6 space-y-4">
                    <div class="border-b border-pos-border pb-2.5">
                        <h2 class="text-base font-bold text-pos-black">Daftar Meja Kasir (Register)</h2>
                        <p class="text-xs text-pos-muted">Tambahkan kode meja kasir jika toko memiliki lebih dari satu perangkat kasir.</p>
                    </div>

                    <form @submit.prevent="createRegister" class="flex flex-col sm:flex-row gap-2.5">
                        <input
                            v-model="regForm.code"
                            type="text"
                            required
                            placeholder="Kode (misal: KSR-03)"
                            class="input-pos sm:w-40 font-mono uppercase"
                        />
                        <input
                            v-model="regForm.name"
                            type="text"
                            required
                            placeholder="Nama Meja Kasir (misal: Kasir Depan)"
                            class="input-pos flex-1"
                        />
                        <button type="submit" class="btn-primary shrink-0">Tambah Kasir</button>
                    </form>

                    <div class="divide-y divide-pos-border text-xs">
                        <div v-for="r in registers" :key="r.id" class="py-2.5 flex items-center justify-between">
                            <div>
                                <span class="font-mono font-bold">{{ r.code }}</span> — {{ r.name }}
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 font-semibold text-[10px]">
                                {{ r.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Users & Roles -->
                <div class="card-pos p-6 space-y-4">
                    <div class="border-b border-pos-border pb-2.5">
                        <h2 class="text-base font-bold text-pos-black">Akun Karyawan & PIN Manajer</h2>
                        <p class="text-xs text-pos-muted">
                            Buat akun untuk kasir atau manajer. Khusus Manajer & Pemilik, isi 6 angka PIN untuk menyetujui diskon besar atau retur.
                        </p>
                    </div>

                    <form @submit.prevent="createUser" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="userForm.name" type="text" required placeholder="Nama Lengkap Karyawan" class="input-pos" />
                        <input v-model="userForm.email" type="email" required placeholder="Email untuk Login" class="input-pos" />
                        <input v-model="userForm.password" type="password" required minlength="6" placeholder="Kata Sandi (min. 6 karakter)" class="input-pos" />
                        <select v-model="userForm.role" class="input-pos">
                            <option value="cashier">Kasir (Melayani Penjualan)</option>
                            <option value="manager">Manajer (Kelola Stok & PIN Izin)</option>
                            <option value="owner">Pemilik Toko (Akses Penuh)</option>
                        </select>
                        <input
                            v-model="userForm.pin"
                            type="password"
                            inputmode="numeric"
                            placeholder="PIN Otorisasi 6 Angka (Khusus Manajer/Pemilik)"
                            class="input-pos font-mono sm:col-span-2"
                        />
                        <button type="submit" class="btn-gold sm:col-span-2">Simpan Akun Karyawan Baru</button>
                    </form>

                    <div class="divide-y divide-pos-border text-xs pt-2">
                        <div v-for="u in users" :key="u.id" class="py-2.5 flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-pos-black">{{ u.name }} ({{ u.email }})</div>
                                <div class="text-[11px] text-pos-muted">
                                    PIN Otorisasi: {{ u.has_pin ? 'Sudah Diatur ✓' : 'Tidak Ada' }}
                                </div>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full bg-pos-gold-tint border border-pos-gold font-semibold text-[11px] capitalize">
                                {{ u.role }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { usePosStore } from '../stores/posStore';
import { apiRequest } from '../utils/api';

const store = usePosStore();
const errorMsg = ref('');
const successMsg = ref('');

const settingsForm = ref({
    store_name: 'TOKOQU RETAIL',
    store_address: '',
    store_phone: '',
    timezone: 'Asia/Jakarta',
    receipt_footer: '',
    tax_mode: 'off',
    tax_rate: 11,
    return_window_days: 7,
    cashier_discount_limit_pct: 10,
    allow_negative_stock: '0',
});

const registers = ref([]);
const users = ref([]);

const regForm = ref({ code: '', name: '' });
const userForm = ref({
    name: '',
    email: '',
    password: '',
    role: 'cashier',
    pin: '',
});

async function loadSystem() {
    errorMsg.value = '';
    try {
        const res = await apiRequest('/system');
        settingsForm.value = { ...settingsForm.value, ...res.settings };
        registers.value = res.registers || [];
        users.value = res.users || [];
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function saveSettings() {
    errorMsg.value = '';
    successMsg.value = '';
    try {
        const res = await apiRequest('/system/settings', {
            method: 'PUT',
            body: settingsForm.value,
        });
        store.settings = { ...store.settings, ...res.settings };
        successMsg.value = 'Pengaturan toko berhasil disimpan.';
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function createRegister() {
    errorMsg.value = '';
    successMsg.value = '';
    try {
        await apiRequest('/system/registers', {
            method: 'POST',
            body: regForm.value,
        });
        regForm.value = { code: '', name: '' };
        successMsg.value = 'Meja kasir baru berhasil ditambahkan.';
        await loadSystem();
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function createUser() {
    errorMsg.value = '';
    successMsg.value = '';
    try {
        await apiRequest('/system/users', {
            method: 'POST',
            body: userForm.value,
        });
        userForm.value = { name: '', email: '', password: '', role: 'cashier', pin: '' };
        successMsg.value = 'Akun karyawan baru berhasil ditambahkan.';
        await loadSystem();
    } catch (err) {
        errorMsg.value = err.message;
    }
}

onMounted(() => {
    loadSystem();
});
</script>
