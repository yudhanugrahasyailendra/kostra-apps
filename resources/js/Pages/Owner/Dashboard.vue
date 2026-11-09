<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Panel from '@/Components/Panel.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate, remainingLabel } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { DashboardSummary } from '@/types';

// Angka berasal dari kelas query yang sama dengan halaman laporan. Bagian keuangan
// kosong (null) bagi staf tanpa izin finance.view.
defineProps<{ summary: DashboardSummary; can: { finance: boolean } }>();

const percent = (value: number) => `${value.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
</script>

<template>
    <Head title="Dashboard" />
    <OwnerLayout title="Dashboard" active="dashboard">
        <section class="grid gap-space-md sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan">
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <p class="text-label-md uppercase text-text-secondary">Okupansi hari ini</p>
                <p class="mt-space-xs text-headline-md text-text-primary">{{ percent(summary.occupancy.percent) }}</p>
                <p class="text-body-sm text-text-secondary">{{ summary.occupancy.occupied }} terisi, {{ summary.occupancy.vacant }} kosong dari {{ summary.occupancy.total }} kamar</p>
                <div class="mt-space-sm h-2 rounded-full bg-bg-subtle" role="presentation">
                    <div class="h-2 rounded-full bg-primary-container" :style="{ width: `${summary.occupancy.percent}%` }" />
                </div>
            </div>

            <template v-if="summary.finance">
                <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                    <p class="text-label-md uppercase text-text-secondary">Pemasukan {{ summary.finance.period_label }}</p>
                    <p class="mt-space-xs text-headline-md text-status-lunas-text">{{ formatRupiah(summary.finance.income) }}</p>
                    <p class="text-body-sm text-text-secondary">{{ summary.finance.income_count }} pembayaran diterima</p>
                    <Link :href="`/app/laporan?type=pemasukan&period=${summary.finance.period}`" class="mt-space-xs inline-block text-title-md text-primary-container hover:underline">Lihat laporan</Link>
                </div>
                <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                    <p class="text-label-md uppercase text-text-secondary">Total tunggakan</p>
                    <p class="mt-space-xs text-headline-md" :class="summary.finance.arrears_total > 0 ? 'text-status-jatuh-tempo-text' : 'text-text-primary'">{{ formatRupiah(summary.finance.arrears_total) }}</p>
                    <p class="text-body-sm text-text-secondary">{{ summary.finance.arrears_count }} tagihan lewat jatuh tempo</p>
                    <Link href="/app/laporan?type=tunggakan" class="mt-space-xs inline-block text-title-md text-primary-container hover:underline">Lihat laporan</Link>
                </div>
            </template>

            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <p class="text-label-md uppercase text-text-secondary">Kontrak segera habis</p>
                <p class="mt-space-xs text-headline-md text-text-primary">{{ summary.expiring_leases.count }}</p>
                <p class="text-body-sm text-text-secondary">berakhir dalam {{ summary.expiring_leases.within_days }} hari</p>
            </div>
        </section>

        <div class="mt-space-lg grid gap-space-lg lg:grid-cols-2">
            <Panel v-if="summary.finance" title="Umur tunggakan">
                <ul class="divide-y divide-border-subtle">
                    <li v-for="bucket in summary.finance.arrears_buckets" :key="bucket.label" class="flex items-center justify-between gap-space-md py-space-sm">
                        <span class="text-body-md text-text-primary">{{ bucket.label }}</span>
                        <span class="text-right">
                            <span class="block text-title-md text-text-primary">{{ formatRupiah(bucket.amount) }}</span>
                            <span class="block text-body-sm text-text-secondary">{{ bucket.count }} tagihan</span>
                        </span>
                    </li>
                </ul>
            </Panel>

            <Panel title="Kontrak segera habis">
                <p v-if="summary.expiring_leases.items.length === 0" class="text-body-md text-text-secondary">
                    Tidak ada kontrak yang berakhir dalam {{ summary.expiring_leases.within_days }} hari ke depan.
                </p>
                <ul v-else class="divide-y divide-border-subtle">
                    <li v-for="item in summary.expiring_leases.items" :key="item.lease_id" class="flex items-center justify-between gap-space-md py-space-sm">
                        <span>
                            <Link :href="`/app/kontrak/${item.lease_id}`" class="text-title-md text-primary-container hover:underline">{{ item.resident_name }}</Link>
                            <span class="block text-body-sm text-text-secondary">Kamar {{ item.room_number }}</span>
                        </span>
                        <span class="text-right text-body-sm text-text-secondary">
                            {{ formatDate(item.end_date, 'short') }}
                            <span class="block text-status-menunggu-text">{{ remainingLabel(item.days_left) }}</span>
                        </span>
                    </li>
                </ul>
            </Panel>
        </div>
    </OwnerLayout>
</template>
