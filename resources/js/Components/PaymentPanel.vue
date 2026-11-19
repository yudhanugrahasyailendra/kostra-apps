<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AccountSelect from '@/Components/AccountSelect.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatDate } from '@/lib/formatDate';
import { formatRupiah, formatRupiahShort } from '@/lib/formatRupiah';
import type { InvoiceData, InvoicePanelData, PaymentOptionsData, RecordedPaymentData } from '@/types';

// Panel catat pembayaran yang mengambang di kanan (alur mengikuti desain acuan GIF):
// panel tagihan -> kartu rincian (opsional) -> kartu pembayaran per metode -> "Mohon tunggu" -> "Pembayaran berhasil".
// Uang diterima di luar Kostra; panel ini hanya mencatatnya lewat POST /app/pembayaran (RecordPayment).
const props = defineProps<{
    show: boolean;
    /** Baris yang diklik, supaya kepala panel langsung terisi sebelum detail dimuat. */
    invoice: InvoiceData | null;
    panel: InvoicePanelData | null;
    options: PaymentOptionsData | null;
    recorded: RecordedPaymentData | null;
}>();
const emit = defineEmits<{ close: [] }>();

const NOTE_MAX = 2000;

const detail = computed(() => props.panel?.invoice ?? null);
const head = computed(() => detail.value ?? props.invoice);
const loading = computed(() => props.show && (detail.value === null || detail.value.id !== props.invoice?.id) && props.invoice !== null);
const outstanding = computed(() => detail.value?.outstanding ?? props.invoice?.outstanding ?? 0);
const canPay = computed(() => !!detail.value?.can.pay && !!props.options);

type Side = 'details' | 'pay' | null;
const side = ref<Side>(null);
const showAllMethods = ref(false);
const success = ref(false);

const defaultAccount = () => props.options?.accounts.find((account) => account.is_default)?.id ?? '';

const form = useForm({
    payment_account_id: defaultAccount() as number | '',
    method: 'cash',
    paid_on: props.options?.today ?? '',
    amount: 0,
    reference: '',
    notes: '',
});

function reset(): void {
    form.reset();
    form.clearErrors();
    form.payment_account_id = defaultAccount();
    form.paid_on = props.options?.today ?? '';
    form.amount = outstanding.value;
    side.value = null;
    showAllMethods.value = false;
    success.value = false;
}

// Panel dibuka untuk tagihan lain: mulai dari awal.
watch(
    () => [props.show, props.invoice?.id] as const,
    ([show]) => {
        if (show) reset();
    },
);
watch(outstanding, (value) => {
    if (!success.value && side.value !== 'pay') form.amount = value;
});

// Pembayaran tersimpan: server mengarahkan kembali ke daftar dan mengirim `recorded`.
const shownSuccessId = ref<number | null>(null);
watch(
    () => props.recorded,
    (recorded) => {
        if (!recorded || !props.show || recorded.id === shownSuccessId.value) return;
        if (!recorded.lines.some((line) => line.invoice_number === head.value?.number)) return;
        shownSuccessId.value = recorded.id;
        success.value = true;
        side.value = null;
    },
);

// Metode
const methodIcons: Record<string, string> = { cash: 'payments', transfer: 'account_balance', qris: 'qr_code_2', ewallet: 'account_balance_wallet', other: 'more_horiz' };
const methods = computed(() => props.options?.methods ?? []);
const visibleMethods = computed(() => (showAllMethods.value ? methods.value : methods.value.slice(0, 2)));
const methodLabel = computed(() => methods.value.find((m) => m.value === form.method)?.label ?? '');

function pickMethod(value: string): void {
    form.method = value;
    form.amount = outstanding.value;
    form.clearErrors();
    side.value = 'pay';
}

// Jumlah: tampil dengan pemisah ribuan, disimpan sebagai integer rupiah.
const amountText = computed({
    get: () => (form.amount > 0 ? form.amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') : ''),
    set: (value: string) => {
        const digits = value.replace(/\D/g, '').slice(0, 12);
        form.amount = digits === '' ? 0 : Number(digits);
    },
});

const quickAmounts = computed(() => {
    const max = outstanding.value;
    const half = Math.round(max / 2 / 1000) * 1000;
    return [...new Set([100_000, 500_000, 1_000_000, half, max])].filter((value) => value > 0 && value <= max).sort((a, b) => a - b).slice(-4);
});

const amountProblem = computed(() => {
    if (form.amount <= 0) return 'Isi jumlah yang diterima.';
    if (form.amount > outstanding.value) return `Melebihi sisa tagihan ${formatRupiah(outstanding.value)}. Kelebihan bayar tidak dicatat.`;
    return null;
});
const remainingAfter = computed(() => Math.max(0, outstanding.value - form.amount));

// Galat dari server: kunci `allocations.*` atau `amount` ditampilkan di kartu pembayaran.
const serverError = computed(() => {
    const errors = form.errors as Record<string, string>;
    const key = Object.keys(errors).find((k) => k === 'amount' || k === 'allocations' || k.startsWith('allocations.') || k === 'resident_id');
    return key ? errors[key] : null;
});

function submit(): void {
    const invoice = detail.value;
    if (!invoice || amountProblem.value) return;

    form
        .transform((data) => ({
            resident_id: invoice.resident_id,
            amount: data.amount,
            paid_on: data.paid_on,
            method: data.method,
            payment_account_id: data.payment_account_id === '' ? null : data.payment_account_id,
            reference: data.method === 'cash' ? null : data.reference || null,
            notes: data.notes || null,
            allocations: [{ invoice_id: invoice.id, amount: data.amount }],
            stay: true,
        }))
        .post('/app/pembayaran', { preserveScroll: true, preserveState: true });
}

// Tutup: Esc menutup kartu samping lebih dulu, lalu panel. Gulir halaman dikunci selama panel terbuka.
const panelEl = ref<HTMLElement | null>(null);

function close(): void {
    if (form.processing) return;
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || event.defaultPrevented) return;
    if (side.value) {
        side.value = null;
    } else {
        close();
    }
}

watch(
    () => props.show,
    async (show) => {
        document.body.style.overflow = show ? 'hidden' : '';
        if (show) {
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            panelEl.value?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});

const noteCount = computed(() => form.notes.length);
const paymentStatusTone = (status: string) => (status === 'active' ? 'lunas' : 'kosong');
const itemIcon = (typeLabel: string) => (/sewa/i.test(typeLabel) ? 'bed' : /listrik|air|utilitas/i.test(typeLabel) ? 'bolt' : /denda/i.test(typeLabel) ? 'gavel' : 'receipt');

const sideCardBase =
    'fixed z-[72] flex flex-col overflow-hidden bg-bg-surface shadow-high inset-0 sm:inset-auto sm:right-space-md sm:top-space-md sm:bottom-space-md sm:w-[26rem] sm:rounded-xl lg:right-[28rem] lg:w-[22rem]';
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="motion-safe:transition-opacity motion-safe:duration-200"
            enter-from-class="opacity-0"
            leave-active-class="motion-safe:transition-opacity motion-safe:duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[70] bg-inverse-surface/50 backdrop-blur-[2px]" aria-hidden="true" @click="close" />
        </Transition>

        <!-- Panel utama -->
        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-300 motion-safe:ease-out"
            enter-from-class="opacity-0 motion-safe:translate-x-8"
            leave-active-class="motion-safe:transition motion-safe:duration-200 motion-safe:ease-in"
            leave-to-class="opacity-0 motion-safe:translate-x-8"
        >
            <section
                v-if="show && head"
                ref="panelEl"
                role="dialog"
                aria-modal="true"
                :aria-label="success ? 'Pembayaran berhasil' : `Catat pembayaran tagihan ${head.number}`"
                tabindex="-1"
                class="fixed inset-0 z-[71] flex flex-col overflow-hidden bg-bg-surface shadow-high outline-none sm:inset-auto sm:bottom-space-md sm:right-space-md sm:top-space-md sm:w-[26rem] sm:rounded-xl"
            >
                <!-- Mohon tunggu -->
                <div v-if="form.processing" class="flex flex-1 flex-col items-center px-space-lg pt-[18%]" role="status" aria-live="polite">
                    <span class="material-symbols-outlined icon-fill text-[56px] text-primary-container motion-safe:animate-pulse" aria-hidden="true">hourglass_top</span>
                    <p class="mt-space-sm text-title-lg font-medium text-text-primary">Mohon tunggu</p>
                    <p class="text-body-sm text-text-muted">Pembayaran sedang dicatat.</p>
                    <div class="mt-space-xl w-full space-y-space-sm rounded-lg border border-border-subtle p-space-md" aria-hidden="true">
                        <div v-for="n in 6" :key="n" class="flex justify-between gap-space-md">
                            <span class="h-3 rounded-full bg-bg-subtle motion-safe:animate-pulse" :class="n % 2 ? 'w-1/3' : 'w-1/2'" />
                            <span class="h-3 w-1/4 rounded-full bg-bg-subtle motion-safe:animate-pulse" />
                        </div>
                    </div>
                </div>

                <!-- Pembayaran berhasil -->
                <template v-else-if="success && recorded">
                    <div class="flex justify-end px-space-md pt-space-md">
                        <button type="button" class="flex size-9 items-center justify-center rounded-full text-text-secondary hover:bg-bg-subtle" aria-label="Tutup" @click="close">
                            <span class="material-symbols-outlined" aria-hidden="true">close</span>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-space-lg pb-space-lg" role="status" aria-live="polite">
                        <div class="flex flex-col items-center text-center">
                            <span class="relative flex size-20 items-center justify-center rounded-2xl bg-primary-container/10 text-primary-container" aria-hidden="true">
                                <span class="material-symbols-outlined text-[44px]">receipt_long</span>
                                <span class="absolute -left-1 -top-1 flex size-8 items-center justify-center rounded-full bg-status-lunas-text text-white ring-4 ring-bg-surface">
                                    <span class="material-symbols-outlined text-[20px]">check</span>
                                </span>
                            </span>
                            <h2 class="mt-space-md text-title-lg font-semibold text-text-primary">Pembayaran berhasil dicatat</h2>
                            <p class="mt-space-md text-body-sm text-text-muted">Jumlah</p>
                            <p class="text-headline-md font-semibold tabular-nums text-text-primary">{{ formatRupiah(recorded.amount) }}</p>
                            <p class="text-body-sm text-text-muted">{{ formatDate(recorded.paid_on, 'long') }} · Kuitansi {{ recorded.number }}</p>
                        </div>

                        <div class="mt-space-lg rounded-xl border border-border-subtle p-space-md">
                            <h3 class="text-title-md font-semibold text-text-primary">Rincian pembayaran</h3>
                            <ul class="mt-space-sm divide-y divide-border-subtle">
                                <li v-for="line in recorded.lines" :key="line.invoice_number ?? ''" class="flex items-center justify-between gap-space-sm py-space-sm text-body-sm">
                                    <span class="flex min-w-0 items-center gap-space-sm">
                                        <span class="material-symbols-outlined text-[18px] text-primary-container" aria-hidden="true">receipt</span>
                                        <span class="truncate text-text-primary">{{ line.invoice_number }}<template v-if="line.period"> · {{ line.period }}</template></span>
                                    </span>
                                    <span class="shrink-0 font-medium tabular-nums text-text-primary">{{ formatRupiah(line.amount) }}</span>
                                </li>
                            </ul>
                            <p class="mt-space-sm text-label-sm uppercase tracking-wide text-text-muted">Detail transaksi</p>
                            <dl class="mt-space-xs space-y-1 text-body-sm">
                                <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Diterima</dt><dd class="font-medium tabular-nums text-text-primary">{{ formatRupiah(recorded.amount) }}</dd></div>
                                <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Metode</dt><dd class="text-text-primary">{{ recorded.method_label }}</dd></div>
                                <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Rekening</dt><dd class="truncate text-text-primary">{{ recorded.account_label ?? 'Tunai' }}</dd></div>
                                <div v-if="recorded.reference" class="flex justify-between gap-space-md"><dt class="text-text-secondary">Referensi</dt><dd class="truncate text-text-primary">{{ recorded.reference }}</dd></div>
                                <div v-if="detail" class="flex justify-between gap-space-md border-t border-border-subtle pt-1">
                                    <dt class="text-text-secondary">Sisa tagihan</dt>
                                    <dd class="font-medium tabular-nums" :class="detail.outstanding > 0 ? 'text-status-menunggu-text' : 'text-status-lunas-text'">
                                        {{ detail.outstanding > 0 ? formatRupiah(detail.outstanding) : 'Lunas' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-space-sm border-t border-border-subtle p-space-md">
                        <button type="button" class="inline-flex items-center justify-center gap-space-xs rounded-full border border-border-strong px-space-md py-2.5 text-title-md text-text-primary hover:bg-bg-subtle" @click="close">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>Kembali
                        </button>
                        <a
                            :href="`/app/pembayaran/${recorded.id}/kuitansi.pdf`"
                            class="inline-flex items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-md py-2.5 text-title-md text-on-primary hover:bg-primary"
                        >
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">print</span>Cetak kuitansi
                        </a>
                    </div>
                </template>

                <!-- Formulir tagihan -->
                <template v-else>
                    <header class="flex items-center gap-space-sm border-b border-border-subtle px-space-md py-space-sm">
                        <p class="min-w-0 flex-1 truncate text-body-sm text-text-muted">
                            No. tagihan <span class="text-title-md font-semibold text-text-primary">{{ head.number }}</span>
                        </p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border border-border-subtle px-space-sm py-1 text-label-md text-text-secondary hover:bg-bg-subtle"
                            :aria-pressed="side === 'details'"
                            @click="side = side === 'details' ? null : 'details'"
                        >
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">history</span>Riwayat
                        </button>
                        <Link
                            :href="`/app/tagihan/${head.id}`"
                            class="flex size-8 items-center justify-center rounded-full text-text-secondary hover:bg-bg-subtle"
                            aria-label="Buka halaman tagihan"
                            title="Buka halaman tagihan"
                        >
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">open_in_new</span>
                        </Link>
                        <button type="button" class="flex size-8 items-center justify-center rounded-full text-text-secondary hover:bg-bg-subtle" aria-label="Tutup panel" @click="close">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
                        </button>
                    </header>

                    <div v-if="loading" class="flex-1 space-y-space-md p-space-md" aria-busy="true" aria-label="Memuat tagihan">
                        <div v-for="n in 5" :key="n" class="h-10 rounded-lg bg-bg-subtle motion-safe:animate-pulse" />
                    </div>

                    <div v-else-if="detail" class="flex-1 space-y-space-md overflow-y-auto p-space-md">
                        <div v-if="options">
                            <label :for="`account-${detail.id}`" class="mb-1 block text-label-md text-text-secondary">Rekening tujuan</label>
                            <AccountSelect :id="`account-${detail.id}`" v-model="form.payment_account_id" :accounts="options.accounts" :error="form.errors.payment_account_id" />
                        </div>

                        <div class="flex items-start justify-between gap-space-md">
                            <div class="min-w-0">
                                <p class="text-label-sm uppercase tracking-wide text-text-muted">Ditagihkan ke</p>
                                <p class="truncate text-title-md font-semibold text-text-primary">{{ detail.resident_name }}</p>
                                <p class="text-body-sm text-text-secondary">Kamar {{ detail.room_number }} · {{ detail.property_name }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-label-sm uppercase tracking-wide text-text-muted">Jatuh tempo</p>
                                <p class="text-body-sm text-text-primary">{{ formatDate(detail.due_date, 'short') }}</p>
                                <StatusBadge class="mt-1" :tone="detail.status_tone" :label="detail.status_label" />
                            </div>
                        </div>

                        <div class="rounded-lg border border-border-subtle">
                            <div class="flex justify-between bg-bg-subtle px-space-sm py-1.5 text-label-sm uppercase tracking-wide text-text-muted">
                                <span>Item tagihan</span><span>Jumlah</span>
                            </div>
                            <ul class="divide-y divide-border-subtle">
                                <li v-for="item in detail.items" :key="item.id" class="flex items-center gap-space-sm px-space-sm py-2 text-body-sm">
                                    <span class="material-symbols-outlined text-[18px] text-primary-container" aria-hidden="true">{{ itemIcon(item.type_label) }}</span>
                                    <span class="min-w-0 flex-1 truncate text-text-primary">{{ item.description }}</span>
                                    <span class="shrink-0 tabular-nums text-text-secondary">{{ formatRupiah(item.amount) }}</span>
                                </li>
                            </ul>
                            <button
                                type="button"
                                class="w-full border-t border-border-subtle px-space-sm py-1.5 text-center text-label-md text-primary-container hover:bg-bg-subtle"
                                @click="side = 'details'"
                            >
                                Lihat rincian
                            </button>
                        </div>

                        <div v-if="canPay">
                            <div class="mb-1 flex items-baseline justify-between">
                                <label :for="`note-${detail.id}`" class="text-label-md text-text-secondary">Catatan <span class="text-text-muted">(opsional)</span></label>
                                <span class="text-label-sm tabular-nums text-text-muted">{{ noteCount }}/{{ NOTE_MAX }}</span>
                            </div>
                            <textarea
                                :id="`note-${detail.id}`"
                                v-model="form.notes"
                                rows="2"
                                :maxlength="NOTE_MAX"
                                placeholder="Tulis catatan…"
                                class="w-full resize-y rounded-lg border border-border-strong bg-bg-surface px-space-sm py-2 text-body-md text-text-primary placeholder:text-text-muted focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
                            />
                            <p v-if="form.errors.notes" class="mt-1 text-body-sm text-status-jatuh-tempo-text">{{ form.errors.notes }}</p>
                        </div>

                        <dl class="space-y-1 text-body-sm">
                            <div class="flex justify-between"><dt class="text-text-secondary">Subtotal</dt><dd class="tabular-nums text-text-primary">{{ formatRupiah(detail.subtotal) }}</dd></div>
                            <div v-if="detail.adjustment_total !== 0" class="flex justify-between"><dt class="text-text-secondary">Penyesuaian</dt><dd class="tabular-nums text-text-primary">{{ formatRupiah(detail.adjustment_total) }}</dd></div>
                            <div v-if="detail.paid_total > 0" class="flex justify-between"><dt class="text-text-secondary">Sudah dibayar</dt><dd class="tabular-nums text-status-lunas-text">-{{ formatRupiah(detail.paid_total) }}</dd></div>
                            <div class="flex justify-between border-t border-border-subtle pt-1 text-title-md font-semibold text-text-primary">
                                <dt>Sisa tagihan</dt><dd class="tabular-nums">{{ formatRupiah(detail.outstanding) }}</dd>
                            </div>
                        </dl>

                        <p class="flex items-start gap-space-xs text-body-sm text-text-muted">
                            <span class="material-symbols-outlined text-[16px] text-status-lunas-text" aria-hidden="true">verified_user</span>
                            Kostra hanya mencatat uang yang sudah Anda terima; tidak ada dana yang ditahan.
                        </p>

                        <div v-if="canPay" class="overflow-hidden rounded-xl border border-secondary-container/60 bg-secondary-container/[0.06]">
                            <p class="px-space-md pb-1 pt-space-sm text-label-sm uppercase tracking-wide text-text-secondary">Pilih metode pembayaran</p>
                            <ul class="px-space-sm pb-space-xs">
                                <li v-for="method in visibleMethods" :key="method.value">
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-space-sm rounded-lg px-space-sm py-2 text-left text-body-md transition-colors"
                                        :class="side === 'pay' && form.method === method.value ? 'bg-bg-surface font-semibold text-text-primary shadow-low' : 'text-text-primary hover:bg-bg-surface'"
                                        :aria-pressed="side === 'pay' && form.method === method.value"
                                        @click="pickMethod(method.value)"
                                    >
                                        <span class="material-symbols-outlined text-[20px] text-primary-container" aria-hidden="true">{{ methodIcons[method.value] ?? 'payments' }}</span>
                                        <span class="flex-1">{{ method.label }}</span>
                                        <span class="material-symbols-outlined text-[18px] text-text-muted" aria-hidden="true">chevron_right</span>
                                    </button>
                                </li>
                            </ul>
                            <button
                                v-if="methods.length > 2"
                                type="button"
                                class="flex w-full items-center gap-1 px-space-md pb-space-sm text-label-md text-text-secondary hover:text-text-primary"
                                :aria-expanded="showAllMethods"
                                @click="showAllMethods = !showAllMethods"
                            >
                                {{ showAllMethods ? 'Tampilkan lebih sedikit' : 'Tampilkan lainnya' }}
                                <span class="material-symbols-outlined text-[16px] motion-safe:transition-transform" :class="{ 'rotate-180': showAllMethods }" aria-hidden="true">expand_less</span>
                            </button>
                        </div>
                        <p v-else class="rounded-lg bg-bg-subtle p-space-sm text-body-sm text-text-secondary">
                            {{ detail.outstanding === 0 ? 'Tagihan ini sudah lunas.' : 'Tagihan ini tidak dapat dibayar saat ini.' }}
                        </p>
                    </div>

                    <p v-else class="flex-1 p-space-md text-body-md text-text-secondary">Tagihan tidak ditemukan.</p>
                </template>
            </section>
        </Transition>

        <!-- Kartu samping: rincian tagihan dan riwayat pembayaran -->
        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-300 motion-safe:ease-out"
            enter-from-class="opacity-0 motion-safe:translate-x-6"
            leave-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-in"
            leave-to-class="opacity-0"
        >
            <aside v-if="show && side === 'details' && detail && !success && !form.processing" :class="sideCardBase" aria-label="Rincian tagihan">
                <header class="flex items-center justify-between border-b border-border-subtle px-space-md py-space-sm">
                    <h2 class="text-title-lg font-semibold text-text-primary">Rincian tagihan</h2>
                    <button type="button" class="flex size-8 items-center justify-center rounded-full border border-border-subtle text-text-secondary hover:bg-bg-subtle" aria-label="Tutup rincian" @click="side = null">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
                    </button>
                </header>
                <div class="flex-1 space-y-space-lg overflow-y-auto p-space-md text-body-sm">
                    <section>
                        <h3 class="mb-space-xs flex items-center justify-between text-title-md font-semibold text-text-primary">
                            <span class="flex items-center gap-space-xs"><span class="material-symbols-outlined text-[18px] text-primary-container" aria-hidden="true">receipt_long</span>Item ({{ detail.items.length }})</span>
                            <span class="text-body-sm font-normal text-text-muted">Total {{ formatRupiah(detail.subtotal) }}</span>
                        </h3>
                        <ul class="space-y-space-sm">
                            <li v-for="item in detail.items" :key="item.id" class="flex justify-between gap-space-md">
                                <span class="min-w-0">
                                    <span class="block text-text-primary">{{ item.description }}</span>
                                    <span class="block text-text-muted">{{ item.type_label }} · {{ item.quantity.toLocaleString('id-ID') }} × {{ formatRupiah(item.unit_price) }}</span>
                                </span>
                                <span class="shrink-0 font-medium tabular-nums text-text-primary">{{ formatRupiah(item.amount) }}</span>
                            </li>
                        </ul>
                    </section>
                    <section v-if="detail.adjustments.length">
                        <h3 class="mb-space-xs flex items-center gap-space-xs text-title-md font-semibold text-text-primary">
                            <span class="material-symbols-outlined text-[18px] text-primary-container" aria-hidden="true">edit_note</span>Penyesuaian ({{ detail.adjustments.length }})
                        </h3>
                        <ul class="space-y-space-sm">
                            <li v-for="adjustment in detail.adjustments" :key="adjustment.id" class="flex justify-between gap-space-md">
                                <span class="min-w-0">
                                    <span class="block text-text-primary">{{ adjustment.reason }}</span>
                                    <span class="block text-text-muted">{{ adjustment.created_by_name ?? '-' }}</span>
                                </span>
                                <span class="shrink-0 font-medium tabular-nums text-text-primary">{{ formatRupiah(adjustment.amount) }}</span>
                            </li>
                        </ul>
                    </section>
                    <section>
                        <h3 class="mb-space-xs flex items-center gap-space-xs text-title-md font-semibold text-text-primary">
                            <span class="material-symbols-outlined text-[18px] text-primary-container" aria-hidden="true">history</span>Riwayat pembayaran
                        </h3>
                        <p v-if="!panel?.payments.length" class="text-text-muted">Belum ada pembayaran untuk tagihan ini.</p>
                        <ul v-else class="space-y-space-sm">
                            <li v-for="payment in panel.payments" :key="payment.id" class="flex items-center justify-between gap-space-md">
                                <span class="min-w-0">
                                    <span class="block text-text-primary">{{ payment.receipt_number }}</span>
                                    <span class="block text-text-muted">{{ formatDate(payment.paid_on, 'short') }} · {{ payment.method_label }}</span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="block font-medium tabular-nums text-text-primary">{{ formatRupiah(payment.amount) }}</span>
                                    <StatusBadge :tone="paymentStatusTone(payment.status)" :label="payment.status_label" />
                                </span>
                            </li>
                        </ul>
                    </section>
                </div>
            </aside>
        </Transition>

        <!-- Kartu pembayaran per metode -->
        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-300 motion-safe:ease-out"
            enter-from-class="opacity-0 motion-safe:translate-y-4"
            leave-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-in"
            leave-to-class="opacity-0"
        >
            <aside
                v-if="show && side === 'pay' && detail && canPay && !success && !form.processing"
                :class="[sideCardBase, 'lg:top-auto']"
                :aria-label="`Pembayaran ${methodLabel}`"
            >
                <form class="flex flex-1 flex-col overflow-y-auto" novalidate @submit.prevent="submit">
                    <header class="flex items-center justify-between px-space-md pt-space-md">
                        <h2 class="text-title-lg font-semibold text-text-primary">{{ methodLabel }}</h2>
                        <button type="button" class="flex size-8 items-center justify-center rounded-full border border-border-subtle text-text-secondary hover:bg-bg-subtle" aria-label="Kembali ke tagihan" @click="side = null">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
                        </button>
                    </header>
                    <div class="flex-1 space-y-space-md p-space-md">
                        <div class="flex items-baseline justify-between gap-space-md">
                            <p class="text-title-lg text-text-primary">Sisa tagihan</p>
                            <p class="text-headline-sm font-semibold tabular-nums text-text-primary">{{ formatRupiah(outstanding) }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-md border border-border-subtle bg-bg-subtle px-space-sm py-0.5 text-label-md text-text-secondary">
                            <span class="material-symbols-outlined text-[16px] text-primary-container" aria-hidden="true">{{ methodIcons[form.method] ?? 'payments' }}</span>{{ methodLabel }}
                        </span>

                        <div>
                            <label :for="`amount-${detail.id}`" class="mb-1 block text-label-md text-text-secondary">Jumlah diterima</label>
                            <div
                                class="flex items-center rounded-lg border bg-bg-surface px-space-sm focus-within:ring-[3px] focus-within:ring-primary-container/15"
                                :class="amountProblem && form.amount > 0 ? 'border-status-jatuh-tempo-border' : 'border-primary-container'"
                            >
                                <span class="text-body-md text-text-muted">Rp</span>
                                <input
                                    :id="`amount-${detail.id}`"
                                    v-model="amountText"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    class="min-w-0 flex-1 bg-transparent px-space-xs py-2.5 text-title-md tabular-nums text-text-primary outline-none"
                                    :aria-invalid="!!amountProblem"
                                    :aria-describedby="`amount-help-${detail.id}`"
                                />
                            </div>
                            <p :id="`amount-help-${detail.id}`" class="mt-1 text-body-sm" :class="amountProblem && form.amount > 0 ? 'text-status-jatuh-tempo-text' : 'text-text-muted'">
                                {{ amountProblem && form.amount > 0 ? amountProblem : remainingAfter > 0 ? `Sisa setelah ini ${formatRupiah(remainingAfter)}` : 'Tagihan akan lunas.' }}
                            </p>
                            <div class="mt-space-sm flex flex-wrap gap-space-xs" role="group" aria-label="Jumlah cepat">
                                <button
                                    v-for="value in quickAmounts"
                                    :key="value"
                                    type="button"
                                    class="rounded-full border px-space-sm py-1 text-label-md tabular-nums transition-colors"
                                    :class="form.amount === value ? 'border-primary-container bg-primary-container text-on-primary' : 'border-border-strong text-text-secondary hover:border-primary-container hover:text-primary-container'"
                                    :aria-pressed="form.amount === value"
                                    @click="form.amount = value"
                                >
                                    {{ value === outstanding ? 'Lunas' : formatRupiahShort(value) }}
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-space-sm" :class="form.method === 'cash' ? '' : 'sm:grid-cols-2'">
                            <div>
                                <label :for="`paid-on-${detail.id}`" class="mb-1 block text-label-md text-text-secondary">Tanggal diterima</label>
                                <input
                                    :id="`paid-on-${detail.id}`"
                                    v-model="form.paid_on"
                                    type="date"
                                    :max="options?.today"
                                    class="h-[42px] w-full rounded-lg border border-border-strong bg-bg-surface px-space-sm text-body-md text-text-primary"
                                />
                                <p v-if="form.errors.paid_on" class="mt-1 text-body-sm text-status-jatuh-tempo-text">{{ form.errors.paid_on }}</p>
                            </div>
                            <div v-if="form.method !== 'cash'">
                                <label :for="`reference-${detail.id}`" class="mb-1 block text-label-md text-text-secondary">No. referensi <span class="text-text-muted">(opsional)</span></label>
                                <input
                                    :id="`reference-${detail.id}`"
                                    v-model="form.reference"
                                    type="text"
                                    maxlength="100"
                                    class="h-[42px] w-full rounded-lg border border-border-strong bg-bg-surface px-space-sm text-body-md text-text-primary"
                                />
                                <p v-if="form.errors.reference" class="mt-1 text-body-sm text-status-jatuh-tempo-text">{{ form.errors.reference }}</p>
                            </div>
                        </div>

                        <p v-if="serverError || form.errors.method" class="rounded-md border border-status-jatuh-tempo-border bg-status-jatuh-tempo-bg p-space-sm text-body-sm text-status-jatuh-tempo-text" role="alert">
                            {{ serverError ?? form.errors.method }}
                        </p>
                    </div>
                    <div class="p-space-md pt-0">
                        <button
                            type="submit"
                            class="flex w-full items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-md py-3 text-title-md font-medium text-on-primary shadow-low transition-colors hover:bg-primary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!!amountProblem || form.processing"
                        >
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">task_alt</span>
                            Catat {{ form.amount > 0 ? formatRupiah(form.amount) : 'pembayaran' }}
                        </button>
                    </div>
                </form>
            </aside>
        </Transition>
    </Teleport>
</template>
