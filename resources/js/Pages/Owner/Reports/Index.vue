<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Panel from '@/Components/Panel.vue';
import ReportTable from '@/Components/ReportTable.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatRupiah } from '@/lib/formatRupiah';
import type { ReportTableData } from '@/types';

type ReportType = 'rekap' | 'pemasukan' | 'pengeluaran' | 'tunggakan' | 'okupansi';

const props = defineProps<{
    type: ReportType;
    types: { key: ReportType; label: string }[];
    filters: { period: string; date: string; property_id: number | null };
    period_label: string;
    property_name: string;
    table: ReportTableData;
    properties: { id: number; name: string }[];
    today: string;
}>();

const period = ref(props.filters.period);
const date = ref(props.filters.date);
const propertyId = ref<number | ''>(props.filters.property_id ?? '');

// Laporan tunggakan dan okupansi memakai tanggal; yang lain memakai bulan.
const usesDate = computed(() => props.type === 'tunggakan' || props.type === 'okupansi');

function query(type: ReportType = props.type) {
    const params = new URLSearchParams({ type });
    if (usesDate.value && type === props.type) params.set('date', date.value);
    else if (!['tunggakan', 'okupansi'].includes(type)) params.set('period', period.value);
    if (propertyId.value !== '') params.set('property_id', String(propertyId.value));
    return params.toString();
}

function go(type: ReportType = props.type) {
    router.get(`/app/laporan?${query(type)}`, {}, { preserveScroll: true });
}

const exportQuery = computed(() => query());

// Grafik batang rekap: tinggi relatif terhadap nilai terbesar. Tabel di bawahnya adalah padanan teksnya.
const chartRows = computed(() =>
    props.type === 'rekap' ? (props.table.rows as { label: string; income: number; expense: number; net: number }[]) : [],
);
const chartMax = computed(() => Math.max(1, ...chartRows.value.flatMap((row) => [row.income, row.expense])));
const pct = (value: number) => `${Math.round((Math.max(0, value) / chartMax.value) * 100)}%`;

function summaryValue(item: ReportTableData['summary'][number]): string {
    if (item.type === 'money') return formatRupiah(Number(item.value));
    return String(item.value ?? '');
}
</script>

<template>
    <Head title="Laporan Keuangan" />
    <OwnerLayout title="Laporan Keuangan" active="laporan">
        <p class="mb-space-lg max-w-3xl text-body-md text-text-secondary">
            Pemasukan dihitung saat uang diterima (tanggal pembayaran), bukan saat tagihan terbit. Pembayaran yang dibatalkan tidak dihitung.
        </p>

        <div class="mb-space-md flex flex-wrap rounded-md bg-bg-subtle p-space-xs" role="group" aria-label="Jenis laporan">
            <button
                v-for="item in types"
                :key="item.key"
                type="button"
                class="rounded-md px-space-md py-space-xs text-title-md"
                :class="item.key === type ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary hover:text-text-primary'"
                :aria-pressed="item.key === type"
                @click="go(item.key)"
            >
                {{ item.label }}
            </button>
        </div>

        <form class="mb-space-lg flex flex-wrap items-end gap-space-md rounded-lg border border-border-subtle bg-bg-surface p-space-md" @submit.prevent="go()">
            <div class="flex flex-col gap-space-xs">
                <label for="report-property" class="text-label-md text-text-primary">Properti</label>
                <select
                    id="report-property"
                    v-model="propertyId"
                    class="h-[42px] min-w-48 rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary"
                    @change="go()"
                >
                    <option value="">Semua properti</option>
                    <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
                </select>
            </div>
            <div v-if="usesDate" class="flex flex-col gap-space-xs">
                <label for="report-date" class="text-label-md text-text-primary">Per tanggal</label>
                <input id="report-date" v-model="date" type="date" class="h-[42px] rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary" @change="go()" />
            </div>
            <div v-else class="flex flex-col gap-space-xs">
                <label for="report-period" class="text-label-md text-text-primary">{{ type === 'rekap' ? 'Bulan terakhir' : 'Bulan' }}</label>
                <input id="report-period" v-model="period" type="month" class="h-[42px] rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary" @change="go()" />
            </div>
            <div class="ml-auto flex flex-wrap gap-space-sm">
                <a :href="`/app/laporan/xlsx?${exportQuery}`" class="inline-flex min-h-11 items-center gap-space-xs rounded-md border border-border-strong bg-bg-surface px-space-md py-2.5 text-title-md text-text-primary hover:bg-bg-subtle lg:min-h-10" download>
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">table_view</span>
                    Excel
                </a>
                <a :href="`/app/laporan/pdf?${exportQuery}`" class="inline-flex min-h-11 items-center gap-space-xs rounded-md border border-border-strong bg-bg-surface px-space-md py-2.5 text-title-md text-text-primary hover:bg-bg-subtle lg:min-h-10" download>
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">picture_as_pdf</span>
                    PDF
                </a>
            </div>
        </form>

        <p class="mb-space-md text-body-sm text-text-secondary">
            <strong class="text-text-primary">{{ table.title }}</strong> · {{ property_name }} · {{ period_label }}
        </p>

        <dl v-if="table.summary.length" class="mb-space-lg grid gap-space-md sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="item in table.summary" :key="item.label" class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <dt class="text-label-md uppercase text-text-secondary">{{ item.label }}</dt>
                <dd class="mt-space-xs text-headline-sm text-text-primary">{{ summaryValue(item) }}</dd>
            </div>
        </dl>

        <Panel v-if="type === 'rekap'" title="Tren 6 bulan" class="mb-space-lg">
            <div role="img" :aria-label="`Grafik batang pemasukan dan pengeluaran 6 bulan. Rincian angka ada pada tabel di bawahnya.`" class="flex flex-col gap-space-md">
                <div v-for="row in chartRows" :key="row.label" class="grid grid-cols-[6.5rem_1fr] items-center gap-space-sm">
                    <span class="text-body-sm text-text-secondary">{{ row.label }}</span>
                    <div class="flex flex-col gap-1">
                        <div class="h-3 rounded-sm bg-bg-subtle"><div class="h-3 rounded-sm bg-primary-container" :style="{ width: pct(row.income) }" /></div>
                        <div class="h-3 rounded-sm bg-bg-subtle"><div class="h-3 rounded-sm bg-secondary-container" :style="{ width: pct(row.expense) }" /></div>
                    </div>
                </div>
            </div>
            <p class="mt-space-md flex flex-wrap gap-space-md text-body-sm text-text-secondary">
                <span class="inline-flex items-center gap-space-xs"><span class="size-3 rounded-sm bg-primary-container" aria-hidden="true" /> Pemasukan</span>
                <span class="inline-flex items-center gap-space-xs"><span class="size-3 rounded-sm bg-secondary-container" aria-hidden="true" /> Pengeluaran</span>
            </p>
            <p class="mt-space-sm text-body-sm text-text-secondary">Tabel di bawah adalah padanan teks dari grafik ini. Dengan filter properti, pengeluaran umum (tanpa properti) tidak ikut dihitung.</p>
        </Panel>

        <ReportTable :table="table" />
    </OwnerLayout>
</template>
