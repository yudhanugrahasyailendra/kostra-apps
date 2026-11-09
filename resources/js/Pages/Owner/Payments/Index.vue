<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { PaginationData, PaymentData } from '@/types';

type StatusTab = 'all' | 'active' | 'voided';

const props = defineProps<{
    payments: PaymentData[];
    pagination: PaginationData;
    filters: { q: string | null; status: StatusTab; period: string | null };
    counts: Record<StatusTab, number>;
    summary: { received: number; count: number };
    can: { create: boolean };
}>();

const search = ref(props.filters.q ?? '');
const period = ref(props.filters.period ?? '');

const tabs: { key: StatusTab; label: string }[] = [
    { key: 'all', label: 'Semua' },
    { key: 'active', label: 'Aktif' },
    { key: 'voided', label: 'Dibatalkan' },
];

// Periode kosong berarti "semua periode" dan harus terkirim sebagai `period=`.
function go(status: StatusTab = props.filters.status) {
    const query = new URLSearchParams();
    if (status !== 'all') query.set('status', status);
    if (search.value.trim()) query.set('q', search.value.trim());
    query.set('period', period.value);
    router.get(`/app/pembayaran?${query.toString()}`, {}, { preserveState: true, preserveScroll: true });
}

function clearPeriod() {
    period.value = '';
    go();
}

const periodLabel = computed(() => {
    if (!props.filters.period) return 'Semua periode';
    const [year, month] = props.filters.period.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

const voidTarget = ref<PaymentData | null>(null);
const voidForm = useForm({ reason: '' });
const voidError = computed(() => (voidForm.errors as Record<string, string>).payment);

function submitVoid() {
    if (!voidTarget.value) return;
    voidForm.post(`/app/pembayaran/${voidTarget.value.id}/batal`, {
        preserveScroll: true,
        onSuccess: () => {
            voidTarget.value = null;
            voidForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Pembayaran" />
    <OwnerLayout title="Pembayaran" active="pembayaran">
        <div class="mb-space-lg flex flex-wrap items-start justify-between gap-space-md">
            <p class="max-w-2xl text-body-md text-text-secondary">
                Kostra hanya mencatat uang yang sudah Anda terima di luar sistem. Pembayaran yang salah tidak dihapus, tetapi dibatalkan dengan alasan sehingga riwayatnya tetap ada.
            </p>
            <div class="flex flex-wrap gap-space-sm">
                <Link href="/app/bukti-transfer" class="inline-flex min-h-11 items-center gap-space-xs rounded-md border border-border-strong bg-bg-surface px-space-md py-2.5 text-title-md text-text-primary hover:bg-bg-subtle lg:min-h-10">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">fact_check</span>
                    Antrean bukti transfer
                </Link>
                <Link v-if="can.create" href="/app/pembayaran/baru" class="inline-flex min-h-11 items-center gap-space-xs rounded-md bg-primary-container px-space-md py-2.5 text-title-md text-on-primary shadow-low hover:bg-primary lg:min-h-10">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                    Catat pembayaran
                </Link>
            </div>
        </div>

        <section class="mb-space-lg grid gap-space-md sm:grid-cols-2" aria-label="Ringkasan pembayaran">
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <p class="text-label-md uppercase text-text-secondary">Diterima ({{ periodLabel }})</p>
                <p class="mt-space-xs text-headline-sm text-status-lunas-text">{{ formatRupiah(summary.received) }}</p>
                <p class="text-body-sm text-text-secondary">Pembayaran aktif; yang dibatalkan tidak dihitung.</p>
            </div>
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <p class="text-label-md uppercase text-text-secondary">Jumlah pembayaran aktif</p>
                <p class="mt-space-xs text-headline-sm text-text-primary">{{ summary.count }}</p>
                <p class="text-body-sm text-text-secondary">{{ counts.voided }} dibatalkan pada filter ini.</p>
            </div>
        </section>

        <div class="mb-space-md flex flex-col gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-sm">
            <div class="flex flex-wrap rounded-md bg-bg-subtle p-space-xs" role="group" aria-label="Status pembayaran">
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
            <form class="grid gap-space-sm md:grid-cols-[1fr_auto_auto]" role="search" @submit.prevent="go()">
                <div>
                    <label for="payment-search" class="sr-only">Cari pembayaran</label>
                    <input
                        id="payment-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        placeholder="Cari nomor kuitansi, nama penghuni, atau nomor referensi"
                        class="h-[42px] w-full rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
                    />
                </div>
                <div class="flex gap-space-xs">
                    <label for="payment-period" class="sr-only">Periode</label>
                    <input id="payment-period" v-model="period" type="month" class="h-[42px] w-full rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary" @change="go()" />
                    <button v-if="period" type="button" class="rounded-md px-space-sm text-title-md text-primary-container hover:bg-bg-subtle" @click="clearPeriod">Semua periode</button>
                </div>
                <Button type="submit" variant="secondary" icon="search">Cari</Button>
            </form>
        </div>

        <p v-if="payments.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            Belum ada pembayaran untuk filter ini. Catat pembayaran dari tombol di atas atau dari halaman detail tagihan.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border border-border-subtle bg-bg-surface">
            <table class="w-full min-w-[56rem] text-left text-body-md">
                <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                    <tr>
                        <th scope="col" class="px-space-md py-space-sm">Kuitansi &amp; tanggal</th>
                        <th scope="col" class="px-space-md py-space-sm">Penghuni</th>
                        <th scope="col" class="px-space-md py-space-sm">Tagihan</th>
                        <th scope="col" class="px-space-md py-space-sm">Metode</th>
                        <th scope="col" class="px-space-md py-space-sm text-right">Jumlah</th>
                        <th scope="col" class="px-space-md py-space-sm">Status</th>
                        <th scope="col" class="px-space-md py-space-sm"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle text-text-primary">
                    <tr v-for="payment in payments" :key="payment.id">
                        <td class="px-space-md py-space-sm">
                            <Link :href="`/app/pembayaran/${payment.id}/kuitansi`" class="text-title-md text-primary-container hover:underline">{{ payment.receipt_number }}</Link>
                            <span class="block text-body-sm text-text-secondary">{{ formatDate(payment.paid_on, 'short') }}</span>
                        </td>
                        <td class="px-space-md py-space-sm">{{ payment.resident_name }}</td>
                        <td class="px-space-md py-space-sm text-body-sm">
                            <Link v-for="invoice in payment.invoices" :key="invoice.id" :href="`/app/tagihan/${invoice.id}`" class="block text-primary-container hover:underline">{{ invoice.number }}</Link>
                        </td>
                        <td class="px-space-md py-space-sm text-body-sm">
                            {{ payment.method_label }}
                            <span v-if="payment.account_label" class="block text-text-secondary">{{ payment.account_label }}</span>
                            <span v-if="payment.reference" class="block text-text-secondary">Ref. {{ payment.reference }}</span>
                        </td>
                        <td class="px-space-md py-space-sm text-right text-title-md" :class="payment.status === 'voided' ? 'text-text-muted line-through' : ''">{{ formatRupiah(payment.amount) }}</td>
                        <td class="px-space-md py-space-sm">
                            <StatusBadge :tone="payment.status_tone as never" :label="payment.status_label" />
                            <span v-if="payment.void_reason" class="mt-space-xs block max-w-48 text-body-sm text-text-secondary">{{ payment.void_reason }}</span>
                        </td>
                        <td class="whitespace-nowrap px-space-md py-space-sm text-right">
                            <Link :href="`/app/pembayaran/${payment.id}/kuitansi`" class="rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle">Kuitansi</Link>
                            <button v-if="payment.can.void" type="button" class="rounded-md px-space-sm py-space-xs text-title-md text-status-jatuh-tempo-text hover:bg-bg-subtle" @click="voidTarget = payment">Batalkan</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between text-body-sm text-text-secondary" aria-label="Halaman">
            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} pembayaran)</span>
            <div class="flex gap-space-xs">
                <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
            </div>
        </nav>

        <Modal :show="voidTarget !== null" title="Batalkan pembayaran" @close="voidTarget = null">
            <form class="flex flex-col gap-space-md" @submit.prevent="submitVoid">
                <p v-if="voidError" class="rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">{{ voidError }}</p>
                <p class="text-body-md text-text-secondary">
                    Pembayaran <strong class="text-text-primary">{{ voidTarget?.receipt_number }}</strong> sebesar
                    <strong class="text-text-primary">{{ voidTarget ? formatRupiah(voidTarget.amount) : '' }}</strong> dibatalkan, dan status tagihan terkait dihitung ulang. Riwayatnya tetap tersimpan.
                </p>
                <TextField v-model="voidForm.reason" label="Alasan pembatalan" :error="voidForm.errors.reason" hint="Wajib; tercatat di log audit." required />
            </form>
            <template #footer>
                <Button variant="ghost" @click="voidTarget = null">Kembali</Button>
                <Button icon="block" :disabled="voidForm.processing" @click="submitVoid">Batalkan pembayaran</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
