<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import type { SharedProps } from '@/types';

// Kerangka panel Super Admin (mockup panel_super_admin_kostra). Telemetri WhatsApp Cloud API, siaran pesan
// nasional, dan mode impersonasi sengaja tidak ada: semuanya tahap berikutnya.
defineProps<{ title: string }>();

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash);
const errors = computed(() => Object.values(page.props.errors as Record<string, string>));
</script>

<template>
    <div class="min-h-screen bg-bg-canvas">
        <header class="fixed inset-x-0 top-0 z-40 flex h-16 items-center justify-between bg-bg-surface/90 px-margin shadow-low backdrop-blur-xl lg:px-space-xl">
            <div class="flex items-center gap-space-lg">
                <div class="flex flex-col leading-tight">
                    <span class="text-title-lg font-bold text-primary">Kostra</span>
                    <span class="text-label-sm text-text-muted">Super Admin</span>
                </div>
                <nav aria-label="Menu admin">
                    <Link href="/admin/tenant" class="flex items-center gap-space-xs rounded-md bg-primary-container px-space-md py-space-xs text-title-md text-on-primary" aria-current="page">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">apartment</span>
                        Tenant
                    </Link>
                </nav>
            </div>
            <div class="flex items-center gap-space-md">
                <ThemeToggle />
                <span class="hidden text-title-md text-text-primary sm:inline">{{ user?.name }}</span>
                <Link href="/keluar" method="post" as="button" class="flex items-center gap-space-xs rounded-md px-space-sm py-space-xs text-title-md text-on-surface-variant hover:bg-bg-subtle">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">logout</span>
                    Keluar
                </Link>
            </div>
        </header>

        <main class="mx-auto min-h-screen max-w-7xl px-margin pb-space-lg pt-24 lg:px-space-xl">
            <p v-if="flash.success" class="mb-space-md rounded-md border border-status-lunas-border bg-status-lunas-bg p-space-sm text-body-md text-status-lunas-text" role="status">{{ flash.success }}</p>
            <p v-for="message in errors" :key="message" class="mb-space-md rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-md text-status-jatuh-tempo-text" role="alert">{{ message }}</p>
            <h1 class="mb-space-lg text-display-lg-mobile text-text-primary lg:text-headline-lg">{{ title }}</h1>
            <slot />
        </main>
    </div>
</template>
