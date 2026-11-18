<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LoginForm from '@/Components/Auth/LoginForm.vue';
import RegisterForm from '@/Components/Auth/RegisterForm.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

// Kartu masuk/daftar dengan panel teal yang bergeser. Dipakai sebagai persistent layout oleh
// Pages/Auth/Login dan Pages/Auth/Register, sehingga berpindah /masuk <-> /daftar tidak me-remount
// kartu dan panel bergeser dengan animasi. Mode diambil dari komponen halaman aktif.
// Di bawah md tidak ada panel geser: hanya form aktif dan tautan untuk beralih.
// Animasi dimulai saat tombol diklik (tidak menunggu jawaban server), lalu diselaraskan lagi dengan
// halaman aktif, supaya tombol Back dan akses langsung lewat URL tetap benar.
const page = usePage();
const registerActive = ref(page.component === 'Auth/Register');
watch(
    () => page.component,
    (component) => (registerActive.value = component === 'Auth/Register'),
);
const isRegister = computed(() => registerActive.value);
const year = new Date().getFullYear();

const slide = 'motion-safe:transition-all motion-safe:duration-600 motion-safe:ease-in-out';
const outlineButton =
    'mt-space-xl inline-flex min-h-11 min-w-40 items-center justify-center rounded-full border-2 border-on-primary px-space-xl text-title-md uppercase tracking-wider text-on-primary transition-colors hover:bg-on-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-on-primary';
</script>

<template>
    <div class="relative isolate flex min-h-screen flex-col items-center justify-center gap-space-lg bg-bg-canvas px-margin py-space-xl">
        <!-- Cahaya latar dari warna logo. Hanya hiasan. -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-40 -top-40 size-[32rem] rounded-full bg-primary-container/15 blur-3xl" />
            <div class="absolute -bottom-48 -right-32 size-[30rem] rounded-full bg-secondary-container/20 blur-3xl" />
        </div>

        <div class="flex w-full max-w-4xl items-center justify-between">
            <Link href="/" class="flex items-center gap-space-sm" aria-label="Kostra, beranda">
                <img src="/images/kostra-mark.png" alt="" width="192" height="144" class="h-9 w-auto dark:rounded-md dark:bg-inverse-surface dark:p-0.5" />
                <span class="text-headline-sm font-bold text-primary-container">Kostra</span>
            </Link>
            <ThemeToggle />
        </div>

        <div class="relative w-full max-w-4xl overflow-hidden rounded-xl bg-bg-surface shadow-high md:h-[620px]">
            <!-- Form masuk: sisi kiri. Saat mode daftar, ikut bergeser ke kanan dan tertutup form daftar. -->
            <section
                class="scrollbar-none flex bg-bg-surface px-space-lg py-space-xl sm:px-space-2xl md:absolute md:inset-y-0 md:left-0 md:w-1/2 md:overflow-y-auto"
                :class="[slide, isRegister ? 'z-10 max-md:hidden md:translate-x-full md:opacity-0' : 'z-20']"
                :inert="isRegister || undefined"
                aria-label="Masuk"
            >
                <div class="m-auto w-full max-w-sm"><LoginForm /></div>
            </section>

            <!-- Form daftar: tersembunyi di kiri, muncul di sisi kanan saat mode daftar. -->
            <section
                class="scrollbar-none flex bg-bg-surface px-space-lg py-space-xl sm:px-space-2xl md:absolute md:inset-y-0 md:left-0 md:w-1/2 md:overflow-y-auto"
                :class="[slide, isRegister ? 'z-30 md:translate-x-full' : 'z-10 max-md:hidden md:opacity-0']"
                :inert="!isRegister || undefined"
                aria-label="Daftar"
            >
                <div class="m-auto w-full max-w-sm"><RegisterForm /></div>
            </section>

            <!-- Panel teal yang bergeser (desktop). Isinya dua sisi; yang terlihat bergantian. -->
            <div class="absolute inset-y-0 left-1/2 z-40 hidden w-1/2 overflow-hidden rounded-xl shadow-high md:block" :class="[slide, isRegister ? '-translate-x-full' : '']">
                <div class="relative -left-full h-full w-[200%] bg-linear-to-br from-primary-container to-primary text-on-primary" :class="[slide, isRegister ? 'translate-x-1/2' : '']">
                    <!-- Hiasan: lingkaran tipis dan garis atap seperti logo. -->
                    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                        <div class="absolute -bottom-32 left-1/4 size-96 -translate-x-1/2 rounded-full border border-on-primary/10" />
                        <div class="absolute -top-24 right-[10%] size-80 rounded-full border border-on-primary/10" />
                        <svg class="absolute bottom-0 left-0 h-40 w-full" viewBox="0 0 800 160" preserveAspectRatio="none" fill="none">
                            <path d="M0 160 L120 70 L240 160 M560 160 L680 70 L800 160" class="stroke-on-primary/10" stroke-width="10" stroke-linejoin="round" />
                        </svg>
                    </div>

                    <!-- Sisi kiri: terlihat saat mode daftar -->
                    <div
                        class="absolute inset-y-0 left-0 flex w-1/2 flex-col items-center justify-center px-space-2xl text-center"
                        :class="[slide, isRegister ? 'translate-x-0' : '-translate-x-1/5']"
                        :inert="!isRegister || undefined"
                    >
                        <span class="flex size-16 items-center justify-center rounded-full bg-white shadow-mid" aria-hidden="true">
                            <img src="/images/kostra-mark.png" alt="" width="192" height="144" class="w-10" />
                        </span>
                        <h2 class="mt-space-lg text-display-lg">Selamat datang kembali!</h2>
                        <p class="mt-space-md max-w-xs text-body-lg opacity-85">Sudah punya akun? Masuk untuk mengelola kamar, tagihan, dan pembayaran kos Anda.</p>
                        <Link href="/masuk" :class="outlineButton" @click="registerActive = false">Masuk</Link>
                    </div>

                    <!-- Sisi kanan: terlihat saat mode masuk -->
                    <div
                        class="absolute inset-y-0 right-0 flex w-1/2 flex-col items-center justify-center px-space-2xl text-center"
                        :class="[slide, isRegister ? 'translate-x-1/5' : 'translate-x-0']"
                        :inert="isRegister || undefined"
                    >
                        <span class="flex size-16 items-center justify-center rounded-full bg-white shadow-mid" aria-hidden="true">
                            <img src="/images/kostra-mark.png" alt="" width="192" height="144" class="w-10" />
                        </span>
                        <h2 class="mt-space-lg text-display-lg">Halo, pemilik kos!</h2>
                        <p class="mt-space-md max-w-xs text-body-lg opacity-85">Daftarkan kos Anda dan kelola tagihan, pembayaran, serta pengingat WhatsApp dengan rapi.</p>
                        <Link href="/daftar" :class="outlineButton" @click="registerActive = true">Daftar</Link>
                    </div>
                </div>
            </div>

            <!-- Ponsel: tautan untuk beralih form -->
            <p class="border-t border-border-subtle px-space-lg py-space-md text-center text-body-md text-text-secondary md:hidden">
                <template v-if="isRegister">
                    Sudah punya akun?
                    <Link href="/masuk" class="font-semibold text-primary-container hover:underline">Masuk</Link>
                </template>
                <template v-else>
                    Belum punya akun?
                    <Link href="/daftar" class="font-semibold text-primary-container hover:underline">Daftar</Link>
                </template>
            </p>
        </div>

        <p class="text-center text-body-sm text-text-muted">&copy; {{ year }} Kostra &middot; Kost Rapi. Hak cipta dilindungi.</p>

        <!-- Isi halaman (hanya <Head>); form ada di layout ini supaya tetap terpasang saat berpindah halaman. -->
        <slot />
    </div>
</template>
