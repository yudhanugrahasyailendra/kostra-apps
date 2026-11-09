<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import TextField from '@/Components/TextField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
    kos_name: '',
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/daftar', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Daftar" />
    <GuestLayout>
        <h1 class="text-headline-md text-text-primary">Daftar gratis</h1>
        <p class="mt-space-xs text-body-md text-text-secondary">Mulai dengan paket Gratis: 1 properti, 10 kamar.</p>

        <form class="mt-space-lg flex flex-col gap-space-md" @submit.prevent="submit">
            <TextField v-model="form.kos_name" label="Nama kos" required :error="form.errors.kos_name" />
            <TextField v-model="form.name" label="Nama Anda" autocomplete="name" required :error="form.errors.name" />
            <TextField v-model="form.email" label="Email" type="email" autocomplete="email" :error="form.errors.email" hint="Isi email atau nomor WhatsApp." />
            <TextField v-model="form.phone" label="Nomor WhatsApp" type="tel" autocomplete="tel" :error="form.errors.phone" hint="Contoh: 081234567890" />
            <TextField v-model="form.password" label="Kata sandi" type="password" autocomplete="new-password" required :error="form.errors.password" hint="Minimal 8 karakter." />
            <TextField v-model="form.password_confirmation" label="Ulangi kata sandi" type="password" autocomplete="new-password" required />
            <Button type="submit" :disabled="form.processing" icon="person_add">Buat akun</Button>
        </form>

        <p class="mt-space-lg text-body-md">
            <Link href="/masuk" class="text-primary-container hover:underline">Sudah punya akun? Masuk</Link>
        </p>
    </GuestLayout>
</template>
