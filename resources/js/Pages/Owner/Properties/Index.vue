<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import RoomsTabs from '@/Components/RoomsTabs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import type { PropertyData, SharedProps } from '@/types';

const props = defineProps<{
    properties: PropertyData[];
    usage: { used: number; max: number | null };
    can: { create: boolean };
}>();

const page = usePage<SharedProps>();
const propertyError = computed(() => (page.props.errors as Record<string, string>).property);

const modal = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({ name: '', address: '', city: '', floors: '' as string | number, notes: '', is_active: true });

function openCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    modal.value = true;
}

function openEdit(property: PropertyData) {
    editingId.value = property.id;
    form.clearErrors();
    form.name = property.name;
    form.address = property.address ?? '';
    form.city = property.city ?? '';
    form.floors = property.floors ?? '';
    form.notes = property.notes ?? '';
    form.is_active = property.is_active;
    modal.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (modal.value = false) };
    if (editingId.value) {
        form.patch(`/app/properti/${editingId.value}`, options);
    } else {
        form.post('/app/properti', options);
    }
}

function remove(property: PropertyData) {
    if (confirm(`Hapus properti ${property.name}?`)) {
        router.delete(`/app/properti/${property.id}`, { preserveScroll: true });
    }
}

const usageLabel = computed(() => `${props.usage.used}${props.usage.max !== null ? ` / ${props.usage.max}` : ''} properti`);
</script>

<template>
    <Head title="Properti" />
    <OwnerLayout title="Manajemen kamar & properti" active="kamar">
        <RoomsTabs active="properti" />

        <div v-if="propertyError" class="mb-space-md rounded-md bg-status-jatuh-tempo-bg p-space-sm text-body-md text-status-jatuh-tempo-text" role="alert">{{ propertyError }}</div>

        <div class="mb-space-md flex flex-wrap items-center justify-between gap-space-sm">
            <p class="text-body-md text-text-secondary">{{ usageLabel }}</p>
            <Button v-if="can.create" icon="add_home" @click="openCreate">Tambah properti</Button>
        </div>

        <p v-if="properties.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            Belum ada properti. Tambahkan properti pertama Anda untuk mulai memasukkan kamar.
        </p>

        <ul class="grid gap-space-md md:grid-cols-2" aria-label="Daftar properti">
            <li v-for="property in properties" :key="property.id" class="flex flex-col gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <div class="flex items-start justify-between gap-space-sm">
                    <div class="min-w-0">
                        <h2 class="truncate text-title-lg text-text-primary">{{ property.name }}</h2>
                        <p class="text-body-sm text-text-secondary">{{ [property.address, property.city].filter(Boolean).join(', ') || 'Alamat belum diisi' }}</p>
                    </div>
                    <StatusBadge :tone="property.is_active ? 'lunas' : 'kosong'" :label="property.is_active ? 'Aktif' : 'Nonaktif'" />
                </div>
                <p class="text-body-md text-text-primary">{{ property.rooms_count ?? 0 }} kamar<span v-if="property.floors"> &middot; {{ property.floors }} lantai</span></p>
                <div v-if="can.create" class="mt-auto flex justify-end gap-space-xs border-t border-border-subtle pt-space-sm">
                    <Button variant="ghost" @click="openEdit(property)">Ubah</Button>
                    <Button variant="ghost" @click="remove(property)">Hapus</Button>
                </div>
            </li>
        </ul>

        <Modal :show="modal" :title="editingId ? 'Ubah properti' : 'Tambah properti'" @close="modal = false">
            <form id="property-form" class="flex flex-col gap-space-md" @submit.prevent="save">
                <TextField v-model="form.name" label="Nama properti" required :error="form.errors.name" />
                <TextField v-model="form.address" label="Alamat" :error="form.errors.address" />
                <div class="grid gap-space-md sm:grid-cols-2">
                    <TextField v-model="form.city" label="Kota" :error="form.errors.city" />
                    <TextField v-model="form.floors" label="Jumlah lantai" type="number" :error="form.errors.floors" />
                </div>
                <TextField v-model="form.notes" label="Catatan" :error="form.errors.notes" />
                <label v-if="editingId" class="flex items-center gap-space-sm text-body-md text-text-secondary">
                    <input v-model="form.is_active" type="checkbox" class="size-[18px] rounded-sm accent-primary-container" />
                    Properti aktif
                </label>
            </form>
            <template #footer>
                <Button variant="ghost" @click="modal = false">Batal</Button>
                <Button type="submit" form="property-form" :disabled="form.processing">Simpan</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
