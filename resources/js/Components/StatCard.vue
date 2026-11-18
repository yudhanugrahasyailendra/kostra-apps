<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

// Kartu angka ringkas di baris atas dashboard. Satu kartu boleh `highlight` (latar teal penuh).
// `tone` mewarnai keterangan di bawah angka: baik (hijau), perlu perhatian (merah), atau netral.
withDefaults(
    defineProps<{
        title: string;
        value: string;
        href: string;
        linkLabel: string;
        note: string;
        badge?: string;
        highlight?: boolean;
        money?: boolean;
        tone?: 'positive' | 'negative' | 'neutral';
        /** Nilai akhir untuk pembaca layar saat `value` sedang dianimasikan (angka berjalan). */
        srValue?: string;
    }>(),
    { badge: undefined, highlight: false, money: false, tone: 'positive', srValue: undefined },
);

const toneText = {
    positive: 'text-status-lunas-text',
    negative: 'text-status-jatuh-tempo-text',
    neutral: 'text-text-secondary',
};
const toneBadge = {
    positive: 'border-status-lunas-border bg-status-lunas-bg',
    negative: 'border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg',
    neutral: 'border-border-strong bg-bg-subtle',
};
</script>

<template>
    <article
        class="relative isolate flex min-h-40 flex-col overflow-hidden rounded-xl p-space-lg transition-[box-shadow,transform] motion-safe:duration-200 motion-safe:hover:-translate-y-0.5"
        :class="
            highlight
                ? 'bg-linear-to-br from-primary-container to-primary text-on-primary shadow-mid hover:shadow-high'
                : 'bg-bg-surface text-text-primary shadow-low ring-1 ring-primary-container/[0.06] hover:shadow-mid dark:bg-surface-container-high'
        "
    >
        <!-- Aksen kartu utama: cahaya emas dan garis atap logo, samar di pojok kanan bawah. -->
        <template v-if="highlight">
            <span class="pointer-events-none absolute -bottom-12 -right-10 -z-10 size-40 rounded-full bg-secondary-container/30 blur-2xl" aria-hidden="true" />
            <svg class="pointer-events-none absolute -bottom-10 -right-12 -z-10 w-40 opacity-10" viewBox="0 0 160 100" fill="none" aria-hidden="true">
                <path d="M 10 80 L 80 24 L 150 80" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M 34 96 L 80 60 L 126 96" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </template>
        <div class="flex items-start justify-between gap-space-sm">
            <h2 class="text-title-lg font-medium">{{ title }}</h2>
            <Link
                :href="href"
                class="flex size-9 shrink-0 items-center justify-center rounded-full border-[1.5px] transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
                :class="
                    highlight
                        ? 'border-on-primary bg-on-primary text-primary hover:bg-on-primary/90 focus-visible:outline-on-primary'
                        : 'border-text-primary text-text-primary hover:bg-text-primary hover:text-bg-surface focus-visible:outline-primary-container'
                "
                :aria-label="linkLabel"
                :title="linkLabel"
            >
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_outward</span>
            </Link>
        </div>
        <p class="mt-space-sm truncate font-medium tabular-nums" :class="money ? 'text-currency-display 2xl:text-headline-lg' : 'text-[2.75rem] leading-[3.25rem] tracking-tight'">
            <template v-if="srValue">
                <span class="sr-only">{{ srValue }}</span>
                <span aria-hidden="true">{{ value }}</span>
            </template>
            <template v-else>{{ value }}</template>
        </p>
        <p class="mt-auto flex items-center gap-space-xs pt-space-xs text-body-sm" :class="highlight ? 'text-on-primary-container' : toneText[tone]">
            <span
                v-if="badge"
                class="inline-flex items-center rounded-sm border px-1 text-label-sm"
                :class="highlight ? 'border-on-primary-container/60' : toneBadge[tone]"
                >{{ badge }}</span
            >
            <span>{{ note }}</span>
        </p>
    </article>
</template>
