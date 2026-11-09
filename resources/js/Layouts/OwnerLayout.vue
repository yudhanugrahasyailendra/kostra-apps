<script setup lang="ts">
import { ref } from 'vue';
import Drawer from '@/Components/Drawer.vue';

// Kerangka dari mockup dashboard_ringkasan_pemilik. Data tenant dan pengguna
// akan datang dari props bersama (HandleInertiaRequests) mulai Fase 1.
withDefaults(
    defineProps<{
        title: string;
        tenantName?: string;
        userName?: string;
        userRole?: string;
        active?: string;
    }>(),
    { tenantName: 'Kost Anda', userName: 'Pemilik', userRole: 'Pemilik Kost', active: 'dashboard' },
);

const navItems = [
    { key: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    { key: 'properti', label: 'Kamar & Properti', icon: 'meeting_room' },
    { key: 'tagihan', label: 'Tagihan & Bayar', icon: 'receipt_long' },
    { key: 'penghuni', label: 'Penghuni & Kontrak', icon: 'group' },
    { key: 'komplain', label: 'Komplain & Servis', icon: 'build' },
    { key: 'laporan', label: 'Laporan Keuangan', icon: 'query_stats' },
    { key: 'pengaturan', label: 'Pengaturan', icon: 'settings' },
];

const menuOpen = ref(false);
</script>

<template>
    <div class="min-h-screen bg-bg-canvas">
        <!-- Sidebar desktop -->
        <aside class="fixed inset-y-0 left-0 z-50 hidden w-64 flex-col bg-bg-surface shadow-low lg:flex">
            <div class="flex h-16 items-center px-space-md">
                <div class="flex flex-col leading-tight">
                    <span class="text-title-lg font-bold text-primary">Kostra</span>
                    <span class="text-label-sm text-text-muted">Kost Rapi</span>
                </div>
            </div>
            <nav class="flex flex-col gap-space-xs px-space-md py-space-sm" aria-label="Menu utama">
                <a
                    v-for="item in navItems"
                    :key="item.key"
                    href="#"
                    class="flex items-center gap-space-sm rounded-md px-space-md py-space-sm text-title-md transition-colors"
                    :class="
                        item.key === active
                            ? 'bg-primary-container text-on-primary shadow-low'
                            : 'text-on-surface-variant hover:bg-bg-subtle hover:text-on-surface'
                    "
                    :aria-current="item.key === active ? 'page' : undefined"
                >
                    <span class="material-symbols-outlined" aria-hidden="true">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </a>
            </nav>
        </aside>

        <!-- Menu ponsel dan tablet -->
        <Drawer :show="menuOpen" title="Menu" side="left" @close="menuOpen = false">
            <nav class="flex flex-col gap-space-xs p-space-md" aria-label="Menu utama">
                <a
                    v-for="item in navItems"
                    :key="item.key"
                    href="#"
                    class="flex items-center gap-space-sm rounded-md px-space-md py-space-sm text-title-md"
                    :class="item.key === active ? 'bg-primary-container text-on-primary' : 'text-on-surface-variant hover:bg-bg-subtle'"
                    @click="menuOpen = false"
                >
                    <span class="material-symbols-outlined" aria-hidden="true">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </a>
            </nav>
        </Drawer>

        <div class="lg:pl-64">
            <header
                class="fixed inset-x-0 top-0 z-40 flex h-16 items-center justify-between bg-bg-surface/90 px-margin shadow-low backdrop-blur-xl lg:left-64 lg:px-space-xl"
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
                        <span class="max-w-40 truncate text-title-md text-text-primary">{{ tenantName }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-space-sm">
                    <div class="hidden flex-col text-right sm:flex">
                        <span class="text-title-md leading-snug text-text-primary">{{ userName }}</span>
                        <span class="text-label-sm text-text-secondary">{{ userRole }}</span>
                    </div>
                    <div class="flex size-8 items-center justify-center rounded-full bg-primary" aria-hidden="true">
                        <span class="material-symbols-outlined text-on-primary">person</span>
                    </div>
                </div>
            </header>

            <main class="min-h-screen px-margin pb-space-lg pt-24 lg:px-space-xl">
                <h1 class="mb-space-lg text-display-lg-mobile text-text-primary lg:text-headline-lg">{{ title }}</h1>
                <slot />
            </main>
        </div>
    </div>
</template>
