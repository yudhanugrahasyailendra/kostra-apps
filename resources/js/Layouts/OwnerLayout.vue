<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Drawer from '@/Components/Drawer.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import type { SharedProps } from '@/types';

// Kerangka dari mockup dashboard_ringkasan_pemilik. Menu disembunyikan lewat `can`
// hanya demi kerapian; otorisasi sebenarnya diperiksa ulang di server.
const props = withDefaults(defineProps<{ title: string; active?: string }>(), { active: 'dashboard' });

const page = usePage<SharedProps>();
const can = computed(() => page.props.auth?.can);
const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
const flash = computed(() => page.props.flash);
// Melewati batas paket bukan galat teknis: tampilkan penjelasan dan tautan naik paket di halaman mana pun.
const limitError = computed(() => (page.props.errors as Record<string, string>).limit);

interface NavTab {
    key: string;
    label: string;
    href: string;
    visible: boolean;
    download?: boolean;
}

interface NavGroup {
    key: string;
    label: string;
    icon: string;
    // Tab di dalam grup; kunci tab sama dengan prop `active` yang dikirim tiap halaman.
    tabs: NavTab[];
}

// Enam menu mockup (varian A). Halaman lama tetap di URL-nya dan muncul sebagai tab grup induknya.
const groups = computed<NavGroup[]>(() => {
    const finance = !!can.value?.viewFinance;
    const settings = !!can.value?.manageSettings;

    return [
        { key: 'dashboard', label: 'Dashboard', icon: 'dashboard', tabs: [{ key: 'dashboard', label: 'Dashboard', href: '/app', visible: true }] },
        { key: 'kamar', label: 'Kamar & Properti', icon: 'meeting_room', tabs: [{ key: 'kamar', label: 'Kamar', href: '/app/kamar', visible: true }] },
        {
            key: 'tagihan',
            label: 'Tagihan & Bayar',
            icon: 'receipt_long',
            tabs: [
                { key: 'tagihan', label: 'Tagihan', href: '/app/tagihan', visible: finance },
                { key: 'pembayaran', label: 'Pembayaran', href: '/app/pembayaran', visible: finance },
                { key: 'bukti', label: 'Bukti Transfer', href: '/app/bukti-transfer', visible: finance },
            ],
        },
        {
            key: 'penghuni',
            label: 'Penghuni & Kontrak',
            icon: 'groups',
            tabs: [
                { key: 'penghuni', label: 'Penghuni', href: '/app/penghuni', visible: true },
                { key: 'kontrak', label: 'Kontrak Sewa', href: '/app/kontrak', visible: true },
            ],
        },
        { key: 'komplain', label: 'Komplain & Servis', icon: 'build', tabs: [{ key: 'komplain', label: 'Komplain', href: '/app/komplain', visible: true }] },
        {
            key: 'laporan',
            label: 'Laporan Keuangan',
            icon: 'query_stats',
            tabs: [
                { key: 'laporan', label: 'Laporan', href: '/app/laporan', visible: finance },
                { key: 'pengeluaran', label: 'Pengeluaran', href: '/app/pengeluaran', visible: finance },
            ],
        },
        {
            key: 'pengaturan',
            label: 'Pengaturan',
            icon: 'settings',
            tabs: [
                { key: 'pengaturan', label: 'Penagihan', href: '/app/pengaturan/penagihan', visible: settings },
                { key: 'rekening', label: 'Rekening Bayar', href: '/app/pengaturan/rekening', visible: settings },
                { key: 'pesan', label: 'Pesan WhatsApp', href: '/app/pesan', visible: settings },
                { key: 'staf', label: 'Staf', href: '/app/staf', visible: !!can.value?.manageStaff },
                { key: 'audit', label: 'Log Audit', href: '/app/log-audit', visible: !!can.value?.viewAudit },
                { key: 'langganan', label: 'Paket Langganan', href: '/app/langganan', visible: !!can.value?.manageSubscription },
                { key: 'ekspor', label: 'Ekspor Data', href: '/app/ekspor', visible: !!can.value?.exportData, download: true },
            ],
        },
    ]
        .map((group) => ({ ...group, tabs: group.tabs.filter((tab) => tab.visible) }))
        .filter((group) => group.tabs.length > 0);
});

// Grup 'kamar' sudah punya tab sendiri (RoomsTabs) yang juga memuat Properti dan Tipe kamar.
const roomKeys = ['kamar', 'properti', 'tipe'];
const activeGroupKey = computed(() => {
    if (roomKeys.includes(props.active)) {
        return 'kamar';
    }

    return groups.value.find((group) => group.tabs.some((tab) => tab.key === props.active))?.key ?? props.active;
});
const activeGroup = computed(() => groups.value.find((group) => group.key === activeGroupKey.value));
const sectionTabs = computed(() => (activeGroupKey.value === 'kamar' ? [] : (activeGroup.value?.tabs ?? [])));

const menuOpen = ref(false);

function linkClass(key: string) {
    return key === activeGroupKey.value
        ? 'bg-primary-container text-on-primary shadow-low'
        : 'text-on-surface-variant hover:bg-bg-subtle hover:text-on-surface';
}
</script>

<template>
    <div class="min-h-screen bg-bg-canvas">
        <!-- Sidebar desktop -->
        <aside class="fixed inset-y-0 left-0 z-50 hidden w-72 print:!hidden flex-col justify-between bg-bg-surface shadow-low lg:flex">
            <div>
                <div class="flex h-16 items-center px-space-md">
                    <div class="flex flex-col leading-tight">
                        <span class="text-title-lg font-bold text-primary">Kostra</span>
                        <span class="text-label-sm text-text-muted">Kost Rapi</span>
                    </div>
                </div>
                <nav class="flex flex-col gap-space-xs px-space-md py-space-sm" aria-label="Menu utama">
                    <Link
                        v-for="group in groups"
                        :key="group.key"
                        :href="group.tabs[0].href"
                        class="flex items-center gap-space-sm rounded-md px-space-md py-space-sm text-title-md transition-colors"
                        :class="linkClass(group.key)"
                        :aria-current="group.key === activeGroupKey ? 'page' : undefined"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">{{ group.icon }}</span>
                        <span>{{ group.label }}</span>
                    </Link>
                </nav>
            </div>
            <div class="p-space-md">
                <Link href="/keluar" method="post" as="button" class="flex w-full items-center gap-space-sm rounded-md px-space-md py-space-sm text-title-md text-on-surface-variant hover:bg-bg-subtle">
                    <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                    <span>Keluar</span>
                </Link>
            </div>
        </aside>

        <!-- Menu ponsel dan tablet -->
        <Drawer :show="menuOpen" title="Menu" side="left" @close="menuOpen = false">
            <nav class="flex flex-col gap-space-xs p-space-md" aria-label="Menu utama">
                <Link
                    v-for="group in groups"
                    :key="group.key"
                    :href="group.tabs[0].href"
                    class="flex items-center gap-space-sm rounded-md px-space-md py-space-sm text-title-md"
                    :class="linkClass(group.key)"
                    @click="menuOpen = false"
                >
                    <span class="material-symbols-outlined" aria-hidden="true">{{ group.icon }}</span>
                    <span>{{ group.label }}</span>
                </Link>
                <Link href="/keluar" method="post" as="button" class="flex items-center gap-space-sm rounded-md px-space-md py-space-sm text-title-md text-on-surface-variant hover:bg-bg-subtle">
                    <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                    <span>Keluar</span>
                </Link>
            </nav>
        </Drawer>

        <div class="lg:pl-72 print:!pl-0">
            <header
                class="fixed inset-x-0 top-0 z-40 flex h-16 print:!hidden items-center justify-between bg-bg-surface/90 px-margin shadow-low backdrop-blur-xl lg:left-72 lg:px-space-xl"
            >
                <div class="flex items-center gap-space-sm">
                    <button
                        type="button"
                        class="rounded-md p-space-xs text-on-surface-variant hover:bg-bg-subtle lg:hidden"
                        aria-label="Buka menu"
                        @click="menuOpen = true"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">menu</span>
                    </button>
                    <div class="flex items-center gap-space-xs rounded-md bg-bg-subtle px-space-sm py-1.5">
                        <span class="material-symbols-outlined text-text-secondary" aria-hidden="true">home_work</span>
                        <span class="max-w-40 truncate text-title-md text-text-primary">{{ tenant?.name }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-space-sm">
                    <ThemeToggle />
                    <div class="hidden flex-col text-right sm:flex">
                        <span class="text-title-md leading-snug text-text-primary">{{ user?.name }}</span>
                        <span class="text-label-sm text-text-secondary">{{ user?.role_label }}</span>
                    </div>
                    <div class="flex size-8 items-center justify-center rounded-full bg-primary" aria-hidden="true">
                        <span class="material-symbols-outlined text-on-primary">person</span>
                    </div>
                </div>
            </header>

            <main class="min-h-screen px-margin pb-space-lg pt-24 lg:px-space-xl print:!min-h-0 print:!p-0">
                <p
                    v-if="tenant?.suspended"
                    class="mb-space-md rounded-md border border-status-menunggu-border bg-status-menunggu-bg p-space-sm text-body-md text-status-menunggu-text"
                    role="alert"
                >
                    Akun kos ini ditangguhkan. Anda masih dapat melihat dan mengekspor data, tetapi tidak dapat mengubahnya.
                </p>
                <p
                    v-if="flash.success"
                    class="mb-space-md rounded-md border border-status-lunas-border bg-status-lunas-bg p-space-sm text-body-md text-status-lunas-text print:hidden"
                    role="status"
                >
                    {{ flash.success }}
                </p>
                <div
                    v-if="limitError"
                    class="mb-space-md flex flex-wrap items-center justify-between gap-space-sm rounded-md border border-status-menunggu-border bg-status-menunggu-bg p-space-md text-status-menunggu-text print:hidden"
                    role="alert"
                >
                    <p class="text-body-md">{{ limitError }}</p>
                    <Link v-if="can?.manageSubscription" href="/app/langganan" class="text-label-md underline">Lihat paket langganan</Link>
                </div>
                <h1 class="mb-space-lg print:hidden text-display-lg-mobile text-text-primary lg:text-headline-lg">{{ title }}</h1>
                <nav
                    v-if="sectionTabs.length > 1"
                    class="mb-space-lg flex gap-space-xs overflow-x-auto rounded-md bg-bg-subtle p-space-xs print:hidden sm:inline-flex"
                    aria-label="Bagian halaman"
                >
                    <component
                        :is="tab.download ? 'a' : Link"
                        v-for="tab in sectionTabs"
                        :key="tab.key"
                        :href="tab.href"
                        class="shrink-0 rounded-md px-space-md py-space-sm text-center text-title-md"
                        :class="tab.key === active ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary hover:text-text-primary'"
                        :aria-current="tab.key === active ? 'page' : undefined"
                    >
                        {{ tab.label }}
                    </component>
                </nav>
                <slot />
            </main>
        </div>
    </div>
</template>
