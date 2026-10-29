<template>
    <div class="min-h-screen flex flex-col bg-pos-alabaster text-pos-black">
        <!-- LOGIN SCREEN OR SESSION EXPIRED MODAL -->
        <div
            v-if="!store.user || store.sessionExpired"
            class="fixed inset-0 z-50 flex items-center justify-center bg-pos-black/85 p-4"
        >
            <div class="bg-pos-white border border-pos-border rounded-3xl w-full max-w-md overflow-hidden shadow-xl">
                <div class="px-7 py-6 bg-pos-black text-pos-white border-b-2 border-pos-gold">
                    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-pos-charcoal border border-pos-gold/40 text-[11px] font-medium text-pos-gold mb-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-pos-gold"></span>
                        Aplikasi Kasir Toko
                    </div>
                    <h1 class="text-xl font-bold tracking-wide">
                        {{ store.settings.store_name || 'TOKOQU RETAIL' }}
                    </h1>
                    <p class="text-xs text-pos-stone/80 mt-1">
                        Silakan masuk menggunakan akun kasir, manajer, atau pemilik toko.
                    </p>
                </div>

                <form @submit.prevent="submitLogin" class="p-7 space-y-4">
                    <div
                        v-if="store.sessionExpired"
                        class="p-3.5 rounded-xl bg-pos-gold-tint border border-pos-gold text-xs text-pos-black leading-relaxed"
                    >
                        <strong>Sesi Anda telah berakhir.</strong> Silakan masuk kembali—pesanan yang sedang dibuat di keranjang tetap tersimpan dengan aman.
                    </div>

                    <div
                        v-if="loginError"
                        class="p-3.5 rounded-xl bg-red-50 border border-pos-danger text-xs text-pos-danger font-medium"
                    >
                        {{ loginError }}
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1.5">
                            Email Pengguna
                        </label>
                        <input
                            v-model="loginForm.email"
                            type="email"
                            required
                            placeholder="Contoh: kasir@tokoqu.id"
                            class="input-pos"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1.5">
                            Kata Sandi
                        </label>
                        <input
                            v-model="loginForm.password"
                            type="password"
                            required
                            placeholder="Masukkan kata sandi"
                            class="input-pos"
                        />
                    </div>

                    <button type="submit" :disabled="loggingIn" class="btn-gold w-full py-3 text-sm">
                        {{ loggingIn ? 'Sedang Masuk...' : 'Masuk Sekarang' }}
                    </button>

                    <!-- Quick Demo Credentials Hint -->
                    <div class="pt-4 border-t border-pos-border text-xs text-pos-muted space-y-2">
                        <div class="font-semibold text-pos-black">Klik untuk isi cepat akun percobaan (Sandi: <code class="font-mono">password</code>):</div>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="fillDemo('kasir@tokoqu.id')"
                                class="px-2.5 py-2 rounded-xl border border-pos-border bg-pos-alabaster hover:border-pos-gold text-pos-black font-medium text-xs text-center transition-colors"
                            >
                                Akun Kasir
                            </button>
                            <button
                                type="button"
                                @click="fillDemo('manager@tokoqu.id')"
                                class="px-2.5 py-2 rounded-xl border border-pos-border bg-pos-alabaster hover:border-pos-gold text-pos-black font-medium text-xs text-center transition-colors"
                            >
                                Manajer <span class="block text-[10px] text-pos-muted">PIN: 123456</span>
                            </button>
                            <button
                                type="button"
                                @click="fillDemo('owner@tokoqu.id')"
                                class="px-2.5 py-2 rounded-xl border border-pos-border bg-pos-alabaster hover:border-pos-gold text-pos-black font-medium text-xs text-center transition-colors"
                            >
                                Pemilik <span class="block text-[10px] text-pos-muted">PIN: 111111</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- TOP NAVIGATION BAR (Responsive & User Friendly) -->
        <header
            v-if="store.user"
            class="bg-pos-white border-b border-pos-border sticky top-0 z-30"
        >
            <div class="max-w-[1600px] mx-auto px-3 sm:px-5 h-16 flex items-center justify-between gap-2">
                <!-- Left: Store Brand & Active Shift Status -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-pos-black text-pos-gold flex items-center justify-center font-bold text-base shrink-0 border border-pos-gold">
                        T
                    </div>
                    <div class="min-w-0">
                        <div class="font-bold text-sm text-pos-black truncate">
                            {{ store.settings.store_name || 'TOKOQU POS' }}
                        </div>
                        <button
                            @click="currentScreen = 'shift'"
                            class="inline-flex items-center gap-1.5 text-[11px] font-medium"
                            :class="store.currentShift ? 'text-pos-success' : 'text-pos-danger'"
                        >
                            <span
                                class="w-2 h-2 rounded-full"
                                :class="store.currentShift ? 'bg-emerald-600' : 'bg-red-600'"
                            ></span>
                            <span>
                                {{
                                    store.currentShift
                                        ? `Shift Aktif: ${store.currentShift.register?.name || store.currentShift.register?.code}`
                                        : 'Shift Belum Dibuka (Klik untuk Buka)'
                                }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Center: Pill Navigation (Desktop) -->
                <nav class="hidden lg:flex items-center gap-1 bg-pos-alabaster p-1 rounded-2xl border border-pos-border">
                    <button
                        v-for="item in visibleNavItems"
                        :key="item.id"
                        @click="currentScreen = item.id"
                        :class="[
                            'min-h-[38px] px-4 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap flex items-center gap-2',
                            currentScreen === item.id
                                ? 'bg-pos-black text-pos-white shadow-sm'
                                : 'text-pos-muted hover:text-pos-black',
                        ]"
                    >
                        <span>{{ item.label }}</span>
                    </button>
                </nav>

                <!-- Right: Connection Status, Outbox Sync & User Profile -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- Online / Offline Pill -->
                    <div
                        class="px-2.5 py-1 rounded-full text-[11px] font-semibold border flex items-center gap-1.5"
                        :class="
                            store.isOnline
                                ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                                : 'bg-amber-50 border-amber-300 text-amber-900'
                        "
                        :title="store.isOnline ? 'Terhubung ke server' : 'Sedang offline — transaksi tetap bisa dilakukan'"
                    >
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="store.isOnline ? 'bg-emerald-500' : 'bg-amber-500'"
                        ></span>
                        <span class="hidden sm:inline">{{ store.isOnline ? 'Online' : 'Mode Offline' }}</span>
                    </div>

                    <!-- Pending Offline Sync Button -->
                    <button
                        v-if="store.pendingOutboxCount > 0 || store.needsAttentionOutbox.length > 0"
                        @click="store.syncOutbox()"
                        class="px-3 py-1.5 rounded-xl bg-pos-gold text-pos-white text-xs font-semibold flex items-center gap-1.5"
                        title="Sinkronkan transaksi yang tersimpan saat offline"
                    >
                        <span>Sinkronkan ({{ store.pendingOutboxCount }})</span>
                    </button>

                    <!-- User Info -->
                    <div class="hidden xl:block text-right pl-2 border-l border-pos-border">
                        <div class="text-xs font-semibold text-pos-black leading-tight">{{ store.user.name }}</div>
                        <div class="text-[11px] text-pos-gold font-medium capitalize">{{ roleLabel(store.user.role) }}</div>
                    </div>

                    <button
                        @click="handleLogout"
                        class="min-h-[38px] px-3 py-1.5 rounded-xl border border-pos-border bg-pos-white text-xs font-medium text-pos-black hover:border-pos-danger hover:text-pos-danger transition-colors"
                    >
                        Keluar
                    </button>
                </div>
            </div>

            <!-- Mobile / Tablet Navigation Bar (< 1024px) -->
            <div class="lg:hidden flex items-center gap-1.5 px-3 py-2 bg-pos-alabaster border-t border-pos-border overflow-x-auto">
                <button
                    v-for="item in visibleNavItems"
                    :key="item.id"
                    @click="currentScreen = item.id"
                    :class="[
                        'min-h-[36px] px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all',
                        currentScreen === item.id
                            ? 'bg-pos-black text-pos-white'
                            : 'bg-pos-white text-pos-muted border border-pos-border',
                    ]"
                >
                    {{ item.label }}
                </button>
            </div>
        </header>

        <!-- MAIN WORKSPACE -->
        <main v-if="store.user" class="flex-1">
            <PosView v-if="currentScreen === 'pos'" @navigate="currentScreen = $event" />
            <ShiftView v-else-if="currentScreen === 'shift'" @navigate="currentScreen = $event" />
            <SalesHistoryView v-else-if="currentScreen === 'history'" />
            <InventoryView v-else-if="currentScreen === 'inventory'" />
            <ReportsView v-else-if="currentScreen === 'reports'" />
            <SystemView v-else-if="currentScreen === 'system'" />
        </main>

        <!-- GLOBAL PRINTABLE RECEIPT / Z-REPORT MODAL -->
        <ReceiptModal
            :receipt="store.activeReceipt"
            :settings="store.settings"
            @close="store.activeReceipt = null"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import ReceiptModal from './components/ReceiptModal.vue';
import { usePosStore } from './stores/posStore';
import InventoryView from './views/InventoryView.vue';
import PosView from './views/PosView.vue';
import ReportsView from './views/ReportsView.vue';
import SalesHistoryView from './views/SalesHistoryView.vue';
import ShiftView from './views/ShiftView.vue';
import SystemView from './views/SystemView.vue';

const store = usePosStore();
const currentScreen = ref('pos');
const loggingIn = ref(false);
const loginError = ref('');

const loginForm = ref({
    email: 'kasir@tokoqu.id',
    password: 'password',
});

const visibleNavItems = computed(() => {
    const role = store.user?.role || 'cashier';
    const items = [
        { id: 'pos', label: 'Menu Kasir', roles: ['cashier', 'manager', 'owner'] },
        { id: 'shift', label: 'Buka/Tutup Shift', roles: ['cashier', 'manager', 'owner'] },
        { id: 'history', label: 'Riwayat & Retur', roles: ['cashier', 'manager', 'owner'] },
        { id: 'inventory', label: 'Produk & Stok', roles: ['manager', 'owner'] },
        { id: 'reports', label: 'Laporan Toko', roles: ['manager', 'owner'] },
        { id: 'system', label: 'Pengaturan Toko', roles: ['owner'] },
    ];
    return items.filter((i) => i.roles.includes(role));
});

function roleLabel(role) {
    if (role === 'owner') return 'Pemilik Toko';
    if (role === 'manager') return 'Manajer Toko';
    return 'Kasir';
}

function fillDemo(email) {
    loginForm.value.email = email;
    loginForm.value.password = 'password';
}

async function submitLogin() {
    loginError.value = '';
    loggingIn.value = true;
    try {
        await store.login(loginForm.value.email, loginForm.value.password);
    } catch (err) {
        loginError.value = err.message || 'Gagal masuk. Periksa email dan kata sandi.';
    } finally {
        loggingIn.value = false;
    }
}

async function handleLogout() {
    try {
        await store.logout();
    } catch (err) {
        window.alert(err.message);
    }
}

onMounted(() => {
    store.initApp();
});
</script>
