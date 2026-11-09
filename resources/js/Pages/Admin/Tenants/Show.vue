<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import Panel from '@/Components/Panel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { AdminTenantRow, StatusTone, SubscriptionInvoiceData } from '@/types';

const props = defineProps<{ tenant: AdminTenantRow; invoices: SubscriptionInvoiceData[] }>();

const paying = ref<SubscriptionInvoiceData | null>(null);
const form = useForm({ payment_method: 'Transfer bank', reference: '' });

function tone(status: AdminTenantRow['status']): StatusTone {
    if (status === 'suspended' || status === 'closed') return 'jatuh-tempo';
    return status === 'trial' ? 'menunggu' : 'lunas';
}

function invoiceTone(status: SubscriptionInvoiceData['status']): StatusTone {
    return status === 'paid' ? 'lunas' : status === 'pending' ? 'menunggu' : 'kosong';
}

function openPay(invoice: SubscriptionInvoiceData) {
    form.clearErrors();
    form.reset();
    paying.value = invoice;
}

function submitPay() {
    if (!paying.value) return;
    form.post(`/admin/tenant/${props.tenant.id}/tagihan/${paying.value.id}/bayar`, {
        preserveScroll: true,
        onSuccess: () => (paying.value = null),
    });
}

function suspend() {
    if (window.confirm(`Tangguhkan ${props.tenant.name}? Pemilik hanya bisa membaca dan mengekspor data.`)) {
        router.post(`/admin/tenant/${props.tenant.id}/tangguhkan`, {}, { preserveScroll: true });
    }
}

function activate() {
    router.post(`/admin/tenant/${props.tenant.id}/aktifkan`, {}, { preserveScroll: true });
}

function usage(used: number, max: number | null): string {
    return `${used} / ${max ?? 'tanpa batas'}`;
}
</script>

<template>
    <Head :title="tenant.name" />
    <AdminLayout :title="tenant.name">
        <Link href="/admin/tenant" class="mb-space-md inline-flex items-center gap-space-xs text-title-md text-primary-container underline">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
            Semua tenant
        </Link>

        <div class="grid gap-space-md lg:grid-cols-3">
            <Panel title="Ringkasan" class="lg:col-span-1">
                <dl class="flex flex-col gap-space-xs text-body-md">
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Status</dt><dd><StatusBadge :tone="tone(tenant.status)" :label="tenant.status_label" /></dd></div>
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Paket</dt><dd>{{ tenant.plan_name }}</dd></div>
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Langganan</dt><dd>{{ tenant.subscription_status_label ?? '-' }}</dd></div>
                    <div v-if="tenant.period_end" class="flex justify-between gap-space-md"><dt class="text-text-secondary">Periode sampai</dt><dd>{{ formatDate(tenant.period_end) }}</dd></div>
                    <div v-if="tenant.trial_ends_at" class="flex justify-between gap-space-md"><dt class="text-text-secondary">Percobaan sampai</dt><dd>{{ formatDate(tenant.trial_ends_at) }}</dd></div>
                    <div v-if="tenant.grace_ends_at" class="flex justify-between gap-space-md"><dt class="text-text-secondary">Masa tenggang sampai</dt><dd>{{ formatDate(tenant.grace_ends_at) }}</dd></div>
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Terdaftar</dt><dd>{{ formatDate(tenant.created_at) }}</dd></div>
                </dl>
                <div class="mt-space-md">
                    <Button v-if="tenant.status !== 'suspended'" variant="secondary" icon="block" @click="suspend">Tangguhkan</Button>
                    <Button v-else icon="check_circle" @click="activate">Aktifkan kembali</Button>
                </div>
            </Panel>

            <Panel title="Pemakaian" class="lg:col-span-2">
                <dl class="grid gap-space-sm text-body-md sm:grid-cols-2">
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Properti</dt><dd>{{ usage(tenant.usage.properties, tenant.usage.max_properties) }}</dd></div>
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Kamar</dt><dd>{{ usage(tenant.usage.rooms, tenant.usage.max_rooms) }}</dd></div>
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Pengguna</dt><dd>{{ usage(tenant.usage.users, tenant.usage.max_users) }}</dd></div>
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Pesan WhatsApp bulan ini</dt><dd>{{ usage(tenant.usage.messages, tenant.usage.message_quota) }}</dd></div>
                </dl>
            </Panel>
        </div>

        <Panel title="Tagihan langganan" class="mt-space-lg">
            <p v-if="invoices.length === 0" class="text-body-md text-text-secondary">Belum ada tagihan langganan.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-left text-body-md">
                    <thead class="text-label-md uppercase text-text-secondary">
                        <tr><th class="py-space-sm pr-space-md">Nomor</th><th class="pr-space-md">Periode</th><th class="pr-space-md">Jatuh tempo</th><th class="pr-space-md text-right">Jumlah</th><th class="pr-space-md">Status</th><th class="pr-space-md">Pembayaran</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices" :key="invoice.id" class="border-t border-border-subtle">
                            <td class="py-space-sm pr-space-md text-text-primary">{{ invoice.number }}</td>
                            <td class="pr-space-md">{{ formatDate(invoice.period_start, 'short') }} - {{ formatDate(invoice.period_end, 'short') }}</td>
                            <td class="pr-space-md">{{ formatDate(invoice.due_date, 'short') }}</td>
                            <td class="pr-space-md text-right">{{ formatRupiah(invoice.amount) }}</td>
                            <td class="pr-space-md"><StatusBadge :tone="invoiceTone(invoice.status)" :label="invoice.status_label" /></td>
                            <td class="pr-space-md">
                                <template v-if="invoice.status === 'paid'">{{ invoice.payment_method }}<span v-if="invoice.reference" class="block text-body-sm text-text-secondary">{{ invoice.reference }}</span></template>
                                <template v-else>-</template>
                            </td>
                            <td><Button v-if="invoice.status === 'pending'" variant="secondary" icon="payments" @click="openPay(invoice)">Catat bayar</Button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Panel>

        <Modal :show="paying !== null" title="Catat pembayaran langganan" @close="paying = null">
            <form class="flex flex-col gap-space-md" @submit.prevent="submitPay">
                <p v-if="paying" class="text-body-md text-text-secondary">
                    Tagihan {{ paying.number }} sebesar {{ formatRupiah(paying.amount) }}. Catat hanya setelah transfer terlihat di rekening Kostra.
                </p>
                <TextField v-model="form.payment_method" label="Metode pembayaran" :error="form.errors.payment_method" required />
                <TextField v-model="form.reference" label="Referensi (opsional)" :error="form.errors.reference" hint="Nomor transaksi atau nama pengirim." />
            </form>
            <template #footer>
                <Button variant="ghost" @click="paying = null">Batal</Button>
                <Button icon="save" :disabled="form.processing" @click="submitPay">Simpan</Button>
            </template>
        </Modal>
    </AdminLayout>
</template>
