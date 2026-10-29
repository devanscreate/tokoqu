<template>
    <div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-pos-border pb-4">
            <div>
                <h1 class="text-xl font-bold text-pos-black">Buka/Tutup Shift & Laci Uang Tunai</h1>
                <p class="text-xs text-pos-muted mt-0.5">
                    Catat uang modal awal saat mulai jaga kasir, uang kas masuk/keluar, dan hitung total uang fisik saat menutup shift.
                </p>
            </div>
            <button @click="loadData" class="btn-outline text-xs self-start sm:self-auto">Perbarui Data</button>
        </div>

        <div v-if="errorMsg" class="p-3.5 rounded-xl bg-red-50 border border-pos-danger text-xs text-pos-danger font-semibold">
            {{ errorMsg }}
        </div>
        <div v-if="successMsg" class="p-3.5 rounded-xl bg-pos-gold-tint border border-pos-gold text-xs text-pos-black font-semibold">
            {{ successMsg }}
        </div>

        <!-- OPEN NEW SHIFT CARD -->
        <div v-if="!store.currentShift" class="card-pos p-6">
            <div class="border-b border-pos-border pb-3 mb-5">
                <h2 class="text-base font-bold text-pos-black">Mulai Shift Kasir Baru</h2>
                <p class="text-xs text-pos-muted mt-0.5">
                    Pilih meja kasir (register) yang Anda gunakan hari ini dan masukkan jumlah uang kembalian (modal awal) yang ada di dalam laci.
                </p>
            </div>

            <form @submit.prevent="handleOpenShift" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1.5">
                        Pilih Meja Kasir (Register)
                    </label>
                    <select v-model="openForm.register_id" required class="input-pos">
                        <option value="" disabled>-- Pilih Meja Kasir --</option>
                        <option
                            v-for="reg in registers"
                            :key="reg.id"
                            :value="reg.id"
                            :disabled="Boolean(reg.open_shift)"
                        >
                            {{ reg.code }} — {{ reg.name }}
                            {{ reg.open_shift ? `(Sedang dipakai ${reg.open_shift.cashier?.name})` : '(Tersedia)' }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-pos-black mb-1.5">
                        Uang Modal Awal di Laci (Rp)
                    </label>
                    <input
                        v-model.number="openForm.opening_cash"
                        type="number"
                        min="0"
                        step="1000"
                        required
                        class="input-pos font-mono font-bold tabular-nums"
                    />
                </div>

                <button type="submit" :disabled="loading || !store.isOnline" class="btn-gold">
                    {{ loading ? 'Membuka Shift...' : 'Buka Laci Kasir & Mulai Jualan' }}
                </button>
            </form>
        </div>

        <!-- ACTIVE SHIFT PANELS -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Cash In / Cash Out -->
            <div class="card-pos p-6 space-y-4">
                <div class="border-b border-pos-border pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-pos-black">
                            Catat Kas Masuk / Kas Keluar Laci
                        </h2>
                        <p class="text-xs text-pos-muted mt-0.5">
                            Digunakan bila ada penambahan uang receh ke laci atau pengambilan uang untuk keperluan operasional toko.
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold shrink-0">
                        Shift Aktif
                    </span>
                </div>

                <form @submit.prevent="handleCashMovement" class="space-y-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-pos-black mb-1">Jenis Catatan</label>
                            <select v-model="cashForm.type" class="input-pos">
                                <option value="in">Uang Masuk ke Laci (Kas Masuk)</option>
                                <option value="out">Ambil Uang dari Laci (Kas Keluar)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-pos-black mb-1">Jumlah Uang (Rp)</label>
                            <input
                                v-model.number="cashForm.amount"
                                type="number"
                                min="1"
                                step="1000"
                                required
                                class="input-pos font-mono font-bold tabular-nums"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">Keterangan / Keperluan</label>
                        <input
                            v-model="cashForm.reason"
                            type="text"
                            required
                            minlength="3"
                            placeholder="Contoh: Tambah uang receh kembalian / beli air galon toko"
                            class="input-pos"
                        />
                    </div>
                    <button type="submit" :disabled="loading" class="btn-primary w-full">
                        Simpan Catatan Kas
                    </button>
                </form>
            </div>

            <!-- Close Shift -->
            <div class="card-pos p-6 space-y-4">
                <div class="border-b border-pos-border pb-3">
                    <h2 class="text-base font-bold text-pos-black">
                        Akhiri & Tutup Shift Kasir
                    </h2>
                    <p class="text-xs text-pos-muted mt-0.5">
                        Hitung seluruh uang tunai fisik yang ada di dalam laci saat ini, lalu masukkan jumlahnya di bawah untuk mencetak laporan ringkasan shift.
                    </p>
                </div>

                <div
                    v-if="store.pendingOutboxCount > 0 || store.heldCarts.length > 0"
                    class="p-3.5 rounded-xl bg-red-50 border border-pos-danger text-xs text-pos-danger space-y-1"
                >
                    <div class="font-bold">Belum Bisa Menutup Shift:</div>
                    <div v-if="store.pendingOutboxCount > 0">
                        • Masih ada {{ store.pendingOutboxCount }} transaksi offline yang belum tersinkronisasi.
                    </div>
                    <div v-if="store.heldCarts.length > 0">
                        • Masih ada {{ store.heldCarts.length }} pesanan pelanggan yang disimpan sementara. Selesaikan atau hapus terlebih dahulu.
                    </div>
                </div>

                <form @submit.prevent="handleCloseShift" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-pos-black mb-1">
                            Total Uang Tunai Fisik di Laci Saat Ini (Rp)
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
                        <label class="block text-xs font-semibold text-pos-black mb-1">
                            Catatan Penutupan Shift (Wajib diisi jika jumlah uang selisih)
                        </label>
                        <input
                            v-model="closeForm.close_note"
                            type="text"
                            placeholder="Contoh: Uang pas sesuai hitungan laci"
                            class="input-pos"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="loading || store.pendingOutboxCount > 0 || store.heldCarts.length > 0"
                        class="btn-gold w-full"
                    >
                        Tutup Shift & Cetak Laporan Shift
                    </button>
                </form>
            </div>
        </div>

        <!-- SHIFT HISTORY -->
        <div class="card-pos overflow-hidden">
            <div class="px-5 py-4 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold">Riwayat Shift Kasir</h2>
                    <p class="text-[11px] text-pos-stone">Klik "Lihat Laporan" untuk mencetak ulang ringkasan kas pada shift tersebut.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-pos-stone border-b border-pos-border text-[11px] font-semibold text-pos-muted">
                            <th class="p-3">Meja Kasir</th>
                            <th class="p-3">Nama Kasir</th>
                            <th class="p-3">Waktu Buka & Tutup</th>
                            <th class="p-3 text-right">Modal Awal</th>
                            <th class="p-3 text-right">Hitungan Sistem</th>
                            <th class="p-3 text-right">Uang Fisik Laci</th>
                            <th class="p-3 text-right">Selisih</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pos-border tabular-nums">
                        <tr v-for="s in shiftsHistory" :key="s.id" class="hover:bg-pos-alabaster">
                            <td class="p-3 font-mono font-bold">{{ s.register?.code }}</td>
                            <td class="p-3 font-medium">{{ s.cashier?.name }}</td>
                            <td class="p-3 text-[11px]">
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
                                :class="s.difference < 0 ? 'text-pos-danger' : s.difference > 0 ? 'text-pos-warning' : 'text-pos-success'"
                            >
                                {{ s.difference !== null ? formatRupiah(s.difference) : '—' }}
                            </td>
                            <td class="p-3">
                                <span
                                    :class="[
                                        'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase',
                                        s.status === 'open'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-pos-stone text-pos-black border border-pos-border',
                                    ]"
                                >
                                    {{ s.status === 'open' ? 'Aktif' : 'Selesai' }}
                                </span>
                                <span
                                    v-if="s.late_adjusted"
                                    class="ml-1 px-2 py-0.5 rounded-full text-[10px] bg-amber-100 text-amber-900 border border-amber-300 font-medium"
                                >
                                    Ada Susulan Offline
                                </span>
                            </td>
                            <td class="p-3 text-right space-x-2 whitespace-nowrap">
                                <button
                                    @click="viewZReport(s.id)"
                                    class="px-3 py-1.5 rounded-xl border border-pos-border bg-pos-white hover:border-pos-gold text-xs font-medium"
                                >
                                    Lihat Laporan
                                </button>
                                <button
                                    v-if="s.status === 'open' && isManagerOrOwner && s.cashier_id !== store.user?.id"
                                    @click="promptForceClose(s)"
                                    class="px-3 py-1.5 rounded-xl bg-pos-danger text-white text-xs font-medium"
                                >
                                    Tutup Paksa
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
        successMsg.value = 'Catatan kas berhasil disimpan ke dalam shift aktif.';
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
        successMsg.value = 'Shift berhasil ditutup. Ringkasan laporan shift siap dicetak.';
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
    const countedStr = window.prompt(
        `Masukkan jumlah uang tunai fisik di laci meja kasir ${shift.register?.code} (Rp):`,
        '0'
    );
    if (countedStr === null) return;
    const reason = window.prompt('Masukkan alasan penutupan paksa shift ini:', 'Kasir selesai bertugas tanpa menutup shift');
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
