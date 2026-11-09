<script setup lang="ts">
// Halaman tanpa login (masuk, daftar, reset sandi). Satu kolom, nyaman mulai 360 px.
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-bg-canvas px-margin py-space-xl">
        <div class="mb-space-lg text-center">
            <p class="text-headline-md text-primary">Kostra</p>
            <p class="text-label-sm uppercase text-text-muted">Kost Rapi</p>
        </div>
        <main class="w-full max-w-md rounded-lg border border-border-subtle bg-bg-surface p-space-lg shadow-low">
            <slot />
        </main>
    </div>
</template>
