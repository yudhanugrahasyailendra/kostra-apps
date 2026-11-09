<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { PortalLeaseData, PortalShell } from '@/types';

defineProps<{ shell: PortalShell; lease: PortalLeaseData | null }>();
</script>

<template>
    <Head title="Kontrak" />
    <PortalLayout :shell="shell" active="kontrak" title="Ringkasan kontrak">
        <p v-if="!lease" class="rounded-lg border border-dashed border-border-strong p-space-lg text-center text-body-md text-text-secondary">Tidak ada kontrak aktif.</p>

        <dl v-else class="grid gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-md text-body-md shadow-low">
            <div class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Kamar</dt><dd class="text-right">{{ lease.room_number }}<template v-if="lease.room_type"> · {{ lease.room_type }}</template></dd></div>
            <div class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Kos</dt><dd class="text-right">{{ lease.property_name }}</dd></div>
            <div v-if="lease.property_address" class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Alamat</dt><dd class="text-right">{{ lease.property_address }}</dd></div>
            <div class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Mulai sewa</dt><dd>{{ formatDate(lease.start_date) }}</dd></div>
            <div class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Berakhir</dt><dd>{{ lease.end_date ? formatDate(lease.end_date) : 'Tanpa tanggal akhir' }}</dd></div>
            <div class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Sewa</dt><dd>{{ formatRupiah(lease.rent_amount) }} / {{ lease.billing_cycle_label.toLowerCase() }}</dd></div>
            <div v-if="lease.due_day" class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Jatuh tempo</dt><dd>Tanggal {{ lease.due_day }} tiap bulan</dd></div>
            <div v-if="lease.deposit_amount !== null" class="flex justify-between gap-space-sm"><dt class="text-text-secondary">Deposit</dt><dd>{{ formatRupiah(lease.deposit_amount) }}</dd></div>
        </dl>
    </PortalLayout>
</template>
