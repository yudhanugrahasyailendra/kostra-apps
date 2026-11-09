<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import Panel from '@/Components/Panel.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { InvoiceDetailData } from '@/types';

const props = defineProps<{ invoice: InvoiceDetailData }>();

const adjustOpen = ref(false);
const voidOpen = ref(false);

const adjustForm = useForm({ kind: 'reduce', amount: '' as number | string, reason: '' });
const voidForm = useForm({ reason: '' });

const signedAmount = computed(() => {
    const value = Math.abs(Number(adjustForm.amount) || 0);
    return adjustForm.kind === 'reduce' ? -value : value;
});
const newTotal = computed(() => props.invoice.total + signedAmount.value);

// Galat tingkat tagihan (mis. tagihan lunas) tidak terikat ke satu field.
const adjustError = computed(() => (adjustForm.errors as Record<string, string>).invoice);
const voidError = computed(() => (voidForm.errors as Record<string, string>).invoice);

function submitAdjust() {
    adjustForm
        .transform((data) => ({ amount: signedAmount.value, reason: data.reason }))
        .post(`/app/tagihan/${props.invoice.id}/penyesuaian`, {
            preserveScroll: true,
            onSuccess: () => {
                adjustOpen.value = false;
                adjustForm.reset();
            },
        });
}

function submitVoid() {
    voidForm.post(`/app/tagihan/${props.invoice.id}/batal`, {
        preserveScroll: true,
        onSuccess: () => {
            voidOpen.value = false;
            voidForm.reset();
        },
    });
}
</script>

<template>
    <Head :title="`Tagihan ${invoice.number}`" />
    <OwnerLayout :title="`Tagihan ${invoice.number}`" active="tagihan">
        <Link href="/app/tagihan" class="mb-space-md inline-flex items-center gap-space-xs text-title-md text-primary-container hover:underline">&larr; Daftar tagihan</Link>

        <div class="mb-space-lg flex flex-wrap items-start justify-between gap-space-md rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
            <div class="grid gap-space-md sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-label-md uppercase text-text-secondary">Penghuni</p>
                    <Link :href="`/app/penghuni/${invoice.resident_id}`" class="text-title-lg text-primary-container hover:underline">{{ invoice.resident_name }}</Link>
                </div>
                <div>
                    <p class="text-label-md uppercase text-text-secondary">Kamar</p>
                    <Link :href="`/app/kontrak/${invoice.lease_id}`" class="text-title-lg text-primary-container hover:underline">Kamar {{ invoice.room_number }}</Link>
                    <p class="text-body-sm text-text-secondary">{{ invoice.property_name }}</p>
                </div>
                <div>
                    <p class="text-label-md uppercase text-text-secondary">Periode</p>
                    <p class="text-body-md text-text-primary">{{ invoice.period ?? 'Tagihan manual' }}</p>
                    <p v-if="invoice.period_start" class="text-body-sm text-text-secondary">{{ formatDate(invoice.period_start, 'short') }} &ndash; {{ formatDate(invoice.period_end, 'short') }}</p>
                </div>
                <div>
                    <p class="text-label-md uppercase text-text-secondary">Terbit / jatuh tempo</p>
                    <p class="text-body-md text-text-primary">{{ formatDate(invoice.issue_date, 'short') }} / {{ formatDate(invoice.due_date, 'short') }}</p>
                </div>
            </div>
            <StatusBadge :tone="invoice.status_tone as never" :label="invoice.status_label" />
        </div>

        <p v-if="invoice.status === 'void'" class="mb-space-md rounded-md border border-status-kosong-border bg-status-kosong-bg p-space-sm text-body-md text-status-kosong-text" role="status">
            Tagihan dibatalkan {{ formatDate(invoice.voided_at, 'short') }}. Alasan: {{ invoice.void_reason }}
        </p>

        <div class="grid gap-space-lg lg:grid-cols-[2fr_1fr]">
            <div class="flex flex-col gap-space-lg">
                <Panel title="Rincian item">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[28rem] text-left text-body-md">
                            <thead class="text-label-md uppercase text-text-secondary">
                                <tr>
                                    <th scope="col" class="py-space-xs pr-space-sm">Keterangan</th>
                                    <th scope="col" class="px-space-sm py-space-xs text-right">Jumlah</th>
                                    <th scope="col" class="px-space-sm py-space-xs text-right">Harga satuan</th>
                                    <th scope="col" class="py-space-xs pl-space-sm text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                <tr v-for="item in invoice.items" :key="item.id">
                                    <td class="py-space-sm pr-space-sm">
                                        {{ item.description }}
                                        <span class="block text-body-sm text-text-secondary">{{ item.type_label }}</span>
                                    </td>
                                    <td class="px-space-sm py-space-sm text-right">{{ item.quantity }}</td>
                                    <td class="px-space-sm py-space-sm text-right">{{ formatRupiah(item.unit_price) }}</td>
                                    <td class="py-space-sm pl-space-sm text-right">{{ formatRupiah(item.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="invoice.notes" class="mt-space-md text-body-sm text-text-secondary">Catatan: {{ invoice.notes }}</p>
                </Panel>

                <Panel title="Penyesuaian">
                    <template #actions>
                        <Button v-if="invoice.can.adjust && invoice.can_adjust_now" variant="secondary" icon="edit_note" @click="adjustOpen = true">Tambah penyesuaian</Button>
                    </template>
                    <p v-if="invoice.adjustments.length === 0" class="text-body-md text-text-secondary">Belum ada penyesuaian. Jumlah tagihan terbit tidak diubah langsung; koreksi dicatat di sini lengkap dengan alasannya.</p>
                    <ul v-else class="divide-y divide-border-subtle">
                        <li v-for="adjustment in invoice.adjustments" :key="adjustment.id" class="flex flex-wrap items-start justify-between gap-space-sm py-space-sm">
                            <div>
                                <p class="text-body-md text-text-primary">{{ adjustment.reason }}</p>
                                <p class="text-body-sm text-text-secondary">{{ adjustment.created_by_name ?? 'Pengguna dihapus' }} &middot; {{ formatDate(adjustment.created_at, 'short') }}</p>
                            </div>
                            <span class="text-title-md" :class="adjustment.amount < 0 ? 'text-status-lunas-text' : 'text-status-jatuh-tempo-text'">
                                {{ adjustment.amount > 0 ? '+' : '' }}{{ formatRupiah(adjustment.amount) }}
                            </span>
                        </li>
                    </ul>
                </Panel>
            </div>

            <div class="flex flex-col gap-space-lg">
                <Panel title="Ringkasan">
                    <dl class="flex flex-col gap-space-sm text-body-md">
                        <div class="flex justify-between"><dt class="text-text-secondary">Subtotal item</dt><dd>{{ formatRupiah(invoice.subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-text-secondary">Penyesuaian</dt><dd>{{ formatRupiah(invoice.adjustment_total) }}</dd></div>
                        <div class="flex justify-between border-t border-border-subtle pt-space-sm text-title-lg text-text-primary"><dt>Total</dt><dd>{{ formatRupiah(invoice.total) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-text-secondary">Sudah dibayar</dt><dd>{{ formatRupiah(invoice.paid_total) }}</dd></div>
                        <div class="flex justify-between text-title-md text-text-primary"><dt>Sisa tagihan</dt><dd>{{ formatRupiah(invoice.outstanding) }}</dd></div>
                    </dl>
                </Panel>

                <Panel v-if="invoice.can.void && invoice.status !== 'void'" title="Pembatalan">
                    <p class="mb-space-sm text-body-sm text-text-secondary">
                        Tagihan yang dibatalkan tetap tersimpan sebagai riwayat. Tagihan lunas atau yang sudah ada pembayarannya tidak dapat dibatalkan; koreksi lewat penyesuaian.
                    </p>
                    <Button variant="secondary" icon="block" :disabled="!invoice.can_void_now" @click="voidOpen = true">Batalkan tagihan</Button>
                </Panel>
            </div>
        </div>

        <Modal :show="adjustOpen" title="Tambah penyesuaian" @close="adjustOpen = false">
            <form id="adjust-form" class="flex flex-col gap-space-md" @submit.prevent="submitAdjust">
                <p v-if="adjustError" class="rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">{{ adjustError }}</p>
                <SelectField v-model="adjustForm.kind" label="Jenis" :options="[{ value: 'reduce', label: 'Kurangi tagihan (diskon, potongan)' }, { value: 'add', label: 'Tambah tagihan (biaya tambahan)' }]" />
                <TextField v-model="adjustForm.amount" label="Jumlah (Rp)" type="number" :error="adjustForm.errors.amount" hint="Angka bulat rupiah, tanpa titik." required />
                <TextField v-model="adjustForm.reason" label="Alasan" :error="adjustForm.errors.reason" hint="Wajib; tercatat di log audit." required />
                <p class="text-body-sm text-text-secondary">Total baru: <strong class="text-text-primary">{{ formatRupiah(newTotal) }}</strong></p>
            </form>
            <template #footer>
                <Button variant="ghost" @click="adjustOpen = false">Batal</Button>
                <Button type="submit" icon="save" :disabled="adjustForm.processing" @click="submitAdjust">Simpan penyesuaian</Button>
            </template>
        </Modal>

        <Modal :show="voidOpen" title="Batalkan tagihan" @close="voidOpen = false">
            <form class="flex flex-col gap-space-md" @submit.prevent="submitVoid">
                <p v-if="voidError" class="rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">{{ voidError }}</p>
                <p class="text-body-md text-text-secondary">Tagihan <strong class="text-text-primary">{{ invoice.number }}</strong> tidak akan ditagihkan lagi dan tidak diterbitkan ulang oleh penjadwal.</p>
                <TextField v-model="voidForm.reason" label="Alasan pembatalan" :error="voidForm.errors.reason" required />
            </form>
            <template #footer>
                <Button variant="ghost" @click="voidOpen = false">Kembali</Button>
                <Button icon="block" :disabled="voidForm.processing" @click="submitVoid">Batalkan tagihan</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
