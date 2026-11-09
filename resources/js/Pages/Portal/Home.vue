<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StatusBadge from '@/Components/StatusBadge.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import { portalBase } from '@/lib/portal';
import type { PortalInvoiceData, PortalShell } from '@/types';

const props = defineProps<{
    shell: PortalShell;
    invoices: PortalInvoiceData[];
    outstanding_total: number;
    lease: { end_date: string | null; days_remaining: number | null } | null;
}>();

const base = portalBase();
</script>

<template>
    <Head title="Tagihan" />
    <PortalLayout :shell="shell" active="tagihan">
        <section v-if="invoices.length === 0" class="rounded-lg border border-border-subtle bg-bg-surface p-space-lg text-center shadow-low">
            <span class="material-symbols-outlined text-[40px] text-status-lunas-text" aria-hidden="true">check_circle</span>
            <p class="mt-space-sm text-title-md text-text-primary">Tidak ada tagihan yang harus dibayar</p>
            <p class="text-body-sm text-text-secondary">Terima kasih sudah membayar tepat waktu.</p>
        </section>

        <template v-else>
            <p class="mb-space-sm text-label-md uppercase text-text-secondary">Total yang harus dibayar</p>
            <p class="mb-space-md text-display-lg-mobile text-text-primary">{{ formatRupiah(outstanding_total) }}</p>

            <ul class="grid gap-space-md">
                <li v-for="invoice in invoices" :key="invoice.id" class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                    <div class="flex items-start justify-between gap-space-sm">
                        <div>
                            <p class="text-label-md uppercase text-text-secondary">No. {{ invoice.number }}</p>
                            <p class="text-title-md text-text-primary">Jatuh tempo {{ formatDate(invoice.due_date) }}</p>
                        </div>
                        <StatusBadge :tone="invoice.status_tone" :label="invoice.status_label" />
                    </div>
                    <p class="mt-space-sm text-headline-sm text-text-primary">{{ formatRupiah(invoice.outstanding) }}</p>
                    <p v-if="invoice.paid_total > 0" class="text-body-sm text-text-secondary">Sudah dibayar {{ formatRupiah(invoice.paid_total) }} dari {{ formatRupiah(invoice.total) }}</p>
                    <ul class="mt-space-sm divide-y divide-border-subtle text-body-sm">
                        <li v-for="(item, index) in invoice.items" :key="index" class="flex justify-between gap-space-sm py-1">
                            <span class="text-text-secondary">{{ item.description }}</span>
                            <span class="shrink-0 text-text-primary">{{ formatRupiah(item.amount) }}</span>
                        </li>
                    </ul>
                    <p v-if="invoice.has_pending_proof" class="mt-space-sm rounded-md bg-status-menunggu-bg p-space-sm text-body-sm text-status-menunggu-text">Bukti transfer Anda sedang diperiksa pengelola.</p>
                    <Link :href="`${base}/tagihan/${invoice.id}`" class="mt-space-md flex min-h-11 items-center justify-center gap-space-xs rounded-md bg-primary-container px-space-md text-title-md text-on-primary">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">payments</span>
                        Bayar &amp; kirim bukti
                    </Link>
                </li>
            </ul>
        </template>

        <section v-if="lease" class="mt-space-lg rounded-lg border border-border-subtle bg-bg-surface p-space-md">
            <p class="text-label-md uppercase text-text-secondary">Masa sewa</p>
            <p class="text-title-md text-text-primary">{{ lease.end_date ? `Berakhir ${formatDate(lease.end_date)}` : 'Tanpa tanggal akhir' }}</p>
            <p v-if="lease.days_remaining !== null" class="text-body-sm text-text-secondary">
                {{ lease.days_remaining >= 0 ? `Sisa ${lease.days_remaining} hari` : `Lewat ${Math.abs(lease.days_remaining)} hari` }}
            </p>
        </section>
    </PortalLayout>
</template>
