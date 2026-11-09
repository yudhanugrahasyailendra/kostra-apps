<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { portalBase } from '@/lib/portal';
import type { PortalComplaintData, PortalShell } from '@/types';

defineProps<{
    shell: PortalShell;
    complaints: PortalComplaintData[];
    categories: { value: string; label: string }[];
    can_create: boolean;
}>();

const base = portalBase();

const form = useForm({ category: '', title: '', description: '', photos: [] as File[] });

function onPhotos(event: Event) {
    form.photos = Array.from((event.target as HTMLInputElement).files ?? []);
}

function submit() {
    form.post(`${base}/komplain`, { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Komplain" />
    <PortalLayout :shell="shell" active="komplain" title="Lapor kendala">
        <form v-if="can_create" class="grid gap-space-md rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low" @submit.prevent="submit">
            <SelectField v-model="form.category" label="Kategori masalah" placeholder="Pilih kategori" :options="categories" :error="form.errors.category" required />
            <TextField v-model="form.title" label="Judul singkat" :error="form.errors.title" required />
            <div class="flex flex-col gap-space-xs">
                <label for="portal-description" class="text-label-md text-text-primary">Jelaskan masalahnya</label>
                <textarea id="portal-description" v-model="form.description" rows="4" required class="rounded-md border bg-bg-surface px-3 py-2 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15" :class="form.errors.description ? 'border-error' : 'border-border-strong'" />
                <p v-if="form.errors.description" class="text-body-sm text-error">{{ form.errors.description }}</p>
            </div>
            <div class="flex flex-col gap-space-xs">
                <label for="portal-photos" class="text-label-md text-text-primary">Foto (opsional)</label>
                <input id="portal-photos" type="file" multiple accept="image/jpeg,image/png" class="text-body-md text-text-primary" @change="onPhotos" />
                <p v-if="form.errors.photos || form.errors['photos.0']" class="text-body-sm text-error">{{ form.errors.photos ?? form.errors['photos.0'] }}</p>
                <p v-else class="text-body-sm text-text-secondary">JPG atau PNG, maksimal 3 foto, masing-masing 5 MB.</p>
            </div>
            <Button type="submit" icon="send" :disabled="form.processing">Kirim laporan</Button>
        </form>
        <p v-else class="rounded-lg border border-dashed border-border-strong p-space-lg text-center text-body-md text-text-secondary">
            Pengajuan komplain belum tersedia untuk Anda saat ini.
        </p>

        <section class="mt-space-lg" aria-label="Laporan saya">
            <h2 class="mb-space-sm text-title-lg text-text-primary">Laporan saya</h2>
            <p v-if="complaints.length === 0" class="text-body-md text-text-secondary">Belum ada laporan.</p>
            <ul v-else class="grid gap-space-sm">
                <li v-for="complaint in complaints" :key="complaint.id">
                    <Link :href="`${base}/komplain/${complaint.id}`" class="block rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                        <div class="flex items-start justify-between gap-space-sm">
                            <p class="text-title-md text-text-primary">{{ complaint.title }}</p>
                            <StatusBadge :tone="complaint.status_tone" :label="complaint.status_label" />
                        </div>
                        <p class="text-body-sm text-text-secondary">{{ complaint.category_label }} · {{ formatDate(complaint.created_at) }}</p>
                    </Link>
                </li>
            </ul>
        </section>
    </PortalLayout>
</template>
