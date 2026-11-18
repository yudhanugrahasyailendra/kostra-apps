<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import InsetField from '@/Components/InsetField.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();

// Satu kolom pengenal: server (LoginRequest) membedakan email dan nomor WhatsApp dari ada tidaknya "@".
const form = useForm({ login: '', password: '', remember: false });

function submit() {
    form.post('/masuk', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <div class="flex w-full flex-col items-center text-center">
        <h1 class="text-display-lg-mobile text-text-primary md:text-display-lg">Masuk</h1>
        <p class="mt-space-sm text-body-md text-text-secondary">Masuk dengan email atau nomor WhatsApp</p>

        <p v-if="page.props.flash.success" class="mt-space-md w-full rounded-md bg-status-lunas-bg p-space-sm text-body-sm text-status-lunas-text" role="status">
            {{ page.props.flash.success }}
        </p>

        <form class="mt-space-lg flex w-full flex-col gap-space-sm text-left" @submit.prevent="submit">
            <InsetField v-model="form.login" label="Email atau nomor WhatsApp" icon="person" autocomplete="username" required :error="form.errors.login" />
            <InsetField v-model="form.password" label="Kata sandi" type="password" autocomplete="current-password" required :error="form.errors.password" />
            <div class="mt-space-xs flex items-center justify-between gap-space-sm">
                <label class="flex items-center gap-space-sm text-body-md text-text-primary">
                    <input v-model="form.remember" type="checkbox" class="size-[18px] rounded-sm border-border-strong accent-primary-container" />
                    Ingat saya
                </label>
                <Link href="/lupa-sandi" class="text-body-md text-text-secondary underline-offset-2 hover:text-primary-container hover:underline">Lupa kata sandi?</Link>
            </div>
            <Button type="submit" pill :disabled="form.processing" class="mx-auto mt-space-md min-w-40 uppercase tracking-wider shadow-mid">Masuk</Button>
        </form>
    </div>
</template>
