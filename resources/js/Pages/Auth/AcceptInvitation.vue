<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import TextField from '@/Components/TextField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps<{
    token: string;
    usable: boolean;
    email: string | null;
    phone: string | null;
}>();

const form = useForm({
    name: '',
    email: props.email ?? '',
    phone: props.phone ?? '',
    password: '',
    password_confirmation: '',
});

// Galat "token" datang dari server tetapi bukan field formulir.
const tokenError = computed(() => (form.errors as Record<string, string | undefined>).token);

function submit() {
    form.post(`/undangan/${props.token}`, { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Terima undangan" />
    <GuestLayout>
        <template v-if="usable">
            <h1 class="text-headline-md text-text-primary">Anda diundang ke Kostra</h1>
            <p class="mt-space-xs text-body-md text-text-secondary">Buat akun staf untuk mulai bekerja.</p>

            <form class="mt-space-lg flex flex-col gap-space-md" @submit.prevent="submit">
                <p v-if="tokenError" class="rounded-md bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">
                    {{ tokenError }}
                </p>
                <TextField v-model="form.name" label="Nama Anda" autocomplete="name" required :error="form.errors.name" />
                <TextField v-model="form.email" label="Email" type="email" autocomplete="email" :error="form.errors.email" />
                <TextField v-model="form.phone" label="Nomor WhatsApp" type="tel" autocomplete="tel" :error="form.errors.phone" />
                <TextField v-model="form.password" label="Kata sandi" type="password" autocomplete="new-password" required :error="form.errors.password" hint="Minimal 8 karakter." />
                <TextField v-model="form.password_confirmation" label="Ulangi kata sandi" type="password" autocomplete="new-password" required />
                <Button type="submit" :disabled="form.processing" icon="how_to_reg">Buat akun</Button>
            </form>
        </template>
        <template v-else>
            <h1 class="text-headline-md text-text-primary">Undangan tidak berlaku</h1>
            <p class="mt-space-sm text-body-md text-text-secondary">Undangan ini sudah dipakai atau kedaluwarsa. Minta pemilik kos mengirim undangan baru.</p>
            <p class="mt-space-lg text-body-md">
                <Link href="/masuk" class="text-primary-container hover:underline">Ke halaman masuk</Link>
            </p>
        </template>
    </GuestLayout>
</template>
