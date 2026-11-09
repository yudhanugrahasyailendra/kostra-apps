<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import ResidentForm, { type ResidentFormData } from '@/Components/ResidentForm.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatPhone, whatsappLink } from '@/lib/formatPhone';
import type { ResidentData, SharedProps } from '@/types';

const props = defineProps<{
    residents: ResidentData[];
    pagination: { current_page: number; last_page: number; total: number; prev_url: string | null; next_url: string | null };
    filters: { q: string | null; status: 'active' | 'inactive' | null };
    counts: { all: number; active: number; inactive: number };
    can: { create: boolean };
}>();

const page = usePage<SharedProps>();
const importResult = computed(() => page.props.flash.resident_import_result);

// Pencarian dan filter lewat query string supaya bisa disalin dan dimuat ulang.
const search = ref(props.filters.q ?? '');

function go(status: string | null, q: string | null = search.value) {
    const query: Record<string, string> = {};
    if (q) query.q = q;
    if (status) query.status = status;
    router.get('/app/penghuni', query, { preserveState: true, preserveScroll: true });
}

const tabs = computed(() => [
    { key: null, label: 'Semua', count: props.counts.all },
    { key: 'active', label: 'Aktif', count: props.counts.active },
    { key: 'inactive', label: 'Riwayat (alumni)', count: props.counts.inactive },
]);

// Tambah penghuni
const createOpen = ref(false);
const createForm = useForm<ResidentFormData>({
    name: '', phone: '', email: '', occupation: '',
    emergency_name: '', emergency_relation: '', emergency_phone: '',
    notes: '', consented: false,
});

function save() {
    createForm.post('/app/penghuni', { preserveScroll: true, onSuccess: () => createForm.reset() });
}

// Impor CSV
const importOpen = ref(false);
const importForm = useForm({ file: null as File | null });

function saveImport() {
    importForm.post('/app/penghuni/impor', { preserveScroll: true, forceFormData: true, onSuccess: () => importForm.reset('file') });
}
</script>

<template>
    <Head title="Penghuni" />
    <OwnerLayout title="Penghuni" active="penghuni">
        <div class="mb-space-lg flex flex-wrap items-start justify-between gap-space-md">
            <p class="max-w-2xl text-body-md text-text-secondary">
                Data orang yang menyewa kamar. Nomor WhatsApp wajib karena menjadi saluran tagihan. Foto KTP bersifat opsional dan disimpan terenkripsi.
            </p>
            <div v-if="can.create" class="flex flex-wrap gap-space-sm">
                <Button variant="secondary" icon="upload_file" @click="importOpen = true">Impor CSV</Button>
                <Button icon="person_add" @click="createOpen = true">Tambah penghuni</Button>
            </div>
        </div>

        <div class="mb-space-md flex flex-col gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-sm lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap rounded-md bg-bg-subtle p-space-xs" role="group" aria-label="Status penghuni">
                <button
                    v-for="tab in tabs"
                    :key="tab.label"
                    type="button"
                    class="rounded-md px-space-md py-space-xs text-title-md"
                    :class="tab.key === filters.status ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary hover:text-text-primary'"
                    :aria-pressed="tab.key === filters.status"
                    @click="go(tab.key)"
                >
                    {{ tab.label }} ({{ tab.count }})
                </button>
            </div>
            <form class="flex flex-1 gap-space-sm lg:max-w-md" role="search" @submit.prevent="go(filters.status)">
                <label for="resident-search" class="sr-only">Cari penghuni</label>
                <input
                    id="resident-search"
                    v-model="search"
                    type="search"
                    maxlength="100"
                    placeholder="Cari nama, nomor WhatsApp, atau pekerjaan"
                    class="h-[42px] w-full rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
                />
                <Button type="submit" variant="secondary" icon="search">Cari</Button>
            </form>
        </div>

        <p v-if="residents.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            <template v-if="filters.q || filters.status">Tidak ada penghuni yang cocok dengan pencarian ini.</template>
            <template v-else>Belum ada penghuni. Tambahkan satu per satu atau impor dari CSV.</template>
        </p>

        <div v-else class="overflow-x-auto rounded-lg border border-border-subtle bg-bg-surface">
            <table class="w-full min-w-[40rem] text-left text-body-md">
                <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                    <tr>
                        <th scope="col" class="px-space-md py-space-sm">Penghuni</th>
                        <th scope="col" class="px-space-md py-space-sm">Kontak darurat</th>
                        <th scope="col" class="px-space-md py-space-sm">Dokumen</th>
                        <th scope="col" class="px-space-md py-space-sm">Status</th>
                        <th scope="col" class="px-space-md py-space-sm"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle text-text-primary">
                    <tr v-for="resident in residents" :key="resident.id">
                        <td class="px-space-md py-space-sm">
                            <Link :href="`/app/penghuni/${resident.id}`" class="text-title-md text-primary-container hover:underline">{{ resident.name }}</Link>
                            <div class="mt-space-xs flex flex-wrap items-center gap-space-xs text-body-sm text-text-secondary">
                                <a :href="whatsappLink(resident.phone)" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ formatPhone(resident.phone) }}</a>
                                <span v-if="resident.has_duplicate_phone" class="rounded-full border border-status-menunggu-border bg-status-menunggu-bg px-space-sm text-label-sm text-status-menunggu-text">Nomor ganda</span>
                            </div>
                            <p v-if="resident.occupation" class="text-body-sm text-text-secondary">{{ resident.occupation }}</p>
                        </td>
                        <td class="px-space-md py-space-sm text-body-sm">
                            <template v-if="resident.emergency_name || resident.emergency_phone">
                                <span class="block text-text-primary">{{ resident.emergency_name }}<span v-if="resident.emergency_relation"> ({{ resident.emergency_relation }})</span></span>
                                <span class="text-text-secondary">{{ formatPhone(resident.emergency_phone) }}</span>
                            </template>
                            <span v-else class="text-text-secondary">-</span>
                        </td>
                        <td class="px-space-md py-space-sm">
                            <StatusBadge v-if="resident.has_ktp" tone="lunas" label="KTP terenkripsi" />
                            <span v-else class="text-body-sm text-text-secondary">Belum ada foto KTP</span>
                        </td>
                        <td class="px-space-md py-space-sm"><StatusBadge :tone="resident.status_tone" :label="resident.status_label" /></td>
                        <td class="whitespace-nowrap px-space-md py-space-sm text-right">
                            <Link :href="`/app/penghuni/${resident.id}`" class="rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle">Lihat</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between text-body-sm text-text-secondary" aria-label="Halaman">
            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} penghuni)</span>
            <div class="flex gap-space-xs">
                <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
            </div>
        </nav>

        <!-- Tambah penghuni -->
        <Modal :show="createOpen" title="Tambah penghuni" @close="createOpen = false">
            <ResidentForm id="resident-create-form" :form="createForm" @submit="save" />
            <template #footer>
                <Button variant="ghost" @click="createOpen = false">Batal</Button>
                <Button type="submit" form="resident-create-form" :disabled="createForm.processing">Simpan</Button>
            </template>
        </Modal>

        <!-- Impor CSV -->
        <Modal :show="importOpen" title="Impor penghuni dari CSV" @close="importOpen = false">
            <form id="resident-import-form" class="flex flex-col gap-space-md" @submit.prevent="saveImport">
                <p class="text-body-md text-text-secondary">
                    Gunakan <a href="/app/penghuni/impor/templat" class="text-primary-container underline">templat CSV</a>. Dari Excel, pilih Simpan sebagai CSV.
                    Kolom wajib: nama dan whatsapp (maksimal 500 baris). Nomor WhatsApp yang sudah ada tetap diimpor dan diberi tanda "Nomor ganda".
                </p>
                <div class="flex flex-col gap-space-xs">
                    <label for="resident-import-file" class="text-label-md text-text-primary">Berkas CSV</label>
                    <input id="resident-import-file" type="file" accept=".csv,text/csv,text/plain" required class="text-body-md" @change="importForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                    <p v-if="importForm.errors.file" class="text-body-sm text-error">{{ importForm.errors.file }}</p>
                </div>
            </form>

            <div v-if="importResult" class="mt-space-md rounded-md border border-border-subtle p-space-md" role="status">
                <p class="text-title-md text-text-primary">{{ importResult.created }} penghuni masuk, {{ importResult.failures.length }} baris gagal.</p>
                <template v-if="importResult.duplicates.length">
                    <p class="mt-space-sm text-label-md text-status-menunggu-text">Nomor ganda (tetap diimpor):</p>
                    <ul class="mt-space-xs flex max-h-32 flex-col gap-space-xs overflow-y-auto text-body-sm text-status-menunggu-text">
                        <li v-for="duplicate in importResult.duplicates" :key="duplicate.row">Baris {{ duplicate.row }} ({{ duplicate.name }})</li>
                    </ul>
                </template>
                <template v-if="importResult.failures.length">
                    <p class="mt-space-sm text-label-md text-status-jatuh-tempo-text">Baris gagal:</p>
                    <ul class="mt-space-xs flex max-h-48 flex-col gap-space-xs overflow-y-auto text-body-sm text-status-jatuh-tempo-text">
                        <li v-for="failure in importResult.failures" :key="failure.row">Baris {{ failure.row }}<span v-if="failure.name"> ({{ failure.name }})</span>: {{ failure.reason }}</li>
                    </ul>
                </template>
            </div>
            <template #footer>
                <Button variant="ghost" @click="importOpen = false">Tutup</Button>
                <Button type="submit" form="resident-import-form" :disabled="importForm.processing" icon="upload">Impor</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
