<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import { portalBase } from '@/lib/portal';
import type { PortalInvoiceData, PortalShell, StatusTone } from '@/types';

const props = defineProps<{
    shell: PortalShell;
    invoice: PortalInvoiceData;
    accounts: { id: number; type_label: string; bank_name: string | null; account_number: string | null; account_name: string | null; instructions: string | null }[];
    proofs: { id: number; amount: number; status_label: string; status_tone: StatusTone; reject_reason: string | null; created_at: string }[];
    can_pay: boolean;
}>();

const base = portalBase();

const copied = ref<number | null>(null);
async function copy(account: { id: number; account_number: string | null }) {
    if (!account.account_number) return;
    try {
        await navigator.clipboard.writeText(account.account_number);
        copied.value = account.id;
        setTimeout(() => (copied.value = null), 2500);
    } catch {
        // Peramban menolak akses papan klip: nomor tetap terlihat dan bisa ditekan lama untuk disalin.
    }
}

const form = useForm({
    amount: props.invoice.outstanding as number | '',
    paid_on: '',
    sender_note: '',
    file: null as File | null,
});

function onFile(event: Event) {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit() {
    form.post(`${base}/tagihan/${props.invoice.id}/bukti`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('file', 'sender_note', 'paid_on'),
    });
}
</script>

<template>
    <Head :title="`Tagihan ${invoice.number}`" />
    <PortalLayout :shell="shell" active="tagihan">
        <Link :href="base" class="mb-space-md inline-flex min-h-11 items-center gap-space-xs text-title-md text-primary-container">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span>
            Kembali
        </Link>

        <section class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
            <div class="flex items-start justify-between gap-space-sm">
                <div>
                    <p class="text-label-md uppercase text-text-secondary">No. {{ invoice.number }}</p>
                    <p class="text-title-md text-text-primary">Jatuh tempo {{ formatDate(invoice.due_date) }}</p>
                </div>
                <StatusBadge :tone="invoice.status_tone" :label="invoice.status_label" />
            </div>
            <ul class="mt-space-sm divide-y divide-border-subtle text-body-md">
                <li v-for="(item, index) in invoice.items" :key="index" class="flex justify-between gap-space-sm py-space-xs">
                    <span class="text-text-secondary">{{ item.description }}</span>
                    <span class="shrink-0">{{ formatRupiah(item.amount) }}</span>
                </li>
            </ul>
            <div class="mt-space-sm flex justify-between border-t border-border-subtle pt-space-sm text-title-md">
                <span>Sisa tagihan</span>
                <span>{{ formatRupiah(invoice.outstanding) }}</span>
            </div>
        </section>

        <section v-if="can_pay" class="mt-space-md rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low" aria-label="Cara pembayaran">
            <h2 class="text-title-lg text-text-primary">Metode pembayaran</h2>
            <p v-if="accounts.length === 0" class="mt-space-sm text-body-md text-text-secondary">Pengelola belum menambahkan rekening. Silakan hubungi pengelola.</p>
            <ul v-else class="mt-space-sm grid gap-space-sm">
                <li v-for="account in accounts" :key="account.id" class="rounded-md border border-border-subtle p-space-sm">
                    <p class="text-label-md uppercase text-text-secondary">{{ account.bank_name ?? account.type_label }}</p>
                    <div v-if="account.account_number" class="flex items-center justify-between gap-space-sm">
                        <p class="break-all text-title-lg text-text-primary">{{ account.account_number }}</p>
                        <button type="button" class="flex min-h-11 shrink-0 items-center gap-1 rounded-md border border-primary-container px-space-sm text-title-md text-primary-container" @click="copy(account)">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">content_copy</span>
                            {{ copied === account.id ? 'Tersalin' : 'Salin' }}
                        </button>
                    </div>
                    <p v-if="account.account_name" class="text-body-sm text-text-secondary">a.n. {{ account.account_name }}</p>
                    <p v-if="account.instructions" class="mt-space-xs whitespace-pre-line text-body-sm text-text-secondary">{{ account.instructions }}</p>
                </li>
            </ul>
            <p class="mt-space-sm text-body-sm text-text-secondary">Kostra tidak menerima pembayaran. Transfer langsung ke pemilik kos, lalu kirim buktinya di bawah.</p>
        </section>

        <form v-if="can_pay" class="mt-space-md grid gap-space-md rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low" @submit.prevent="submit">
            <h2 class="text-title-lg text-text-primary">Kirim bukti transfer</h2>
            <TextField v-model="form.amount" label="Jumlah transfer (Rp)" type="number" :error="form.errors.amount" required />
            <TextField v-model="form.paid_on" label="Tanggal transfer (opsional)" type="date" :error="form.errors.paid_on" />
            <TextField v-model="form.sender_note" label="Catatan (opsional)" :error="form.errors.sender_note" />
            <div class="flex flex-col gap-space-xs">
                <label for="proof-file" class="text-label-md text-text-primary">Foto atau berkas bukti</label>
                <input id="proof-file" type="file" accept="image/jpeg,image/png,application/pdf" required class="text-body-md text-text-primary" @change="onFile" />
                <p v-if="form.errors.file" class="text-body-sm text-error">{{ form.errors.file }}</p>
                <p v-else class="text-body-sm text-text-secondary">JPG, PNG, atau PDF, maksimal 5 MB.</p>
            </div>
            <Button type="submit" icon="send" :disabled="form.processing">Kirim bukti pembayaran</Button>
        </form>

        <section v-if="proofs.length" class="mt-space-md rounded-lg border border-border-subtle bg-bg-surface p-space-md" aria-label="Bukti yang dikirim">
            <h2 class="text-title-lg text-text-primary">Bukti yang sudah dikirim</h2>
            <ul class="mt-space-sm divide-y divide-border-subtle">
                <li v-for="proof in proofs" :key="proof.id" class="py-space-sm">
                    <div class="flex items-center justify-between gap-space-sm">
                        <span class="text-title-md">{{ formatRupiah(proof.amount) }}</span>
                        <StatusBadge :tone="proof.status_tone" :label="proof.status_label" />
                    </div>
                    <p v-if="proof.reject_reason" class="text-body-sm text-status-jatuh-tempo-text">Alasan ditolak: {{ proof.reject_reason }}</p>
                </li>
            </ul>
        </section>
    </PortalLayout>
</template>
