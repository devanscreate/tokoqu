<template>
    <div class="min-h-screen flex flex-col bg-pos-alabaster text-pos-black">
        <!-- LOGIN SCREEN (W2) OR SESSION EXPIRED RE-AUTH MODAL -->
        <div
            v-if="!store.user || store.sessionExpired"
            class="fixed inset-0 z-50 flex items-center justify-center bg-pos-black/90 p-4"
        >
            <div class="bg-pos-white border-2 border-pos-gold w-full max-w-md">
                <div class="px-6 py-5 bg-pos-black text-pos-white border-b border-pos-gold">
                    <div class="text-xs font-mono uppercase tracking-widest text-pos-gold">Point of Sale System</div>
                    <h1 class="text-xl font-bold tracking-wider uppercase mt-0.5">
                        {{ store.settings.store_name || 'TOKOQU RETAIL' }}
                    </h1>
                </div>

                <form @submit.prevent="submitLogin" class="p-6 space-y-4">
                    <div v-if="store.sessionExpired" class="p-3 bg-pos-gold-tint border border-pos-gold text-xs text-pos-black">
                        Sesi Anda telah kedaluwarsa. Silakan masuk kembali—keranjang belanja dan antrean transaksi offline Anda tetap aman tersimpan (W2 / W13.6).
                    </div>

                    <div v-if="loginError" class="p-3 bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
                        {{ loginError }}
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-pos-muted mb-1">
                            Alamat Email
                        </label>
                        <input
                            v-model="loginForm.email"
                            type="email"
                            required
                            placeholder="kasir@tokoqu.id"
                            class="input-pos"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-pos-muted mb-1">
                            Kata Sandi
                        </label>
                        <input
                            v-model="loginForm.password"
                            type="password"
                            required
                            placeholder="••••••••"
                            class="input-pos"
                        />
                    </div>

                    <button type="submit" :disabled="loggingIn" class="btn-gold w-full">
                        {{ loggingIn ? 'Memverifikasi...' : 'Masuk ke Kasir' }}
                    </button>

                    <!-- Quick Demo Credentials Hint -->
                    <div class="pt-3 border-t border-pos-border text-[11px] text-pos-muted space-y-1 font-mono">
                        <div class="font-sans font-semibold text-pos-black">Akun Seeder Standar (Password: password):</div>
                        <div class="flex justify-between">
                            <button type="button" @click="fillDemo('kasir@tokoqu.id')" class="hover:text-pos-gold underline">
                                kasir@tokoqu.id (Kasir)
                            </button>
                            <button type="button" @click="fillDemo('manager@tokoqu.id')" class="hover:text-pos-gold underline">
                                manager@tokoqu.id (PIN: 123456)
                            </button>
                        </div>
                        <div>
                            <button type="button" @click="fillDemo('owner@tokoqu.id')" class="hover:text-pos-gold underline">
                                owner@tokoqu.id (Owner - PIN: 111111)
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- TOP STATUS & NAVIGATION BAR (Section 9) -->
        <header v-if="store.user" class="h-14 bg-pos-black text-pos-white border-b-2 border-pos-gold px-4 flex items-center justify-between gap-3">
            <!-- Brand & Store Info -->
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-pos-gold inline-block"></span>
                    <span class="font-bold text-sm uppercase tracking-wider truncate">
                        {{ store.settings.store_name || 'TOKOQU POS' }}
                    </span>
                </div>

                <!-- Register & Shift Badge -->
                <button
                    @click="currentScreen = 'shift'"
                    class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-mono border"
                    :class="
                        store.currentShift
                            ? 'bg-pos-charcoal border-pos-gold text-pos-gold'
                            : 'bg-pos-charcoal border-pos-danger text-red-300'
                    "
                >
                    <span>{{ store.currentShift ? `SHIFT: ${store.currentShift.register?.code}` : 'SHIFT BELUM BUKA' }}</span>
                </button>
            </div>

            <!-- Navigation Tabs (Role-Gated per Section 2) -->
            <nav class="flex items-center gap-1 overflow-x-auto">
                <button
                    v-for="item in visibleNavItems"
                    :key="item.id"
                    @click="currentScreen = item.id"
                    :class="[
                        'min-h-[38px] px-3 py-1.5 text-xs font-semibold uppercase tracking-wider transition-colors whitespace-nowrap',
                        currentScreen === item.id
                            ? 'bg-pos-gold text-pos-white'
                            : 'text-pos-stone hover:text-pos-gold',
                    ]"
                >
                    {{ item.label }}
                </button>
            </nav>

            <!-- Connectivity, Pending Outbox Sync & User Info -->
            <div class="flex items-center gap-2.5">
                <!-- Online/Offline Indicator -->
                <div
                    class="px-2 py-1 text-[11px] font-mono uppercase border flex items-center gap-1.5"
                    :class="
                        store.isOnline
                            ? 'border-pos-border text-pos-stone'
                            : 'border-pos-gold bg-pos-gold text-pos-black font-bold'
                    "
                >
                    <span
                        class="w-2 h-2 rounded-full"
                        :class="store.isOnline ? 'bg-emerald-400' : 'bg-pos-black'"
                    ></span>
                    <span>{{ store.isOnline ? 'ONLINE' : 'OFFLINE' }}</span>
                </div>

                <!-- Outbox Pending Counter (W13) -->
                <button
                    v-if="store.pendingOutboxCount > 0 || store.needsAttentionOutbox.length > 0"
                    @click="store.syncOutbox()"
                    class="px-2.5 py-1 bg-pos-gold text-pos-black font-mono text-xs font-bold uppercase"
                    title="Klik untuk sinkronisasi penjualan offline"
                >
                    Sync: {{ store.pendingOutboxCount }}
                    <span v-if="store.needsAttentionOutbox.length > 0" class="text-pos-danger">
                        (!{{ store.needsAttentionOutbox.length }})
                    </span>
                </button>

                <!-- Cashier Name & Logout -->
                <div class="hidden md:block text-right text-xs leading-tight">
                    <div class="font-semibold text-pos-white">{{ store.user.name }}</div>
                    <div class="text-[10px] font-mono uppercase text-pos-gold">{{ store.user.role }}</div>
                </div>

                <button
                    @click="handleLogout"
                    class="min-h-[36px] px-2.5 py-1 border border-pos-border text-xs text-pos-stone hover:border-pos-gold hover:text-pos-gold"
                >
                    Keluar
                </button>
            </div>
        </header>

        <!-- ACTIVE VIEW -->
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
        { id: 'pos', label: 'Kasir (POS)', roles: ['cashier', 'manager', 'owner'] },
        { id: 'shift', label: 'Shift & Kas', roles: ['cashier', 'manager', 'owner'] },
        { id: 'history', label: 'Nota, Void & Retur', roles: ['cashier', 'manager', 'owner'] },
        { id: 'inventory', label: 'Produk & Stok', roles: ['manager', 'owner'] },
        { id: 'reports', label: 'Laporan & Audit', roles: ['manager', 'owner'] },
        { id: 'system', label: 'Pengaturan (Owner)', roles: ['owner'] },
    ];
    return items.filter((i) => i.roles.includes(role));
});

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

