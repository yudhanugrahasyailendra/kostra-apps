<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import { portalBase } from '@/lib/portal';
import type { PortalReceiptData, PortalShell } from '@/types';

const props = defineProps<{ shell: PortalShell; receipt: PortalReceiptData; payment_id: number }>();

const base = portalBase();
</script>

<template>
    <Head :title="`Kuitansi ${receipt.number}`" />
    <PortalLayout :shell="shell" active="riwayat">
        <Link :href="`${base}/riwayat`" class="mb-space-md inline-flex min-h-11 items-center gap-space-xs text-title-md text-primary-container">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span>
            Kembali
        </Link>

        <article class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
            <p class="text-label-md uppercase text-text-secondary">Kuitansi pembayaran</p>
            <p class="text-headline-sm text-text-primary">No. {{ receipt.number }}</p>
            <p class="text-body-sm text-text-secondary">{{ receipt.business_name }}<template v-if="receipt.property_name"> · {{ receipt.property_name }}</template></p>

            <dl class="mt-space-md grid gap-space-sm text-body-md">
                <div class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Diterima dari</dt><dd class="text-right">{{ receipt.resident_name }}</dd></div>
                <div v-if="receipt.room_number" class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Kamar</dt><dd>{{ receipt.room_number }}</dd></div>
                <div class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Tanggal bayar</dt><dd>{{ formatDate(receipt.paid_on) }}</dd></div>
                <div class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Metode</dt><dd>{{ receipt.method_label }}</dd></div>
                <div v-if="receipt.reference" class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Referensi</dt><dd class="break-all text-right">{{ receipt.reference }}</dd></div>
            </dl>

            <ul class="mt-space-md divide-y divide-border-subtle border-y border-border-subtle text-body-md">
                <li v-for="line in receipt.lines" :key="line.invoice_number ?? ''" class="flex justify-between gap-space-sm py-space-xs">
                    <span class="text-text-secondary">Tagihan {{ line.invoice_number }}</span>
                    <span>{{ formatRupiah(line.amount) }}</span>
                </li>
            </ul>
            <div class="mt-space-sm flex justify-between text-title-lg"><span>Total dibayar</span><span>{{ formatRupiah(receipt.amount) }}</span></div>
        </article>

        <a :href="`${base}/kuitansi/${props.payment_id}/pdf`" class="mt-space-md flex min-h-11 items-center justify-center gap-space-xs rounded-md bg-primary-container px-space-md text-title-md text-on-primary">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">picture_as_pdf</span>
            Unduh PDF
        </a>
    </PortalLayout>
</template>
