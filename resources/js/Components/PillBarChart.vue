<script setup lang="ts">
import { computed } from 'vue';
import { useEntered } from '@/lib/animate';

export interface PillBar {
    key: string;
    label: string;
    value: number;
    /** Teks nilai lengkap untuk pembaca layar dan tooltip. */
    display: string;
    /** Teks pendek di gelembung atas batang yang di-`highlight`. */
    short?: string;
    highlight?: boolean;
}

// Grafik batang berbentuk pil. Batang tertinggi paling gelap, batang `highlight` terang dengan gelembung nilai,
// sisanya warna tengah. Nilai nol digambar sebagai pil berarsir (tanpa data), bukan setinggi nilainya.
const props = defineProps<{ bars: PillBar[]; caption: string; emptyLabel: string }>();

const max = computed(() => Math.max(0, ...props.bars.map((bar) => bar.value)));

// Animasi masuk: batang tumbuh dari bawah satu per satu, gelembung nilai muncul setelah batangnya.
const entered = useEntered();
const STAGGER_MS = 90;
const shownHeight = (bar: PillBar) => (entered.value ? height(bar) : 0);

const EMPTY_HEIGHT = 92;
const MIN_HEIGHT = 30;

function height(bar: PillBar): number {
    if (bar.value <= 0 || max.value <= 0) {
        return EMPTY_HEIGHT;
    }

    // Skala 30-100% supaya batang kecil tetap terbaca sebagai pil, seperti grafik acuan.
    return Math.round(MIN_HEIGHT + (bar.value / max.value) * (100 - MIN_HEIGHT));
}

function barClass(bar: PillBar): string {
    if (bar.value <= 0) {
        return 'bg-hatch';
    }
    if (bar.highlight) {
        return 'bg-primary-fixed-dim';
    }

    return bar.value === max.value ? 'bg-primary dark:bg-on-primary-container' : 'bg-surface-tint dark:bg-primary/55';
}
</script>

<template>
    <figure class="flex flex-1 flex-col">
        <figcaption class="sr-only">{{ caption }}. Pil berarsir: {{ emptyLabel }}.</figcaption>
        <div class="flex min-h-36 flex-1 items-end justify-between gap-space-sm pt-space-lg sm:gap-space-md" aria-hidden="true">
            <div v-for="(bar, index) in bars" :key="bar.key" class="relative flex h-full flex-1 items-end justify-center" :title="`${bar.label}: ${bar.value > 0 ? bar.display : emptyLabel}`">
                <span
                    v-if="bar.highlight && bar.value > 0"
                    class="absolute left-1/2 z-10 flex -translate-x-1/2 flex-col items-center motion-safe:transition-[bottom,opacity] motion-safe:duration-700 motion-safe:ease-out"
                    :class="entered ? 'opacity-100' : 'opacity-0'"
                    :style="{ bottom: `calc(${shownHeight(bar)}% - 0.375rem)`, transitionDelay: `${index * STAGGER_MS + 200}ms` }"
                >
                    <span class="whitespace-nowrap rounded-md border border-border-subtle bg-bg-surface px-1.5 py-0.5 text-[10px] font-semibold leading-3 text-text-primary shadow-low">{{ bar.short ?? bar.display }}</span>
                    <span class="mt-0.5 size-2.5 rounded-full border-2 border-bg-surface bg-text-primary" />
                </span>
                <div
                    class="w-full max-w-16 rounded-full motion-safe:transition-[height] motion-safe:duration-700 motion-safe:ease-out"
                    :class="barClass(bar)"
                    :style="{ height: `${shownHeight(bar)}%`, transitionDelay: `${index * STAGGER_MS}ms` }"
                />
            </div>
        </div>
        <div class="mt-space-sm flex justify-between gap-space-sm sm:gap-space-md" aria-hidden="true">
            <span v-for="bar in bars" :key="bar.key" class="flex-1 text-center text-body-sm text-text-muted">{{ bar.label }}</span>
        </div>
        <ul class="sr-only">
            <li v-for="bar in bars" :key="bar.key">{{ bar.label }}: {{ bar.value > 0 ? bar.display : emptyLabel }}</li>
        </ul>
    </figure>
</template>
