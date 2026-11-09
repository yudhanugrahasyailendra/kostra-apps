<script setup lang="ts">
import type { StatusTone } from '@/types';

// Status selalu tampil sebagai teks dan warna, tidak warna saja.
defineProps<{
    tone: StatusTone;
    label: string;
}>();

const tones: Record<StatusTone, string> = {
    lunas: 'border-status-lunas-border bg-status-lunas-bg text-status-lunas-text',
    menunggu: 'border-status-menunggu-border bg-status-menunggu-bg text-status-menunggu-text',
    'jatuh-tempo': 'border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg text-status-jatuh-tempo-text',
    kosong: 'border-status-kosong-border bg-status-kosong-bg text-status-kosong-text',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2.5 py-1 text-label-sm uppercase"
        :class="tones[tone]"
    >
        {{ label }}
    </span>
</template>
