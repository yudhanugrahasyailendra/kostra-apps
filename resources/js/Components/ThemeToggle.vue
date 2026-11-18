<script setup lang="ts">
import { computed } from 'vue';
import { useTheme, type ThemeMode } from '@/lib/theme';

// Pilihan tema: terang, gelap, atau ikuti pengaturan komputer. Pilihan disimpan di peramban.
const { mode, setMode } = useTheme();

// `round`: satu tombol bulat yang menampilkan mode aktif dan berganti ke mode berikutnya tiap diklik
// (kepala halaman pemilik). Tanpa `round`: tiga pilihan berdampingan.
withDefaults(defineProps<{ round?: boolean }>(), { round: false });

const options: { value: ThemeMode; label: string; icon: string }[] = [
    { value: 'light', label: 'Terang', icon: 'light_mode' },
    { value: 'dark', label: 'Gelap', icon: 'dark_mode' },
    { value: 'system', label: 'Sistem', icon: 'computer' },
];

const currentIndex = computed(() => Math.max(0, options.findIndex((option) => option.value === mode.value)));
const current = computed(() => options[currentIndex.value]);
const next = computed(() => options[(currentIndex.value + 1) % options.length]);
const cycleLabel = computed(() => `Tema: ${current.value.label}. Klik untuk ganti ke ${next.value.label}.`);
</script>

<template>
    <button
        v-if="round"
        type="button"
        class="flex size-10 items-center justify-center rounded-full bg-bg-subtle text-primary-container lg:bg-bg-surface transition-colors hover:bg-primary-container hover:text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-container"
        :aria-label="cycleLabel"
        :title="cycleLabel"
        @click="setMode(next.value)"
    >
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ current.icon }}</span>
    </button>
    <div v-else class="inline-flex gap-0.5 rounded-md bg-bg-subtle p-0.5" role="radiogroup" aria-label="Tema tampilan">
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
