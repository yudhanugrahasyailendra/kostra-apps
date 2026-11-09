<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import Panel from '@/Components/Panel.vue';
import ResidentForm, { type ResidentFormData } from '@/Components/ResidentForm.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatPhone, whatsappLink } from '@/lib/formatPhone';
import type { AttachmentData, ResidentDetailData } from '@/types';

const props = defineProps<{
    resident: ResidentDetailData;
    attachments: AttachmentData[];
    categories: { value: string; label: string }[];
    can: { update: boolean; delete: boolean; upload_sensitive: boolean };
}>();

// Foto KTP hanya ditawarkan bila pengguna berizin; server tetap memeriksa ulang.
const categoryOptions = computed(() => props.categories.filter((c) => c.value !== 'ktp' || props.can.upload_sensitive));

const hiddenKtp = computed(() => props.resident.has_ktp && !props.attachments.some((a) => a.category === 'ktp'));

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-';
}

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

// Ubah data
const editOpen = ref(false);
const editForm = useForm<ResidentFormData>({
    name: props.resident.name,
    phone: props.resident.phone,
    email: props.resident.email ?? '',
    occupation: props.resident.occupation ?? '',
    emergency_name: props.resident.emergency_name ?? '',
    emergency_relation: props.resident.emergency_relation ?? '',
    emergency_phone: props.resident.emergency_phone ?? '',
    notes: props.resident.notes ?? '',
    consented: props.resident.consented_at !== null,
});

function saveEdit() {
    editForm.patch(`/app/penghuni/${props.resident.id}`, { preserveScroll: true, onSuccess: () => (editOpen.value = false) });
}

function removeResident() {
    if (confirm(`Hapus penghuni ${props.resident.name}?`)) {
        router.delete(`/app/penghuni/${props.resident.id}`);
    }
}

// Dokumen
const uploadForm = useForm({ category: '' as string, file: null as File | null });

function upload() {
    uploadForm.post(`/app/penghuni/${props.resident.id}/lampiran`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => uploadForm.reset(),
    });
}

function removeAttachment(attachment: AttachmentData) {
    if (confirm(`Hapus dokumen "${attachment.category_label}"?`)) {
        router.delete(`/app/lampiran/${attachment.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="resident.name" />
    <OwnerLayout :title="resident.name" active="penghuni">
        <Link href="/app/penghuni" class="mb-space-md inline-flex items-center gap-space-xs text-title-md text-primary-container hover:underline">&larr; Semua penghuni</Link>

        <div class="mb-space-lg flex flex-wrap items-center justify-between gap-space-md">
            <div class="flex flex-wrap items-center gap-space-sm">
                <StatusBadge :tone="resident.status_tone" :label="resident.status_label" />
                <StatusBadge v-if="resident.has_ktp" tone="lunas" label="KTP terenkripsi" />
                <span v-if="resident.whatsapp_opted_out" class="rounded-full border border-status-menunggu-border bg-status-menunggu-bg px-space-sm text-label-sm text-status-menunggu-text">Berhenti menerima pesan otomatis</span>
            </div>
            <div class="flex gap-space-sm">
                <Button v-if="can.update" variant="secondary" icon="edit" @click="editOpen = true">Ubah data</Button>
                <Button v-if="can.delete" variant="ghost" icon="delete" @click="removeResident">Hapus</Button>
            </div>
        </div>

        <div class="grid gap-space-lg lg:grid-cols-2">
            <Panel title="Data penghuni">
                <dl class="grid gap-space-md text-body-md sm:grid-cols-2">
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Nomor WhatsApp</dt>
                        <dd><a :href="whatsappLink(resident.phone)" target="_blank" rel="noopener noreferrer" class="text-primary-container hover:underline">{{ formatPhone(resident.phone) }}</a></dd>
                    </div>
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Email</dt>
                        <dd class="break-all">{{ resident.email ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-label-md uppercase text-text-secondary">Pekerjaan atau kampus</dt>
                        <dd>{{ resident.occupation ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-label-md uppercase text-text-secondary">Kontak darurat</dt>
                        <dd v-if="resident.emergency_name || resident.emergency_phone">
                            {{ resident.emergency_name }}<span v-if="resident.emergency_relation"> ({{ resident.emergency_relation }})</span>
                            <span v-if="resident.emergency_phone" class="block text-text-secondary">{{ formatPhone(resident.emergency_phone) }}</span>
                        </dd>
                        <dd v-else>-</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-label-md uppercase text-text-secondary">Catatan internal</dt>
                        <dd class="whitespace-pre-line">{{ resident.notes ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Persetujuan data</dt>
                        <dd>{{ resident.consented_at ? formatDate(resident.consented_at) : 'Belum dicatat' }}</dd>
                    </div>
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Terdaftar</dt>
                        <dd>{{ formatDate(resident.created_at) }}</dd>
                    </div>
                </dl>
            </Panel>

            <Panel title="Dokumen">
                <p v-if="hiddenKtp" class="mb-space-md rounded-md border border-border-subtle bg-bg-subtle p-space-sm text-body-sm text-text-secondary">
                    Foto KTP sudah tersimpan. Hanya pemilik dan staf berizin yang dapat membukanya.
                </p>

                <p v-if="attachments.length === 0 && !hiddenKtp" class="text-body-md text-text-secondary">Belum ada dokumen.</p>

                <ul v-else class="divide-y divide-border-subtle">
                    <li v-for="attachment in attachments" :key="attachment.id" class="flex flex-wrap items-center justify-between gap-space-sm py-space-sm">
                        <div class="min-w-0">
                            <p class="text-title-md text-text-primary">
                                {{ attachment.category_label }}
                                <StatusBadge v-if="attachment.is_sensitive" tone="lunas" label="Terenkripsi" />
                            </p>
                            <p class="truncate text-body-sm text-text-secondary">{{ attachment.original_name }} &middot; {{ formatSize(attachment.size) }} &middot; {{ formatDate(attachment.created_at) }}</p>
                        </div>
                        <div class="flex gap-space-xs">
                            <a :href="attachment.url" target="_blank" rel="noopener noreferrer" class="rounded-md px-space-sm py-space-xs text-title-md text-primary-container hover:bg-bg-subtle">Buka</a>
                            <Button v-if="can.update" variant="ghost" @click="removeAttachment(attachment)">Hapus</Button>
                        </div>
                    </li>
                </ul>

                <form v-if="can.update" class="mt-space-lg flex flex-col gap-space-md border-t border-border-subtle pt-space-md" @submit.prevent="upload">
                    <h3 class="text-title-md text-text-primary">Unggah dokumen</h3>
                    <SelectField v-model="uploadForm.category" label="Jenis dokumen" required placeholder="Pilih jenis" :options="categoryOptions" :error="uploadForm.errors.category" />
                    <div class="flex flex-col gap-space-xs">
                        <label for="attachment-file" class="text-label-md text-text-primary">Berkas (JPG, PNG, atau PDF, maksimal 5 MB)</label>
                        <input id="attachment-file" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required class="text-body-md" @change="uploadForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                        <p v-if="uploadForm.errors.file" class="text-body-sm text-error">{{ uploadForm.errors.file }}</p>
                    </div>
                    <p v-if="uploadForm.category === 'ktp'" class="rounded-md border border-status-menunggu-border bg-status-menunggu-bg p-space-sm text-body-sm text-status-menunggu-text" role="note">
                        Foto KTP adalah data pribadi. Simpan hanya bila perlu dan setelah penghuni setuju. Berkas dienkripsi, dan setiap pembukaan dicatat di log audit. Nomor KTP tidak disimpan.
                    </p>
                    <div><Button type="submit" icon="upload" :disabled="uploadForm.processing">Unggah</Button></div>
                </form>
            </Panel>
        </div>

        <Modal :show="editOpen" title="Ubah data penghuni" @close="editOpen = false">
            <ResidentForm id="resident-edit-form" :form="editForm" :consent-recorded="resident.consented_at !== null" @submit="saveEdit" />
            <template #footer>
                <Button variant="ghost" @click="editOpen = false">Batal</Button>
                <Button type="submit" form="resident-edit-form" :disabled="editForm.processing">Simpan</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
