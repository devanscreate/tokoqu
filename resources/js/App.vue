<template>
    <div class="min-h-screen flex flex-col bg-pos-alabaster text-pos-black transition-colors duration-200">
        <!-- TOP ACCENT STRIPE SO THEME COLOR IS ALWAYS PROMINENT -->
        <div class="h-1.5 w-full bg-pos-gold"></div>

        <!-- LOGIN SCREEN OR SESSION EXPIRED MODAL -->
        <div
            v-if="!store.user || store.sessionExpired"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4"
        >
            <div class="bg-pos-white border-2 border-pos-border rounded-3xl w-full max-w-md overflow-hidden shadow-2xl">
                <div class="px-7 py-6 bg-pos-black text-pos-white border-b-4 border-pos-gold">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-pos-white/15 text-xs font-semibold text-pos-white mb-2">
                        <span class="w-2 h-2 rounded-full bg-pos-gold"></span>
                        Aplikasi Kasir Toko
                    </div>
                    <h1 class="text-xl font-bold tracking-wide text-pos-white">
                        {{ store.settings.store_name || 'TOKOQU RETAIL' }}
                    </h1>
                    <p class="text-xs text-pos-white/90 mt-1">
                        Silakan masuk menggunakan akun kasir, manajer, atau pemilik toko.
                    </p>
                </div>

                <form @submit.prevent="submitLogin" class="p-7 space-y-4">
                    <!-- Quick Theme Selector on Login Screen -->
                    <div class="p-3 rounded-2xl bg-pos-alabaster border border-pos-border">
                        <div class="text-xs font-bold text-pos-black mb-2">Pilih Tema Warna Tampilan:</div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="t in themes"
                                :key="t.id"
                                type="button"
                                @click="store.setTheme(t.id)"
                                :class="[
                                    'px-2.5 py-1.5 rounded-xl text-xs font-semibold border flex items-center gap-1.5 transition-all',
                                    store.theme === t.id
                                        ? 'bg-pos-black text-pos-white border-pos-black shadow-sm'
                                        : 'bg-pos-white text-pos-black border-pos-border hover:border-pos-gold',
                                ]"
                            >
                                <span
                                    class="w-3.5 h-3.5 rounded-full border border-black/20 shrink-0"
                                    :style="{ backgroundColor: t.primaryHex }"
                                ></span>
                                <span>{{ t.label }}</span>
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="store.sessionExpired"
                        class="p-3.5 rounded-xl bg-pos-gold-tint border border-pos-gold text-xs text-pos-black leading-relaxed font-medium"
                    >
                        <strong>Sesi Anda telah berakhir.</strong> Silakan masuk kembali—pesanan yang sedang dibuat di keranjang tetap tersimpan dengan aman.
                    </div>

                    <div
                        v-if="loginError"
                        class="p-3.5 rounded-xl bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold"
                    >
                        {{ loginError }}
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-pos-black mb-1.5">
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
                        <label class="block text-xs font-bold text-pos-black mb-1.5">
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
                        {{ loggingIn ? 'Sedang Masuk...' : 'Masuk ke Aplikasi Kasir' }}
                    </button>

                    <!-- Quick Demo Credentials Hint -->
                    <div class="pt-4 border-t border-pos-border text-xs text-pos-muted space-y-2">
                        <div class="font-bold text-pos-black">Klik untuk isi cepat akun percobaan (Sandi: <code class="font-mono">password</code>):</div>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="fillDemo('kasir@tokoqu.id')"
                                class="px-2.5 py-2 rounded-xl border border-pos-border bg-pos-alabaster hover:border-pos-gold text-pos-black font-semibold text-xs text-center transition-colors"
                            >
                                Akun Kasir
                            </button>
                            <button
                                type="button"
                                @click="fillDemo('manager@tokoqu.id')"
                                class="px-2.5 py-2 rounded-xl border border-pos-border bg-pos-alabaster hover:border-pos-gold text-pos-black font-semibold text-xs text-center transition-colors"
                            >
                                Manajer <span class="block text-[10px] text-pos-muted">PIN: 123456</span>
                            </button>
                            <button
                                type="button"
                                @click="fillDemo('owner@tokoqu.id')"
                                class="px-2.5 py-2 rounded-xl border border-pos-border bg-pos-alabaster hover:border-pos-gold text-pos-black font-semibold text-xs text-center transition-colors"
                            >
                                Pemilik <span class="block text-[10px] text-pos-muted">PIN: 111111</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- TOP NAVIGATION BAR (Responsive, Clear & Theme-Aware) -->
        <header
            v-if="store.user"
            class="bg-pos-white border-b-2 border-pos-border sticky top-0 z-30 shadow-sm"
        >
            <div class="max-w-[1600px] mx-auto px-3 sm:px-5 h-16 flex items-center justify-between gap-2">
                <!-- Left: Store Brand & Active Shift Status -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-pos-black text-pos-white flex items-center justify-center font-bold text-base shrink-0 border-2 border-pos-gold shadow-sm">
                        T
                    </div>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base text-pos-black truncate">
                            {{ store.settings.store_name || 'TOKOQU POS' }}
                        </div>
                        <button
                            @click="currentScreen = 'shift'"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold"
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
                <nav class="hidden lg:flex items-center gap-1.5 bg-pos-alabaster p-1.5 rounded-2xl border border-pos-border">
                    <button
                        v-for="item in visibleNavItems"
                        :key="item.id"
                        @click="currentScreen = item.id"
                        :class="[
                            'min-h-[38px] px-4 py-1.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2',
                            currentScreen === item.id
                                ? 'bg-pos-black text-pos-white shadow-sm'
                                : 'text-pos-black hover:bg-pos-stone',
                        ]"
                    >
                        <span>{{ item.label }}</span>
                    </button>
                </nav>

                <!-- Right: Theme Switcher, Connection Status, Outbox Sync & User Profile -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- THEME COLOR SWITCHER (Direct Swatches + Dropdown) -->
                    <div class="relative">
                        <button
                            @click="showThemeMenu = !showThemeMenu"
                            class="min-h-[38px] px-3 py-1.5 rounded-xl border border-pos-border bg-pos-alabaster hover:border-pos-gold flex items-center gap-2 text-xs font-bold text-pos-black transition-colors"
                            title="Ganti Tema Warna Aplikasi"
                        >
                            <span
                                class="w-4 h-4 rounded-full border border-black/20 shrink-0"
                                :style="{ backgroundColor: activeThemeObj.primaryHex }"
                            ></span>
                            <span class="hidden md:inline">{{ activeThemeObj.label }}</span>
                            <span class="text-[10px]">▼</span>
                        </button>

                        <!-- Theme Dropdown Popover -->
                        <div
                            v-if="showThemeMenu"
                            class="absolute right-0 mt-2 w-60 rounded-2xl bg-pos-white border-2 border-pos-border shadow-xl p-2 z-50 space-y-1"
                        >
                            <div class="px-2.5 py-1.5 text-[11px] font-bold text-pos-muted uppercase tracking-wider">
                                Pilih Tema Warna Program
                            </div>
                            <button
                                v-for="t in themes"
                                :key="t.id"
                                type="button"
                                @click="selectTheme(t.id)"
                                :class="[
                                    'w-full px-3 py-2.5 rounded-xl text-xs font-bold flex items-center justify-between transition-colors',
                                    store.theme === t.id
                                        ? 'bg-pos-gold-tint text-pos-black border border-pos-gold'
                                        : 'text-pos-black hover:bg-pos-alabaster',
                                ]"
                            >
                                <div class="flex items-center gap-2.5">
                                    <!-- Dual Color Swatch (White + Theme Color) -->
                                    <span class="inline-flex items-center rounded-full overflow-hidden border border-black/25 w-6 h-6 shrink-0">
                                        <span class="w-3 h-6 bg-white"></span>
                                        <span class="w-3 h-6" :style="{ backgroundColor: t.primaryHex }"></span>
                                    </span>
                                    <span>{{ t.label }}</span>
                                </div>
                                <span v-if="store.theme === t.id" class="text-xs font-bold">✓</span>
                            </button>
                        </div>
                    </div>

                    <!-- Online / Offline Pill -->
                    <div
                        class="px-2.5 py-1.5 rounded-full text-xs font-bold border flex items-center gap-1.5"
                        :class="
                            store.isOnline
                                ? 'bg-emerald-50 border-emerald-300 text-emerald-900'
                                : 'bg-amber-50 border-amber-400 text-amber-900'
                        "
                        :title="store.isOnline ? 'Terhubung ke server' : 'Sedang offline — transaksi tetap bisa dilakukan'"
                    >
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="store.isOnline ? 'bg-emerald-600' : 'bg-amber-600'"
                        ></span>
                        <span class="hidden sm:inline">{{ store.isOnline ? 'Online' : 'Offline' }}</span>
                    </div>

                    <!-- Pending Offline Sync Button -->
                    <button
                        v-if="store.pendingOutboxCount > 0 || store.needsAttentionOutbox.length > 0"
                        @click="store.syncOutbox()"
                        class="px-3 py-1.5 rounded-xl bg-pos-gold text-xs font-bold flex items-center gap-1.5"
                        title="Sinkronkan transaksi yang tersimpan saat offline"
                    >
                        <span>Sinkronkan ({{ store.pendingOutboxCount }})</span>
                    </button>

                    <!-- User Info -->
                    <div class="hidden xl:block text-right pl-2 border-l border-pos-border">
                        <div class="text-xs font-bold text-pos-black leading-tight">{{ store.user.name }}</div>
                        <div class="text-[11px] text-pos-muted font-semibold">{{ roleLabel(store.user.role) }}</div>
                    </div>

                    <button
                        @click="handleLogout"
                        class="min-h-[38px] px-3 py-1.5 rounded-xl border border-pos-border bg-pos-white text-xs font-bold text-pos-black hover:border-pos-danger hover:text-pos-danger transition-colors"
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
                        'min-h-[36px] px-3.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all',
                        currentScreen === item.id
                            ? 'bg-pos-black text-pos-white'
                            : 'bg-pos-white text-pos-black border border-pos-border',
                    ]"
                >
                    {{ item.label }}
                </button>
            </div>
        </header>

        <!-- Click-outside backdrop for theme menu -->
        <div
            v-if="showThemeMenu"
            @click="showThemeMenu = false"
            class="fixed inset-0 z-20"
        ></div>

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
import { AVAILABLE_THEMES } from './utils/format';
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
const showThemeMenu = ref(false);

const themes = AVAILABLE_THEMES;

const activeThemeObj = computed(() => {
    return themes.find((t) => t.id === store.theme) || themes[0];
});

function selectTheme(themeId) {
    store.setTheme(themeId);
    showThemeMenu.value = false;
}

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
