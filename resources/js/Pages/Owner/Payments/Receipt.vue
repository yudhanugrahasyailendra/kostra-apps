<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';

interface ReceiptData {
    number: string | null;
    issued_at: string | null;
    business_name: string | null;
    property_name: string | null;
    property_address: string | null;
    resident_name: string;
    room_number: string | null;
    amount: number;
    paid_on: string;
    method_label: string;
    reference: string | null;
    account_label: string | null;
    account_name: string | null;
    recorded_by_name: string | null;
    status: 'active' | 'voided';
    status_label: string;
    void_reason: string | null;
    lines: { invoice_number: string | null; period: string | null; amount: number; invoice_total: number | null; invoice_paid: boolean }[];
}

const props = defineProps<{ receipt: ReceiptData; can: { void: boolean }; payment_id: number }>();

const voided = computed(() => props.receipt.status === 'voided');
const voidOpen = ref(false);
const voidForm = useForm({ reason: '' });
const voidError = computed(() => (voidForm.errors as Record<string, string>).payment);

function submitVoid() {
    voidForm.post(`/app/pembayaran/${props.payment_id}/batal`, {
        preserveScroll: true,
        onSuccess: () => {
            voidOpen.value = false;
            voidForm.reset();
        },
    });
}

function downloadPdf() {
    window.location.href = `/app/pembayaran/${props.payment_id}/kuitansi.pdf`;
}

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Kuitansi ${receipt.number}`" />
    <OwnerLayout :title="`Kuitansi ${receipt.number}`" active="pembayaran">
        <div class="mb-space-md flex flex-wrap items-center justify-between gap-space-sm print:hidden">
            <Link href="/app/pembayaran" class="inline-flex items-center gap-space-xs text-title-md text-primary-container hover:underline">&larr; Daftar pembayaran</Link>
            <div class="flex flex-wrap gap-space-sm">
                <Button icon="download" @click="downloadPdf">Unduh PDF</Button>
                <Button variant="secondary" icon="print" @click="print">Cetak</Button>
                <Button v-if="can.void" variant="secondary" icon="block" @click="voidOpen = true">Batalkan pembayaran</Button>
            </div>
        </div>
        <p class="mb-space-md max-w-2xl text-body-sm text-text-secondary print:hidden">
            Unduh kuitansi sebagai PDF atau cetak langsung dari peramban.
        </p>

        <article class="relative mx-auto max-w-3xl rounded-lg border border-border-subtle bg-bg-surface p-space-lg shadow-low print:max-w-none print:rounded-none print:border-0 print:shadow-none" aria-label="Kuitansi pembayaran">
            <p
                v-if="voided"
                class="mb-space-md rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-title-md text-status-jatuh-tempo-text"
                role="status"
            >
                KUITANSI INI DIBATALKAN. {{ receipt.void_reason }}
            </p>

            <header class="flex flex-wrap items-start justify-between gap-space-md border-b border-border-subtle pb-space-md">
                <div>
                    <h2 class="text-headline-md text-primary">{{ receipt.property_name ?? receipt.business_name }}</h2>
                    <p v-if="receipt.property_name && receipt.business_name" class="text-body-sm text-text-secondary">{{ receipt.business_name }}</p>
                    <p v-if="receipt.property_address" class="text-body-sm text-text-secondary">{{ receipt.property_address }}</p>
                </div>
                <div class="text-right">
                    <p class="text-label-md uppercase text-text-secondary">Kuitansi pembayaran</p>
                    <p class="text-title-lg text-text-primary">{{ receipt.number }}</p>
                    <p class="text-body-sm text-text-secondary">Terbit {{ formatDate(receipt.issued_at, 'long') }}</p>
                </div>
            </header>

            <dl class="grid gap-space-md border-b border-border-subtle py-space-md sm:grid-cols-2">
                <div>
                    <dt class="text-label-md uppercase text-text-secondary">Diterima dari</dt>
                    <dd class="text-title-lg text-text-primary">{{ receipt.resident_name }}</dd>
                    <dd v-if="receipt.room_number" class="text-body-sm text-text-secondary">Kamar {{ receipt.room_number }}</dd>
                </div>
                <div>
                    <dt class="text-label-md uppercase text-text-secondary">Tanggal &amp; metode</dt>
                    <dd class="text-body-md text-text-primary">{{ formatDate(receipt.paid_on, 'long') }} &middot; {{ receipt.method_label }}</dd>
                    <dd v-if="receipt.account_label" class="text-body-sm text-text-secondary">
                        Ke {{ receipt.account_label }}<template v-if="receipt.account_name"> a.n. {{ receipt.account_name }}</template>
                    </dd>
                    <dd v-if="receipt.reference" class="text-body-sm text-text-secondary">Ref. {{ receipt.reference }}</dd>
                </div>
            </dl>

            <div class="overflow-x-auto py-space-md">
                <table class="w-full min-w-[22rem] text-left text-body-md">
                    <thead class="text-label-md uppercase text-text-secondary">
                        <tr>
                            <th scope="col" class="py-space-xs pr-space-sm">Untuk pembayaran tagihan</th>
                            <th scope="col" class="px-space-sm py-space-xs">Periode</th>
                            <th scope="col" class="py-space-xs pl-space-sm text-right">Dibayar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        <tr v-for="line in receipt.lines" :key="line.invoice_number ?? ''">
                            <td class="py-space-sm pr-space-sm">
                                {{ line.invoice_number }}
                                <span v-if="line.invoice_paid" class="block text-body-sm text-status-lunas-text">Tagihan lunas</span>
                            </td>
                            <td class="px-space-sm py-space-sm">{{ line.period ?? 'Manual' }}</td>
                            <td class="py-space-sm pl-space-sm text-right">{{ formatRupiah(line.amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-border-strong text-title-lg text-text-primary">
                            <th scope="row" colspan="2" class="py-space-sm text-left">Total diterima</th>
                            <td class="py-space-sm pl-space-sm text-right">{{ formatRupiah(receipt.amount) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <footer class="border-t border-border-subtle pt-space-md text-body-sm text-text-secondary">
                <p>Dicatat oleh {{ receipt.recorded_by_name ?? 'pengelola' }}. Kuitansi ini mencatat pembayaran yang diterima langsung oleh pemilik kos; Kostra tidak menerima atau menahan dana.</p>
            </footer>
        </article>

        <Modal :show="voidOpen" title="Batalkan pembayaran" @close="voidOpen = false">
            <form class="flex flex-col gap-space-md" @submit.prevent="submitVoid">
                <p v-if="voidError" class="rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">{{ voidError }}</p>
                <p class="text-body-md text-text-secondary">Status tagihan terkait dihitung ulang. Kuitansi tetap tersimpan dengan tanda dibatalkan.</p>
                <TextField v-model="voidForm.reason" label="Alasan pembatalan" :error="voidForm.errors.reason" hint="Wajib; tercatat di log audit." required />
            </form>
            <template #footer>
                <Button variant="ghost" @click="voidOpen = false">Kembali</Button>
                <Button icon="block" :disabled="voidForm.processing" @click="submitVoid">Batalkan pembayaran</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
