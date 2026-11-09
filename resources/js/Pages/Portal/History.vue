<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import { portalBase } from '@/lib/portal';
import type { PortalInvoiceData, PortalPaymentData, PortalShell } from '@/types';

defineProps<{ shell: PortalShell; invoices: PortalInvoiceData[]; payments: PortalPaymentData[] }>();

const base = portalBase();

function periodLabel(period: string | null | undefined): string {
    if (!period) return '';
    const [year, month] = period.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}
</script>

<template>
    <Head title="Riwayat" />
    <PortalLayout :shell="shell" active="riwayat" title="Kuitansi & riwayat">
        <section aria-label="Kuitansi pembayaran">
            <h2 class="mb-space-sm text-title-lg text-text-primary">Kuitansi pembayaran</h2>
            <p v-if="payments.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-lg text-center text-body-md text-text-secondary">Belum ada pembayaran.</p>
            <ul v-else class="grid gap-space-sm">
                <li v-for="payment in payments" :key="payment.id" class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                    <div class="flex items-start justify-between gap-space-sm">
                        <div>
                            <p class="text-label-md uppercase text-text-secondary">No. {{ payment.receipt_number }}</p>
                            <p class="text-title-md text-text-primary">{{ formatRupiah(payment.amount) }}</p>
                            <p class="text-body-sm text-text-secondary">{{ formatDate(payment.paid_on) }} · {{ payment.method_label }}</p>
                            <p v-if="payment.periods?.length" class="text-body-sm text-text-secondary">Periode {{ payment.periods.map(periodLabel).join(', ') }}</p>
                        </div>
                    </div>
                    <div class="mt-space-sm flex gap-space-sm">
                        <Link :href="`${base}/kuitansi/${payment.id}`" class="flex min-h-11 flex-1 items-center justify-center gap-1 rounded-md border border-primary-container text-title-md text-primary-container">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">receipt</span>
                            Lihat
                        </Link>
                        <a :href="`${base}/kuitansi/${payment.id}/pdf`" class="flex min-h-11 flex-1 items-center justify-center gap-1 rounded-md bg-primary-container text-title-md text-on-primary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">picture_as_pdf</span>
                            Unduh PDF
                        </a>
                    </div>
                </li>
            </ul>
        </section>

        <section class="mt-space-lg" aria-label="Tagihan lunas">
            <h2 class="mb-space-sm text-title-lg text-text-primary">Tagihan lunas</h2>
            <p v-if="invoices.length === 0" class="text-body-md text-text-secondary">Belum ada tagihan lunas.</p>
            <ul v-else class="divide-y divide-border-subtle rounded-lg border border-border-subtle bg-bg-surface">
                <li v-for="invoice in invoices" :key="invoice.id" class="flex items-center justify-between gap-space-sm p-space-md">
                    <div>
                        <p class="text-title-md text-text-primary">{{ periodLabel(invoice.period) || invoice.number }}</p>
                        <p class="text-body-sm text-text-secondary">No. {{ invoice.number }}</p>
                    </div>
                    <span class="text-title-md">{{ formatRupiah(invoice.total) }}</span>
                </li>
            </ul>
        </section>
    </PortalLayout>
</template>
