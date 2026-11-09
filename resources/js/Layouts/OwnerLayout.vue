<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Drawer from '@/Components/Drawer.vue';
import type { SharedProps } from '@/types';

// Kerangka dari mockup dashboard_ringkasan_pemilik. Menu disembunyikan lewat `can`
// hanya demi kerapian; otorisasi sebenarnya diperiksa ulang di server.
const props = withDefaults(defineProps<{ title: string; active?: string }>(), { active: 'dashboard' });

const page = usePage<SharedProps>();
const can = computed(() => page.props.auth?.can);
const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
const flash = computed(() => page.props.flash);

interface NavItem {
    key: string;
    label: string;
    icon: string;
    href: string;
    visible: boolean;
    download?: boolean;
}

const navItems = computed<NavItem[]>(() => [
    { key: 'dashboard', label: 'Dashboard', icon: 'dashboard', href: '/app', visible: true },
    { key: 'kamar', label: 'Kamar & Properti', icon: 'meeting_room', href: '/app/kamar', visible: true },
    { key: 'penghuni', label: 'Penghuni', icon: 'groups', href: '/app/penghuni', visible: true },
    { key: 'kontrak', label: 'Kontrak Sewa', icon: 'assignment', href: '/app/kontrak', visible: true },
    { key: 'tagihan', label: 'Tagihan', icon: 'receipt_long', href: '/app/tagihan', visible: !!can.value?.viewFinance },
    { key: 'pembayaran', label: 'Pembayaran', icon: 'payments', href: '/app/pembayaran', visible: !!can.value?.viewFinance },
    { key: 'bukti', label: 'Bukti Transfer', icon: 'fact_check', href: '/app/bukti-transfer', visible: !!can.value?.viewFinance },
    { key: 'pengeluaran', label: 'Pengeluaran', icon: 'outbox', href: '/app/pengeluaran', visible: !!can.value?.viewFinance },
    { key: 'laporan', label: 'Laporan Keuangan', icon: 'query_stats', href: '/app/laporan', visible: !!can.value?.viewFinance },
    { key: 'rekening', label: 'Rekening Bayar', icon: 'account_balance', href: '/app/pengaturan/rekening', visible: !!can.value?.manageSettings },
    { key: 'staf', label: 'Staf', icon: 'badge', href: '/app/staf', visible: !!can.value?.manageStaff },
    { key: 'audit', label: 'Log Audit', icon: 'policy', href: '/app/log-audit', visible: !!can.value?.viewAudit },
    { key: 'pengaturan', label: 'Pengaturan', icon: 'settings', href: '/app/pengaturan/penagihan', visible: !!can.value?.manageSettings },
    { key: 'ekspor', label: 'Ekspor Data', icon: 'download', href: '/app/ekspor', visible: !!can.value?.exportData, download: true },
].filter((item) => item.visible));

const menuOpen = ref(false);

function linkClass(key: string) {
    return key === props.active
        ? 'bg-primary-container text-on-primary shadow-low'
        : 'text-on-surface-variant hover:bg-bg-subtle hover:text-on-surface';
}
</script>

<template>
    <div class="min-h-screen bg-bg-canvas">
        <!-- Sidebar desktop -->
        <aside class="fixed inset-y-0 left-0 z-50 hidden w-64 print:!hidden flex-col justify-between bg-bg-surface shadow-low lg:flex">
            <div>
                <div class="flex h-16 items-center px-space-md">
                    <div class="flex flex-col leading-tight">
                        <span class="text-title-lg font-bold text-primary">Kostra</span>
                        <span class="text-label-sm text-text-muted">Kost Rapi</span>
                    </div>
                </div>
                <nav class="flex flex-col gap-space-xs px-space-md py-space-sm" aria-label="Menu utama">
                    <component
                        :is="item.download ? 'a' : Link"
                        v-for="item in navItems"
                        :key="item.key"
                        :href="item.href"
                        class="flex items-center gap-space-sm rounded-md px-space-md py-space-sm text-title-md transition-colors"
                        :class="linkClass(item.key)"
                        :aria-current="item.key === active ? 'page' : undefined"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">{{ item.icon }}</span>
                        <span>{{ item.label }}</span>
                    </component>
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
                <component
                    :is="item.download ? 'a' : Link"
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    class="flex items-center gap-space-sm rounded-md px-space-md py-space-sm text-title-md"
                    :class="linkClass(item.key)"
                    @click="menuOpen = false"
                >
                    <span class="material-symbols-outlined" aria-hidden="true">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </component>
                <Link href="/keluar" method="post" as="button" class="flex items-center gap-space-sm rounded-md px-space-md py-space-sm text-title-md text-on-surface-variant hover:bg-bg-subtle">
                    <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                    <span>Keluar</span>
                </Link>
            </nav>
        </Drawer>

        <div class="lg:pl-64 print:!pl-0">
            <header
                class="fixed inset-x-0 top-0 z-40 flex h-16 print:!hidden items-center justify-between bg-bg-surface/90 px-margin shadow-low backdrop-blur-xl lg:left-64 lg:px-space-xl"
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
                <h1 class="mb-space-lg print:hidden text-display-lg-mobile text-text-primary lg:text-headline-lg">{{ title }}</h1>
                <slot />
            </main>
        </div>
    </div>
</template>
