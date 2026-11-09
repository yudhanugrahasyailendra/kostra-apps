<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Panel from '@/Components/Panel.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { PaymentAccountData, PaymentMethodOption, StatusTone } from '@/types';

interface OpenInvoice {
    id: number;
    number: string;
    period: string | null;
    room_number: string | null;
    due_date: string;
    status_label: string;
    status_tone: StatusTone;
    total: number;
    paid_total: number;
    outstanding: number;
}

const props = defineProps<{
    residents: { id: number; name: string }[];
    resident_id: number | null;
    invoices: OpenInvoice[];
    accounts: PaymentAccountData[];
    methods: PaymentMethodOption[];
    defaults: { invoice_id: number | null; paid_on: string; payment_account_id: number | null };
}>();

// Jumlah yang dialokasikan per tagihan (rupiah, bulat). Tagihan yang dicentang terisi sisa tagihannya.
const selected = ref<Record<number, boolean>>({});
const amounts = ref<Record<number, number | string>>({});

function resetSelection() {
    selected.value = {};
    amounts.value = {};
    for (const invoice of props.invoices) {
        const preselect = props.defaults.invoice_id === invoice.id;
        selected.value[invoice.id] = preselect;
        amounts.value[invoice.id] = invoice.outstanding;
    }
}
resetSelection();
watch(() => props.invoices, resetSelection);

const form = useForm({
    resident_id: props.resident_id as number | null,
    paid_on: props.defaults.paid_on,
    method: 'transfer',
    payment_account_id: (props.defaults.payment_account_id ?? '') as number | string,
    reference: '',
    notes: '',
});

const allocations = computed(() =>
    props.invoices
        .filter((invoice) => selected.value[invoice.id])
        .map((invoice) => ({ invoice_id: invoice.id, amount: Math.trunc(Number(amounts.value[invoice.id]) || 0) })),
);
const total = computed(() => allocations.value.reduce((sum, row) => sum + row.amount, 0));

// Galat alokasi muncul sebagai `allocations`, `allocations.0.amount`, dan seterusnya.
const allocationError = computed(() => {
    const errors = form.errors as Record<string, string>;
    const key = Object.keys(errors).find((k) => k === 'allocations' || k.startsWith('allocations.'));
    return key ? errors[key] : undefined;
});
const generalError = computed(() => (form.errors as Record<string, string>).amount);

const residentOptions = computed(() => props.residents.map((r) => ({ value: r.id, label: r.name })));
const accountOptions = computed(() => [{ value: '', label: 'Tanpa rekening (tunai)' }, ...props.accounts.map((a) => ({ value: a.id, label: a.label }))]);

function pickResident(value: string | number) {
    router.get('/app/pembayaran/baru', value === '' ? {} : { resident_id: value }, { preserveState: false });
}

function submit() {
    form
        .transform((data) => ({
            ...data,
            payment_account_id: data.payment_account_id === '' ? null : data.payment_account_id,
            amount: total.value,
            allocations: allocations.value,
        }))
        .post('/app/pembayaran', { preserveScroll: true });
}
</script>

<template>
    <Head title="Catat pembayaran" />
    <OwnerLayout title="Catat pembayaran" active="pembayaran">
        <Link href="/app/pembayaran" class="mb-space-md inline-flex items-center gap-space-xs text-title-md text-primary-container hover:underline">&larr; Daftar pembayaran</Link>

        <p class="mb-space-lg max-w-2xl text-body-md text-text-secondary">
            Catat uang yang sudah Anda terima, tunai atau transfer ke rekening Anda. Satu pembayaran dapat melunasi beberapa tagihan penghuni yang sama; kelebihan bayar tidak diterima.
        </p>

        <form class="grid gap-space-lg lg:grid-cols-[2fr_1fr]" @submit.prevent="submit">
            <div class="flex flex-col gap-space-lg">
                <Panel title="Tagihan yang dibayar">
                    <div class="mb-space-md max-w-md">
                        <SelectField
                            :model-value="resident_id ?? ''"
                            label="Penghuni"
                            placeholder="Pilih penghuni yang punya tagihan terbuka"
                            :options="residentOptions"
                            :error="form.errors.resident_id"
                            required
                            @update:model-value="pickResident"
                        />
                    </div>

                    <p v-if="residents.length === 0" class="text-body-md text-text-secondary">Tidak ada tagihan terbuka saat ini.</p>
                    <p v-else-if="resident_id === null" class="text-body-md text-text-secondary">Pilih penghuni untuk melihat tagihan terbukanya.</p>

                    <p v-if="allocationError" class="mb-space-sm rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">{{ allocationError }}</p>

                    <ul v-if="invoices.length > 0" class="divide-y divide-border-subtle rounded-md border border-border-subtle">
                        <li v-for="invoice in invoices" :key="invoice.id" class="flex flex-wrap items-center gap-space-md p-space-sm">
                            <input :id="`invoice-${invoice.id}`" v-model="selected[invoice.id]" type="checkbox" class="size-5 accent-primary-container" />
                            <label :for="`invoice-${invoice.id}`" class="min-w-0 flex-1">
                                <span class="block text-title-md text-text-primary">{{ invoice.number }}</span>
                                <span class="block text-body-sm text-text-secondary">
                                    Kamar {{ invoice.room_number }} &middot; jatuh tempo {{ formatDate(invoice.due_date, 'short') }} &middot; total {{ formatRupiah(invoice.total) }}
                                </span>
                                <span class="block text-body-sm text-text-secondary">Sisa tagihan {{ formatRupiah(invoice.outstanding) }}</span>
                            </label>
                            <StatusBadge :tone="invoice.status_tone" :label="invoice.status_label" />
                            <div class="w-40">
                                <label :for="`amount-${invoice.id}`" class="sr-only">Jumlah dibayar untuk {{ invoice.number }}</label>
                                <input
                                    :id="`amount-${invoice.id}`"
                                    v-model="amounts[invoice.id]"
                                    type="number"
                                    min="1"
                                    :max="invoice.outstanding"
                                    step="1"
                                    :disabled="!selected[invoice.id]"
                                    class="h-[42px] w-full rounded-md border border-border-strong bg-bg-surface px-3 text-right text-body-md text-text-primary disabled:bg-bg-subtle disabled:text-text-muted"
                                />
                            </div>
                        </li>
                    </ul>
                </Panel>

                <Panel title="Rincian pembayaran">
                    <div class="grid gap-space-md sm:grid-cols-2">
                        <TextField v-model="form.paid_on" label="Tanggal pembayaran" type="date" :error="form.errors.paid_on" required />
                        <SelectField v-model="form.method" label="Metode" :options="methods" :error="form.errors.method" required />
                        <SelectField v-model="form.payment_account_id" label="Rekening tujuan" :options="accountOptions" :error="form.errors.payment_account_id" />
                        <TextField v-model="form.reference" label="Nomor referensi (opsional)" :error="form.errors.reference" hint="Mis. nomor referensi transfer." />
                    </div>
                    <div class="mt-space-md">
                        <TextField v-model="form.notes" label="Catatan (opsional)" :error="form.errors.notes" />
                    </div>
                </Panel>
            </div>

            <div class="flex flex-col gap-space-lg">
                <Panel title="Ringkasan">
                    <dl class="flex flex-col gap-space-sm text-body-md">
                        <div class="flex justify-between"><dt class="text-text-secondary">Tagihan dipilih</dt><dd>{{ allocations.length }}</dd></div>
                        <div class="flex justify-between border-t border-border-subtle pt-space-sm text-title-lg text-text-primary"><dt>Total diterima</dt><dd>{{ formatRupiah(total) }}</dd></div>
                    </dl>
                    <p v-if="generalError" class="mt-space-sm rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">{{ generalError }}</p>
                    <div class="mt-space-md">
                        <Button type="submit" icon="save" :disabled="form.processing || allocations.length === 0 || total <= 0">Simpan pembayaran</Button>
                    </div>
                    <p class="mt-space-sm text-body-sm text-text-secondary">Kuitansi dengan nomor unik terbit otomatis setelah pembayaran disimpan.</p>
                </Panel>
            </div>
        </form>
    </OwnerLayout>
</template>
