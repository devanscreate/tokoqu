<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="bg-pos-white border border-pos-border rounded-3xl overflow-hidden shadow-2xl w-full max-w-md">
            <div class="px-6 py-4 bg-pos-black text-pos-white border-b border-pos-gold flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-pos-gold"></span>
                    <h3 class="font-semibold text-sm">Persetujuan PIN Manajer</h3>
                </div>
                <button @click="$emit('close')" class="text-xs text-pos-white hover:text-pos-gold">Tutup [ESC]</button>
            </div>

            <form @submit.prevent="submitPin" class="p-6 space-y-4">
                <div class="p-3.5 rounded-xl bg-pos-gold-tint border border-pos-gold text-xs text-pos-black leading-relaxed">
                    Tindakan <strong>{{ actionLabel }}</strong> memerlukan izin Manajer atau Pemilik Toko dengan memasukkan PIN di bawah ini.
                </div>

                <div v-if="errorMsg" class="p-3 bg-red-50 border border-pos-danger text-xs text-pos-danger font-medium">
                    {{ errorMsg }}
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-pos-muted mb-1">
                        Alasan Otorisasi
                    </label>
                    <input
                        v-model="reason"
                        type="text"
                        required
                        minlength="3"
                        placeholder="Contoh: Diskon pelanggan tetap / salah input item"
                        class="input-pos"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-pos-muted mb-1">
                        PIN Manager / Owner
                    </label>
                    <input
                        ref="pinInput"
                        v-model="pin"
                        type="password"
                        inputmode="numeric"
                        required
                        minlength="4"
                        maxlength="12"
                        placeholder="Masukkan 6 digit PIN (Default demo: 123456)"
                        class="input-pos font-mono tracking-widest text-center text-lg"
                    />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="$emit('close')" class="btn-outline">
                        Batal
                    </button>
                    <button type="submit" :disabled="loading" class="btn-gold">
                        {{ loading ? 'Memverifikasi...' : 'Setujui dengan PIN' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch, nextTick } from 'vue';
import { apiRequest } from '../utils/api';

const props = defineProps({
    open: { type: Boolean, default: false },
    action: { type: String, default: 'discount' }, // discount | void | return
    defaultReason: { type: String, default: '' },
});

const emit = defineEmits(['close', 'approved']);

const pin = ref('');
const reason = ref('');
const loading = ref(false);
const errorMsg = ref('');
const pinInput = ref(null);

const actionLabel = computed(() => {
    if (props.action === 'void') return 'Void Transaksi';
    if (props.action === 'return') return 'Retur & Refund';
    return 'Diskon di Atas Batas Kasir';
});

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            pin.value = '';
            reason.value = props.defaultReason || '';
            errorMsg.value = '';
            await nextTick();
            pinInput.value?.focus();
        }
    }
);

async function submitPin() {
    errorMsg.value = '';
    loading.value = true;
    try {
        const res = await apiRequest('/approvals', {
            method: 'POST',
            body: {
                pin: pin.value,
                action: props.action,
                reason: reason.value,
            },
        });
        emit('approved', {
            approval_token: res.approval_token,
            manager_name: res.manager_name,
            reason: reason.value,
        });
    } catch (err) {
        errorMsg.value = err.message || 'Gagal memverifikasi PIN Manager.';
    } finally {
        loading.value = false;
    }
}
</script>

