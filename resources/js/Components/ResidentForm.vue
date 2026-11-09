<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextField from '@/Components/TextField.vue';

export interface ResidentFormData {
    name: string;
    phone: string;
    email: string;
    occupation: string;
    emergency_name: string;
    emergency_relation: string;
    emergency_phone: string;
    notes: string;
    consented: boolean;
}

// Isi form dipegang induk (useForm); komponen ini hanya tata letak kolom.
defineProps<{ form: InertiaForm<ResidentFormData>; id: string; consentRecorded?: boolean }>();
defineEmits<{ submit: [] }>();
</script>

<template>
    <form :id="id" class="grid gap-space-md sm:grid-cols-2" @submit.prevent="$emit('submit')">
        <div class="sm:col-span-2"><TextField v-model="form.name" label="Nama lengkap" required autocomplete="off" :error="form.errors.name" /></div>
        <TextField v-model="form.phone" label="Nomor WhatsApp" type="tel" required autocomplete="off" hint="Contoh: 0812 3456 7890. Disimpan sebagai +62." :error="form.errors.phone" />
        <TextField v-model="form.email" label="Email (opsional)" type="email" autocomplete="off" :error="form.errors.email" />
        <div class="sm:col-span-2"><TextField v-model="form.occupation" label="Pekerjaan atau kampus" autocomplete="off" :error="form.errors.occupation" /></div>

        <fieldset class="grid gap-space-md sm:col-span-2 sm:grid-cols-3">
            <legend class="mb-space-sm text-label-md uppercase text-text-secondary">Kontak darurat</legend>
            <TextField v-model="form.emergency_name" label="Nama" autocomplete="off" :error="form.errors.emergency_name" />
            <TextField v-model="form.emergency_relation" label="Hubungan" autocomplete="off" :error="form.errors.emergency_relation" />
            <TextField v-model="form.emergency_phone" label="Nomor telepon" type="tel" autocomplete="off" :error="form.errors.emergency_phone" />
        </fieldset>

        <div class="flex flex-col gap-space-xs sm:col-span-2">
            <label :for="`${id}-notes`" class="text-label-md text-text-primary">Catatan internal</label>
            <textarea
                :id="`${id}-notes`"
                v-model="form.notes"
                rows="3"
                class="rounded-md border border-border-strong bg-bg-surface px-3 py-2 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
                aria-describedby="notes-hint"
            />
            <p id="notes-hint" class="text-body-sm text-text-secondary">Hanya terlihat oleh pemilik dan staf. Tidak tampil di portal penghuni.</p>
            <p v-if="form.errors.notes" class="text-body-sm text-error">{{ form.errors.notes }}</p>
        </div>

        <label class="flex items-start gap-space-sm sm:col-span-2">
            <input v-model="form.consented" type="checkbox" class="mt-1 h-4 w-4 rounded border-border-strong text-primary-container" :disabled="consentRecorded" />
            <span class="text-body-md text-text-primary">
                Penghuni sudah menyetujui pengumpulan dan penggunaan datanya untuk pengelolaan kos
                <span v-if="consentRecorded" class="text-text-secondary">(sudah tercatat)</span>.
            </span>
        </label>
    </form>
</template>
