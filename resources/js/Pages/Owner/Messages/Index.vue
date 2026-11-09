<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import type { MessageLogData, MessageTemplateData, PaginationData } from '@/types';

const props = defineProps<{
    quota: { period: string; limit: number; used: number; remaining: number };
    provider: string;
    templates: MessageTemplateData[];
    variables: string[];
    logs: MessageLogData[];
    pagination: PaginationData;
    filters: { status: string };
    statuses: { value: string; label: string }[];
}>();

const percentUsed = computed(() => (props.quota.limit > 0 ? Math.min(100, Math.round((props.quota.used / props.quota.limit) * 100)) : 100));
const periodLabel = computed(() => {
    const [year, month] = props.quota.period.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

const status = ref(props.filters.status);
function filter() {
    router.get(`/app/pesan?status=${status.value}`, {}, { preserveState: true, preserveScroll: true });
}

function formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-';
}

// Penyuntingan template: satu modal untuk semua key.
const editing = ref<MessageTemplateData | null>(null);
const form = useForm({ body: '' });

function openEdit(template: MessageTemplateData) {
    editing.value = template;
    form.body = template.body;
    form.clearErrors();
}

function save() {
    if (!editing.value) return;
    form.patch(`/app/pesan/templat/${editing.value.key}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });
}

function reset(template: MessageTemplateData) {
    router.post(`/app/pesan/templat/${template.key}/reset`, {}, { preserveScroll: true });
}

/** Penulisan variabel di isi pesan, mis. {{nama}}. */
function token(variable: string): string {
    return '{' + '{' + variable + '}' + '}';
}

function resend(log: MessageLogData) {
    router.post(`/app/pesan/${log.id}/kirim-ulang`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pesan WhatsApp" />
    <OwnerLayout title="Pesan WhatsApp" active="pesan">
        <p class="mb-space-lg max-w-2xl text-body-md text-text-secondary">
            Pengingat tagihan dan kuitansi dikirim otomatis sesuai jadwal penagihan dan jam kirim. Penghuni yang berhenti menerima pesan tidak dikirimi.
        </p>

        <section class="mb-space-lg grid gap-space-md sm:grid-cols-2" aria-label="Kuota pesan">
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <p class="text-label-md uppercase text-text-secondary">Sisa kuota ({{ periodLabel }})</p>
                <p class="mt-space-xs text-headline-sm text-text-primary">{{ quota.remaining }} <span class="text-body-md text-text-secondary">dari {{ quota.limit }} pesan</span></p>
                <div class="mt-space-sm h-2 overflow-hidden rounded-full bg-bg-subtle" role="progressbar" :aria-valuenow="percentUsed" aria-valuemin="0" aria-valuemax="100" :aria-label="`Kuota terpakai ${percentUsed} persen`">
                    <div class="h-full bg-primary-container" :style="{ width: `${percentUsed}%` }" />
                </div>
                <p class="mt-space-xs text-body-sm text-text-secondary">{{ quota.used }} terpakai. Kuota kembali penuh tiap tanggal 1.</p>
                <p v-if="quota.remaining === 0" class="mt-space-sm text-body-sm text-status-menunggu-text">Kuota habis: pesan baru dicatat sebagai dilewati sampai bulan berikutnya.</p>
            </div>
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <p class="text-label-md uppercase text-text-secondary">Penyedia pengiriman</p>
                <p class="mt-space-xs text-title-md text-text-primary">{{ provider === 'fake' ? 'Penyedia uji (tidak mengirim pesan sungguhan)' : provider }}</p>
            </div>
        </section>

        <section class="mb-space-lg" aria-label="Templat pesan">
            <h2 class="mb-space-sm text-headline-sm text-text-primary">Templat pesan</h2>
            <ul class="grid gap-space-sm">
                <li v-for="template in templates" :key="template.key" class="rounded-lg border border-border-subtle bg-bg-surface p-space-md">
                    <div class="flex flex-wrap items-start justify-between gap-space-sm">
                        <div>
                            <p class="text-title-md text-text-primary">{{ template.label }}</p>
                            <p class="text-body-sm text-text-secondary">{{ template.is_custom ? 'Disesuaikan' : 'Bawaan sistem' }} · kategori utilitas</p>
                        </div>
                        <div v-if="template.available" class="flex gap-space-xs">
                            <Button variant="secondary" icon="edit" @click="openEdit(template)">Ubah</Button>
                            <Button v-if="template.is_custom" variant="ghost" icon="restart_alt" @click="reset(template)">Kembalikan</Button>
                        </div>
                    </div>
                    <p v-if="template.available" class="mt-space-sm whitespace-pre-line rounded-md bg-bg-subtle p-space-sm text-body-md text-text-primary">{{ template.body }}</p>
                    <p v-else class="mt-space-sm text-body-sm text-text-secondary">Templat bawaan belum dipasang. Jalankan seeder templat.</p>
                </li>
            </ul>
        </section>

        <section aria-label="Log pengiriman">
            <div class="mb-space-sm flex flex-wrap items-end justify-between gap-space-sm">
                <h2 class="text-headline-sm text-text-primary">Log pengiriman</h2>
                <div class="w-48"><SelectField v-model="status" label="Status" :options="[{ value: 'all', label: 'Semua status' }, ...statuses]" @change="filter" /></div>
            </div>

            <p v-if="logs.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">Belum ada pesan.</p>

            <div v-else class="overflow-x-auto rounded-lg border border-border-subtle bg-bg-surface">
                <table class="w-full min-w-[48rem] text-left text-body-md">
                    <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                        <tr>
                            <th scope="col" class="px-space-md py-space-sm">Penerima</th>
                            <th scope="col" class="px-space-md py-space-sm">Templat</th>
                            <th scope="col" class="px-space-md py-space-sm">Waktu</th>
                            <th scope="col" class="px-space-md py-space-sm">Status</th>
                            <th scope="col" class="px-space-md py-space-sm"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-text-primary">
                        <tr v-for="log in logs" :key="log.id">
                            <td class="px-space-md py-space-sm">
                                <span class="text-title-md">{{ log.resident_name ?? '-' }}</span>
                                <span class="block text-body-sm text-text-secondary">{{ log.recipient }}</span>
                            </td>
                            <td class="px-space-md py-space-sm text-body-sm">{{ log.template_name ?? '-' }}</td>
                            <td class="px-space-md py-space-sm text-body-sm">
                                {{ formatDateTime(log.sent_at ?? log.scheduled_for ?? log.created_at) }}
                                <span v-if="log.status === 'queued' && log.scheduled_for" class="block text-text-secondary">dijadwalkan</span>
                            </td>
                            <td class="px-space-md py-space-sm">
                                <StatusBadge :tone="log.status_tone" :label="log.status_label" />
                                <span v-if="log.skip_reason_label" class="block text-body-sm text-text-secondary">{{ log.skip_reason_label }}</span>
                                <span v-if="log.error_code" class="block text-body-sm text-status-jatuh-tempo-text">Galat {{ log.error_code }} ({{ log.attempts }} percobaan)</span>
                            </td>
                            <td class="whitespace-nowrap px-space-md py-space-sm text-right">
                                <Button v-if="log.can_resend" variant="secondary" icon="refresh" @click="resend(log)">Kirim ulang</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between text-body-sm text-text-secondary" aria-label="Halaman">
                <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} pesan)</span>
                <div class="flex gap-space-xs">
                    <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                    <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
                </div>
            </nav>
        </section>

        <Modal :show="editing !== null" :title="`Ubah templat: ${editing?.label ?? ''}`" @close="editing = null">
            <form class="grid gap-space-sm" @submit.prevent="save">
                <label for="template-body" class="text-label-md text-text-primary">Isi pesan</label>
                <textarea id="template-body" v-model="form.body" rows="6" class="rounded-md border bg-bg-surface px-3 py-2 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15" :class="form.errors.body ? 'border-error' : 'border-border-strong'" />
                <p v-if="form.errors.body" class="text-body-sm text-error">{{ form.errors.body }}</p>
                <p class="text-body-sm text-text-secondary">
                    Variabel yang diizinkan:
                    <code v-for="variable in variables" :key="variable" class="mr-space-xs rounded bg-bg-subtle px-1">{{ token(variable) }}</code>
                    Pesan harus bersifat pemberitahuan (utilitas), bukan promosi.
                </p>
            </form>
            <template #footer>
                <Button variant="ghost" @click="editing = null">Batal</Button>
                <Button icon="save" :disabled="form.processing" @click="save">Simpan</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
