<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import TextField from '@/Components/TextField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();

const form = useForm({ login: '', password: '', remember: false });

function submit() {
    form.post('/masuk', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Masuk" />
    <GuestLayout>
        <h1 class="text-headline-md text-text-primary">Masuk ke Kostra</h1>
        <p class="mt-space-xs text-body-md text-text-secondary">Gunakan email atau nomor WhatsApp Anda.</p>

        <p v-if="page.props.flash.success" class="mt-space-md rounded-md bg-status-lunas-bg p-space-sm text-body-sm text-status-lunas-text" role="status">
            {{ page.props.flash.success }}
        </p>

        <form class="mt-space-lg flex flex-col gap-space-md" @submit.prevent="submit">
            <TextField v-model="form.login" label="Email atau nomor WhatsApp" autocomplete="username" required :error="form.errors.login" />
            <TextField v-model="form.password" label="Kata sandi" type="password" autocomplete="current-password" required :error="form.errors.password" />
            <label class="flex items-center gap-space-sm text-body-md text-text-secondary">
                <input v-model="form.remember" type="checkbox" class="size-[18px] rounded-sm border-border-strong accent-primary-container" />
                Ingat saya
            </label>
            <Button type="submit" :disabled="form.processing" icon="login">Masuk</Button>
        </form>

        <div class="mt-space-lg flex flex-col gap-space-xs text-body-md">
            <Link href="/lupa-sandi" class="text-primary-container hover:underline">Lupa kata sandi?</Link>
            <Link href="/daftar" class="text-primary-container hover:underline">Belum punya akun? Daftar gratis</Link>
        </div>
    </GuestLayout>
</template>
