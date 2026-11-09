<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import TextField from '@/Components/TextField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });

function submit() {
    form.post('/atur-ulang-sandi', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Atur ulang kata sandi" />
    <GuestLayout>
        <h1 class="text-headline-md text-text-primary">Atur ulang kata sandi</h1>

        <form class="mt-space-lg flex flex-col gap-space-md" @submit.prevent="submit">
            <TextField v-model="form.email" label="Email" type="email" autocomplete="email" required :error="form.errors.email" />
            <TextField v-model="form.password" label="Kata sandi baru" type="password" autocomplete="new-password" required :error="form.errors.password" hint="Minimal 8 karakter." />
            <TextField v-model="form.password_confirmation" label="Ulangi kata sandi baru" type="password" autocomplete="new-password" required />
            <Button type="submit" :disabled="form.processing" icon="lock_reset">Simpan kata sandi</Button>
        </form>
    </GuestLayout>
</template>
