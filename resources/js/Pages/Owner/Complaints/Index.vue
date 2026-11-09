<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import type { ComplaintData, PaginationData } from '@/types';

type Option = { value: string; label: string };

const props = defineProps<{
    complaints: ComplaintData[];
    pagination: PaginationData;
    filters: { status: string; room_id: number | null; category: string | null; assigned_to: string | null; stale: boolean };
    stale_count: number;
    statuses: Option[];
    categories: Option[];
    rooms: { id: number; label: string }[];
    residents: { id: number; name: string }[];
    staff: { id: number; name: string }[];
    can: { create: boolean };
}>();

const status = ref(props.filters.status);
const roomId = ref<number | ''>(props.filters.room_id ?? '');
const category = ref(props.filters.category ?? '');
const assignedTo = ref(props.filters.assigned_to ?? '');

function go(overrides: Record<string, string> = {}) {
    const query = new URLSearchParams({ status: status.value });
    if (roomId.value !== '') query.set('room_id', String(roomId.value));
    if (category.value !== '') query.set('category', category.value);
    if (assignedTo.value !== '') query.set('assigned_to', String(assignedTo.value));
    if (props.filters.stale) query.set('stale', '1');
    Object.entries(overrides).forEach(([key, value]) => (value === '' ? query.delete(key) : query.set(key, value)));
    router.get(`/app/komplain?${query.toString()}`, {}, { preserveState: true, preserveScroll: true });
}

const statusOptions: Option[] = [
    { value: 'open', label: 'Masih terbuka' },
    { value: 'all', label: 'Semua status' },
    ...props.statuses,
];

const formOpen = ref(false);
const form = useForm({
    room_id: '' as number | '',
    resident_id: '' as number | '',
    category: '',
    title: '',
    description: '',
    photos: [] as File[],
});

function openCreate() {
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function onPhotos(event: Event) {
    form.photos = Array.from((event.target as HTMLInputElement).files ?? []);
}

function submit() {
    form.post('/app/komplain', { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Komplain" />
    <OwnerLayout title="Komplain" active="komplain">
        <div class="mb-space-lg flex flex-wrap items-start justify-between gap-space-md">
            <p class="max-w-2xl text-body-md text-text-secondary">
                Keluhan penghuni tercatat sebagai tiket sampai selesai atau ditolak. Tiket tidak dihapus, hanya ditutup.
            </p>
            <Button v-if="can.create" icon="add" @click="openCreate">Buat tiket</Button>
        </div>

        <div v-if="stale_count > 0" class="mb-space-md flex flex-wrap items-center justify-between gap-space-sm rounded-lg border border-status-menunggu-border bg-status-menunggu-bg p-space-md text-status-menunggu-text" role="status">
            <span class="text-body-md">{{ stale_count }} tiket terbuka lebih dari 3 hari.</span>
            <Link :href="`/app/komplain?status=open&stale=${filters.stale ? 0 : 1}`" class="text-title-md underline">
                {{ filters.stale ? 'Tampilkan semua' : 'Tampilkan yang tertunda' }}
            </Link>
        </div>

        <form class="mb-space-md grid gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-sm sm:grid-cols-2 lg:grid-cols-4" role="search" @submit.prevent="go()">
            <SelectField v-model="status" label="Status" :options="statusOptions" @change="go()" />
            <SelectField v-model="roomId" label="Kamar" placeholder="Semua kamar" :options="rooms.map((r) => ({ value: r.id, label: r.label }))" @change="go()" />
            <SelectField v-model="category" label="Kategori" placeholder="Semua kategori" :options="categories" @change="go()" />
            <SelectField
                v-model="assignedTo"
                label="Penanggung jawab"
                placeholder="Semua"
                :options="[{ value: 'none', label: 'Belum ditugaskan' }, ...staff.map((s) => ({ value: s.id, label: s.name }))]"
                @change="go()"
            />
        </form>

        <p v-if="complaints.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            Tidak ada tiket untuk filter ini.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border border-border-subtle bg-bg-surface">
            <table class="w-full min-w-[52rem] text-left text-body-md">
                <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                    <tr>
                        <th scope="col" class="px-space-md py-space-sm">Tiket</th>
                        <th scope="col" class="px-space-md py-space-sm">Kamar &amp; penghuni</th>
                        <th scope="col" class="px-space-md py-space-sm">Kategori</th>
                        <th scope="col" class="px-space-md py-space-sm">Penanggung jawab</th>
                        <th scope="col" class="px-space-md py-space-sm">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle text-text-primary">
                    <tr v-for="complaint in complaints" :key="complaint.id">
                        <td class="px-space-md py-space-sm">
                            <Link :href="`/app/komplain/${complaint.id}`" class="text-title-md text-primary-container hover:underline">{{ complaint.title }}</Link>
                            <span class="block text-body-sm text-text-secondary">Dibuat {{ formatDate(complaint.created_at, 'short') }}</span>
                            <span v-if="complaint.is_stale" class="mt-space-xs inline-flex items-center gap-1 rounded-full border border-status-menunggu-border bg-status-menunggu-bg px-2 py-0.5 text-label-sm uppercase text-status-menunggu-text">
                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
                                Terbuka &gt; 3 hari
                            </span>
                        </td>
                        <td class="px-space-md py-space-sm">
                            <span class="text-title-md">Kamar {{ complaint.room_number }}</span>
                            <span class="block text-body-sm text-text-secondary">{{ complaint.resident_name }} · {{ complaint.property_name }}</span>
                        </td>
                        <td class="px-space-md py-space-sm text-body-sm">{{ complaint.category_label }}</td>
                        <td class="px-space-md py-space-sm text-body-sm">{{ complaint.assignee_name ?? 'Belum ditugaskan' }}</td>
                        <td class="px-space-md py-space-sm"><StatusBadge :tone="complaint.status_tone" :label="complaint.status_label" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between text-body-sm text-text-secondary" aria-label="Halaman">
            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} tiket)</span>
            <div class="flex gap-space-xs">
                <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
            </div>
        </nav>

        <Modal :show="formOpen" title="Buat tiket komplain" @close="formOpen = false">
            <form class="grid gap-space-md" @submit.prevent="submit">
                <SelectField v-model="form.room_id" label="Kamar" placeholder="Pilih kamar" :options="rooms.map((r) => ({ value: r.id, label: r.label }))" :error="form.errors.room_id" required />
                <SelectField v-model="form.resident_id" label="Penghuni pengaju" placeholder="Pilih penghuni" :options="residents.map((r) => ({ value: r.id, label: r.name }))" :error="form.errors.resident_id" required />
                <SelectField v-model="form.category" label="Kategori" placeholder="Pilih kategori" :options="categories" :error="form.errors.category" required />
                <TextField v-model="form.title" label="Judul" :error="form.errors.title" required />
                <div class="flex flex-col gap-space-xs">
                    <label for="complaint-description" class="text-label-md text-text-primary">Deskripsi</label>
                    <textarea id="complaint-description" v-model="form.description" rows="4" required class="rounded-md border border-border-strong bg-bg-surface px-3 py-2 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15" />
                    <p v-if="form.errors.description" class="text-body-sm text-error">{{ form.errors.description }}</p>
                </div>
                <div class="flex flex-col gap-space-xs">
                    <label for="complaint-photos" class="text-label-md text-text-primary">Foto (opsional)</label>
                    <input id="complaint-photos" type="file" multiple accept="image/jpeg,image/png" class="text-body-md text-text-primary" @change="onPhotos" />
                    <p v-if="form.errors.photos || form.errors['photos.0']" class="text-body-sm text-error">{{ form.errors.photos ?? form.errors['photos.0'] }}</p>
                    <p v-else class="text-body-sm text-text-secondary">JPG atau PNG, maksimal 5 foto, masing-masing 5 MB.</p>
                </div>
            </form>
            <template #footer>
                <Button variant="ghost" @click="formOpen = false">Batal</Button>
                <Button icon="save" :disabled="form.processing" @click="submit">Buat tiket</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
