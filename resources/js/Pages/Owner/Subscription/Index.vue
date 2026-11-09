<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Panel from '@/Components/Panel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah } from '@/lib/formatRupiah';
import type { PlanData, StatusTone, SubscriptionData, SubscriptionInvoiceData, TenantUsageData } from '@/types';

// Mockup: paket_langganan_kostra. Auto-debit, QRIS dinamis, virtual account, Enterprise, add-on kuota,
// dan faktur pajak sengaja tidak ditampilkan (belum diputuskan).
const props = defineProps<{
    plans: PlanData[];
    currentPlanKey: string;
    subscription: SubscriptionData | null;
    usage: TenantUsageData;
    invoices: SubscriptionInvoiceData[];
    trialAvailable: boolean;
    trialDays: number;
    can: { manage: boolean };
}>();

const featureLabels: Record<string, string> = {
    'rooms.manual_billing': 'Data kamar dan tagihan manual',
    'billing.auto': 'Tagihan bulanan otomatis',
    'whatsapp.reminders': 'Pengingat WhatsApp',
    'reports.basic': 'Laporan keuangan dasar',
    'reports.advanced': 'Laporan lanjutan',
    'users.multi': 'Banyak pengguna (staf)',
    'support.priority': 'Dukungan prioritas',
    'export.advanced': 'Ekspor lanjutan',
};

const period = ref<'monthly' | 'yearly'>('monthly');
// Galat dari Action memakai kunci `plan`, yang tidak ada di tipe form.
const planError = computed(() => (form.errors as Record<string, string | undefined>).plan ?? form.errors.plan_key);
const form = useForm({ plan_key: '', billing_period: 'monthly' });
const current = computed(() => props.plans.find((p) => p.key === props.currentPlanKey));
const currentRank = computed(() => props.plans.findIndex((p) => p.key === props.currentPlanKey));

const subscriptionTone = computed<StatusTone>(() => {
    switch (props.subscription?.status) {
        case 'active':
            return 'lunas';
        case 'trialing':
            return 'menunggu';
        case 'past_due':
        case 'expired':
            return 'jatuh-tempo';
        default:
            return 'kosong';
    }
});

function invoiceTone(status: SubscriptionInvoiceData['status']): StatusTone {
    return status === 'paid' ? 'lunas' : status === 'pending' ? 'menunggu' : 'kosong';
}

function priceOf(plan: PlanData): number | null {
    return period.value === 'yearly' ? plan.price_yearly : plan.price_monthly;
}

function limitText(value: number | null, unit: string): string {
    return value === null ? `${unit} tanpa batas` : `${value} ${unit}`;
}

function actionLabel(plan: PlanData, index: number): string {
    if (plan.is_free) return 'Turun ke Gratis';
    if (current.value?.is_free && props.trialAvailable) return `Coba gratis ${props.trialDays} hari`;
    return index > currentRank.value ? `Naik ke ${plan.name}` : `Pindah ke ${plan.name}`;
}

function choose(plan: PlanData) {
    form.plan_key = plan.key;
    form.billing_period = plan.is_free ? 'monthly' : period.value;
    form.post('/app/langganan', { preserveScroll: true });
}

function meter(used: number, max: number | null): number {
    return max === null || max === 0 ? 0 : Math.min(100, Math.round((used / max) * 100));
}
</script>

<template>
    <Head title="Paket langganan" />
    <OwnerLayout title="Paket langganan" active="langganan">
        <p class="mb-space-lg max-w-2xl text-body-md text-text-secondary">
            Kelola paket Kostra, pantau batas pemakaian, dan lihat tagihan langganan. Pembayaran langganan lewat transfer
            dan dikonfirmasi manual oleh tim Kostra.
        </p>

        <p v-if="planError" class="mb-space-md rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-md text-status-jatuh-tempo-text" role="alert">
            {{ planError }}
        </p>

        <div class="grid gap-space-md lg:grid-cols-3">
            <Panel title="Paket Anda" class="lg:col-span-1">
                <div class="flex flex-col gap-space-sm">
                    <div class="flex flex-wrap items-center gap-space-sm">
                        <span class="text-headline-md text-text-primary">{{ current?.name }}</span>
                        <StatusBadge v-if="subscription" :tone="subscriptionTone" :label="subscription.status_label" />
                    </div>
                    <p v-if="subscription?.status === 'trialing'" class="text-body-md text-text-secondary">
                        Masa percobaan sampai {{ formatDate(subscription.trial_ends_at) }}. Bayar tagihan di bawah sebelum tanggal itu agar paket berlanjut; tanpa pembayaran Anda kembali ke Gratis.
                    </p>
                    <p v-else-if="subscription?.status === 'past_due'" class="text-body-md text-status-jatuh-tempo-text">
                        Tagihan langganan lewat jatuh tempo. Lunasi sebelum {{ formatDate(subscription.grace_ends_at) }} agar akun tidak ditangguhkan.
                    </p>
                    <p v-else-if="subscription && !current?.is_free" class="text-body-md text-text-secondary">
                        Periode {{ subscription.billing_period_label.toLowerCase() }} sampai {{ formatDate(subscription.current_period_end) }}.
                    </p>
                    <p v-else class="text-body-md text-text-secondary">Paket Gratis tanpa biaya dan tanpa batas waktu.</p>
                </div>
            </Panel>

            <Panel title="Pemakaian" class="lg:col-span-2">
                <dl class="grid gap-space-md sm:grid-cols-2">
                    <div v-for="row in [
                        { label: 'Properti', used: usage.properties, max: usage.max_properties },
                        { label: 'Kamar', used: usage.rooms, max: usage.max_rooms },
                        { label: 'Pengguna', used: usage.users, max: usage.max_users },
                        { label: 'Pesan WhatsApp bulan ini', used: usage.messages, max: usage.message_quota },
                    ]" :key="row.label">
                        <dt class="flex justify-between text-body-md"><span class="text-text-secondary">{{ row.label }}</span><span class="text-text-primary">{{ row.used }} / {{ row.max ?? 'tanpa batas' }}</span></dt>
                        <dd class="mt-space-xs h-2 rounded-full bg-bg-subtle" role="presentation">
                            <div class="h-2 rounded-full bg-primary-container" :style="{ width: meter(row.used, row.max) + '%' }"></div>
                        </dd>
                    </div>
                </dl>
            </Panel>
        </div>

        <div class="mb-space-md mt-space-xl flex flex-wrap items-center justify-between gap-space-md">
            <h2 class="text-headline-sm text-text-primary">Pilih paket</h2>
            <div class="inline-flex rounded-md bg-bg-subtle p-space-xs" role="group" aria-label="Periode tagihan">
                <button type="button" class="rounded-md px-space-md py-space-xs text-title-md" :class="period === 'monthly' ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary'" :aria-pressed="period === 'monthly'" @click="period = 'monthly'">Bulanan</button>
                <button type="button" class="rounded-md px-space-md py-space-xs text-title-md" :class="period === 'yearly' ? 'bg-bg-surface text-text-primary shadow-low' : 'text-text-secondary'" :aria-pressed="period === 'yearly'" @click="period = 'yearly'">Tahunan</button>
            </div>
        </div>

        <div class="grid gap-space-md sm:grid-cols-2 xl:grid-cols-4">
            <section
                v-for="(plan, index) in plans"
                :key="plan.key"
                class="flex flex-col rounded-lg border bg-bg-surface p-space-md shadow-low"
                :class="plan.key === currentPlanKey ? 'border-primary-container' : 'border-border-subtle'"
            >
                <header class="flex items-center justify-between gap-space-sm">
                    <h3 class="text-title-lg text-text-primary">{{ plan.name }}</h3>
                    <StatusBadge v-if="plan.key === currentPlanKey" tone="lunas" label="Paket Anda" />
                </header>
                <p class="mt-space-sm text-currency-display text-text-primary">
                    <template v-if="plan.is_free">Rp 0</template>
                    <template v-else-if="priceOf(plan) !== null">{{ formatRupiah(priceOf(plan) as number) }}</template>
                    <template v-else>-</template>
                    <span class="text-body-md text-text-secondary"> / {{ period === 'yearly' && !plan.is_free ? 'tahun' : 'bulan' }}</span>
                </p>
                <ul class="mt-space-md flex flex-1 flex-col gap-space-xs text-body-md text-text-primary">
                    <li>{{ limitText(plan.max_properties, 'properti') }}</li>
                    <li>{{ limitText(plan.max_rooms, 'kamar') }}</li>
                    <li>{{ limitText(plan.max_users, 'pengguna') }}</li>
                    <li v-for="feature in plan.features" :key="feature" class="text-text-secondary">{{ featureLabels[feature] ?? feature }}</li>
                </ul>
                <Button
                    v-if="can.manage"
                    class="mt-space-md"
                    :variant="plan.key === currentPlanKey ? 'secondary' : 'primary'"
                    :disabled="form.processing || (plan.key === currentPlanKey && (plan.is_free || subscription?.billing_period === period))"
                    @click="choose(plan)"
                >
                    {{ plan.key === currentPlanKey && !plan.is_free && subscription?.billing_period !== period ? 'Ganti periode' : plan.key === currentPlanKey ? 'Paket saat ini' : actionLabel(plan, index) }}
                </Button>
            </section>
        </div>

        <Panel title="Tagihan langganan" class="mt-space-xl">
            <p v-if="invoices.length === 0" class="text-body-md text-text-secondary">Belum ada tagihan langganan.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-body-md">
                    <thead class="text-label-md uppercase text-text-secondary">
                        <tr><th class="py-space-sm pr-space-md">Nomor</th><th class="pr-space-md">Periode</th><th class="pr-space-md">Jatuh tempo</th><th class="pr-space-md text-right">Jumlah</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices" :key="invoice.id" class="border-t border-border-subtle">
                            <td class="py-space-sm pr-space-md text-text-primary">{{ invoice.number }}</td>
                            <td class="pr-space-md">{{ formatDate(invoice.period_start, 'short') }} - {{ formatDate(invoice.period_end, 'short') }}</td>
                            <td class="pr-space-md">{{ formatDate(invoice.due_date, 'short') }}</td>
                            <td class="pr-space-md text-right">{{ formatRupiah(invoice.amount) }}</td>
                            <td><StatusBadge :tone="invoiceTone(invoice.status)" :label="invoice.status_label" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-space-md text-body-sm text-text-secondary">
                Cara bayar: transfer sesuai jumlah tagihan lalu kirim bukti ke tim Kostra. Status berubah setelah pembayaran dikonfirmasi.
            </p>
        </Panel>
    </OwnerLayout>
</template>
