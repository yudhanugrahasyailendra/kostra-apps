<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { PaginationData, PaymentAccountData, PaymentMethodOption, PaymentProofData } from '@/types';

type StatusTab = 'pending' | 'approved' | 'rejected' | 'all';

const props = defineProps<{
    proofs: PaymentProofData[];
    pagination: PaginationData;
    filters: { status: StatusTab };
    counts: Record<StatusTab, number>;
    accounts: PaymentAccountData[];
    methods: PaymentMethodOption[];
    default_account_id: number | null;
}>();

const tabs: { key: StatusTab; label: string }[] = [
    { key: 'pending', label: 'Menunggu' },
    { key: 'approved', label: 'Disetujui' },
    { key: 'rejected', label: 'Ditolak' },
    { key: 'all', label: 'Semua' },
];

function go(status: StatusTab) {
    router.get('/app/bukti-transfer', { status }, { preserveState: true, preserveScroll: true });
}

const accountOptions = computed(() => [{ value: '', label: 'Tanpa rekening' }, ...props.accounts.map((a) => ({ value: a.id, label: a.label }))]);

const approveTarget = ref<PaymentProofData | null>(null);
const approveForm = useForm({ amount: '' as number | string, method: 'transfer', payment_account_id: '' as number | string, paid_on: '' });
const approveError = computed(() => {
    const errors = approveForm.errors as Record<string, string>;
    return errors.allocations ?? errors.proof ?? errors.amount;
});

function openApprove(proof: PaymentProofData) {
    approveTarget.value = proof;
    approveForm.reset();
    approveForm.clearErrors();
    approveForm.amount = proof.amount;
    approveForm.paid_on = proof.paid_on ?? '';
    approveForm.payment_account_id = props.default_account_id ?? '';
}

function submitApprove() {
    if (!approveTarget.value) return;
    approveForm
        .transform((data) => ({
            amount: data.amount === '' ? null : data.amount,
            method: data.method,
            payment_account_id: data.payment_account_id === '' ? null : data.payment_account_id,
            paid_on: data.paid_on === '' ? null : data.paid_on,
        }))
        .post(`/app/bukti-transfer/${approveTarget.value.id}/setujui`, {
            preserveScroll: true,
            onSuccess: () => (approveTarget.value = null),
        });
}

const rejectTarget = ref<PaymentProofData | null>(null);
const rejectForm = useForm({ reason: '' });
const rejectError = computed(() => (rejectForm.errors as Record<string, string>).proof);

function submitReject() {
    if (!rejectTarget.value) return;
    rejectForm.post(`/app/bukti-transfer/${rejectTarget.value.id}/tolak`, {
        preserveScroll: true,
        onSuccess: () => {
            rejectTarget.value = null;
            rejectForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Bukti transfer" />
    <OwnerLayout title="Bukti transfer" active="bukti">
        <p class="mb-space-lg max-w-2xl text-body-md text-text-secondary">
            Cocokkan bukti dengan mutasi rekening Anda sebelum menyetujui. Persetujuan mencatat pembayaran dan menutup tagihan; penolakan wajib beralasan. Bukti yang menunggu lebih dari 3 hari ditandai.
        </p>

        <div class="mb-space-md flex flex-wrap rounded-md bg-bg-subtle p-space-xs" role="group" aria-label="Status bukti">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-md px-space-md py-space-xs text-title-md"
                :class="tab.key === filters.status ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary hover:text-text-primary'"
                :aria-pressed="tab.key === filters.status"
                @click="go(tab.key)"
            >
                {{ tab.label }} ({{ counts[tab.key] }})
            </button>
        </div>

        <p v-if="proofs.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            <template v-if="filters.status === 'pending'">Tidak ada bukti yang menunggu verifikasi.</template>
            <template v-else>Tidak ada bukti pada status ini.</template>
        </p>

        <ul v-else class="flex flex-col gap-space-md">
            <li v-for="proof in proofs" :key="proof.id" class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <div class="flex flex-wrap items-start justify-between gap-space-md">
                    <div class="min-w-0">
                        <p class="text-title-lg text-text-primary">{{ proof.resident_name }} <span class="text-body-md text-text-secondary">&middot; Kamar {{ proof.room_number }}</span></p>
                        <p class="text-body-sm text-text-secondary">
                            Tagihan
                            <Link :href="`/app/tagihan/${proof.invoice_id}`" class="text-primary-container hover:underline">{{ proof.invoice_number }}</Link>
                            &middot; sisa {{ formatRupiah(proof.invoice_outstanding ?? 0) }}
                        </p>
                        <p class="mt-space-xs text-headline-sm text-text-primary">{{ formatRupiah(proof.amount) }}</p>
                        <p class="text-body-sm text-text-secondary">
                            Transfer {{ formatDate(proof.paid_on, 'short') }} &middot; diunggah {{ formatDate(proof.created_at, 'short') }}
                        </p>
                        <p v-if="proof.sender_note" class="text-body-sm text-text-secondary">Catatan: {{ proof.sender_note }}</p>
                        <p v-if="proof.reject_reason" class="text-body-sm text-status-jatuh-tempo-text">Ditolak: {{ proof.reject_reason }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-space-xs">
                        <StatusBadge :tone="proof.status_tone" :label="proof.status_label" />
                        <p v-if="proof.is_stale" class="text-body-sm text-status-menunggu-text">Menunggu {{ proof.age_days }} hari. Segera tindak.</p>
                        <a
                            v-if="proof.file"
                            :href="proof.file.url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-space-xs rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle"
                        >
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">attach_file</span>
                            Buka berkas bukti
                        </a>
                        <Link v-if="proof.payment_id" :href="`/app/pembayaran/${proof.payment_id}/kuitansi`" class="rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle">Lihat kuitansi</Link>
                    </div>
                </div>
                <div v-if="proof.can.review" class="mt-space-md flex flex-wrap justify-end gap-space-sm border-t border-border-subtle pt-space-md">
                    <Button variant="secondary" icon="close" @click="rejectTarget = proof">Tolak</Button>
                    <Button icon="check" @click="openApprove(proof)">Setujui</Button>
                </div>
            </li>
        </ul>

        <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between text-body-sm text-text-secondary" aria-label="Halaman">
            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} bukti)</span>
            <div class="flex gap-space-xs">
                <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
            </div>
        </nav>

        <Modal :show="approveTarget !== null" title="Setujui bukti transfer" @close="approveTarget = null">
            <form class="flex flex-col gap-space-md" @submit.prevent="submitApprove">
                <p v-if="approveError" class="rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">{{ approveError }}</p>
                <p class="text-body-md text-text-secondary">Pembayaran dicatat untuk tagihan <strong class="text-text-primary">{{ approveTarget?.invoice_number }}</strong> dan kuitansi terbit otomatis.</p>
                <TextField v-model="approveForm.amount" label="Jumlah diterima (Rp)" type="number" :error="approveForm.errors.amount" hint="Ubah bila mutasi rekening berbeda dari pengakuan penghuni." required />
                <TextField v-model="approveForm.paid_on" label="Tanggal transfer" type="date" :error="approveForm.errors.paid_on" />
                <SelectField v-model="approveForm.method" label="Metode" :options="methods" :error="approveForm.errors.method" required />
                <SelectField v-model="approveForm.payment_account_id" label="Rekening tujuan" :options="accountOptions" :error="approveForm.errors.payment_account_id" />
            </form>
            <template #footer>
                <Button variant="ghost" @click="approveTarget = null">Batal</Button>
                <Button icon="check" :disabled="approveForm.processing" @click="submitApprove">Setujui dan catat</Button>
            </template>
        </Modal>

        <Modal :show="rejectTarget !== null" title="Tolak bukti transfer" @close="rejectTarget = null">
            <form class="flex flex-col gap-space-md" @submit.prevent="submitReject">
                <p v-if="rejectError" class="rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">{{ rejectError }}</p>
                <p class="text-body-md text-text-secondary">Tidak ada pembayaran yang dicatat dan tagihan tidak berubah.</p>
                <TextField v-model="rejectForm.reason" label="Alasan penolakan" :error="rejectForm.errors.reason" hint="Wajib; mis. nominal tidak sesuai mutasi." required />
            </form>
            <template #footer>
                <Button variant="ghost" @click="rejectTarget = null">Kembali</Button>
                <Button icon="close" :disabled="rejectForm.processing" @click="submitReject">Tolak bukti</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
