<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import TextField from '@/Components/TextField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();

// Dua cara mengisi pengenal akun; keduanya memakai kata sandi (OTP WhatsApp menyusul di Fase 9).
const mode = ref<'email' | 'phone'>('email');

const form = useForm({ login: '', password: '', remember: false });

function setMode(next: 'email' | 'phone') {
    mode.value = next;
    form.login = '';
    form.clearErrors();
}

function submit() {
    form.post('/masuk', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Masuk" />
    <GuestLayout>
        <h1 class="text-headline-lg text-text-primary">Selamat datang di Kostra</h1>
        <p class="mt-space-xs text-body-md text-text-secondary">Masuk untuk memantau kamar dan tagihan kos Anda.</p>

        <p v-if="page.props.flash.success" class="mt-space-md rounded-md bg-status-lunas-bg p-space-sm text-body-sm text-status-lunas-text" role="status">
            {{ page.props.flash.success }}
        </p>

        <div class="mt-space-lg grid grid-cols-2 gap-space-xs rounded-md bg-bg-subtle p-space-xs" role="tablist" aria-label="Cara masuk">
            <button
                type="button"
                role="tab"
                :aria-selected="mode === 'email'"
                class="flex items-center justify-center gap-space-xs rounded-md py-space-sm text-title-md"
                :class="mode === 'email' ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary'"
                @click="setMode('email')"
            >
                <span class="material-symbols-outlined" aria-hidden="true">mail</span>Email
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="mode === 'phone'"
                class="flex items-center justify-center gap-space-xs rounded-md py-space-sm text-title-md"
                :class="mode === 'phone' ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary'"
                @click="setMode('phone')"
            >
                <span class="material-symbols-outlined" aria-hidden="true">chat</span>Nomor WhatsApp
            </button>
        </div>

        <form class="mt-space-lg flex flex-col gap-space-md" @submit.prevent="submit">
            <TextField
                v-if="mode === 'email'"
                v-model="form.login"
                label="Alamat email pengelola / pemilik"
                type="email"
                autocomplete="username"
                required
                :error="form.errors.login"
            />
            <TextField
                v-else
                v-model="form.login"
                label="Nomor WhatsApp"
                type="tel"
                autocomplete="tel"
                required
                hint="Contoh: 081234567890"
                :error="form.errors.login"
            />
            <div>
                <TextField v-model="form.password" label="Kata sandi" type="password" autocomplete="current-password" required :error="form.errors.password" />
                <Link href="/lupa-sandi" class="mt-space-xs inline-block text-label-md text-primary-container hover:underline">Lupa kata sandi?</Link>
            </div>
            <label class="flex items-center gap-space-sm text-body-md text-text-secondary">
                <input v-model="form.remember" type="checkbox" class="size-[18px] rounded-sm border-border-strong accent-primary-container" />
                Ingat saya di perangkat ini
            </label>
            <Button type="submit" :disabled="form.processing" icon="arrow_forward">Masuk ke dashboard Kostra</Button>
        </form>

        <p class="mt-auto border-t border-border-subtle pt-space-lg text-body-md text-text-secondary">
            Belum punya akun Kostra?
            <Link href="/daftar" class="text-title-md text-primary-container hover:underline">Daftarkan kos Anda gratis</Link>
        </p>
    </GuestLayout>
</template>
