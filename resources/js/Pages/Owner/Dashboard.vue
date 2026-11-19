<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import OccupancyGauge from '@/Components/OccupancyGauge.vue';
import Panel from '@/Components/Panel.vue';
import PillBarChart, { type PillBar } from '@/Components/PillBarChart.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { useCountUp } from '@/lib/animate';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah, formatRupiahShort } from '@/lib/formatRupiah';
import type { DashboardSummary, SharedProps } from '@/types';

// Isi dari mockup dashboard_ringkasan_pemilik, ditata mengikuti desain acuan: baris kartu angka, lalu kolom 1-3
// (grafik + pengingat, tagihan + busur okupansi) dan kolom 4 (kontrak + kuota WhatsApp). Angka dari kelas query
// yang sama dengan halaman laporan. Bagian keuangan kosong (null) bagi staf tanpa izin finance.view.
const props = defineProps<{ summary: DashboardSummary; can: { finance: boolean } }>();

const page = usePage<SharedProps>();
const canViewMessages = computed(() => !!page.props.auth?.can.manageSettings);
const canManageSubscription = computed(() => !!page.props.auth?.can.manageSubscription);

const percent = (value: number) => `${value.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;

const counts = computed(() => {
    const all = props.summary.room_map.flatMap((floor) => floor.rooms);

    return {
        occupied: all.filter((room) => room.status === 'occupied').length,
        vacant: all.filter((room) => room.status === 'vacant').length,
        maintenance: all.filter((room) => room.status === 'maintenance').length,
    };
});

// Angka berjalan (naik dari 0 saat halaman dibuka). Nilai akhir tetap dikirim ke pembaca layar lewat srValue.
const animated = {
    occupancy: useCountUp(() => props.summary.occupancy.percent),
    income: useCountUp(() => props.summary.finance?.income ?? 0, { delay: 80 }),
    arrears: useCountUp(() => props.summary.finance?.arrears_total ?? 0, { delay: 160 }),
    expiring: useCountUp(() => props.summary.expiring_leases.count, { delay: 240 }),
    vacant: useCountUp(() => counts.value.vacant, { delay: 80 }),
    complaints: useCountUp(() => props.summary.complaints.count, { delay: 240 }),
    quota: useCountUp(() => props.summary.quota?.remaining ?? 0, { duration: 1200, delay: 300 }),
};
const animatedPercent = (value: number) => percent(Math.round(value * 10) / 10);
const animatedCount = (value: number) => String(Math.round(value));
const animatedMoney = (value: number) => formatRupiah(Math.round(value));

// Baris kartu angka
interface Stat {
    key: string;
    title: string;
    value: string;
    href: string;
    linkLabel: string;
    note: string;
    badge?: string;
    highlight?: boolean;
    money?: boolean;
    tone?: 'positive' | 'negative' | 'neutral';
    srValue?: string;
}

const stats = computed<Stat[]>(() => {
    const { occupancy, expiring_leases: expiring, finance, complaints } = props.summary;
    const occupancyStat: Stat = {
        key: 'occupancy',
        title: 'Kamar Terisi',
        value: animatedPercent(animated.occupancy.value),
        srValue: percent(occupancy.percent),
        href: '/app/kamar',
        linkLabel: 'Buka daftar kamar',
        badge: `${occupancy.occupied}/${occupancy.total}`,
        note: 'kamar terisi saat ini',
        highlight: true,
    };
    const expiringStat: Stat = {
        key: 'expiring',
        title: 'Kontrak Segera Habis',
        value: animatedCount(animated.expiring.value),
        srValue: String(expiring.count),
        href: '/app/kontrak',
        linkLabel: 'Tinjau kontrak',
        note: `Berakhir dalam ${expiring.within_days} hari`,
        tone: expiring.count > 0 ? 'neutral' : 'positive',
    };

    if (finance) {
        return [
            occupancyStat,
            {
                key: 'income',
                title: 'Pemasukan Bulan Ini',
                value: animatedMoney(animated.income.value),
                srValue: formatRupiah(finance.income),
                money: true,
                href: `/app/laporan?type=pemasukan&period=${finance.period}`,
                linkLabel: 'Lihat laporan pemasukan',
                badge: String(finance.income_count),
                note: `pembayaran ${finance.period_label}`,
            },
            {
                key: 'arrears',
                title: 'Total Tunggakan',
                value: animatedMoney(animated.arrears.value),
                srValue: formatRupiah(finance.arrears_total),
                money: true,
                href: '/app/laporan?type=tunggakan',
                linkLabel: 'Lihat laporan tunggakan',
                badge: String(finance.arrears_count),
                note: 'tagihan lewat jatuh tempo',
                tone: finance.arrears_count > 0 ? 'negative' : 'positive',
            },
            expiringStat,
        ];
    }

    return [
        occupancyStat,
        {
            key: 'vacant',
            title: 'Kamar Kosong',
            value: animatedCount(animated.vacant.value),
            srValue: String(counts.value.vacant),
            href: '/app/kamar', linkLabel: 'Buka daftar kamar',
            note: 'Siap disewakan',
        },
        expiringStat,
        {
            key: 'complaints',
            title: 'Komplain Terbuka',
            value: animatedCount(animated.complaints.value),
            srValue: String(complaints.count),
            href: '/app/komplain',
            linkLabel: 'Buka komplain',
            note: 'Menunggu ditangani',
            tone: complaints.count > 0 ? 'negative' : 'positive',
        },
    ];
});

// Grafik: tren pemasukan bagi yang berizin keuangan, okupansi per lantai bagi staf lain.
const monthShort = new Intl.DateTimeFormat('id-ID', { month: 'short', timeZone: 'UTC' });
const floorLabel = (floor: number | null) => (floor === null ? 'Tanpa lantai' : `Lantai ${floor}`);

const chart = computed(() => {
    const finance = props.summary.finance;

    if (finance) {
        const last = finance.trend.length - 1;

        return {
            title: 'Analitik Pemasukan',
            caption: `Pemasukan ${finance.trend.length} bulan terakhir, basis kas`,
            emptyLabel: 'belum ada pemasukan',
            bars: finance.trend.map((row, index): PillBar => {
                const [year, month] = row.month.split('-').map(Number);

                return {
                    key: row.month,
                    label: monthShort.format(Date.UTC(year, month - 1, 1)),
                    value: row.income,
                    display: formatRupiah(row.income),
                    short: formatRupiahShort(row.income),
                    highlight: index === last,
                };
            }),
        };
    }

    const floors = props.summary.room_map.map((floor) => ({
        floor: floor.floor,
        total: floor.rooms.length,
        occupied: floor.rooms.filter((room) => room.status === 'occupied').length,
    }));

    return {
        title: 'Okupansi per Lantai',
        caption: 'Persentase kamar terisi tiap lantai',
        emptyLabel: 'belum ada penghuni',
        bars: floors.map((floor, index): PillBar => {
            const value = floor.total ? Math.round((floor.occupied / floor.total) * 100) : 0;

            return {
                key: String(floor.floor ?? 'none'),
                label: floor.floor === null ? '-' : `Lt ${floor.floor}`,
                value,
                display: `${floorLabel(floor.floor)}: ${floor.occupied}/${floor.total} terisi`,
                short: `${value}%`,
                highlight: index === 0,
            };
        }),
    };
});

// Pengingat: satu hal paling mendesak, berurutan dari uang masuk yang perlu dicek sampai komplain.
interface Reminder {
    title: string;
    detail: string;
    href: string;
    cta: string;
    icon: string;
}

function lateness(days: number): { label: string; tone: 'jatuh-tempo' | 'menunggu' } {
    return days > 0 ? { label: `+${days} hari`, tone: 'jatuh-tempo' } : { label: days === 0 ? 'Hari ini' : `${Math.abs(days)} hari lagi`, tone: 'menunggu' };
}

const more = (count: number) => (count > 1 ? ` · +${count - 1} lainnya` : '');

const reminder = computed<Reminder | null>(() => {
    const { pending_proofs: proofs, due_soon: due, expiring_leases: expiring, complaints } = props.summary;
    const proof = proofs?.items[0];

    if (proofs && proof) {
        return {
            title: `Cek bukti transfer ${proof.resident_name}`,
            detail: `${proof.room_number ? `Kamar ${proof.room_number} · ` : ''}${formatRupiah(proof.amount)}${more(proofs.count)}`,
            href: '/app/bukti-transfer',
            cta: 'Cek Bukti Transfer',
            icon: 'fact_check',
        };
    }

    const overdue = due?.items.find((item) => item.days_late > 0);

    if (due && overdue) {
        return {
            title: `Tagihan ${overdue.resident_name} lewat jatuh tempo`,
            detail: `Terlambat ${overdue.days_late} hari · ${formatRupiah(overdue.outstanding)}${more(due.overdue_count)}`,
            href: `/app/tagihan/${overdue.invoice_id}`,
            cta: 'Lihat Tagihan',
            icon: 'receipt_long',
        };
    }

    const lease = expiring.items[0];

    if (lease) {
        return {
            title: `Kontrak ${lease.resident_name} segera berakhir`,
            detail: `Kamar ${lease.room_number} · ${formatDate(lease.end_date, 'short')}${more(expiring.count)}`,
            href: `/app/kontrak/${lease.lease_id}`,
            cta: 'Tinjau Kontrak',
            icon: 'event',
        };
    }

    const complaint = complaints.items[0];

    if (complaint) {
        return {
            title: complaint.title,
            detail: `${complaint.room_number ? `Kamar ${complaint.room_number} · ` : ''}Dilaporkan ${formatDate(complaint.created_at, 'short')}${more(complaints.count)}`,
            href: `/app/komplain/${complaint.id}`,
            cta: 'Tangani Komplain',
            icon: 'build',
        };
    }

    return null;
});

// Inisial penghuni dan ikon daftar kontrak; warna hanya hiasan, bukan status.
const avatarTones = ['bg-primary-fixed-dim text-on-primary-fixed', 'bg-secondary-fixed text-on-secondary-fixed', 'bg-tertiary-fixed text-on-tertiary-fixed'];
const leaseIcons = [
    { icon: 'event_busy', class: 'text-primary-container' },
    { icon: 'contract', class: 'text-surface-tint' },
    { icon: 'key', class: 'text-secondary-container' },
    { icon: 'home', class: 'text-tertiary-container dark:text-tertiary' },
    { icon: 'calendar_month', class: 'text-primary' },
];
const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase())
        .join('');

const hasQuota = computed(() => !!props.summary.quota && props.summary.quota.limit > 0);

const plateClass: Record<string, string> = {
    occupied: 'border-status-lunas-border bg-status-lunas-bg text-status-lunas-text',
    vacant: 'border-status-kosong-border bg-status-kosong-bg text-status-kosong-text',
    maintenance: 'border-status-menunggu-border bg-status-menunggu-bg text-status-menunggu-text',
};

const pillLink = 'inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border border-text-primary px-space-sm py-1 text-label-md text-text-primary transition-colors hover:bg-text-primary hover:text-bg-surface';
</script>

<template>
    <Head title="Dashboard" />
    <OwnerLayout title="Dashboard" active="dashboard" subtitle="Pantau kamar, tagihan, dan pembayaran kos Anda dengan mudah." framed>
        <template #actions>
            <Button v-if="can.finance" pill icon="add" class="bg-linear-to-r from-primary-container to-primary" @click="router.visit('/app/pembayaran/baru')">Catat Pembayaran</Button>
            <Button pill variant="secondary" class="border-text-primary! text-text-primary!" @click="router.visit('/app/kamar')">Tambah Kamar</Button>
        </template>

        <section class="grid gap-space-md sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan">
            <StatCard
                v-for="stat in stats"
                :key="stat.key"
                :title="stat.title"
                :value="stat.value"
                :href="stat.href"
                :link-label="stat.linkLabel"
                :note="stat.note"
                :badge="stat.badge"
                :highlight="stat.highlight"
                :money="stat.money"
                :tone="stat.tone"
                :sr-value="stat.srValue"
            />
        </section>

        <div class="mt-space-md grid gap-space-md xl:grid-cols-4">
            <!-- Kolom 1-3 -->
            <div class="grid gap-space-md md:grid-cols-3 xl:col-span-3">
                <Panel soft :title="chart.title" class="md:col-span-2">
                    <p v-if="chart.bars.length === 0" class="text-body-md text-text-secondary">Belum ada kamar. Tambahkan kamar lebih dulu.</p>
                    <PillBarChart v-else :bars="chart.bars" :caption="chart.caption" :empty-label="chart.emptyLabel" />
                </Panel>

                <Panel soft title="Pengingat">
                    <template v-if="reminder">
                        <p class="line-clamp-3 text-headline-md font-medium text-primary-container">{{ reminder.title }}</p>
                        <p class="mt-space-xs text-body-sm text-text-muted">{{ reminder.detail }}</p>
                        <div class="mt-auto pt-space-lg">
                            <Link
                                :href="reminder.href"
                                class="flex items-center justify-center gap-space-sm rounded-full bg-linear-to-r from-primary-container to-primary px-space-md py-3 text-title-md font-medium text-on-primary transition-opacity hover:opacity-90"
                            >
                                <span class="material-symbols-outlined icon-fill" aria-hidden="true">{{ reminder.icon }}</span>
                                {{ reminder.cta }}
                            </Link>
                        </div>
                    </template>
                    <div v-else class="flex flex-1 flex-col items-center justify-center gap-space-sm text-center">
                        <span class="material-symbols-outlined icon-fill text-[32px] text-primary-container" aria-hidden="true">task_alt</span>
                        <p class="text-body-md text-text-secondary">Tidak ada yang perlu ditindaklanjuti hari ini.</p>
                    </div>
                </Panel>

                <div class="grid gap-space-md md:col-span-3 md:grid-cols-[1.25fr_1fr]">
                    <Panel v-if="summary.due_soon" soft title="Tagihan Jatuh Tempo">
                        <template #actions>
                            <Link href="/app/pembayaran/baru" :class="pillLink"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">add</span>Catat Bayar</Link>
                        </template>
                        <p v-if="summary.due_soon.items.length === 0" class="text-body-md text-text-secondary">Tidak ada tagihan yang menunggu pembayaran.</p>
                        <ul v-else class="flex flex-col gap-space-md">
                            <li v-for="(item, index) in summary.due_soon.items" :key="item.invoice_id">
                                <Link :href="`/app/tagihan/${item.invoice_id}`" class="group flex items-center gap-space-sm">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full text-title-md" :class="avatarTones[index % avatarTones.length]" aria-hidden="true">
                                        {{ initials(item.resident_name) }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-body-md font-medium text-text-primary group-hover:underline">{{ item.resident_name }}</span>
                                        <span class="block text-body-sm text-text-muted">
                                            <template v-if="item.room_number">Kamar {{ item.room_number }} · </template>sisa
                                            <span class="font-semibold text-text-primary">{{ formatRupiah(item.outstanding) }}</span>
                                        </span>
                                    </span>
                                    <StatusBadge class="shrink-0" :tone="lateness(item.days_late).tone" :label="lateness(item.days_late).label" />
                                </Link>
                            </li>
                        </ul>
                    </Panel>
                    <Panel v-else soft title="Komplain Penghuni">
                        <template #actions>
                            <Link href="/app/komplain" :class="pillLink">Semua</Link>
                        </template>
                        <p v-if="summary.complaints.items.length === 0" class="text-body-md text-text-secondary">Tidak ada komplain yang terbuka.</p>
                        <ul v-else class="flex flex-col gap-space-md">
                            <li v-for="complaint in summary.complaints.items" :key="complaint.id" class="flex items-start justify-between gap-space-sm">
                                <span class="min-w-0">
                                    <Link :href="`/app/komplain/${complaint.id}`" class="block text-body-md font-medium text-text-primary hover:underline">
                                        <template v-if="complaint.room_number">Kamar {{ complaint.room_number }} · </template>{{ complaint.title }}
                                    </Link>
                                    <span class="block text-body-sm text-text-muted">Dilaporkan {{ formatDate(complaint.created_at, 'short') }}</span>
                                </span>
                                <StatusBadge class="shrink-0" :tone="complaint.status_tone" :label="complaint.status_label" />
                            </li>
                        </ul>
                    </Panel>

                    <Panel soft title="Status Kamar">
                        <OccupancyGauge :occupied="counts.occupied" :maintenance="counts.maintenance" :vacant="counts.vacant" :percent="summary.occupancy.percent" />
                    </Panel>
                </div>
            </div>

            <!-- Kolom 4 -->
            <div class="grid gap-space-md md:grid-cols-2 xl:flex xl:flex-col">
                <Panel soft title="Kontrak Berakhir" class="xl:flex-1">
                    <template #actions>
                        <Link href="/app/kontrak/baru" :class="pillLink"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">add</span>Baru</Link>
                    </template>
                    <p v-if="summary.expiring_leases.items.length === 0" class="text-body-md text-text-secondary">
                        Tidak ada kontrak yang berakhir dalam {{ summary.expiring_leases.within_days }} hari ke depan.
                    </p>
                    <ul v-else class="flex flex-col gap-space-md">
                        <li v-for="(item, index) in summary.expiring_leases.items.slice(0, 5)" :key="item.lease_id">
                            <Link :href="`/app/kontrak/${item.lease_id}`" class="group flex items-center gap-space-sm">
                                <span class="material-symbols-outlined icon-fill shrink-0 text-[22px]" :class="leaseIcons[index % leaseIcons.length].class" aria-hidden="true">
                                    {{ leaseIcons[index % leaseIcons.length].icon }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-body-md font-medium text-text-primary group-hover:underline">{{ item.resident_name }} · Kamar {{ item.room_number }}</span>
                                    <span class="block truncate text-label-sm font-normal text-text-muted">Berakhir: {{ formatDate(item.end_date, 'short') }}</span>
                                </span>
                            </Link>
                        </li>
                    </ul>
                </Panel>

                <section
                    v-if="summary.quota && hasQuota"
                    class="relative flex flex-col overflow-hidden rounded-xl bg-linear-to-br from-primary to-primary-container p-space-lg text-on-primary"
                    aria-label="Kuota WhatsApp"
                >
                    <svg class="pointer-events-none absolute inset-0 size-full opacity-25" viewBox="0 0 300 200" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
                        <ellipse v-for="n in 16" :key="n" cx="250" cy="170" :rx="n * 13" :ry="n * 8" transform="rotate(-25 250 170)" stroke="currentColor" stroke-width="0.8" />
                    </svg>
                    <h2 class="relative text-title-lg font-medium">Kuota WhatsApp</h2>
                    <p class="relative mt-space-md text-center text-[2.5rem] font-semibold leading-[3rem] tabular-nums tracking-tight">
                        <span class="sr-only">{{ summary.quota.remaining }} dari {{ summary.quota.limit }}</span>
                        <span aria-hidden="true"
                            >{{ animatedCount(animated.quota.value) }}<span class="text-headline-sm font-normal opacity-75"> / {{ summary.quota.limit }}</span></span
                        >
                    </p>
                    <p class="relative text-center text-body-sm opacity-80">pesan tersisa · {{ summary.quota.used }} terpakai</p>
                    <div v-if="canViewMessages || canManageSubscription" class="relative mt-space-md flex justify-center gap-space-sm">
                        <Link
                            v-if="canViewMessages"
                            href="/app/pesan"
                            class="flex size-11 items-center justify-center rounded-full bg-on-primary text-primary-container transition-opacity hover:opacity-90"
                            aria-label="Lihat log pesan WhatsApp"
                            title="Lihat log pesan WhatsApp"
                        >
                            <span class="material-symbols-outlined icon-fill" aria-hidden="true">chat</span>
                        </Link>
                        <Link
                            v-if="canManageSubscription"
                            href="/app/langganan"
                            class="flex size-11 items-center justify-center rounded-full bg-secondary-container text-on-secondary-container transition-opacity hover:opacity-90"
                            aria-label="Tambah kuota lewat paket langganan"
                            title="Tambah kuota lewat paket langganan"
                        >
                            <span class="material-symbols-outlined icon-fill" aria-hidden="true">add</span>
                        </Link>
                    </div>
                </section>
            </div>
        </div>

        <!-- Tambahan di luar desain acuan: denah kamar, komplain, umur tunggakan -->
        <div class="mt-space-md grid gap-space-md xl:grid-cols-4">
            <Panel soft title="Denah Kamar" :class="summary.finance ? 'xl:col-span-3' : 'xl:col-span-4'">
                <template #actions>
                    <ul class="flex flex-wrap justify-end gap-space-sm text-label-sm text-text-secondary" aria-label="Keterangan status">
                        <li class="flex items-center gap-1"><span class="size-3 rounded-sm border border-status-lunas-border bg-status-lunas-bg" aria-hidden="true" />Terisi ({{ counts.occupied }})</li>
                        <li class="flex items-center gap-1"><span class="size-3 rounded-sm border border-status-kosong-border bg-status-kosong-bg" aria-hidden="true" />Kosong ({{ counts.vacant }})</li>
                        <li class="flex items-center gap-1"><span class="size-3 rounded-sm border border-status-menunggu-border bg-status-menunggu-bg" aria-hidden="true" />Perawatan ({{ counts.maintenance }})</li>
                    </ul>
                </template>
                <p v-if="summary.room_map.length === 0" class="text-body-md text-text-secondary">Belum ada kamar. Tambahkan kamar lebih dulu.</p>
                <div v-else class="flex flex-col gap-space-md">
                    <section v-for="floor in summary.room_map" :key="floor.floor ?? 0">
                        <h3 class="mb-space-sm text-body-md font-medium text-text-secondary">{{ floorLabel(floor.floor) }} · {{ floor.rooms.length }} kamar</h3>
                        <ul class="grid grid-cols-3 gap-space-xs sm:grid-cols-4 md:grid-cols-6">
                            <li v-for="room in floor.rooms" :key="room.id">
                                <Link
                                    href="/app/kamar"
                                    class="flex flex-col items-center rounded-lg border px-space-xs py-space-sm text-center"
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

            <div v-if="summary.finance" class="grid gap-space-md md:grid-cols-2 xl:flex xl:flex-col">
                <Panel soft title="Komplain Penghuni">
                    <template #actions>
                        <Link href="/app/komplain" :class="pillLink">Semua</Link>
                    </template>
                    <p v-if="summary.complaints.items.length === 0" class="text-body-md text-text-secondary">Tidak ada komplain yang terbuka.</p>
                    <ul v-else class="flex flex-col gap-space-md">
                        <li v-for="complaint in summary.complaints.items" :key="complaint.id" class="flex items-start justify-between gap-space-sm">
                            <span class="min-w-0">
                                <Link :href="`/app/komplain/${complaint.id}`" class="block text-body-md font-medium text-text-primary hover:underline">
                                    <template v-if="complaint.room_number">Kamar {{ complaint.room_number }} · </template>{{ complaint.title }}
                                </Link>
                                <span class="block text-body-sm text-text-muted">Dilaporkan {{ formatDate(complaint.created_at, 'short') }}</span>
                            </span>
                            <StatusBadge class="shrink-0" :tone="complaint.status_tone" :label="complaint.status_label" />
                        </li>
                    </ul>
                </Panel>

                <Panel soft title="Umur Tunggakan">
                    <ul class="flex flex-col gap-space-sm">
                        <li v-for="bucket in summary.finance.arrears_buckets" :key="bucket.label" class="flex items-center justify-between gap-space-md">
                            <span>
                                <span class="block text-body-md font-medium text-text-primary">{{ bucket.label }}</span>
                                <span class="block text-body-sm text-text-muted">{{ bucket.count }} tagihan</span>
                            </span>
                            <span class="text-body-md font-semibold text-text-primary">{{ formatRupiah(bucket.amount) }}</span>
                        </li>
                    </ul>
                </Panel>
            </div>
        </div>
    </OwnerLayout>
</template>
