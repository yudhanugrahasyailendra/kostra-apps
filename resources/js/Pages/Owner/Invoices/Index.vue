<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { InvoiceData } from '@/types';

type StatusTab = 'all' | 'issued' | 'partial' | 'overdue' | 'paid' | 'void';

const props = defineProps<{
    invoices: InvoiceData[];
    pagination: { current_page: number; last_page: number; total: number; prev_url: string | null; next_url: string | null };
    filters: { q: string | null; status: StatusTab; property_id: number | null; period: string | null };
    counts: Record<StatusTab | 'draft', number>;
    summary: { billed: number; collected: number; outstanding: number; overdue_amount: number; overdue_count: number };
    properties: { id: number; name: string }[];
    can: { create: boolean };
}>();

const search = ref(props.filters.q ?? '');
const period = ref(props.filters.period ?? '');
const propertyId = ref<number | ''>(props.filters.property_id ?? '');

const tabs: { key: StatusTab; label: string }[] = [
    { key: 'all', label: 'Semua' },
    { key: 'issued', label: 'Terbit' },
    { key: 'partial', label: 'Sebagian dibayar' },
    { key: 'overdue', label: 'Lewat jatuh tempo' },
    { key: 'paid', label: 'Lunas' },
    { key: 'void', label: 'Batal' },
];

// Periode kosong berarti "semua periode" dan harus terkirim sebagai `period=`; query dibangun manual
// supaya nilai kosong tidak dibuang.
function go(status: StatusTab = props.filters.status) {
    const query = new URLSearchParams();
    if (status !== 'all') query.set('status', status);
    if (search.value.trim()) query.set('q', search.value.trim());
    if (propertyId.value !== '') query.set('property_id', String(propertyId.value));
    query.set('period', period.value);
    router.get(`/app/tagihan?${query.toString()}`, {}, { preserveState: true, preserveScroll: true });
}

function clearPeriod() {
    period.value = '';
    go();
}

const periodLabel = computed(() => {
    if (!props.filters.period) return 'semua periode';
    const [year, month] = props.filters.period.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

const cards = computed(() => [
    { label: 'Total tagihan', value: props.summary.billed, hint: periodLabel.value, icon: 'receipt_long', tone: 'text-text-primary' },
    { label: 'Sudah terkumpul', value: props.summary.collected, hint: 'Pembayaran tercatat', icon: 'task_alt', tone: 'text-status-lunas-text' },
    { label: 'Sisa tagihan', value: props.summary.outstanding, hint: 'Belum dibayar', icon: 'hourglass_top', tone: 'text-status-menunggu-text' },
    { label: 'Lewat jatuh tempo', value: props.summary.overdue_amount, hint: `${props.summary.overdue_count} tagihan`, icon: 'warning', tone: 'text-status-jatuh-tempo-text' },
]);
</script>

<template>
    <Head title="Tagihan" />
    <OwnerLayout title="Tagihan" active="tagihan">
        <div class="mb-space-lg flex flex-wrap items-start justify-between gap-space-md">
            <p class="max-w-2xl text-body-md text-text-secondary">
                Tagihan bulanan terbit otomatis dari kontrak aktif. Jumlah tagihan terbit tidak diubah; koreksi dicatat sebagai penyesuaian beralasan.
            </p>
            <div class="flex flex-wrap gap-space-sm">
                <Link href="/app/pengaturan/penagihan" class="inline-flex min-h-11 items-center gap-space-xs rounded-md border border-border-strong bg-bg-surface px-space-md py-2.5 text-title-md text-text-primary hover:bg-bg-subtle lg:min-h-10">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">tune</span>
                    Aturan penagihan
                </Link>
                <Link v-if="can.create" href="/app/tagihan/baru" class="inline-flex min-h-11 items-center gap-space-xs rounded-md bg-primary-container px-space-md py-2.5 text-title-md text-on-primary shadow-low hover:bg-primary lg:min-h-10">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                    Buat tagihan manual
                </Link>
            </div>
        </div>

        <section class="mb-space-lg grid gap-space-md sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan tagihan">
            <div v-for="card in cards" :key="card.label" class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <div class="flex items-start justify-between gap-space-sm">
                    <p class="text-label-md uppercase text-text-secondary">{{ card.label }}</p>
                    <span class="material-symbols-outlined text-text-muted" aria-hidden="true">{{ card.icon }}</span>
                </div>
                <p class="mt-space-xs text-headline-sm" :class="card.tone">{{ formatRupiah(card.value) }}</p>
                <p class="text-body-sm text-text-secondary">{{ card.hint }}</p>
            </div>
        </section>

        <div class="mb-space-md flex flex-col gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-sm">
            <div class="flex flex-wrap rounded-md bg-bg-subtle p-space-xs" role="group" aria-label="Status tagihan">
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
            <form class="grid gap-space-sm md:grid-cols-[1fr_auto_auto_auto]" role="search" @submit.prevent="go()">
                <div>
                    <label for="invoice-search" class="sr-only">Cari tagihan</label>
                    <input
                        id="invoice-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        placeholder="Cari nomor tagihan, nama penghuni, atau nomor kamar"
                        class="h-[42px] w-full rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
                    />
                </div>
                <div>
                    <label for="invoice-property" class="sr-only">Properti</label>
                    <select id="invoice-property" v-model="propertyId" class="h-[42px] w-full rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary" @change="go()">
                        <option value="">Semua properti</option>
                        <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
                    </select>
                </div>
                <div class="flex gap-space-xs">
                    <label for="invoice-period" class="sr-only">Periode</label>
                    <input id="invoice-period" v-model="period" type="month" class="h-[42px] w-full rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary" @change="go()" />
                    <button v-if="period" type="button" class="rounded-md px-space-sm text-title-md text-primary-container hover:bg-bg-subtle" @click="clearPeriod">Semua periode</button>
                </div>
                <Button type="submit" variant="secondary" icon="search">Cari</Button>
            </form>
        </div>

        <p v-if="invoices.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            <template v-if="filters.q || filters.property_id || filters.status !== 'all'">Tidak ada tagihan yang cocok dengan filter ini.</template>
            <template v-else>Belum ada tagihan untuk {{ periodLabel }}. Tagihan bulanan terbit otomatis dari kontrak aktif pada tanggal terbit yang diatur.</template>
        </p>

        <div v-else class="overflow-x-auto rounded-lg border border-border-subtle bg-bg-surface">
            <table class="w-full min-w-[52rem] text-left text-body-md">
                <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                    <tr>
                        <th scope="col" class="px-space-md py-space-sm">No. tagihan &amp; kamar</th>
                        <th scope="col" class="px-space-md py-space-sm">Penghuni</th>
                        <th scope="col" class="px-space-md py-space-sm">Periode</th>
                        <th scope="col" class="px-space-md py-space-sm text-right">Total</th>
                        <th scope="col" class="px-space-md py-space-sm">Jatuh tempo</th>
                        <th scope="col" class="px-space-md py-space-sm">Status</th>
                        <th scope="col" class="px-space-md py-space-sm"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle text-text-primary">
                    <tr v-for="invoice in invoices" :key="invoice.id">
                        <td class="px-space-md py-space-sm">
                            <Link :href="`/app/tagihan/${invoice.id}`" class="text-title-md text-primary-container hover:underline">{{ invoice.number }}</Link>
                            <span class="block text-body-sm text-text-secondary">Kamar {{ invoice.room_number }} &middot; {{ invoice.property_name }}</span>
                        </td>
                        <td class="px-space-md py-space-sm">{{ invoice.resident_name }}</td>
                        <td class="px-space-md py-space-sm text-body-sm">
                            <span class="block">{{ invoice.period ?? 'Manual' }}</span>
                            <span class="block text-text-secondary">{{ invoice.type_label }}</span>
                        </td>
                        <td class="px-space-md py-space-sm text-right">
                            <span class="block text-title-md">{{ formatRupiah(invoice.total) }}</span>
                            <span v-if="invoice.paid_total > 0" class="block text-body-sm text-text-secondary">Sisa {{ formatRupiah(invoice.outstanding) }}</span>
                        </td>
                        <td class="px-space-md py-space-sm text-body-sm" :class="invoice.status === 'overdue' ? 'text-status-jatuh-tempo-text' : ''">{{ formatDate(invoice.due_date, 'short') }}</td>
                        <td class="px-space-md py-space-sm"><StatusBadge :tone="invoice.status_tone as never" :label="invoice.status_label" /></td>
                        <td class="whitespace-nowrap px-space-md py-space-sm text-right">
                            <Link :href="`/app/tagihan/${invoice.id}`" class="rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle">Lihat</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between text-body-sm text-text-secondary" aria-label="Halaman">
            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} tagihan)</span>
            <div class="flex gap-space-xs">
                <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
            </div>
        </nav>
    </OwnerLayout>
</template>
