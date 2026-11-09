<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { formatRupiah } from '@/lib/formatRupiah';

defineProps<{
    message: string;
}>();
</script>

<template>
    <Head title="Selamat datang" />
    <GuestLayout>
        <h1 class="text-headline-md text-text-primary">Selamat datang di Kostra</h1>
        <p class="mt-space-sm text-body-md text-text-secondary">{{ message }}</p>
        <p class="mt-space-md text-currency-display text-text-primary">{{ formatRupiah(1850000) }}</p>
        <div class="mt-space-md flex flex-wrap items-center gap-space-sm">
            <StatusBadge tone="lunas" label="Lunas" />
            <StatusBadge tone="menunggu" label="Menunggu" />
            <StatusBadge tone="jatuh-tempo" label="Jatuh tempo" />
            <StatusBadge tone="kosong" label="Kosong" />
        </div>
        <div class="mt-space-lg">
            <Button icon="login">Masuk</Button>
        </div>
    </GuestLayout>
</template>
