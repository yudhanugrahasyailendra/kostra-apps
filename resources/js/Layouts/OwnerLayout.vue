<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Drawer from '@/Components/Drawer.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { buildNotifications } from '@/lib/notifications';
import type { SharedProps } from '@/types';

// Kerangka dari mockup dashboard_ringkasan_pemilik. Menu disembunyikan lewat `can`
// hanya demi kerapian; otorisasi sebenarnya diperiksa ulang di server.
// `framed`: isi halaman diletakkan di panel abu-abu bersudut bulat (dipakai dashboard). Halaman lain tetap di kanvas.
const props = withDefaults(defineProps<{ title: string; active?: string; subtitle?: string; framed?: boolean }>(), {
    active: 'dashboard',
    subtitle: undefined,
    framed: false,
});

const page = usePage<SharedProps>();
const can = computed(() => page.props.auth?.can);
const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
const flash = computed(() => page.props.flash);
// Lonceng di semua halaman pemilik; hitungan dibagikan server lewat props `notifications`.
const notifications = computed(() => (page.props.notifications ? buildNotifications(page.props.notifications, can.value ?? {}) : null));
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

// Bagian "Menu" berisi pekerjaan harian; "Umum" berisi pengaturan akun, terpisah seperti di sidebar acuan.
const menuGroups = computed(() => groups.value.filter((group) => group.key !== 'pengaturan'));
const generalGroups = computed(() => groups.value.filter((group) => group.key === 'pengaturan'));

const initials = computed(() =>
    (user.value?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase())
        .join(''),
);

const menuOpen = ref(false);

const isActive = (key: string) => key === activeGroupKey.value;

// Warna menu mengikuti logo (docs/plan/brand): teal untuk menu, emas untuk aksen. Dipetakan ke token
// terdekat DESIGN.md: teal logo #105F62 ≈ primary-container, emas logo #DBA63C ≈ secondary-container.
// Emas hanya untuk aksen non-teks (garis, ikon di atas teal), bukan teks di atas putih.
function linkClass(key: string) {
    return isActive(key)
        ? 'bg-primary-container font-semibold text-on-primary shadow-low before:absolute before:-left-space-md before:top-1/2 before:h-8 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-secondary-container dark:before:bg-secondary'
        : 'text-text-secondary hover:bg-primary-container/10 hover:text-primary-container';
}

function iconClass(key: string) {
    return isActive(key) ? 'icon-fill text-secondary-container dark:text-on-primary' : 'text-primary-container';
}

// Label bagian menu dengan garis emas pendek, seperti "— Kost Rapi —" pada logo.
const sectionLabelClass = 'flex items-center gap-space-sm px-space-md text-label-sm uppercase tracking-[0.2em] text-primary-container';
</script>

<template>
    <div class="isolate min-h-screen bg-bg-canvas">
        <!-- Aksen latar: cahaya teal (kiri atas) dan emas (kanan bawah) dari warna logo. Hanya hiasan. -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden print:hidden" aria-hidden="true">
            <div class="absolute -left-48 -top-48 size-[40rem] rounded-full bg-primary-container/20 blur-3xl" />
            <div class="absolute -bottom-56 -right-40 size-[36rem] rounded-full bg-secondary-container/25 blur-3xl" />
            <div class="absolute left-1/2 top-1/3 size-[28rem] -translate-x-1/2 rounded-full bg-primary-fixed-dim/15 blur-3xl" />
        </div>
        <!-- Sidebar desktop: panel melayang dengan bagian Menu dan Umum -->
        <aside
            class="scrollbar-none fixed inset-y-space-md left-space-md z-50 hidden w-64 print:!hidden flex-col overflow-y-auto rounded-xl panel-glass lg:flex"
        >
            <!-- Layar pendek: jarak dirapatkan dan kartu paket disembunyikan supaya sidebar muat tanpa digulir. -->
            <!-- Logo dari docs/plan/brand/Logo Kostra (Kost Rapi).png: simbol rumah-K + tulisan "Kostra" (latar transparan),
                 tagline "Kost Rapi" dengan garis emas seperti logo aslinya. Tema gelap: alas terang supaya teal terbaca. -->
            <Link href="/app" class="flex flex-col items-start gap-1 px-space-lg pb-space-sm pt-space-md tall:pb-space-md tall:pt-space-lg" aria-label="Kostra, Kost Rapi. Ke dashboard">
                <img
                    src="/images/kostra-logo-horizontal.png"
                    alt=""
                    width="450"
                    height="96"
                    class="h-10 w-auto dark:rounded-lg dark:bg-inverse-surface dark:px-space-sm dark:py-1"
                />
                <span class="flex w-full max-w-47 items-center gap-space-sm text-label-sm tracking-[0.3em] text-primary-container" aria-hidden="true">
                    <span class="h-0.5 flex-1 rounded-full bg-secondary-container dark:bg-secondary" />Kost Rapi<span class="h-0.5 flex-1 rounded-full bg-secondary-container dark:bg-secondary" />
                </span>
            </Link>
            <nav class="flex flex-1 flex-col px-space-md" aria-label="Menu utama">
                <p :class="sectionLabelClass" class="pb-space-xs pt-space-sm tall:pb-space-sm tall:pt-space-md">
                    <span class="h-0.5 w-4 rounded-full bg-secondary-container dark:bg-secondary" aria-hidden="true" />Menu
                </p>
                <Link
                    v-for="group in menuGroups"
                    :key="group.key"
                    :href="group.tabs[0].href"
                    class="relative flex items-center gap-space-sm rounded-md px-space-md py-2 text-body-lg transition-colors tall:py-2.5"
                    :class="linkClass(group.key)"
                    :aria-current="isActive(group.key) ? 'page' : undefined"
                >
                    <span class="material-symbols-outlined" :class="iconClass(group.key)" aria-hidden="true">{{ group.icon }}</span>
                    <span>{{ group.label }}</span>
                </Link>
                <p :class="sectionLabelClass" class="pb-space-xs pt-space-md tall:pb-space-sm tall:pt-space-lg">
                    <span class="h-0.5 w-4 rounded-full bg-secondary-container dark:bg-secondary" aria-hidden="true" />Umum
                </p>
                <Link
                    v-for="group in generalGroups"
                    :key="group.key"
                    :href="group.tabs[0].href"
                    class="relative flex items-center gap-space-sm rounded-md px-space-md py-2 text-body-lg transition-colors tall:py-2.5"
                    :class="linkClass(group.key)"
                    :aria-current="isActive(group.key) ? 'page' : undefined"
                >
                    <span class="material-symbols-outlined" :class="iconClass(group.key)" aria-hidden="true">{{ group.icon }}</span>
                    <span>{{ group.label }}</span>
                </Link>
                <Link
                    href="/keluar"
                    method="post"
                    as="button"
                    class="flex w-full items-center gap-space-sm rounded-md px-space-md py-2 text-body-lg text-text-secondary transition-colors hover:bg-primary-container/10 hover:text-primary-container tall:py-2.5"
                >
                    <span class="material-symbols-outlined text-primary-container" aria-hidden="true">logout</span>
                    <span>Keluar</span>
                </Link>
            </nav>
            <div v-if="can?.manageSubscription" class="hidden p-space-md roomy:block">
                <div class="relative overflow-hidden rounded-xl bg-linear-to-br from-primary to-primary-container p-space-md text-on-primary">
                    <svg class="pointer-events-none absolute inset-0 size-full opacity-20" viewBox="0 0 200 220" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
                        <ellipse v-for="n in 9" :key="n" cx="190" cy="230" :rx="n * 22" :ry="n * 16" stroke="currentColor" stroke-width="1" />
                    </svg>
                    <span class="relative flex size-7 items-center justify-center rounded-full bg-secondary-container text-on-secondary-container" aria-hidden="true">
                        <span class="material-symbols-outlined icon-fill text-[16px]">workspace_premium</span>
                    </span>
                    <p class="relative mt-space-md text-title-lg font-medium">Paket langganan</p>
                    <p class="relative mt-space-xs text-body-sm opacity-80">Tambah kamar dan kuota WhatsApp sesuai kebutuhan.</p>
                    <Link
                        href="/app/langganan"
                        class="relative mt-space-lg flex items-center justify-center rounded-full bg-secondary-container px-space-md py-2.5 text-title-md text-on-secondary-container shadow-low transition-colors hover:bg-secondary-fixed"
                        >Lihat paket</Link
                    >
                </div>
            </div>
        </aside>

        <!-- Menu ponsel dan tablet -->
        <Drawer :show="menuOpen" title="Menu" side="left" @close="menuOpen = false">
            <nav class="flex flex-col p-space-md" aria-label="Menu utama">
                <img src="/images/kostra-logo-horizontal.png" alt="Kostra" width="450" height="96" class="mb-space-md ml-space-md h-8 w-auto self-start dark:rounded-lg dark:bg-inverse-surface dark:px-space-sm dark:py-1" />
                <p :class="sectionLabelClass" class="pb-space-sm"><span class="h-0.5 w-4 rounded-full bg-secondary-container dark:bg-secondary" aria-hidden="true" />Menu</p>
                <Link
                    v-for="group in menuGroups"
                    :key="group.key"
                    :href="group.tabs[0].href"
                    class="relative flex items-center gap-space-sm rounded-md px-space-md py-2.5 text-body-lg"
                    :class="linkClass(group.key)"
                    :aria-current="isActive(group.key) ? 'page' : undefined"
                    @click="menuOpen = false"
                >
                    <span class="material-symbols-outlined" :class="iconClass(group.key)" aria-hidden="true">{{ group.icon }}</span>
                    <span>{{ group.label }}</span>
                </Link>
                <p :class="sectionLabelClass" class="pb-space-sm pt-space-lg"><span class="h-0.5 w-4 rounded-full bg-secondary-container dark:bg-secondary" aria-hidden="true" />Umum</p>
                <Link
                    v-for="group in generalGroups"
                    :key="group.key"
                    :href="group.tabs[0].href"
                    class="relative flex items-center gap-space-sm rounded-md px-space-md py-2.5 text-body-lg"
                    :class="linkClass(group.key)"
                    :aria-current="isActive(group.key) ? 'page' : undefined"
                    @click="menuOpen = false"
                >
                    <span class="material-symbols-outlined" :class="iconClass(group.key)" aria-hidden="true">{{ group.icon }}</span>
                    <span>{{ group.label }}</span>
                </Link>
                <Link
                    href="/keluar"
                    method="post"
                    as="button"
                    class="flex items-center gap-space-sm rounded-md px-space-md py-2.5 text-body-lg text-text-secondary hover:bg-primary-container/10 hover:text-primary-container"
                >
                    <span class="material-symbols-outlined text-primary-container" aria-hidden="true">logout</span>
                    <span>Keluar</span>
                </Link>
            </nav>
        </Drawer>

        <div class="lg:pl-72 lg:pr-space-md print:!p-0">
            <header
                class="fixed inset-x-0 top-0 z-40 flex h-16 print:!hidden items-center justify-between gap-space-sm bg-bg-surface/90 px-margin shadow-low backdrop-blur-xl lg:left-72 lg:right-space-md lg:top-space-md lg:h-[4.5rem] lg:rounded-xl lg:px-space-lg lg:panel-glass"
            >
                <div class="flex min-w-0 items-center gap-space-sm">
                    <button
                        type="button"
                        class="rounded-full p-space-sm text-on-surface-variant hover:bg-bg-subtle lg:hidden"
                        aria-label="Buka menu"
                        @click="menuOpen = true"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">menu</span>
                    </button>
                    <div class="flex min-w-0 items-center gap-space-sm rounded-full bg-bg-subtle px-space-md py-2.5 sm:min-w-72 lg:bg-bg-surface">
                        <span class="material-symbols-outlined text-primary-container" aria-hidden="true">home_work</span>
                        <span class="truncate text-body-md text-text-secondary">{{ tenant?.name }}</span>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-space-sm sm:gap-space-lg">
                    <div class="flex items-center gap-space-sm">
                        <ThemeToggle round />
                        <!-- Tombol tambahan dari halaman, bila ada. -->
                        <slot name="header-actions" />
                        <NotificationBell v-if="notifications" :items="notifications" />
                    </div>
                    <div class="flex items-center gap-space-sm">
                        <div
                            class="flex size-11 items-center justify-center rounded-full bg-secondary-fixed text-title-lg text-on-secondary-fixed"
                            aria-hidden="true"
                        >
                            {{ initials }}
                        </div>
                        <div class="hidden flex-col sm:flex">
                            <span class="text-title-lg font-medium leading-snug text-text-primary">{{ user?.name }}</span>
                            <span class="text-body-sm text-text-muted">{{ user?.email ?? user?.role_label }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <main
                class="min-h-screen px-margin pb-space-lg pt-24 print:!m-0 print:!min-h-0 print:!bg-transparent print:!p-0"
                :class="
                    framed
                        ? 'relative isolate panel-tint lg:mb-space-md lg:mt-[6.5rem] lg:min-h-[calc(100vh-7.5rem)] lg:rounded-xl lg:p-space-lg'
                        : 'lg:px-0 lg:pt-28'
                "
            >
                <!-- Aksen bingkai: garis atap rumah dan jendela emas dari logo, samar di pojok kanan atas. -->
                <div v-if="framed" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden lg:rounded-xl print:hidden" aria-hidden="true">
                    <svg class="absolute -right-16 -top-20 w-120 max-w-[90%]" viewBox="0 0 320 200" fill="none">
                        <path d="M 24 150 L 160 46 L 296 150" class="stroke-primary-container/[0.07]" stroke-width="18" stroke-linecap="round" stroke-linejoin="round" />
                        <rect x="168" y="92" width="14" height="14" rx="2" class="fill-secondary-container/20" />
                        <rect x="186" y="92" width="14" height="14" rx="2" class="fill-secondary-container/20" />
                        <rect x="168" y="110" width="14" height="14" rx="2" class="fill-secondary-container/20" />
                        <rect x="186" y="110" width="14" height="14" rx="2" class="fill-secondary-container/20" />
                    </svg>
                </div>
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
                <div class="mb-space-lg flex flex-wrap items-end justify-between gap-space-md print:hidden">
                    <div>
                        <h1 class="text-display-lg-mobile font-semibold text-text-primary lg:text-display-lg lg:font-medium">{{ title }}</h1>
                        <!-- Aksen judul: garis teal dan emas seperti garis di bawah "Kost Rapi" pada logo. -->
                        <span class="mt-space-xs flex gap-1" aria-hidden="true">
                            <span class="h-1 w-10 rounded-full bg-primary-container" />
                            <span class="h-1 w-4 rounded-full bg-secondary-container dark:bg-secondary" />
                        </span>
                        <p v-if="subtitle" class="mt-space-sm text-body-md text-text-muted">{{ subtitle }}</p>
                    </div>
                    <div v-if="$slots.actions" class="flex flex-wrap gap-space-sm">
                        <slot name="actions" />
                    </div>
                </div>
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
                        :class="tab.key === active ? 'bg-primary-container text-on-primary shadow-low' : 'text-text-secondary hover:text-primary-container'"
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
