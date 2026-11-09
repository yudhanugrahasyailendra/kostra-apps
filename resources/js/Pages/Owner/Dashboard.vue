<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import Panel from '@/Components/Panel.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
</script>

<template>
    <Head title="Dashboard" />
    <OwnerLayout title="Dashboard" active="dashboard">
        <Panel title="Selamat datang">
            <p class="text-body-md text-text-secondary">
                Halo, {{ page.props.auth?.user.name }}. Dashboard {{ page.props.tenant?.name }} masih kosong. Properti, kamar, dan tagihan
                akan muncul di sini pada fase berikutnya.
            </p>
        </Panel>
    </OwnerLayout>
</template>
