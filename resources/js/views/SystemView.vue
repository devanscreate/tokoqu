<template>
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <div class="border-b border-pos-border pb-4">
            <h1 class="text-xl font-bold uppercase tracking-wider text-pos-black">Pengaturan Toko, Register & Pengguna (W1)</h1>
            <p class="text-xs text-pos-muted mt-0.5">
                Halaman khusus Owner untuk mengelola konfigurasi toko, pajak, mesin kasir (register), akun pengguna, dan PIN Manager.
            </p>
        </div>

        <div v-if="errorMsg" class="p-3.5 bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>
        <div v-if="successMsg" class="p-3.5 bg-pos-gold-tint border border-pos-gold text-xs text-pos-black font-semibold">
            {{ successMsg }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- STORE SETTINGS FORM (W1.2) -->
            <form @submit.prevent="saveSettings" class="card-pos p-5 space-y-3">
                <div class="border-b border-pos-border pb-2">
                    <h2 class="text-sm font-bold uppercase tracking-wider">Konfigurasi Toko & Aturan Transaksi</h2>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Nama Toko *</label>
                    <input v-model="settingsForm.store_name" type="text" required class="input-pos" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">No. Telepon</label>
                        <input v-model="settingsForm.store_phone" type="text" class="input-pos font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Zona Waktu (R10)</label>
                        <input v-model="settingsForm.timezone" type="text" required class="input-pos font-mono" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Alamat Toko</label>
                    <input v-model="settingsForm.store_address" type="text" class="input-pos" />
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Footer Struk</label>
                    <input v-model="settingsForm.receipt_footer" type="text" class="input-pos" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Mode Pajak / PPN (A5, R5)</label>
                        <select v-model="settingsForm.tax_mode" class="input-pos">
                            <option value="off">Nonaktif (Off)</option>
                            <option value="inclusive">Harga Termasuk Pajak (Inclusive)</option>
                            <option value="exclusive">Harga Belum Termasuk Pajak (Exclusive)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Tarif Pajak (%)</label>
                        <input v-model.number="settingsForm.tax_rate" type="number" min="0" max="100" step="0.1" class="input-pos font-mono" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Batas Diskon Kasir (%)</label>
                        <input v-model.number="settingsForm.cashier_discount_limit_pct" type="number" min="0" max="100" class="input-pos font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Batas Hari Retur</label>
                        <input v-model.number="settingsForm.return_window_days" type="number" min="1" max="365" class="input-pos font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Stok Negatif Online</label>
                        <select v-model="settingsForm.allow_negative_stock" class="input-pos">
                            <option value="0">Diblokir (Default)</option>
                            <option value="1">Diizinkan</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-gold w-full">Simpan Pengaturan Toko</button>
            </form>

            <!-- REGISTERS & USERS MANAGEMENT (W1.3 & W1.4) -->
            <div class="space-y-6">
                <!-- Registers -->
                <div class="card-pos p-5 space-y-3">
                    <div class="border-b border-pos-border pb-2">
                        <h2 class="text-sm font-bold uppercase tracking-wider">Mesin Kasir / Register (W1.3)</h2>
                    </div>

                    <form @submit.prevent="createRegister" class="flex gap-2">
                        <input
                            v-model="regForm.code"
                            type="text"
                            required
                            placeholder="Kode (mis: KSR-03)"
                            class="input-pos w-36 font-mono uppercase"
                        />
                        <input
                            v-model="regForm.name"
                            type="text"
                            required
                            placeholder="Nama Register"
                            class="input-pos flex-1"
                        />
                        <button type="submit" class="btn-primary">Tambah</button>
                    </form>

                    <div class="divide-y divide-pos-border text-xs">
                        <div v-for="r in registers" :key="r.id" class="py-2 flex items-center justify-between">
                            <div>
                                <span class="font-mono font-bold">{{ r.code }}</span> — {{ r.name }}
                            </div>
                            <span class="px-2 py-0.5 bg-pos-stone font-mono text-[10px] uppercase">
                                {{ r.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Users & Roles -->
                <div class="card-pos p-5 space-y-3">
                    <div class="border-b border-pos-border pb-2">
                        <h2 class="text-sm font-bold uppercase tracking-wider">Pengguna, Peran & PIN Otorisasi (W1.4)</h2>
                    </div>

                    <form @submit.prevent="createUser" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <input v-model="userForm.name" type="text" required placeholder="Nama Lengkap" class="input-pos" />
                        <input v-model="userForm.email" type="email" required placeholder="Email Login" class="input-pos" />
                        <input v-model="userForm.password" type="password" required minlength="6" placeholder="Password (min 6)" class="input-pos" />
                        <select v-model="userForm.role" class="input-pos">
                            <option value="cashier">Cashier (Kasir)</option>
                            <option value="manager">Manager (Stok & Approval PIN)</option>
                            <option value="owner">Owner (Akses Penuh)</option>
                        </select>
                        <input
                            v-model="userForm.pin"
                            type="password"
                            inputmode="numeric"
                            placeholder="PIN Otorisasi (Khusus Manager/Owner)"
                            class="input-pos font-mono sm:col-span-2"
                        />
                        <button type="submit" class="btn-gold sm:col-span-2">Tambah Pengguna Baru</button>
                    </form>

                    <div class="divide-y divide-pos-border text-xs pt-2">
                        <div v-for="u in users" :key="u.id" class="py-2 flex items-center justify-between">
                            <div>
                                <div class="font-semibold">{{ u.name }} ({{ u.email }})</div>
                                <div class="font-mono text-[10px] text-pos-muted uppercase">
                                    Role: {{ u.role }} · PIN: {{ u.has_pin ? 'Tersetel ✓' : 'Belum ada' }}
                                </div>
                            </div>
                            <span class="px-2 py-0.5 bg-pos-gold-tint border border-pos-gold font-mono text-[10px] uppercase">
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
        successMsg.value = 'Pengaturan toko berhasil diperbarui dan dicatat di log audit.';
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
        successMsg.value = 'Register baru berhasil ditambahkan.';
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
        successMsg.value = 'Akun pengguna baru berhasil dibuat.';
        await loadSystem();
    } catch (err) {
        errorMsg.value = err.message;
    }
}

onMounted(() => {
    loadSystem();
});
</script>

