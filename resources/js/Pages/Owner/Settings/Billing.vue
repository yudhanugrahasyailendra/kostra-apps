<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Panel from '@/Components/Panel.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

const props = defineProps<{
    settings: { invoice_prefix: string; issue_day: number; due_day: number; prorate_first_month: boolean; prorate_last_month: boolean };
}>();

const form = useForm({ ...props.settings });

const preview = computed(() => `${form.invoice_prefix || 'INV'}-2026-11-0001`);

function submit() {
    form.patch('/app/pengaturan/penagihan', { preserveScroll: true });
}
</script>

<template>
    <Head title="Aturan Penagihan" />
    <OwnerLayout title="Aturan Penagihan" active="pengaturan">
        <form class="grid max-w-3xl gap-space-lg" @submit.prevent="submit">
            <Panel title="Jadwal dan nomor">
                <div class="grid gap-space-md sm:grid-cols-3">
                    <TextField v-model="form.issue_day" label="Tanggal terbit" type="number" :error="form.errors.issue_day" hint="Tagihan bulanan terbit mulai tanggal ini." required />
                    <TextField v-model="form.due_day" label="Tanggal jatuh tempo" type="number" :error="form.errors.due_day" hint="Bisa ditimpa per kontrak." required />
                    <TextField v-model="form.invoice_prefix" label="Awalan nomor" :error="form.errors.invoice_prefix" :hint="`Contoh: ${preview}`" required />
                </div>
                <p class="mt-space-sm text-body-sm text-text-secondary">Tanggal di atas 28 dipakai sebagai akhir bulan pada bulan yang lebih pendek. Perubahan hanya berlaku untuk tagihan berikutnya.</p>
            </Panel>

            <Panel title="Pro rata">
                <div class="flex flex-col gap-space-sm">
                    <label class="flex items-start gap-space-sm text-body-md text-text-primary">
                        <input v-model="form.prorate_first_month" type="checkbox" class="mt-1 size-4 rounded border-border-strong text-primary-container" />
                        <span>Pro rata bulan pertama<span class="block text-body-sm text-text-secondary">Penghuni yang masuk di tengah bulan hanya ditagih untuk hari yang dipakai.</span></span>
                    </label>
                    <label class="flex items-start gap-space-sm text-body-md text-text-primary">
                        <input v-model="form.prorate_last_month" type="checkbox" class="mt-1 size-4 rounded border-border-strong text-primary-container" />
                        <span>Pro rata bulan terakhir<span class="block text-body-sm text-text-secondary">Kontrak yang berakhir di tengah bulan hanya ditagih sampai tanggal akhirnya.</span></span>
                    </label>
                </div>
            </Panel>

            <div class="flex justify-end">
                <Button type="submit" icon="save" :disabled="form.processing">Simpan aturan</Button>
            </div>
        </form>
    </OwnerLayout>
</template>
