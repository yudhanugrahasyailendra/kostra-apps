<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import SelectField from '@/Components/SelectField.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { ExpenseData, PaginationData } from '@/types';

const props = defineProps<{
    expenses: ExpenseData[];
    pagination: PaginationData;
    filters: { period: string; property_id: number | null; category_id: number | null };
    total: number;
    categories: { id: number; name: string }[];
    properties: { id: number; name: string }[];
    today: string;
    can: { create: boolean };
}>();

const period = ref(props.filters.period);
const propertyId = ref<number | ''>(props.filters.property_id ?? '');
const categoryId = ref<number | ''>(props.filters.category_id ?? '');

function go() {
    const query = new URLSearchParams({ period: period.value });
    if (propertyId.value !== '') query.set('property_id', String(propertyId.value));
    if (categoryId.value !== '') query.set('category_id', String(categoryId.value));
    router.get(`/app/pengeluaran?${query.toString()}`, {}, { preserveState: true, preserveScroll: true });
}

// Formulir tambah dan ubah memakai satu modal. Ubah dikirim lewat POST ke /pengeluaran/{id} (multipart).
const editing = ref<ExpenseData | null>(null);
const formOpen = ref(false);
const form = useForm({
    expense_category_id: '' as number | '',
    property_id: '' as number | '',
    amount: '' as number | '',
    spent_on: props.today,
    description: '',
    vendor_name: '',
    receipt: null as File | null,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.spent_on = props.today;
    formOpen.value = true;
}

function openEdit(expense: ExpenseData) {
    editing.value = expense;
    form.clearErrors();
    form.expense_category_id = expense.expense_category_id;
    form.property_id = expense.property_id ?? '';
    form.amount = expense.amount;
    form.spent_on = expense.spent_on;
    form.description = expense.description;
    form.vendor_name = expense.vendor_name ?? '';
    form.receipt = null;
    formOpen.value = true;
}

function submit() {
    const url = editing.value ? `/app/pengeluaran/${editing.value.id}` : '/app/pengeluaran';
    form.post(url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
        },
    });
}

function onFile(event: Event) {
    form.receipt = (event.target as HTMLInputElement).files?.[0] ?? null;
}

const deleteTarget = ref<ExpenseData | null>(null);
function confirmDelete() {
    if (!deleteTarget.value) return;
    router.delete(`/app/pengeluaran/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}

const periodLabel = computed(() => {
    const [year, month] = props.filters.period.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});
</script>

<template>
    <Head title="Pengeluaran" />
    <OwnerLayout title="Pengeluaran" active="pengeluaran">
        <div class="mb-space-lg flex flex-wrap items-start justify-between gap-space-md">
            <p class="max-w-2xl text-body-md text-text-secondary">
                Catat biaya operasional agar saldo bersih tiap bulan terlihat di laporan. Pengeluaran tanpa properti dihitung sebagai biaya umum semua properti.
            </p>
            <div class="flex flex-wrap gap-space-sm">
                <Link href="/app/laporan?type=rekap" class="inline-flex min-h-11 items-center gap-space-xs rounded-md border border-border-strong bg-bg-surface px-space-md py-2.5 text-title-md text-text-primary hover:bg-bg-subtle lg:min-h-10">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">query_stats</span>
                    Rekap bulanan
                </Link>
                <Button v-if="can.create" icon="add" @click="openCreate">Catat pengeluaran</Button>
            </div>
        </div>

        <section class="mb-space-md grid gap-space-md sm:grid-cols-2" aria-label="Ringkasan pengeluaran">
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <p class="text-label-md uppercase text-text-secondary">Total pengeluaran ({{ periodLabel }})</p>
                <p class="mt-space-xs text-headline-sm text-text-primary">{{ formatRupiah(total) }}</p>
                <p class="text-body-sm text-text-secondary">{{ pagination.total }} catatan pada filter ini</p>
            </div>
        </section>

        <form class="mb-space-md grid gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-sm md:grid-cols-3" role="search" @submit.prevent="go">
            <div class="flex flex-col gap-space-xs">
                <label for="expense-period" class="text-label-md text-text-primary">Bulan</label>
                <input id="expense-period" v-model="period" type="month" class="h-[42px] rounded-md border border-border-strong bg-bg-surface px-3 text-body-md text-text-primary" @change="go" />
            </div>
            <SelectField v-model="propertyId" label="Properti" placeholder="Semua properti" :options="properties.map((p) => ({ value: p.id, label: p.name }))" @change="go" />
            <SelectField v-model="categoryId" label="Kategori" placeholder="Semua kategori" :options="categories.map((c) => ({ value: c.id, label: c.name }))" @change="go" />
        </form>

        <p v-if="expenses.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            Belum ada pengeluaran untuk filter ini.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border border-border-subtle bg-bg-surface">
            <table class="w-full min-w-[52rem] text-left text-body-md">
                <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                    <tr>
                        <th scope="col" class="px-space-md py-space-sm">Tanggal</th>
                        <th scope="col" class="px-space-md py-space-sm">Kategori &amp; keterangan</th>
                        <th scope="col" class="px-space-md py-space-sm">Properti</th>
                        <th scope="col" class="px-space-md py-space-sm">Nota</th>
                        <th scope="col" class="px-space-md py-space-sm text-right">Jumlah</th>
                        <th scope="col" class="px-space-md py-space-sm"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle text-text-primary">
                    <tr v-for="expense in expenses" :key="expense.id">
                        <td class="whitespace-nowrap px-space-md py-space-sm">{{ formatDate(expense.spent_on, 'short') }}</td>
                        <td class="px-space-md py-space-sm">
                            <span class="text-title-md">{{ expense.category_name }}</span>
                            <span class="block text-body-sm text-text-secondary">{{ expense.description }}<template v-if="expense.vendor_name"> · {{ expense.vendor_name }}</template></span>
                            <span v-if="expense.created_by_name" class="block text-body-sm text-text-muted">Dicatat {{ expense.created_by_name }}</span>
                        </td>
                        <td class="px-space-md py-space-sm text-body-sm">{{ expense.property_name ?? 'Umum (semua properti)' }}</td>
                        <td class="px-space-md py-space-sm text-body-sm">
                            <a v-for="receipt in expense.receipts" :key="receipt.id" :href="receipt.url" target="_blank" rel="noopener" class="block text-primary-container hover:underline">Lihat nota</a>
                            <span v-if="expense.receipts.length === 0" class="text-text-muted">Tanpa nota</span>
                        </td>
                        <td class="px-space-md py-space-sm text-right text-title-md">{{ formatRupiah(expense.amount) }}</td>
                        <td class="whitespace-nowrap px-space-md py-space-sm text-right">
                            <template v-if="can.create">
                                <button type="button" class="rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle" @click="openEdit(expense)">Ubah</button>
                                <button type="button" class="rounded-md px-space-sm py-space-xs text-title-md text-status-jatuh-tempo-text hover:bg-bg-subtle" @click="deleteTarget = expense">Hapus</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between text-body-sm text-text-secondary" aria-label="Halaman">
            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} catatan)</span>
            <div class="flex gap-space-xs">
                <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
            </div>
        </nav>

        <Modal :show="formOpen" :title="editing ? 'Ubah pengeluaran' : 'Catat pengeluaran'" @close="formOpen = false">
            <form class="grid gap-space-md" @submit.prevent="submit">
                <SelectField v-model="form.expense_category_id" label="Kategori" placeholder="Pilih kategori" :options="categories.map((c) => ({ value: c.id, label: c.name }))" :error="form.errors.expense_category_id" required />
                <SelectField v-model="form.property_id" label="Properti" placeholder="Umum (semua properti)" :options="properties.map((p) => ({ value: p.id, label: p.name }))" :error="form.errors.property_id" />
                <div class="grid gap-space-md sm:grid-cols-2">
                    <TextField v-model="form.amount" label="Jumlah (Rp)" type="number" :error="form.errors.amount" hint="Rupiah, tanpa titik atau koma." required />
                    <TextField v-model="form.spent_on" label="Tanggal" type="date" :error="form.errors.spent_on" required />
                </div>
                <TextField v-model="form.description" label="Keterangan" :error="form.errors.description" required />
                <TextField v-model="form.vendor_name" label="Penyedia (opsional)" :error="form.errors.vendor_name" />
                <div class="flex flex-col gap-space-xs">
                    <label for="expense-receipt" class="text-label-md text-text-primary">Nota (opsional)</label>
                    <input id="expense-receipt" type="file" accept="image/jpeg,image/png,application/pdf" class="text-body-md text-text-primary" @change="onFile" />
                    <p v-if="form.errors.receipt" class="text-body-sm text-error">{{ form.errors.receipt }}</p>
                    <p v-else class="text-body-sm text-text-secondary">JPG, PNG, atau PDF, maksimal 5 MB.</p>
                </div>
            </form>
            <template #footer>
                <Button variant="ghost" @click="formOpen = false">Batal</Button>
                <Button icon="save" :disabled="form.processing" @click="submit">Simpan</Button>
            </template>
        </Modal>

        <Modal :show="deleteTarget !== null" title="Hapus pengeluaran" @close="deleteTarget = null">
            <p class="text-body-md text-text-secondary">
                Pengeluaran <strong class="text-text-primary">{{ deleteTarget?.description }}</strong> sebesar
                <strong class="text-text-primary">{{ deleteTarget ? formatRupiah(deleteTarget.amount) : '' }}</strong> tidak lagi dihitung di laporan.
            </p>
            <template #footer>
                <Button variant="ghost" @click="deleteTarget = null">Kembali</Button>
                <Button icon="delete" @click="confirmDelete">Hapus</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
