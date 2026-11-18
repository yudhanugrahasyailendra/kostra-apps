<script setup lang="ts">
import { computed } from 'vue';
import { useCountUp, useEntered } from '@/lib/animate';

// Busur setengah lingkaran: terisi, perawatan, kosong (berarsir). Persentase di tengah dari laporan okupansi.
const props = defineProps<{ occupied: number; maintenance: number; vacant: number; percent: number }>();

// Panjang busur memakai pathLength=100. Ujung bulat (setengah tebal garis) menjorok CAP satuan di tiap sisi.
const RADIUS = 86;
const STROKE = 36;
const CAP = ((STROKE / 2) / (Math.PI * RADIUS)) * 100;
const SPACING = 0.5;

const segments = computed(() => {
    const parts = [
        { key: 'occupied', value: props.occupied, class: 'stroke-surface-tint dark:stroke-primary-container' },
        { key: 'maintenance', value: props.maintenance, class: 'stroke-primary dark:stroke-on-primary-container' },
        { key: 'vacant', value: props.vacant, class: '', hatch: true },
    ].filter((part) => part.value > 0);

    const total = parts.reduce((sum, part) => sum + part.value, 0);
    const available = 100 - parts.length * 2 * CAP - (parts.length - 1) * SPACING;
    let cursor = CAP;

    return parts.map((part) => {
        const length = Math.max(0.01, (part.value / total) * available);
        const segment = { ...part, length, offset: -cursor };
        cursor += length + 2 * CAP + SPACING;

        return segment;
    });
});

// Animasi masuk: tiap bagian busur tergambar berurutan, persentase di tengah naik dari 0.
const entered = useEntered();
const SEGMENT_MS = 700;
const animatedPercent = useCountUp(() => props.percent, { duration: 1200 });
const percentLabel = computed(() => `${(Math.round(animatedPercent.value * 10) / 10).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`);
const finalPercentLabel = computed(() => `${props.percent.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`);
const arc = `M ${110 - RADIUS} 116 A ${RADIUS} ${RADIUS} 0 0 1 ${110 + RADIUS} 116`;
</script>

<template>
    <figure class="flex flex-1 flex-col items-center justify-between">
        <div class="relative w-full max-w-80">
            <svg viewBox="0 0 220 136" class="w-full" aria-hidden="true">
                <defs>
                    <pattern id="kostra-gauge-hatch" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                        <rect width="7" height="7" class="fill-bg-surface" />
                        <line x1="0" y1="0" x2="0" y2="7" class="stroke-outline-variant" stroke-width="3.5" />
                    </pattern>
                </defs>
                <path v-if="segments.length === 0" :d="arc" pathLength="100" fill="none" :stroke-width="STROKE" stroke-linecap="round" class="stroke-bg-subtle" />
                <path
                    v-for="(segment, index) in segments"
                    :key="segment.key"
                    :d="arc"
                    pathLength="100"
                    fill="none"
                    :stroke-width="STROKE"
                    stroke-linecap="round"
                    class="motion-safe:transition-[stroke-dasharray,opacity] motion-safe:ease-out"
                    :class="[segment.class, entered ? 'opacity-100' : 'opacity-0']"
                    :stroke="segment.hatch ? 'url(#kostra-gauge-hatch)' : undefined"
                    :stroke-dashoffset="segment.offset"
                    :style="{
                        strokeDasharray: `${entered ? segment.length : 0} 200`,
                        transitionDuration: `${SEGMENT_MS}ms`,
                        transitionDelay: `${index * (SEGMENT_MS * 0.6)}ms`,
                    }"
                />
            </svg>
            <div class="absolute inset-x-0 bottom-0 text-center">
                <p class="sr-only">{{ finalPercentLabel }}</p>
                <p class="text-[2.75rem] font-bold leading-[3rem] tracking-tight tabular-nums text-text-primary" aria-hidden="true">{{ percentLabel }}</p>
                <p class="text-body-sm text-primary-container">Kamar terisi</p>
            </div>
        </div>
        <figcaption class="mt-space-md flex w-full flex-wrap justify-between gap-x-space-md gap-y-space-xs text-body-sm text-text-secondary">
            <span class="flex items-center gap-space-xs"><span class="size-3.5 rounded-full bg-surface-tint dark:bg-primary-container" aria-hidden="true" />Terisi ({{ occupied }})</span>
            <span class="flex items-center gap-space-xs"><span class="size-3.5 rounded-full bg-primary dark:bg-on-primary-container" aria-hidden="true" />Perawatan ({{ maintenance }})</span>
            <span class="flex items-center gap-space-xs"><span class="bg-hatch size-3.5 rounded-full" aria-hidden="true" />Kosong ({{ vacant }})</span>
        </figcaption>
    </figure>
</template>
