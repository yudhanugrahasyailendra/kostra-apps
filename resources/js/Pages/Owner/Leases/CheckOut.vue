<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Panel from '@/Components/Panel.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { LeaseDetailData } from '@/types';

const props = defineProps<{
    lease: LeaseDetailData;
    outstanding: number;
    reasons: { value: string; label: string }[];
}>();

interface DeductionRow {
    label: string;
    amount: number | string;
}

const form = useForm({
    ended_reason: 'moved_out',
    room_status: 'vacant',
    checkout_notes: '',
    deductions: [] as DeductionRow[],
    deduct_outstanding: props.outstanding > 0,
});

const roomStatusOptions = [
    { value: 'vacant', label: 'Kosong (siap disewakan)' },
    { value: 'maintenance', label: 'Perawatan (dibersihkan atau diperbaiki dulu)' },
];

const depositAmount = computed(() => props.lease.deposit_detail?.amount ?? 0);
const depositPending = computed(() => props.lease.deposit?.status === 'pending');
const manualTotal = computed(() => form.deductions.reduce((sum, row) => sum + (Number(row.amount) || 0), 0));
const outstandingDeducted = computed(() => (form.deduct_outstanding && !depositPending.value ? props.outstanding : 0));
const totalDeduction = computed(() => manualTotal.value + outstandingDeducted.value);
const refund = computed(() => (depositPending.value ? 0 : depositAmount.value - totalDeduction.value));
const overLimit = computed(() => !depositPending.value && totalDeduction.value > depositAmount.value);

// Galat tingkat kontrak (mis. sudah check-out) tidak terikat ke field formulir.
const leaseError = computed(() => (form.errors as Record<string, string>).lease);

function addDeduction() {
    form.deductions.push({ label: '', amount: '' });
}

function submit() {
    // Baris kosong dibuang supaya tidak mengganggu validasi.
    form.transform((data) => ({ ...data, deductions: data.deductions.filter((row) => row.label.trim() !== '' || Number(row.amount) > 0) })).post(`/app/kontrak/${props.lease.id}/check-out`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Check-out Kamar ${lease.room_number}`" />
    <OwnerLayout :title="`Check-out Kamar ${lease.room_number}`" active="kontrak">
        <Link :href="`/app/kontrak/${lease.id}`" class="mb-space-md inline-flex items-center gap-space-xs text-title-md text-primary-container hover:underline">&larr; Detail kontrak</Link>

        <div class="mb-space-lg rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
            <div class="flex flex-wrap items-center justify-between gap-space-sm">
                <div>
                    <p class="text-label-md uppercase text-text-secondary">Penghuni</p>
                    <p class="text-title-lg text-text-primary">{{ lease.primary_resident_name }}</p>
                </div>
                <div>
                    <p class="text-label-md uppercase text-text-secondary">Kamar</p>
                    <p class="text-title-lg text-text-primary">Kamar {{ lease.room_number }} <span class="text-body-sm text-text-secondary">{{ lease.property_name }}</span></p>
                </div>
                <div>
                    <p class="text-label-md uppercase text-text-secondary">Masa sewa</p>
                    <p class="text-body-md text-text-primary">{{ formatDate(lease.start_date, 'short') }} &ndash; {{ lease.end_date ? formatDate(lease.end_date, 'short') : 'tanpa akhir' }}</p>
                </div>
            </div>
        </div>

        <form class="grid gap-space-lg lg:grid-cols-[1fr_22rem]" @submit.prevent="submit">
            <div class="flex flex-col gap-space-lg">
                <Panel title="1. Alasan dan kondisi kamar">
                    <div class="flex flex-col gap-space-md">
                        <SelectField v-model="form.ended_reason" label="Alasan kontrak berakhir" required :options="reasons" :error="form.errors.ended_reason" />
                        <div class="flex flex-col gap-space-xs">
                            <label for="checkout-notes" class="text-label-md text-text-primary">Kondisi kamar saat keluar</label>
                            <textarea
                                id="checkout-notes"
                                v-model="form.checkout_notes"
                                rows="4"
                                placeholder="Catat kunci yang dikembalikan, kondisi perabot, dinding, dan kebersihan."
                                class="rounded-md border border-border-strong bg-bg-surface px-3 py-2 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
                            />
                            <p v-if="form.errors.checkout_notes" class="text-body-sm text-error">{{ form.errors.checkout_notes }}</p>
                        </div>
                        <SelectField v-model="form.room_status" label="Status kamar setelah check-out" required :options="roomStatusOptions" :error="form.errors.room_status" />
                    </div>
                </Panel>

                <Panel title="2. Potongan deposit">
                    <p v-if="depositPending" class="rounded-md border border-status-menunggu-border bg-status-menunggu-bg p-space-sm text-body-sm text-status-menunggu-text" role="note">
                        Deposit kontrak ini belum diterima, jadi tidak ada yang bisa dipotong atau dikembalikan.
                    </p>
                    <template v-else>
                        <p class="mb-space-md text-body-md text-text-secondary">Catat kerusakan atau biaya yang dibebankan ke penghuni. Kosongkan bila tidak ada.</p>

                        <ul class="flex flex-col gap-space-sm">
                            <li v-for="(row, index) in form.deductions" :key="index" class="grid grid-cols-[1fr_9rem_auto] items-start gap-space-sm">
                                <TextField v-model="row.label" label="Keterangan" :error="form.errors[`deductions.${index}.label` as keyof typeof form.errors]" />
                                <TextField v-model="row.amount" label="Jumlah (Rp)" type="number" :error="form.errors[`deductions.${index}.amount` as keyof typeof form.errors]" />
                                <div class="pt-[26px]">
                                    <button type="button" class="flex h-[42px] w-[42px] items-center justify-center rounded-md text-text-secondary hover:bg-bg-subtle" :aria-label="`Hapus potongan ${index + 1}`" @click="form.deductions.splice(index, 1)">
                                        <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                                    </button>
                                </div>
                            </li>
                        </ul>

                        <div class="mt-space-md"><Button variant="secondary" icon="add" @click="addDeduction">Tambah potongan</Button></div>

                        <label v-if="outstanding > 0" class="mt-space-md flex items-start gap-space-sm">
                            <input v-model="form.deduct_outstanding" type="checkbox" class="mt-1 h-4 w-4 rounded border-border-strong text-primary-container" />
                            <span class="text-body-md">Potong sisa tagihan {{ formatRupiah(outstanding) }} dari deposit</span>
                        </label>
                        <p v-if="form.errors.deductions" class="mt-space-sm text-body-sm text-error">{{ form.errors.deductions }}</p>
                    </template>
                </Panel>
            </div>

            <aside>
                <Panel title="3. Rekonsiliasi deposit">
                    <dl class="flex flex-col gap-space-sm text-body-md">
                        <div class="flex justify-between gap-space-sm"><dt>Deposit tercatat</dt><dd>{{ formatRupiah(depositAmount) }}</dd></div>
                        <div class="flex justify-between gap-space-sm"><dt>Potongan kerusakan</dt><dd>-{{ formatRupiah(manualTotal) }}</dd></div>
                        <div v-if="outstandingDeducted > 0" class="flex justify-between gap-space-sm"><dt>Sisa tagihan</dt><dd>-{{ formatRupiah(outstandingDeducted) }}</dd></div>
                        <div class="flex justify-between gap-space-sm border-t border-border-subtle pt-space-sm text-title-md"><dt>Total potongan</dt><dd>-{{ formatRupiah(totalDeduction) }}</dd></div>
                    </dl>
                    <div class="mt-space-md rounded-md bg-bg-subtle p-space-md">
                        <p class="text-label-md uppercase text-text-secondary">Deposit dikembalikan ke penghuni</p>
                        <p class="text-headline-md" :class="overLimit ? 'text-error' : 'text-text-primary'">{{ formatRupiah(Math.max(refund, 0)) }}</p>
                        <p v-if="lease.deposit" class="mt-space-xs"><StatusBadge :tone="lease.deposit.status_tone" :label="`Status sekarang: ${lease.deposit.status_label}`" /></p>
                    </div>
                    <p v-if="overLimit" class="mt-space-sm text-body-sm text-error" role="alert">Total potongan melebihi deposit. Kurangi potongan.</p>
                    <p class="mt-space-md text-body-sm text-text-secondary">
                        Kostra hanya mencatat. Transfer pengembalian dilakukan sendiri di luar sistem; tidak ada uang yang lewat Kostra.
                    </p>
                    <p v-if="leaseError" class="mt-space-sm text-body-sm text-error" role="alert">{{ leaseError }}</p>
                    <div class="mt-space-md">
                        <Button type="submit" icon="assignment_turned_in" :disabled="form.processing || overLimit">Konfirmasi check-out</Button>
                    </div>
                </Panel>
            </aside>
        </form>
    </OwnerLayout>
</template>
