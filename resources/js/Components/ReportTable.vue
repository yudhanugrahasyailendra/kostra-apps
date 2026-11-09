<script setup lang="ts">
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { ReportCell, ReportColumn, ReportTableData } from '@/types';

// Tabel laporan generik: kolom, baris, dan total datang dari ReportTable di server,
// jadi tampilan, Excel, dan PDF selalu memuat baris yang sama.
defineProps<{ table: ReportTableData; emptyText?: string }>();

function format(column: ReportColumn, value: ReportCell | undefined): string {
    if (value === null || value === undefined || value === '') return '';
    switch (column.type) {
        case 'money':
            return formatRupiah(Number(value));
        case 'date':
            return formatDate(String(value), 'short');
        case 'percent':
            return `${Number(value).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
        case 'number':
            return typeof value === 'number' ? value.toLocaleString('id-ID', { maximumFractionDigits: 1 }) : String(value);
        default:
            return String(value);
    }
}

const numeric = (column: ReportColumn) => ['money', 'number', 'percent'].includes(column.type);
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-border-subtle bg-bg-surface">
        <table class="w-full min-w-[40rem] text-left text-body-md">
            <caption class="sr-only">{{ table.title }}</caption>
            <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                <tr>
                    <th v-for="column in table.columns" :key="column.key" scope="col" class="px-space-md py-space-sm" :class="numeric(column) ? 'text-right' : ''">
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle text-text-primary">
                <tr v-if="table.rows.length === 0">
                    <td :colspan="table.columns.length" class="px-space-md py-space-xl text-center text-text-secondary">
                        {{ emptyText ?? 'Tidak ada data untuk filter ini.' }}
                    </td>
                </tr>
                <tr v-for="(row, index) in table.rows" :key="index">
                    <td v-for="column in table.columns" :key="column.key" class="px-space-md py-space-sm" :class="numeric(column) ? 'text-right' : ''">
                        {{ format(column, row[column.key]) }}
                    </td>
                </tr>
            </tbody>
            <tfoot v-if="table.totals" class="bg-bg-subtle text-title-md text-text-primary">
                <tr>
                    <td v-for="column in table.columns" :key="column.key" class="px-space-md py-space-sm" :class="numeric(column) ? 'text-right' : ''">
                        {{ format(column, table.totals[column.key]) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
