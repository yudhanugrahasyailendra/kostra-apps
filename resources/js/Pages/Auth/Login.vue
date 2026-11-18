<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { formatRupiah } from '@/lib/formatRupiah';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();

// Satu kolom pengenal: server (LoginRequest) membedakan email dan nomor WhatsApp dari ada tidaknya "@".
const form = useForm({ login: '', password: '', remember: false });

const year = new Date().getFullYear();
const period = new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });

function submit() {
    form.post('/masuk', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Masuk" />
    <div class="flex min-h-screen bg-bg-canvas lg:p-space-md">
        <div class="flex w-full overflow-hidden bg-bg-surface lg:rounded-lg lg:shadow-high">
            <!-- Panel formulir (kiri) -->
            <main class="flex w-full flex-col px-margin py-space-lg sm:px-space-xl lg:w-[400px] lg:shrink-0 lg:px-space-xl">
                <div class="mx-auto flex w-full max-w-sm flex-1 flex-col">
                    <div class="flex items-center justify-between">
                        <Link href="/" class="flex items-center gap-space-sm" aria-label="Kostra, beranda">
                            <img src="/images/kostra-mark.png" alt="" width="192" height="144" class="h-9 w-auto dark:rounded-md dark:bg-inverse-surface dark:p-0.5" />
                            <span class="text-headline-sm font-bold text-primary-container">Kostra</span>
                        </Link>
                        <ThemeToggle />
                    </div>

                    <h1 class="mt-space-xl text-headline-md text-text-primary">Masuk</h1>
                    <p class="mt-space-sm text-body-md text-text-secondary">
                        Belum punya akun Kostra?
                        <Link href="/daftar" class="font-semibold text-primary-container underline underline-offset-2 hover:no-underline">Daftarkan kos Anda</Link>
                    </p>

                    <p v-if="page.props.flash.success" class="mt-space-md rounded-md bg-status-lunas-bg p-space-sm text-body-sm text-status-lunas-text" role="status">
                        {{ page.props.flash.success }}
                    </p>

                    <form class="mt-space-lg flex flex-col gap-space-md" @submit.prevent="submit">
                        <TextField
                            v-model="form.login"
                            label="Email atau nomor WhatsApp"
                            autocomplete="username"
                            placeholder="Masukkan email atau nomor WhatsApp"
                            required
                            :error="form.errors.login"
                        />
                        <TextField
                            v-model="form.password"
                            label="Kata sandi"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Masukkan kata sandi"
                            required
                            :error="form.errors.password"
                        />
                        <label class="flex items-center gap-space-sm text-body-md text-text-primary">
                            <input v-model="form.remember" type="checkbox" class="size-[18px] rounded-sm border-border-strong accent-primary-container" />
                            Ingat saya
                        </label>
                        <Button type="submit" pill :disabled="form.processing" class="mt-space-sm w-full">Masuk</Button>
                    </form>

                    <Link href="/lupa-sandi" class="mt-space-md self-start text-title-md text-text-primary underline underline-offset-2 hover:text-primary-container">
                        Lupa kata sandi?
                    </Link>

                    <p class="mt-auto pt-space-2xl text-center text-body-sm text-text-muted">
                        &copy; {{ year }} Kostra &middot; Kost Rapi. Hak cipta dilindungi.
                    </p>
                </div>
            </main>

            <!-- Panel merek (kanan, desktop saja). Pengganti foto: peraga antarmuka Kostra; datanya contoh, bukan data akun. -->
            <aside class="relative hidden flex-1 overflow-hidden bg-on-primary-fixed lg:block" aria-hidden="true">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_75%_70%,var(--color-on-primary-fixed-variant)_0%,transparent_60%)]" />
                <div class="absolute -right-24 top-1/3 size-[420px] rounded-full border border-primary-fixed-dim/15" />
                <div class="absolute -right-8 top-[42%] size-[260px] rounded-full border border-primary-fixed-dim/10" />

                <div class="relative max-w-md p-space-2xl">
                    <h2 class="text-display-lg text-white">Kos rapi, tagihan beres, dari mana saja</h2>
                    <p class="mt-space-md text-body-lg text-tertiary-fixed">
                        Dengan Kostra, kelola kamar, tagihan, dan pengingat WhatsApp secara sederhana, cepat, dan tertata.
                    </p>
                    <Link href="/daftar" tabindex="-1" class="mt-space-lg inline-flex items-center gap-space-xs text-title-lg text-white underline underline-offset-4 hover:text-primary-fixed-dim">
                        Mulai sekarang
                        <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                    </Link>
                </div>

                <!-- Ikon mengambang -->
                <div class="absolute left-[38%] top-[44%] grid size-16 place-items-center rounded-lg bg-bg-surface text-primary-container shadow-high motion-safe:animate-[login-float_6s_ease-in-out_infinite]">
                    <span class="material-symbols-outlined text-[30px]">receipt_long</span>
                </div>

                <!-- Kartu daftar tagihan (pengganti laptop di foto) -->
                <div class="absolute bottom-0 right-space-2xl w-[min(440px,60%)] rounded-t-lg bg-bg-surface p-space-lg shadow-high">
                    <div class="flex items-center justify-between">
                        <p class="text-title-lg text-text-primary">Tagihan {{ period }}</p>
                        <span class="text-label-md text-text-secondary">3 kamar</span>
                    </div>
                    <ul class="mt-space-md flex flex-col divide-y divide-border-subtle">
                        <li class="flex items-center justify-between gap-space-sm py-space-sm">
                            <span class="flex flex-col">
                                <span class="text-title-md text-text-primary">Kamar A-01</span>
                                <span class="text-body-sm text-text-secondary">{{ formatRupiah(1250000) }}</span>
                            </span>
                            <StatusBadge tone="lunas" label="Lunas" />
                        </li>
                        <li class="flex items-center justify-between gap-space-sm py-space-sm">
                            <span class="flex flex-col">
                                <span class="text-title-md text-text-primary">Kamar A-02</span>
                                <span class="text-body-sm text-text-secondary">{{ formatRupiah(1100000) }}</span>
                            </span>
                            <StatusBadge tone="menunggu" label="Menunggu" />
                        </li>
                        <li class="flex items-center justify-between gap-space-sm pb-space-lg pt-space-sm">
                            <span class="flex flex-col">
                                <span class="text-title-md text-text-primary">Kamar B-03</span>
                                <span class="text-body-sm text-text-secondary">{{ formatRupiah(1850000) }}</span>
                            </span>
                            <StatusBadge tone="jatuh-tempo" label="Jatuh tempo" />
                        </li>
                    </ul>
                </div>

                <!-- Kartu kecil mengambang (seperti kartu pilihan mata uang di desain) -->
                <div class="absolute bottom-[38%] right-space-xl w-60 rounded-lg bg-bg-surface p-space-md shadow-high motion-safe:animate-[login-float_7s_ease-in-out_1s_infinite]">
                    <p class="text-label-sm uppercase text-text-secondary">Pengingat WhatsApp</p>
                    <p class="mt-space-xs flex items-center gap-space-xs text-title-md text-text-primary">
                        <span class="material-symbols-outlined text-[18px] text-primary-container">chat</span>
                        12 pesan terkirim
                    </p>
                    <div class="mt-space-sm h-1.5 overflow-hidden rounded-full bg-bg-subtle">
                        <div class="h-full w-3/4 rounded-full bg-primary-container" />
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

<style>
@keyframes login-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}
</style>
