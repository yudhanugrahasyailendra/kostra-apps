<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const props = defineProps<{ active: 'kamar' | 'properti' | 'tipe' }>();

const tabs = [
    { key: 'kamar', label: 'Kamar', href: '/app/kamar' },
    { key: 'properti', label: 'Properti', href: '/app/properti' },
    { key: 'tipe', label: 'Tipe kamar', href: '/app/tipe-kamar' },
];
</script>

<template>
    <div class="mb-space-lg">
        <nav class="flex gap-space-xs rounded-md bg-bg-subtle p-space-xs sm:inline-flex" aria-label="Bagian kamar dan properti">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                class="flex-1 rounded-md px-space-md py-space-sm text-center text-title-md sm:flex-none"
                :class="tab.key === props.active ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary hover:text-text-primary'"
                :aria-current="tab.key === props.active ? 'page' : undefined"
            >
                {{ tab.label }}
            </Link>
        </nav>

    </div>
</template>
