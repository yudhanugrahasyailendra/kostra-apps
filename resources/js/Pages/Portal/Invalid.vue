<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

// Satu pesan untuk semua kegagalan, supaya penyebabnya (salah, dicabut, kedaluwarsa) tidak bisa ditebak.
defineProps<{ status: number }>();
</script>

<template>
    <Head title="Tautan tidak berlaku" />
    <main class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center gap-space-md px-space-md text-center">
        <span class="material-symbols-outlined text-[48px] text-text-muted" aria-hidden="true">link_off</span>
        <h1 class="text-headline-sm text-text-primary">
            {{ status === 429 ? 'Terlalu banyak percobaan' : status === 403 ? 'Layanan sedang dijeda' : 'Tautan tidak berlaku' }}
        </h1>
        <p class="text-body-md text-text-secondary">
            <template v-if="status === 429">Mohon tunggu sebentar lalu coba lagi.</template>
            <template v-else-if="status === 403">Layanan kos ini sedang dijeda. Silakan hubungi pengelola kos Anda.</template>
            <template v-else>Tautan ini tidak ditemukan atau sudah tidak berlaku. Silakan minta tautan baru kepada pengelola kos Anda.</template>
        </p>
    </main>
</template>
