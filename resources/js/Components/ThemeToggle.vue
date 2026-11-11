<script setup lang="ts">
import { useTheme, type ThemeMode } from '@/lib/theme';

// Pilihan tema: terang, gelap, atau ikuti pengaturan komputer. Pilihan disimpan di peramban.
const { mode, setMode } = useTheme();

const options: { value: ThemeMode; label: string; icon: string }[] = [
    { value: 'light', label: 'Terang', icon: 'light_mode' },
    { value: 'dark', label: 'Gelap', icon: 'dark_mode' },
    { value: 'system', label: 'Sistem', icon: 'computer' },
];
</script>

<template>
    <div class="inline-flex gap-0.5 rounded-md bg-bg-subtle p-0.5" role="radiogroup" aria-label="Tema tampilan">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="mode === option.value"
            :aria-label="option.label"
            :title="option.label"
            class="flex size-8 items-center justify-center rounded-sm transition-colors"
            :class="mode === option.value ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary hover:text-text-primary'"
            @click="setMode(option.value)"
        >
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ option.icon }}</span>
        </button>
    </div>
</template>
