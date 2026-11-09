<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Panel from '@/Components/Panel.vue';
import SelectField from '@/Components/SelectField.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatRupiah } from '@/lib/formatRupiah';

const props = defineProps<{
    leases: { id: number; label: string }[];
    itemTypes: { value: string; label: string }[];
    defaults: { lease_id: number | null; due_date: string; today: string };
}>();

interface ItemRow {
    type: string;
    description: string;
    quantity: number | string;
    unit_price: number | string;
}

const blankItem = (): ItemRow => ({ type: 'other', description: '', quantity: 1, unit_price: '' });

const form = useForm({
    lease_id: (props.defaults.lease_id ?? '') as number | string,
    due_date: props.defaults.due_date,
    notes: '',
    items: [blankItem()] as ItemRow[],
});

// Pembulatan sama dengan server: kuantitas dua desimal dikali harga, dibulatkan ke rupiah.
function lineAmount(row: ItemRow): number {
    const hundredths = Math.round((Number(row.quantity) || 0) * 100);
    return Math.floor((hundredths * (Number(row.unit_price) || 0) + 50) / 100);
}

const total = computed(() => form.items.reduce((sum, row) => sum + lineAmount(row), 0));
const errors = computed(() => form.errors as Record<string, string>);

function submit() {
    form.post('/app/tagihan', { preserveScroll: true });
}
</script>

<template>
    <Head title="Tagihan Manual" />
    <OwnerLayout title="Tagihan Manual" active="tagihan">
        <Link href="/app/tagihan" class="mb-space-md inline-flex items-center gap-space-xs text-title-md text-primary-container hover:underline">&larr; Daftar tagihan</Link>

        <form class="grid max-w-4xl gap-space-lg" @submit.prevent="submit">
            <Panel title="Penghuni dan jatuh tempo">
                <div class="grid gap-space-md sm:grid-cols-2">
                    <SelectField
                        v-model="form.lease_id"
                        label="Kontrak aktif"
                        placeholder="Pilih kamar dan penghuni"
                        :options="leases.map((lease) => ({ value: lease.id, label: lease.label }))"
                        :error="errors.lease_id"
                        required
                    />
                    <TextField v-model="form.due_date" label="Jatuh tempo" type="date" :error="errors.due_date" required />
                </div>
                <div class="mt-space-md">
                    <TextField v-model="form.notes" label="Catatan (opsional)" :error="errors.notes" />
                </div>
            </Panel>

            <Panel title="Item tagihan">
                <p v-if="errors.items" class="mb-space-sm text-body-sm text-error" role="alert">{{ errors.items }}</p>
                <div class="flex flex-col gap-space-md">
                    <fieldset v-for="(row, index) in form.items" :key="index" class="rounded-md border border-border-subtle p-space-sm">
                        <legend class="px-space-xs text-label-md uppercase text-text-secondary">Item {{ index + 1 }}</legend>
                        <div class="grid gap-space-sm md:grid-cols-[10rem_1fr_6rem_10rem]">
                            <SelectField v-model="row.type" label="Jenis" :options="itemTypes" />
                            <TextField v-model="row.description" label="Keterangan" :error="errors[`items.${index}.description`]" required />
                            <TextField v-model="row.quantity" label="Jumlah" type="number" :error="errors[`items.${index}.quantity`]" required />
                            <TextField v-model="row.unit_price" label="Harga satuan (Rp)" type="number" :error="errors[`items.${index}.unit_price`]" required />
                        </div>
                        <div class="mt-space-xs flex items-center justify-between text-body-sm text-text-secondary">
                            <span>Subtotal {{ formatRupiah(lineAmount(row)) }}</span>
                            <button v-if="form.items.length > 1" type="button" class="rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle" @click="form.items.splice(index, 1)">Hapus item</button>
                        </div>
                    </fieldset>
                </div>
                <div class="mt-space-md flex flex-wrap items-center justify-between gap-space-sm">
                    <Button variant="secondary" icon="add" :disabled="form.items.length >= 20" @click="form.items.push(blankItem())">Tambah item</Button>
                    <p class="text-title-lg text-text-primary">Total {{ formatRupiah(total) }}</p>
                </div>
            </Panel>

            <div class="flex justify-end gap-space-sm">
                <Link href="/app/tagihan" class="inline-flex min-h-11 items-center rounded-md px-space-md text-title-md text-text-secondary hover:bg-bg-subtle lg:min-h-10">Batal</Link>
                <Button type="submit" icon="receipt_long" :disabled="form.processing">Terbitkan tagihan</Button>
            </div>
        </form>
    </OwnerLayout>
</template>
