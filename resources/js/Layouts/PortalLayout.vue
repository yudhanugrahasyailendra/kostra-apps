<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { portalBase } from '@/lib/portal';
import type { PortalShell, SharedProps } from '@/types';

// Kerangka mobile-first dari mockup portal_penghuni_mobile: header kos, isi, tabbar bawah.
const props = withDefaults(defineProps<{ shell: PortalShell; active?: string; title?: string }>(), { active: 'tagihan', title: undefined });

const page = usePage<SharedProps>();
const flash = computed(() => page.props.flash);
const base = computed(() => portalBase());

const tabs = computed(() => [
    { key: 'tagihan', label: 'Tagihan', icon: 'receipt_long', href: base.value },
    { key: 'komplain', label: 'Komplain', icon: 'build_circle', href: `${base.value}/komplain` },
    { key: 'riwayat', label: 'Riwayat', icon: 'history', href: `${base.value}/riwayat` },
    { key: 'kontrak', label: 'Kontrak', icon: 'assignment', href: `${base.value}/kontrak` },
]);
</script>

<template>
    <div class="min-h-screen bg-bg-canvas pb-24">
        <header class="bg-primary text-on-primary">
            <div class="mx-auto flex max-w-xl items-center justify-between gap-space-sm px-space-md py-space-md">
                <div class="min-w-0">
                    <p class="truncate text-title-lg font-bold">{{ props.shell.business_name }}</p>
                    <p class="truncate text-body-sm text-on-primary-container">
                        Halo, {{ props.shell.resident.name }}<template v-if="props.shell.room_number"> (Kamar {{ props.shell.room_number }})</template>
                    </p>
                </div>
                <ThemeToggle />
            </div>
        </header>

        <main class="mx-auto max-w-xl px-space-md py-space-md">
            <p v-if="props.shell.read_only" class="mb-space-md rounded-md border border-status-menunggu-border bg-status-menunggu-bg p-space-sm text-body-sm text-status-menunggu-text" role="status">
                Layanan kos ini sedang dijeda. Anda masih dapat melihat data, tetapi belum dapat mengirim bukti atau komplain.
            </p>
            <p v-if="flash.success" class="mb-space-md rounded-md border border-status-lunas-border bg-status-lunas-bg p-space-sm text-body-md text-status-lunas-text" role="status">
                {{ flash.success }}
            </p>
            <h1 v-if="props.title" class="mb-space-md text-headline-sm text-text-primary">{{ props.title }}</h1>
            <slot />
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-border-subtle bg-bg-surface" aria-label="Menu portal">
            <ul class="mx-auto grid max-w-xl grid-cols-4">
                <li v-for="tab in tabs" :key="tab.key">
                    <Link
                        :href="tab.href"
                        class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 py-2 text-label-sm"
                        :class="tab.key === props.active ? 'text-primary-container' : 'text-text-secondary'"
                        :aria-current="tab.key === props.active ? 'page' : undefined"
                    >
                        <span class="material-symbols-outlined text-[24px]" aria-hidden="true">{{ tab.icon }}</span>
                        {{ tab.label }}
                    </Link>
                </li>
            </ul>
        </nav>
    </div>
</template>
