<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import TextField from '@/Components/TextField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const form = useForm({ email: '' });

function submit() {
    form.post('/lupa-sandi');
}
</script>

<template>
    <Head title="Lupa kata sandi" />
    <GuestLayout>
        <h1 class="text-headline-md text-text-primary">Lupa kata sandi</h1>
        <p class="mt-space-xs text-body-md text-text-secondary">Kami kirim tautan atur ulang ke email Anda. Tautan berlaku 60 menit.</p>

        <p v-if="page.props.flash.success" class="mt-space-md rounded-md bg-status-lunas-bg p-space-sm text-body-sm text-status-lunas-text" role="status">
            {{ page.props.flash.success }}
        </p>

        <form class="mt-space-lg flex flex-col gap-space-md" @submit.prevent="submit">
            <TextField v-model="form.email" label="Email" type="email" autocomplete="email" required :error="form.errors.email" />
            <Button type="submit" :disabled="form.processing" icon="mail">Kirim tautan</Button>
        </form>

        <p class="mt-space-lg text-body-md">
            <Link href="/masuk" class="text-primary-container hover:underline">Kembali ke halaman masuk</Link>
        </p>
    </GuestLayout>
</template>
