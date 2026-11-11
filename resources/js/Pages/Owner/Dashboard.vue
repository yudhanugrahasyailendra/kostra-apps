<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Panel from '@/Components/Panel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate, remainingLabel } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { DashboardSummary, SharedProps } from '@/types';

// Tata letak mengikuti mockup dashboard_ringkasan_pemilik. Angka dari kelas query yang sama dengan
// halaman laporan. Bagian keuangan kosong (null) bagi staf tanpa izin finance.view.
const props = defineProps<{ summary: DashboardSummary; can: { finance: boolean } }>();

const page = usePage<SharedProps>();
const firstName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? '');

const percent = (value: number) => `${value.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 1 })}%`;

const quotaPercent = computed(() => {
    const quota = props.summary.quota;

    return quota && quota.limit > 0 ? Math.min(100, Math.round((quota.used / quota.limit) * 100)) : 0;
});

function lateness(days: number): { label: string; tone: 'jatuh-tempo' | 'menunggu' } {
    return days > 0 ? { label: `+${days} hari`, tone: 'jatuh-tempo' } : { label: days === 0 ? 'Hari ini' : `${Math.abs(days)} hari lagi`, tone: 'menunggu' };
}

const plateClass: Record<string, string> = {
    occupied: 'border-status-lunas-border bg-status-lunas-bg text-status-lunas-text',
    vacant: 'border-status-kosong-border bg-status-kosong-bg text-status-kosong-text',
    maintenance: 'border-status-menunggu-border bg-status-menunggu-bg text-status-menunggu-text',
};

const counts = computed(() => {
    const all = props.summary.room_map.flatMap((floor) => floor.rooms);

    return {
        occupied: all.filter((room) => room.status === 'occupied').length,
        vacant: all.filter((room) => room.status === 'vacant').length,
        maintenance: all.filter((room) => room.status === 'maintenance').length,
    };
});

const floorLabel = (floor: number | null) => (floor === null ? 'Tanpa lantai' : `Lantai ${floor}`);
</script>

<template>
    <Head title="Dashboard" />
    <OwnerLayout title="Dashboard" active="dashboard">
        <div class="-mt-space-sm mb-space-lg flex flex-wrap items-end justify-between gap-space-md">
            <p class="text-body-lg text-text-secondary">
                Selamat datang kembali<template v-if="firstName">, {{ firstName }}</template
                >. Berikut ringkasan operasional dan kesehatan finansial kos Anda hari ini.
            </p>
            <div class="flex flex-wrap gap-space-sm">
                <Button v-if="can.finance" icon="payments" @click="router.visit('/app/pembayaran/baru')">Catat Pembayaran</Button>
                <Button variant="secondary" icon="add_home" @click="router.visit('/app/kamar')">Tambah Kamar</Button>
            </div>
        </div>

        <section class="grid gap-space-md sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan">
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <div class="flex items-start justify-between gap-space-sm">
                    <p class="text-label-md uppercase text-text-secondary">Okupansi kamar</p>
                    <span class="material-symbols-outlined rounded-md bg-bg-subtle p-space-xs text-text-secondary" aria-hidden="true">bed</span>
                </div>
                <p class="mt-space-sm text-headline-lg text-text-primary">{{ percent(summary.occupancy.percent) }}</p>
                <div class="mt-space-sm h-2 rounded-full bg-bg-subtle" role="presentation">
                    <div class="h-2 rounded-full bg-primary-container" :style="{ width: `${summary.occupancy.percent}%` }" />
                </div>
                <p class="mt-space-xs text-body-sm text-text-secondary">
                    {{ summary.occupancy.occupied }} terisi · {{ summary.occupancy.vacant }} kosong · {{ summary.occupancy.total }} kamar
                </p>
            </div>

            <template v-if="summary.finance">
                <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                    <div class="flex items-start justify-between gap-space-sm">
                        <p class="text-label-md uppercase text-text-secondary">Pemasukan {{ summary.finance.period_label }}</p>
                        <span class="material-symbols-outlined rounded-md bg-status-lunas-bg p-space-xs text-status-lunas-text" aria-hidden="true">payments</span>
                    </div>
                    <p class="mt-space-sm text-headline-md text-text-primary">{{ formatRupiah(summary.finance.income) }}</p>
                    <p class="text-body-sm text-text-secondary">{{ summary.finance.income_count }} pembayaran diterima</p>
                    <Link :href="`/app/laporan?type=pemasukan&period=${summary.finance.period}`" class="mt-space-xs inline-block text-title-md text-primary-container hover:underline">Lihat laporan</Link>
                </div>
                <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                    <div class="flex items-start justify-between gap-space-sm">
                        <p class="text-label-md uppercase text-text-secondary">Total tunggakan</p>
                        <span class="material-symbols-outlined rounded-md bg-status-jatuh-tempo-bg p-space-xs text-status-jatuh-tempo-text" aria-hidden="true">warning</span>
                    </div>
                    <p class="mt-space-sm text-headline-md" :class="summary.finance.arrears_total > 0 ? 'text-status-jatuh-tempo-text' : 'text-text-primary'">
                        {{ formatRupiah(summary.finance.arrears_total) }}
                    </p>
                    <p class="text-body-sm text-text-secondary">{{ summary.finance.arrears_count }} tagihan lewat jatuh tempo</p>
                    <Link href="/app/laporan?type=tunggakan" class="mt-space-xs inline-block text-title-md text-primary-container hover:underline">Lihat laporan</Link>
                </div>
            </template>

            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <div class="flex items-start justify-between gap-space-sm">
                    <p class="text-label-md uppercase text-text-secondary">Kontrak segera habis</p>
                    <span class="material-symbols-outlined rounded-md bg-status-menunggu-bg p-space-xs text-status-menunggu-text" aria-hidden="true">event_busy</span>
                </div>
                <p class="mt-space-sm text-headline-lg text-text-primary">
                    {{ summary.expiring_leases.count }} <span class="text-body-md text-text-secondary">penyewa</span>
                </p>
                <p class="text-body-sm text-text-secondary">Berakhir dalam {{ summary.expiring_leases.within_days }} hari</p>
                <Link href="/app/kontrak" class="mt-space-xs inline-block text-title-md text-primary-container hover:underline">Tinjau kontrak</Link>
            </div>
        </section>

        <div class="mt-space-lg grid gap-space-lg xl:grid-cols-3">
            <div class="flex flex-col gap-space-lg xl:col-span-2">
                <Panel v-if="summary.due_soon" title="Tunggakan & jatuh tempo terdekat">
                    <template #actions>
                        <StatusBadge v-if="summary.due_soon.overdue_count > 0" tone="jatuh-tempo" :label="`${summary.due_soon.overdue_count} lewat tempo`" />
                    </template>
                    <p v-if="summary.due_soon.items.length === 0" class="text-body-md text-text-secondary">Tidak ada tagihan yang menunggu pembayaran.</p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[34rem] text-left">
                            <thead>
                                <tr class="text-label-md uppercase text-text-secondary">
                                    <th class="pb-space-sm pr-space-md font-medium">Unit & penghuni</th>
                                    <th class="pb-space-sm pr-space-md font-medium">Jatuh tempo</th>
                                    <th class="pb-space-sm pr-space-md text-right font-medium">Sisa tagihan</th>
                                    <th class="pb-space-sm pr-space-md font-medium">Keterlambatan</th>
                                    <th class="pb-space-sm text-right font-medium"><span class="sr-only">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                <tr v-for="item in summary.due_soon.items" :key="item.invoice_id">
                                    <td class="py-space-sm pr-space-md">
                                        <span class="flex items-center gap-space-sm">
                                            <span class="rounded-md bg-bg-subtle px-space-sm py-space-xs text-title-md text-primary-container">{{ item.room_number ?? '-' }}</span>
                                            <span>
                                                <span class="block text-title-md text-text-primary">{{ item.resident_name }}</span>
                                                <span v-if="item.room_type" class="block text-body-sm text-text-secondary">{{ item.room_type }}<template v-if="item.floor"> (Lt. {{ item.floor }})</template></span>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="py-space-sm pr-space-md text-body-md text-text-primary">{{ formatDate(item.due_date, 'short') }}</td>
                                    <td class="py-space-sm pr-space-md text-right text-title-md text-text-primary">{{ formatRupiah(item.outstanding) }}</td>
                                    <td class="py-space-sm pr-space-md"><StatusBadge :tone="lateness(item.days_late).tone" :label="lateness(item.days_late).label" /></td>
                                    <td class="py-space-sm text-right">
                                        <Link :href="`/app/tagihan/${item.invoice_id}`" class="text-title-md text-primary-container hover:underline">Lihat tagihan</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Panel>

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

                <Panel title="Ringkasan status denah kamar">
                    <template #actions>
                        <ul class="flex flex-wrap gap-space-sm text-label-sm text-text-secondary" aria-label="Keterangan status">
                            <li class="flex items-center gap-1"><span class="size-3 rounded-sm border border-status-lunas-border bg-status-lunas-bg" aria-hidden="true" />Terisi ({{ counts.occupied }})</li>
                            <li class="flex items-center gap-1"><span class="size-3 rounded-sm border border-status-kosong-border bg-status-kosong-bg" aria-hidden="true" />Kosong ({{ counts.vacant }})</li>
                            <li class="flex items-center gap-1"><span class="size-3 rounded-sm border border-status-menunggu-border bg-status-menunggu-bg" aria-hidden="true" />Perawatan ({{ counts.maintenance }})</li>
                        </ul>
                    </template>
                    <p v-if="summary.room_map.length === 0" class="text-body-md text-text-secondary">Belum ada kamar. Tambahkan kamar lebih dulu.</p>
                    <div v-else class="flex flex-col gap-space-md">
                        <section v-for="floor in summary.room_map" :key="floor.floor ?? 0" class="rounded-md bg-bg-subtle p-space-md">
                            <h3 class="mb-space-sm text-title-md text-text-primary">{{ floorLabel(floor.floor) }} · {{ floor.rooms.length }} kamar</h3>
                            <ul class="grid grid-cols-3 gap-space-xs sm:grid-cols-4 md:grid-cols-6">
                                <li v-for="room in floor.rooms" :key="room.id">
                                    <Link
                                        href="/app/kamar"
                                        class="flex flex-col items-center rounded-md border px-space-xs py-space-sm text-center"
                                        :class="plateClass[room.status]"
                                        :title="`${room.number}: ${room.status_label}`"
                                    >
                                        <span class="text-title-md">{{ room.number }}</span>
                                        <span class="max-w-full truncate text-label-sm">{{ room.resident_name ?? room.status_label }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </section>
                    </div>
                </Panel>
            </div>

            <div class="flex flex-col gap-space-lg">
                <Panel v-if="summary.pending_proofs" title="Antrean bukti transfer">
                    <template #actions>
                        <StatusBadge v-if="summary.pending_proofs.count > 0" tone="menunggu" :label="`${summary.pending_proofs.count} menunggu`" />
                    </template>
                    <p v-if="summary.pending_proofs.items.length === 0" class="text-body-md text-text-secondary">Tidak ada bukti transfer yang menunggu.</p>
                    <ul v-else class="flex flex-col gap-space-sm">
                        <li v-for="proof in summary.pending_proofs.items" :key="proof.proof_id" class="flex items-center justify-between gap-space-sm rounded-md bg-bg-subtle p-space-sm">
                            <span class="min-w-0">
                                <span class="block truncate text-title-md text-text-primary">{{ proof.room_number ?? '-' }} · {{ proof.resident_name }}</span>
                                <span class="block text-body-sm text-text-secondary">{{ formatRupiah(proof.amount) }}</span>
                            </span>
                            <Link href="/app/bukti-transfer" class="shrink-0 rounded-md bg-primary-container px-space-sm py-space-xs text-label-md text-on-primary hover:bg-primary">Cek bukti</Link>
                        </li>
                    </ul>
                </Panel>

                <Panel title="Komplain penghuni">
                    <template #actions>
                        <Link href="/app/komplain" class="text-title-md text-primary-container hover:underline">Semua</Link>
                    </template>
                    <p v-if="summary.complaints.items.length === 0" class="text-body-md text-text-secondary">Tidak ada komplain yang terbuka.</p>
                    <ul v-else class="flex flex-col gap-space-sm">
                        <li v-for="complaint in summary.complaints.items" :key="complaint.id" class="rounded-md bg-bg-subtle p-space-sm">
                            <div class="flex items-start justify-between gap-space-sm">
                                <Link :href="`/app/komplain/${complaint.id}`" class="text-title-md text-text-primary hover:underline">
                                    <template v-if="complaint.room_number">Kamar {{ complaint.room_number }} · </template>{{ complaint.title }}
                                </Link>
                                <StatusBadge :tone="complaint.status_tone" :label="complaint.status_label" />
                            </div>
                            <p class="mt-space-xs text-body-sm text-text-secondary">Dilaporkan {{ formatDate(complaint.created_at, 'short') }}</p>
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

                <section v-if="summary.quota && summary.quota.limit > 0" class="rounded-lg bg-primary-container p-space-md text-on-primary shadow-low" aria-label="Kuota WhatsApp">
                    <p class="text-label-md uppercase opacity-80">Kuota WhatsApp bulan ini</p>
                    <p class="mt-space-sm text-headline-lg">
                        {{ summary.quota.remaining }} <span class="text-body-md opacity-80">/ {{ summary.quota.limit }} pesan tersisa</span>
                    </p>
                    <div class="mt-space-sm h-2 rounded-full bg-on-primary/20" role="presentation">
                        <div class="h-2 rounded-full bg-secondary-container" :style="{ width: `${quotaPercent}%` }" />
                    </div>
                    <p class="mt-space-xs text-body-sm opacity-80">{{ summary.quota.used }} pesan sudah terpakai.</p>
                </section>
            </div>
        </div>
    </OwnerLayout>
</template>
