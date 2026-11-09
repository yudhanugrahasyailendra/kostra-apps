<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    message: {
        type: String,
        default: 'Selamat datang di Kostra Apps dengan Laravel, Inertia, dan Vue.js!',
    },
});
</script>

<template>

    <Head title="Welcome" />
    <div class="min-h-screen flex items-center justify-center bg-gray-100 text-gray-800">
        <div class="text-center p-8 bg-white rounded-xl shadow-md max-w-md">
            <h1 class="text-2xl font-bold mb-4">Inertia.js + Vue 3</h1>
            <p class="text-gray-600">{{ message }}</p>
        </div>
    </div>
</template>
