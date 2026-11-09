<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import RoomPlate from '@/Components/RoomPlate.vue';
import RoomsTabs from '@/Components/RoomsTabs.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatRupiah } from '@/lib/formatRupiah';
import type { PropertyData, RoomData, RoomTypeData, SharedProps } from '@/types';

const props = defineProps<{
    rooms: RoomData[];
    pagination: { current_page: number; last_page: number; total: number; prev_url: string | null; next_url: string | null };
    filters: { status: string | null; property_id: number | null; room_type_id: number | null };
    properties: PropertyData[];
    roomTypes: RoomTypeData[];
    statusOptions: { value: string; label: string }[];
    manualStatusOptions: { value: string; label: string }[];
    usage: { used: number; max: number | null };
    can: { create: boolean };
}>();

const page = usePage<SharedProps>();
const importResult = computed(() => page.props.flash.import_result);

const view = ref<'grid' | 'table'>('grid');
const filter = reactive({
    property_id: props.filters.property_id ?? '',
    room_type_id: props.filters.room_type_id ?? '',
    status: props.filters.status ?? '',
});

function applyFilter() {
    const query = Object.fromEntries(Object.entries(filter).filter(([, v]) => v !== ''));
    router.get('/app/kamar', query, { preserveState: true, preserveScroll: true });
}

const propertyOptions = computed(() => props.properties.map((p) => ({ value: p.id, label: p.name })));
const typeOptions = computed(() => props.roomTypes.map((t) => ({ value: t.id, label: t.name })));
const usagePercent = computed(() => (props.usage.max ? Math.min(100, Math.round((props.usage.used / props.usage.max) * 100)) : 0));

const byFloor = computed(() => {
    const groups = new Map<string, RoomData[]>();
    for (const room of props.rooms) {
        const key = room.floor === null ? 'Tanpa lantai' : `Lantai ${room.floor}`;
        groups.set(key, [...(groups.get(key) ?? []), room]);
    }
    return [...groups.entries()];
});

const counts = computed(() => ({
    occupied: props.rooms.filter((r) => r.status === 'occupied').length,
    vacant: props.rooms.filter((r) => r.status === 'vacant').length,
    maintenance: props.rooms.filter((r) => r.status === 'maintenance').length,
}));

// Tambah dan ubah satu kamar
const roomModal = ref(false);
const editingId = ref<number | null>(null);
const editingOccupied = computed(() => props.rooms.some((room) => room.id === editingId.value && room.status === 'occupied'));
const roomForm = useForm({ property_id: '' as string | number, room_type_id: '' as string | number, number: '', floor: '' as string | number, price: '' as string | number, status: 'vacant', notes: '' });

function openCreate() {
    editingId.value = null;
    roomForm.reset();
    roomForm.clearErrors();
    roomForm.property_id = props.properties.length === 1 ? props.properties[0].id : '';
    roomModal.value = true;
}

function openEdit(room: RoomData) {
    editingId.value = room.id;
    roomForm.clearErrors();
    roomForm.property_id = room.property_id;
    roomForm.room_type_id = room.room_type_id ?? '';
    roomForm.number = room.number;
    roomForm.floor = room.floor ?? '';
    roomForm.price = room.price;
    roomForm.status = room.status === 'occupied' ? 'vacant' : room.status;
    roomForm.notes = room.notes ?? '';
    roomModal.value = true;
}

function saveRoom() {
    const options = { preserveScroll: true, onSuccess: () => (roomModal.value = false) };
    if (editingId.value) {
        roomForm.patch(`/app/kamar/${editingId.value}`, options);
    } else {
        roomForm.post('/app/kamar', options);
    }
}

function removeRoom(room: RoomData) {
    if (confirm(`Hapus kamar ${room.number}?`)) {
        router.delete(`/app/kamar/${room.id}`, { preserveScroll: true });
    }
}

// Tambah massal
const bulkModal = ref(false);
const bulkForm = useForm({ property_id: '' as string | number, prefix: '', start: '' as string | number, end: '' as string | number, floor: '' as string | number, room_type_id: '' as string | number, price: '' as string | number });

function saveBulk() {
    bulkForm.post('/app/kamar/massal', { preserveScroll: true, onSuccess: () => { bulkModal.value = false; bulkForm.reset(); } });
}

// Impor CSV
const importModal = ref(false);
const importForm = useForm({ property_id: '' as string | number, file: null as File | null });

function saveImport() {
    importForm.post('/app/kamar/impor', { preserveScroll: true, forceFormData: true, onSuccess: () => importForm.reset('file') });
}
</script>

<template>
    <Head title="Kamar" />
    <OwnerLayout title="Manajemen kamar & properti" active="kamar">
        <RoomsTabs active="kamar" />

        <div class="mb-space-lg grid gap-space-md lg:grid-cols-[1fr_20rem]">
            <p class="max-w-2xl text-body-md text-text-secondary">Kelola kamar di semua properti Anda, pantau status, dan atur harga. Status "Terisi" ditentukan oleh kontrak sewa aktif.</p>
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <div class="flex items-baseline justify-between">
                    <span class="text-label-sm uppercase text-text-secondary">Kamar terdaftar</span>
                    <span class="text-title-md text-text-primary">{{ usage.used }}{{ usage.max !== null ? ` / ${usage.max}` : '' }}</span>
                </div>
                <div v-if="usage.max !== null" class="mt-space-sm h-2 overflow-hidden rounded-full bg-bg-subtle" role="progressbar" :aria-valuenow="usagePercent" aria-valuemin="0" aria-valuemax="100" aria-label="Pemakaian kuota kamar">
                    <div class="h-full rounded-full bg-primary-container" :style="{ width: `${usagePercent}%` }" />
                </div>
                <p v-else class="mt-space-xs text-body-sm text-text-secondary">Tanpa batas pada paket Anda.</p>
            </div>
        </div>

        <div class="mb-space-md flex flex-wrap items-center justify-between gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-sm">
            <div class="flex rounded-md bg-bg-subtle p-space-xs" role="group" aria-label="Tampilan">
                <button type="button" class="rounded-md px-space-md py-space-xs text-title-md" :class="view === 'grid' ? 'bg-bg-surface shadow-low' : 'text-text-secondary'" :aria-pressed="view === 'grid'" @click="view = 'grid'">Grid</button>
                <button type="button" class="rounded-md px-space-md py-space-xs text-title-md" :class="view === 'table' ? 'bg-bg-surface shadow-low' : 'text-text-secondary'" :aria-pressed="view === 'table'" @click="view = 'table'">Tabel</button>
            </div>
            <div v-if="can.create" class="flex flex-wrap gap-space-sm">
                <Button variant="secondary" icon="upload_file" @click="importModal = true">Impor CSV</Button>
                <Button variant="secondary" icon="library_add" @click="bulkModal = true">Tambah massal</Button>
                <Button icon="add" @click="openCreate">Tambah kamar</Button>
            </div>
        </div>

        <form class="mb-space-md grid gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-md sm:grid-cols-2 lg:grid-cols-4 lg:items-end" @submit.prevent="applyFilter">
            <SelectField v-model="filter.property_id" label="Properti" placeholder="Semua properti" :options="propertyOptions" />
            <SelectField v-model="filter.room_type_id" label="Tipe" placeholder="Semua tipe" :options="roomTypes.map((t) => ({ value: t.id, label: t.name }))" />
            <SelectField v-model="filter.status" label="Status" placeholder="Semua status" :options="statusOptions" />
            <Button type="submit" variant="secondary" icon="filter_list">Terapkan filter</Button>
            <p class="text-body-sm text-text-secondary sm:col-span-2 lg:col-span-4">
                Ditampilkan: Terisi {{ counts.occupied }} &middot; Kosong {{ counts.vacant }} &middot; Perawatan {{ counts.maintenance }} ({{ pagination.total }} kamar)
            </p>
        </form>

        <p v-if="rooms.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            Belum ada kamar. Tambahkan satu per satu, massal (mis. 101 sampai 120), atau impor dari CSV.
        </p>

        <template v-else-if="view === 'grid'">
            <section v-for="[floor, floorRooms] in byFloor" :key="floor" class="mb-space-lg" :aria-label="floor">
                <h2 class="mb-space-sm text-title-lg text-text-primary">{{ floor }} <span class="text-body-sm font-normal text-text-secondary">({{ floorRooms.length }} kamar)</span></h2>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] gap-space-md">
                    <RoomPlate v-for="room in floorRooms" :key="room.id" :room="room" :can-manage="can.create" @edit="openEdit" @remove="removeRoom" />
                </div>
            </section>
        </template>

        <div v-else class="overflow-x-auto rounded-lg border border-border-subtle bg-bg-surface">
            <table class="w-full text-left text-body-md">
                <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                    <tr>
                        <th scope="col" class="px-space-md py-space-sm">Nomor</th>
                        <th scope="col" class="px-space-md py-space-sm">Properti</th>
                        <th scope="col" class="px-space-md py-space-sm">Tipe</th>
                        <th scope="col" class="px-space-md py-space-sm">Lantai</th>
                        <th scope="col" class="px-space-md py-space-sm text-right">Harga</th>
                        <th scope="col" class="px-space-md py-space-sm">Status</th>
                        <th v-if="can.create" scope="col" class="px-space-md py-space-sm"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle text-text-primary">
                    <tr v-for="room in rooms" :key="room.id">
                        <td class="px-space-md py-space-sm font-semibold">{{ room.number }}</td>
                        <td class="px-space-md py-space-sm">{{ room.property_name }}</td>
                        <td class="px-space-md py-space-sm">{{ room.room_type_name ?? '-' }}</td>
                        <td class="px-space-md py-space-sm">{{ room.floor ?? '-' }}</td>
                        <td class="px-space-md py-space-sm text-right tabular-nums">{{ formatRupiah(room.price) }}</td>
                        <td class="px-space-md py-space-sm"><StatusBadge :tone="room.status_tone" :label="room.status_label" /></td>
                        <td v-if="can.create" class="whitespace-nowrap px-space-md py-space-sm text-right">
                            <Button variant="ghost" @click="openEdit(room)">Ubah</Button>
                            <Button variant="ghost" @click="removeRoom(room)">Hapus</Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between text-body-sm text-text-secondary" aria-label="Halaman">
            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
            <div class="flex gap-space-xs">
                <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
            </div>
        </nav>

        <!-- Tambah / ubah kamar -->
        <Modal :show="roomModal" :title="editingId ? 'Ubah kamar' : 'Tambah kamar'" @close="roomModal = false">
            <form id="room-form" class="grid gap-space-md sm:grid-cols-2" @submit.prevent="saveRoom">
                <SelectField v-model="roomForm.property_id" label="Properti" required placeholder="Pilih properti" :options="propertyOptions" :error="roomForm.errors.property_id" />
                <TextField v-model="roomForm.number" label="Nomor kamar" required :error="roomForm.errors.number" />
                <SelectField v-model="roomForm.room_type_id" label="Tipe" placeholder="Tanpa tipe" :options="typeOptions" :error="roomForm.errors.room_type_id" />
                <TextField v-model="roomForm.floor" label="Lantai" type="number" :error="roomForm.errors.floor" />
                <TextField v-model="roomForm.price" label="Harga per bulan (Rp)" type="number" :error="roomForm.errors.price" hint="Kosong = ikut harga dasar tipe." />
                <p v-if="editingOccupied" class="rounded-md border border-border-subtle bg-bg-subtle p-space-sm text-body-sm text-text-secondary">
                    Kamar ini terisi oleh kontrak aktif. Status berubah lewat check-out atau pindah kamar.
                </p>
                <SelectField v-else v-model="roomForm.status" label="Status" :options="manualStatusOptions" :error="roomForm.errors.status" />
                <div class="sm:col-span-2"><TextField v-model="roomForm.notes" label="Catatan" :error="roomForm.errors.notes" /></div>
            </form>
            <template #footer>
                <Button variant="ghost" @click="roomModal = false">Batal</Button>
                <Button type="submit" form="room-form" :disabled="roomForm.processing">Simpan</Button>
            </template>
        </Modal>

        <!-- Tambah massal -->
        <Modal :show="bulkModal" title="Tambah kamar massal" @close="bulkModal = false">
            <form id="bulk-form" class="grid gap-space-md sm:grid-cols-2" @submit.prevent="saveBulk">
                <div class="sm:col-span-2"><SelectField v-model="bulkForm.property_id" label="Properti" required placeholder="Pilih properti" :options="propertyOptions" :error="bulkForm.errors.property_id" /></div>
                <TextField v-model="bulkForm.start" label="Dari nomor" type="number" required :error="bulkForm.errors.start" hint="Contoh: 101" />
                <TextField v-model="bulkForm.end" label="Sampai nomor" type="number" required :error="bulkForm.errors.end" hint="Contoh: 120 (maks. 100 kamar)" />
                <TextField v-model="bulkForm.prefix" label="Awalan nomor (opsional)" :error="bulkForm.errors.prefix" hint="Contoh: A, menjadi A101" />
                <TextField v-model="bulkForm.floor" label="Lantai" type="number" :error="bulkForm.errors.floor" />
                <SelectField v-model="bulkForm.room_type_id" label="Tipe" placeholder="Tanpa tipe" :options="typeOptions" :error="bulkForm.errors.room_type_id" />
                <TextField v-model="bulkForm.price" label="Harga per bulan (Rp)" type="number" :error="bulkForm.errors.price" hint="Kosong = ikut harga dasar tipe." />
            </form>
            <template #footer>
                <Button variant="ghost" @click="bulkModal = false">Batal</Button>
                <Button type="submit" form="bulk-form" :disabled="bulkForm.processing">Buat kamar</Button>
            </template>
        </Modal>

        <!-- Impor CSV -->
        <Modal :show="importModal" title="Impor kamar dari CSV" @close="importModal = false">
            <form id="import-form" class="flex flex-col gap-space-md" @submit.prevent="saveImport">
                <p class="text-body-md text-text-secondary">
                    Gunakan <a href="/app/kamar/impor/templat" class="text-primary-container underline">templat CSV</a>. Dari Excel, pilih Simpan sebagai CSV. Kolom: nomor, lantai, tipe, harga, catatan.
                </p>
                <SelectField v-model="importForm.property_id" label="Properti tujuan" required placeholder="Pilih properti" :options="propertyOptions" :error="importForm.errors.property_id" />
                <div class="flex flex-col gap-space-xs">
                    <label for="import-file" class="text-label-md text-text-primary">Berkas CSV</label>
                    <input id="import-file" type="file" accept=".csv,text/csv,text/plain" required class="text-body-md" @change="importForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                    <p v-if="importForm.errors.file" class="text-body-sm text-error">{{ importForm.errors.file }}</p>
                </div>
            </form>

            <div v-if="importResult" class="mt-space-md rounded-md border border-border-subtle p-space-md" role="status">
                <p class="text-title-md text-text-primary">{{ importResult.created }} kamar masuk, {{ importResult.failures.length }} baris gagal.</p>
                <ul v-if="importResult.failures.length" class="mt-space-sm flex max-h-48 flex-col gap-space-xs overflow-y-auto text-body-sm text-status-jatuh-tempo-text">
                    <li v-for="failure in importResult.failures" :key="failure.row">Baris {{ failure.row }}<span v-if="failure.number"> (kamar {{ failure.number }})</span>: {{ failure.reason }}</li>
                </ul>
            </div>
            <template #footer>
                <Button variant="ghost" @click="importModal = false">Tutup</Button>
                <Button type="submit" form="import-form" :disabled="importForm.processing" icon="upload">Impor</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
