<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate, remainingLabel } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { LeaseData } from '@/types';

type Tab = 'active' | 'expiring' | 'overdue' | 'history';

const props = defineProps<{
    leases: LeaseData[];
    pagination: { current_page: number; last_page: number; total: number; prev_url: string | null; next_url: string | null };
    filters: { q: string | null; tab: Tab };
    counts: Record<Tab, number>;
    can: { create: boolean };
}>();

const search = ref(props.filters.q ?? '');

function go(tab: Tab, q: string | null = search.value) {
    const query: Record<string, string> = { tab };
    if (q) query.q = q;
    router.get('/app/kontrak', query, { preserveState: true, preserveScroll: true });
}

const tabs = computed<{ key: Tab; label: string }[]>(() => [
    { key: 'active', label: 'Kontrak aktif' },
    { key: 'expiring', label: 'Segera habis (30 hari)' },
    { key: 'overdue', label: 'Lewat masa sewa' },
    { key: 'history', label: 'Riwayat' },
]);

function remainingTone(lease: LeaseData): 'jatuh-tempo' | 'menunggu' | null {
    if (lease.is_overdue) return 'jatuh-tempo';
    return lease.expires_soon ? 'menunggu' : null;
}
</script>

<template>
    <Head title="Kontrak Sewa" />
    <OwnerLayout title="Kontrak Sewa" active="kontrak">
        <div class="mb-space-lg flex flex-wrap items-start justify-between gap-space-md">
            <p class="max-w-2xl text-body-md text-text-secondary">
                Kontrak menghubungkan penghuni, kamar, harga, dan masa sewa. Satu kamar hanya boleh punya satu kontrak aktif. Deposit dicatat sebagai kewajiban yang dapat dikembalikan.
            </p>
            <Link v-if="can.create" href="/app/kontrak/baru" class="inline-flex min-h-11 items-center gap-space-xs rounded-md bg-primary-container px-space-md py-2.5 text-title-md text-on-primary shadow-low hover:bg-primary lg:min-h-10">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">login</span>
                Check-in penghuni
            </Link>
        </div>

        <div class="mb-space-md flex flex-col gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-sm lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap rounded-md bg-bg-subtle p-space-xs" role="group" aria-label="Kelompok kontrak">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-md px-space-md py-space-xs text-title-md"
                    :class="tab.key === filters.tab ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary hover:text-text-primary'"
                    :aria-pressed="tab.key === filters.tab"
                    @click="go(tab.key)"
                >
                    {{ tab.label }} ({{ counts[tab.key] }})
                </button>
            </div>
            <form class="flex flex-1 gap-space-sm lg:max-w-md" role="search" @submit.prevent="go(filters.tab)">
                <label for="lease-search" class="sr-only">Cari kontrak</label>
                <input
                    id="lease-search"
                    v-model="search"
                    type="search"
                    maxlength="100"
                    placeholder="Cari nama penghuni atau nomor kamar"
                    class="h-[42px] w-full rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
                />
                <Button type="submit" variant="secondary" icon="search">Cari</Button>
            </form>
        </div>

        <p v-if="leases.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            <template v-if="filters.q">Tidak ada kontrak yang cocok dengan pencarian ini.</template>
            <template v-else-if="filters.tab === 'active'">Belum ada kontrak aktif. Mulai dengan check-in penghuni ke kamar kosong.</template>
            <template v-else>Tidak ada kontrak pada kelompok ini.</template>
        </p>

        <div v-else class="overflow-x-auto rounded-lg border border-border-subtle bg-bg-surface">
            <table class="w-full min-w-[48rem] text-left text-body-md">
                <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                    <tr>
                        <th scope="col" class="px-space-md py-space-sm">Penghuni</th>
                        <th scope="col" class="px-space-md py-space-sm">Kamar</th>
                        <th scope="col" class="px-space-md py-space-sm">Periode kontrak</th>
                        <th scope="col" class="px-space-md py-space-sm">Tarif &amp; deposit</th>
                        <th scope="col" class="px-space-md py-space-sm">Status</th>
                        <th scope="col" class="px-space-md py-space-sm"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle text-text-primary">
                    <tr v-for="lease in leases" :key="lease.id">
                        <td class="px-space-md py-space-sm">
                            <Link :href="`/app/penghuni/${lease.primary_resident_id}`" class="text-title-md text-primary-container hover:underline">{{ lease.primary_resident_name }}</Link>
                        </td>
                        <td class="px-space-md py-space-sm">
                            <span class="text-title-md">Kamar {{ lease.room_number }}</span>
                            <span class="block text-body-sm text-text-secondary">{{ lease.property_name }}</span>
                        </td>
                        <td class="px-space-md py-space-sm text-body-sm">
                            <span class="block text-text-primary">{{ formatDate(lease.start_date, 'short') }} &ndash; {{ lease.end_date ? formatDate(lease.end_date, 'short') : 'tanpa akhir' }}</span>
                            <span
                                v-if="lease.status === 'active'"
                                class="block"
                                :class="remainingTone(lease) === 'jatuh-tempo' ? 'text-status-jatuh-tempo-text' : remainingTone(lease) === 'menunggu' ? 'text-status-menunggu-text' : 'text-text-secondary'"
                            >{{ remainingLabel(lease.days_remaining) }}</span>
                            <span v-else-if="lease.ended_reason_label" class="block text-text-secondary">{{ lease.ended_reason_label }}</span>
                        </td>
                        <td class="px-space-md py-space-sm">
                            <span class="block">{{ formatRupiah(lease.rent_amount) }} / bln</span>
                            <span v-if="lease.deposit" class="block text-body-sm text-text-secondary">Deposit {{ formatRupiah(lease.deposit.amount) }} &middot; {{ lease.deposit.status_label }}</span>
                        </td>
                        <td class="px-space-md py-space-sm">
                            <div class="flex flex-col items-start gap-space-xs">
                                <StatusBadge :tone="lease.status_tone" :label="lease.status_label" />
                                <StatusBadge v-if="lease.is_overdue" tone="jatuh-tempo" label="Lewat masa sewa" />
                                <StatusBadge v-else-if="lease.expires_soon" tone="menunggu" label="Segera habis" />
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-space-md py-space-sm text-right">
                            <Link :href="`/app/kontrak/${lease.id}`" class="rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle">Lihat</Link>
                            <Link v-if="lease.status === 'active' && lease.can.check_out" :href="`/app/kontrak/${lease.id}/check-out`" class="rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle">Check-out</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between text-body-sm text-text-secondary" aria-label="Halaman">
            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} kontrak)</span>
            <div class="flex gap-space-xs">
                <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
            </div>
        </nav>
    </OwnerLayout>
</template>
