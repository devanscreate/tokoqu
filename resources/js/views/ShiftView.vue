<template>
    <div class="max-w-5xl mx-auto p-6 space-y-6">
        <div class="flex items-center justify-between border-b border-pos-border pb-4">
            <div>
                <h1 class="text-xl font-bold uppercase tracking-wider text-pos-black">Manajemen Shift & Laci Kas</h1>
                <p class="text-xs text-pos-muted mt-0.5">
                    Buka/tutup shift kasir, catat kas masuk/keluar, dan rekonsiliasi laci uang tunai (W3, W8, W9, W12).
                </p>
            </div>
            <button @click="loadData" class="btn-outline text-xs">Segarkan Data</button>
        </div>

        <div v-if="errorMsg" class="p-3.5 bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>
        <div v-if="successMsg" class="p-3.5 bg-pos-gold-tint border border-pos-gold text-xs text-pos-black font-semibold">
            {{ successMsg }}
        </div>

        <!-- CURRENT SHIFT STATUS OR OPEN SHIFT FORM (W3) -->
        <div v-if="!store.currentShift" class="card-pos p-6">
            <div class="border-b border-pos-border pb-3 mb-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-pos-black">Buka Shift Baru (W3)</h2>
                <p class="text-xs text-pos-muted">
                    Pilih register yang tersedia dan masukkan jumlah modal uang tunai awal di laci.
                </p>
            </div>

            <form @submit.prevent="handleOpenShift" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-pos-muted mb-1">
                        Pilih Register
                    </label>
                    <select v-model="openForm.register_id" required class="input-pos">
                        <option value="" disabled>-- Pilih Register --</option>
                        <option
                            v-for="reg in registers"
                            :key="reg.id"
                            :value="reg.id"
                            :disabled="Boolean(reg.open_shift)"
                        >
                            {{ reg.code }} — {{ reg.name }}
                            {{ reg.open_shift ? `(Dibuka oleh ${reg.open_shift.cashier?.name})` : '(Tersedia)' }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-pos-muted mb-1">
                        Modal Kas Awal (Opening Cash Rp)
                    </label>
                    <input
                        v-model.number="openForm.opening_cash"
                        type="number"
                        min="0"
                        step="1000"
                        required
                        class="input-pos font-mono tabular-nums"
                    />
                </div>

                <button type="submit" :disabled="loading || !store.isOnline" class="btn-gold">
                    {{ loading ? 'Membuka Shift...' : 'Buka Shift & Mulai Jualan' }}
                </button>
            </form>
        </div>

        <!-- ACTIVE OPEN SHIFT PANEL (W8 & W9) -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Cash In / Cash Out (W8) -->
            <div class="card-pos p-5 space-y-4">
                <div class="border-b border-pos-border pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-pos-black">
                            Kas Masuk / Kas Keluar (W8)
                        </h2>
                        <p class="text-xs text-pos-muted">
                            Shift Aktif: <strong>{{ store.currentShift.register?.code }}</strong> · Dibuka {{ formatDateTime(store.currentShift.opened_at) }}
                        </p>
                    </div>
                    <span class="px-2.5 py-1 bg-pos-black text-pos-gold text-xs font-mono font-bold uppercase">
                        OPEN
                    </span>
                </div>

                <form @submit.prevent="handleCashMovement" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Jenis Mutasi</label>
                            <select v-model="cashForm.type" class="input-pos">
                                <option value="in">Kas Masuk (Cash In)</option>
                                <option value="out">Kas Keluar (Cash Out)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Nominal (Rp)</label>
                            <input
                                v-model.number="cashForm.amount"
                                type="number"
                                min="1"
                                step="1000"
                                required
                                class="input-pos font-mono tabular-nums"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">Alasan / Keterangan</label>
                        <input
                            v-model="cashForm.reason"
                            type="text"
                            required
                            minlength="3"
                            placeholder="Contoh: Tambah uang kembalian / beli galon toko"
                            class="input-pos"
                        />
                    </div>
                    <button type="submit" :disabled="loading" class="btn-primary w-full">
                        Catat Mutasi Kas
                    </button>
                </form>
            </div>

            <!-- Close Shift Blind Count (W9) -->
            <div class="card-pos p-5 space-y-4">
                <div class="border-b border-pos-border pb-3">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-pos-black">
                        Tutup Shift & Hitung Kas Buta (Blind Count - W9)
                    </h2>
                    <p class="text-xs text-pos-muted">
                        Hitung fisik uang tunai di laci. Kas sistem disembunyikan hingga Anda mengirim hitungan.
                    </p>
                </div>

                <div v-if="store.pendingOutboxCount > 0 || store.heldCarts.length > 0" class="p-3 bg-red-50 border border-pos-danger text-xs text-pos-danger">
                    <strong>Perhatian (W9 Precondition):</strong>
                    <div v-if="store.pendingOutboxCount > 0">• Masih ada {{ store.pendingOutboxCount }} penjualan offline belum disinkronkan.</div>
                    <div v-if="store.heldCarts.length > 0">• Masih ada {{ store.heldCarts.length }} keranjang ditahan (Hold).</div>
                </div>

                <form @submit.prevent="handleCloseShift" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">
                            Total Uang Fisik Dihitung di Laci (Counted Cash Rp)
                        </label>
                        <input
                            v-model.number="closeForm.counted_cash"
                            type="number"
                            min="0"
                            step="500"
                            required
                            class="input-pos font-mono font-bold text-base tabular-nums"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-pos-muted mb-1">
                            Catatan Penutupan Shift (Wajib jika terdapat selisih kas)
                        </label>
                        <input
                            v-model="closeForm.close_note"
                            type="text"
                            placeholder="Catatan rekonsiliasi laci kas..."
                            class="input-pos"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="loading || store.pendingOutboxCount > 0 || store.heldCarts.length > 0"
                        class="btn-gold w-full"
                    >
                        Tutup Shift & Cetak Z-Report
                    </button>
                </form>
            </div>
        </div>

        <!-- SHIFT HISTORY & MANAGER FORCE-CLOSE (W12) -->
        <div class="card-pos">
            <div class="px-5 py-3.5 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider">Riwayat Shift & Laporan Z-Report</h2>
                <span class="text-[11px] font-mono text-pos-gold">Immutable Closed Shifts (R9)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-pos-stone border-b border-pos-border uppercase text-[11px] text-pos-muted">
                            <th class="p-3">Register</th>
                            <th class="p-3">Kasir</th>
                            <th class="p-3">Waktu Buka / Tutup</th>
                            <th class="p-3 text-right">Modal Awal</th>
                            <th class="p-3 text-right">Kas Sistem</th>
                            <th class="p-3 text-right">Kas Fisik</th>
                            <th class="p-3 text-right">Selisih</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pos-border tabular-nums">
                        <tr v-for="s in shiftsHistory" :key="s.id" class="hover:bg-pos-alabaster">
                            <td class="p-3 font-mono font-bold">{{ s.register?.code }}</td>
                            <td class="p-3">{{ s.cashier?.name }}</td>
                            <td class="p-3 font-mono text-[11px]">
                                <div>Buka: {{ formatDateTime(s.opened_at) }}</div>
                                <div v-if="s.closed_at" class="text-pos-muted">Tutup: {{ formatDateTime(s.closed_at) }}</div>
                            </td>
                            <td class="p-3 text-right font-mono">{{ formatRupiah(s.opening_cash) }}</td>
                            <td class="p-3 text-right font-mono">
                                {{ s.expected_cash !== null ? formatRupiah(s.expected_cash) : '—' }}
                            </td>
                            <td class="p-3 text-right font-mono">
                                {{ s.counted_cash !== null ? formatRupiah(s.counted_cash) : '—' }}
                            </td>
                            <td
                                class="p-3 text-right font-mono font-bold"
                                :class="s.difference < 0 ? 'text-pos-danger' : s.difference > 0 ? 'text-pos-warning' : ''"
                            >
                                {{ s.difference !== null ? formatRupiah(s.difference) : '—' }}
                            </td>
                            <td class="p-3">
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-[10px] font-mono font-bold uppercase',
                                        s.status === 'open'
                                            ? 'bg-pos-gold text-white'
                                            : 'bg-pos-stone text-pos-black border border-pos-border',
                                    ]"
                                >
                                    {{ s.status }}
                                </span>
                                <span
                                    v-if="s.late_adjusted"
                                    class="ml-1 px-1.5 py-0.5 text-[10px] bg-amber-100 text-amber-900 border border-amber-400 font-mono"
                                >
                                    LATE_ADJUSTED
                                </span>
                            </td>
                            <td class="p-3 text-right space-x-2">
                                <button
                                    @click="viewZReport(s.id)"
                                    class="px-2.5 py-1 border border-pos-border bg-pos-white hover:border-pos-gold text-xs font-medium"
                                >
                                    Z-Report
                                </button>
                                <button
                                    v-if="s.status === 'open' && isManagerOrOwner && s.cashier_id !== store.user?.id"
                                    @click="promptForceClose(s)"
                                    class="px-2.5 py-1 bg-pos-danger text-white text-xs font-medium"
                                >
                                    Force Close (W12)
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePosStore } from '../stores/posStore';
import { apiRequest } from '../utils/api';
import { formatDateTime, formatRupiah } from '../utils/format';

const emit = defineEmits(['navigate']);
const store = usePosStore();

const loading = ref(false);
const errorMsg = ref('');
const successMsg = ref('');
const registers = ref([]);
const shiftsHistory = ref([]);

const openForm = ref({
    register_id: '',
    opening_cash: 200000,
});

const cashForm = ref({
    type: 'in',
    amount: 50000,
    reason: '',
});

const closeForm = ref({
    counted_cash: 0,
    close_note: '',
});

const isManagerOrOwner = computed(() =>
    ['manager', 'owner'].includes(store.user?.role)
);

async function loadData() {
    errorMsg.value = '';
    try {
        const data = await apiRequest('/shifts');
        shiftsHistory.value = data.shifts || [];
        registers.value = data.registers || [];
        if (!openForm.value.register_id && registers.value.length) {
            const available = registers.value.find((r) => !r.open_shift);
            if (available) openForm.value.register_id = available.id;
        }
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function handleOpenShift() {
    errorMsg.value = '';
    successMsg.value = '';
    loading.value = true;
    try {
        const res = await apiRequest('/shifts/open', {
            method: 'POST',
            body: openForm.value,
        });
        store.currentShift = res.shift;
        localStorage.setItem('pos_current_shift', JSON.stringify(res.shift));
        await store.syncCatalog(true);
        await loadData();
        emit('navigate', 'pos');
    } catch (err) {
        errorMsg.value = err.message;
    } finally {
        loading.value = false;
    }
}

async function handleCashMovement() {
    errorMsg.value = '';
    successMsg.value = '';
    loading.value = true;
    try {
        await apiRequest('/cash-movements', {
            method: 'POST',
            body: {
                shift_id: store.currentShift.id,
                ...cashForm.value,
            },
        });
        cashForm.value.reason = '';
        successMsg.value = 'Mutasi kas berhasil dicatat ke dalam shift aktif (W8).';
        await loadData();
    } catch (err) {
        errorMsg.value = err.message;
    } finally {
        loading.value = false;
    }
}

async function handleCloseShift() {
    errorMsg.value = '';
    successMsg.value = '';
    loading.value = true;
    try {
        const res = await apiRequest(`/shifts/${store.currentShift.id}/close`, {
            method: 'POST',
            body: {
                counted_cash: closeForm.value.counted_cash,
                close_note: closeForm.value.close_note,
                has_unsynced_sales: store.pendingOutboxCount > 0,
                has_held_carts: store.heldCarts.length > 0,
            },
        });
        store.currentShift = null;
        localStorage.removeItem('pos_current_shift');
        store.activeReceipt = {
            type: 'z_report',
            shift: res.shift,
            z_report: res.z_report,
        };
        successMsg.value = 'Shift berhasil ditutup. Laporan Z-Report telah dibuat (W9).';
        await loadData();
    } catch (err) {
        errorMsg.value = err.message;
    } finally {
        loading.value = false;
    }
}

async function viewZReport(shiftId) {
    try {
        const res = await apiRequest(`/shifts/${shiftId}/report`);
        store.activeReceipt = {
            type: 'z_report',
            shift: res.shift,
            z_report: res.z_report,
        };
    } catch (err) {
        errorMsg.value = err.message;
    }
}

async function promptForceClose(shift) {
    const countedStr = window.prompt(`Masukkan jumlah uang tunai fisik di laci register ${shift.register?.code} (Rp):`, '0');
    if (countedStr === null) return;
    const reason = window.prompt('Masukkan alasan Force-Close shift (W12):', 'Kasir pulang tanpa menutup shift');
    if (!reason) return;

    try {
        const res = await apiRequest(`/shifts/${shift.id}/force-close`, {
            method: 'POST',
            body: {
                counted_cash: Number(countedStr),
                reason,
            },
        });
        store.activeReceipt = {
            type: 'z_report',
            shift: res.shift,
            z_report: res.z_report,
        };
        await loadData();
    } catch (err) {
        errorMsg.value = err.message;
    }
}

onMounted(() => {
    loadData();
});
</script>

