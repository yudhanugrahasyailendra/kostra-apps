<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import RoomsTabs from '@/Components/RoomsTabs.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatRupiah } from '@/lib/formatRupiah';
import type { PropertyData, RoomTypeData, SharedProps } from '@/types';

const props = defineProps<{
    roomTypes: RoomTypeData[];
    properties: PropertyData[];
    can: { create: boolean };
}>();

const page = usePage<SharedProps>();
const typeError = computed(() => (page.props.errors as Record<string, string>).room_type);
const propertyOptions = computed(() => props.properties.map((p) => ({ value: p.id, label: p.name })));

const modal = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({
    property_id: '' as string | number,
    name: '',
    base_price: '' as string | number,
    size_m2: '' as string | number,
    facilities: '',
    description: '',
    is_active: true,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    modal.value = true;
}

function openEdit(type: RoomTypeData) {
    editingId.value = type.id;
    form.clearErrors();
    form.property_id = type.property_id ?? '';
    form.name = type.name;
    form.base_price = type.base_price;
    form.size_m2 = type.size_m2 ?? '';
    form.facilities = type.facilities.join(', ');
    form.description = type.description ?? '';
    form.is_active = type.is_active;
    modal.value = true;
}

function save() {
    // Fasilitas diketik dipisah koma, dikirim sebagai daftar.
    form
        .transform((data) => ({
            ...data,
            facilities: String(data.facilities).split(',').map((f) => f.trim()).filter(Boolean),
        }))
        .submit(editingId.value ? 'patch' : 'post', editingId.value ? `/app/tipe-kamar/${editingId.value}` : '/app/tipe-kamar', {
            preserveScroll: true,
            onSuccess: () => (modal.value = false),
        });
}

function remove(type: RoomTypeData) {
    if (confirm(`Hapus tipe ${type.name}?`)) {
        router.delete(`/app/tipe-kamar/${type.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Tipe kamar" />
    <OwnerLayout title="Manajemen kamar & properti" active="kamar">
        <RoomsTabs active="tipe" />

        <div v-if="typeError" class="mb-space-md rounded-md bg-status-jatuh-tempo-bg p-space-sm text-body-md text-status-jatuh-tempo-text" role="alert">{{ typeError }}</div>

        <div class="mb-space-md flex flex-wrap items-center justify-between gap-space-sm">
            <p class="max-w-2xl text-body-md text-text-secondary">Mengubah harga dasar tipe tidak mengubah harga kamar yang sudah ada.</p>
            <Button v-if="can.create" icon="add" @click="openCreate">Tambah tipe</Button>
        </div>

        <p v-if="roomTypes.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            Belum ada tipe kamar. Tipe membantu mengisi harga dan fasilitas kamar dengan cepat.
        </p>

        <ul class="grid gap-space-md md:grid-cols-2 xl:grid-cols-3" aria-label="Daftar tipe kamar">
            <li v-for="type in roomTypes" :key="type.id" class="flex flex-col gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <div class="flex items-start justify-between gap-space-sm">
                    <div class="min-w-0">
                        <h2 class="truncate text-title-lg text-text-primary">{{ type.name }}</h2>
                        <p class="text-body-sm text-text-secondary">{{ type.property_name ?? 'Semua properti' }} &middot; {{ type.rooms_count ?? 0 }} kamar</p>
                    </div>
                    <StatusBadge :tone="type.is_active ? 'lunas' : 'kosong'" :label="type.is_active ? 'Aktif' : 'Nonaktif'" />
                </div>
                <p class="text-currency-display text-text-primary">{{ formatRupiah(type.base_price) }}<span class="text-body-sm font-normal text-text-secondary"> / bulan</span></p>
                <ul v-if="type.facilities.length" class="flex flex-wrap gap-space-xs">
                    <li v-for="facility in type.facilities" :key="facility" class="rounded-full bg-bg-subtle px-space-sm py-space-xs text-label-md text-text-secondary">{{ facility }}</li>
                </ul>
                <div v-if="can.create" class="mt-auto flex justify-end gap-space-xs border-t border-border-subtle pt-space-sm">
                    <Button variant="ghost" @click="openEdit(type)">Ubah</Button>
                    <Button variant="ghost" @click="remove(type)">Hapus</Button>
                </div>
            </li>
        </ul>

        <Modal :show="modal" :title="editingId ? 'Ubah tipe kamar' : 'Tambah tipe kamar'" @close="modal = false">
            <form id="type-form" class="grid gap-space-md sm:grid-cols-2" @submit.prevent="save">
                <div class="sm:col-span-2"><TextField v-model="form.name" label="Nama tipe" required :error="form.errors.name" /></div>
                <TextField v-model="form.base_price" label="Harga dasar per bulan (Rp)" type="number" required :error="form.errors.base_price" />
                <TextField v-model="form.size_m2" label="Ukuran (m²)" type="number" :error="form.errors.size_m2" />
                <div class="sm:col-span-2"><SelectField v-model="form.property_id" label="Berlaku di" placeholder="Semua properti" :options="propertyOptions" :error="form.errors.property_id" /></div>
                <div class="sm:col-span-2"><TextField v-model="form.facilities" label="Fasilitas" hint="Pisahkan dengan koma, mis. AC, Kamar mandi dalam" :error="form.errors.facilities" /></div>
                <div class="sm:col-span-2"><TextField v-model="form.description" label="Deskripsi" :error="form.errors.description" /></div>
                <label v-if="editingId" class="flex items-center gap-space-sm text-body-md text-text-secondary sm:col-span-2">
                    <input v-model="form.is_active" type="checkbox" class="size-[18px] rounded-sm accent-primary-container" />
                    Tipe aktif
                </label>
            </form>
            <template #footer>
                <Button variant="ghost" @click="modal = false">Batal</Button>
                <Button type="submit" form="type-form" :disabled="form.processing">Simpan</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
