<script setup lang="ts">
// Halaman tanpa login (masuk, daftar, reset sandi, terima undangan). Mengikuti mockup
// autentikasi_reset_password_kostra: panel merek di kiri (desktop saja), formulir di kanan.
// Klaim yang belum benar (enkripsi AES-256, testimoni, status server) sengaja tidak ditampilkan.
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-bg-canvas px-margin py-space-xl">
        <div class="grid w-full max-w-5xl overflow-hidden rounded-lg bg-bg-surface shadow-high lg:grid-cols-[5fr_6fr]">
            <aside class="hidden flex-col justify-between bg-primary p-space-xl text-on-primary lg:flex" aria-hidden="true">
                <div>
                    <span class="inline-flex items-center gap-space-xs rounded-full bg-on-primary/10 px-space-md py-space-xs text-label-sm uppercase text-primary-fixed">
                        <span class="material-symbols-outlined text-[16px]">verified</span>
                        Sistem manajemen kos modern
                    </span>
                    <h2 class="mt-space-xl text-display-lg text-on-primary">Kelola ratusan kamar &amp; tagihan tanpa ribet</h2>
                    <p class="mt-space-md text-body-lg text-on-primary-container">
                        Satu portal untuk pencatatan sewa, pengingat WhatsApp, dan pembayaran penghuni.
                    </p>
                </div>
                <ul class="mt-space-xl flex flex-col gap-space-sm text-body-md text-primary-fixed">
                    <li class="flex items-center gap-space-sm"><span class="material-symbols-outlined">receipt_long</span>Tagihan bulanan terbit otomatis</li>
                    <li class="flex items-center gap-space-sm"><span class="material-symbols-outlined">chat</span>Pengingat WhatsApp ke penghuni</li>
                    <li class="flex items-center gap-space-sm"><span class="material-symbols-outlined">query_stats</span>Laporan pemasukan dan tunggakan</li>
                </ul>
            </aside>

            <main class="flex flex-col p-space-lg sm:p-space-xl">
                <div class="mb-space-lg flex flex-col leading-tight">
                    <span class="text-headline-sm font-bold text-primary">Kostra <span class="text-label-md font-medium text-secondary">| Kost Rapi</span></span>
                    <span class="text-body-sm text-text-secondary">Owner &amp; Staff Access Hub</span>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
