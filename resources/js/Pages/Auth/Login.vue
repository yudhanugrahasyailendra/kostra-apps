<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthSliderLayout from '@/Layouts/AuthSliderLayout.vue';

// Form ada di AuthSliderLayout (persistent layout) supaya kartu bergeser beranimasi ke /daftar dan kembali.
defineOptions({ layout: AuthSliderLayout });
</script>

<template>
    <Head title="Masuk" />
</template>
