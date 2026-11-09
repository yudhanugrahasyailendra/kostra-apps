<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import type { ComplaintDetailData } from '@/types';

const props = defineProps<{
    complaint: ComplaintDetailData;
    staff: { id: number; name: string }[];
    can: { update: boolean };
}>();

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const assignee = ref<number | ''>(props.complaint.assigned_to ?? '');
const assignError = ref<string | undefined>();

function assign() {
    router.post(
        `/app/komplain/${props.complaint.id}/penanggung-jawab`,
        { assigned_to: assignee.value === '' ? null : assignee.value },
        { preserveScroll: true, onError: (errors) => (assignError.value = errors.assigned_to), onSuccess: () => (assignError.value = undefined) },
    );
}

const statusError = ref<string | undefined>();

function changeStatus(status: string) {
    router.post(
        `/app/komplain/${props.complaint.id}/status`,
        { status },
        { preserveScroll: true, onError: (errors) => (statusError.value = errors.status), onSuccess: () => (statusError.value = undefined) },
    );
}

const commentForm = useForm({ body: '' });

function submitComment() {
    commentForm.post(`/app/komplain/${props.complaint.id}/komentar`, {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
}

const isClosed = props.complaint.allowed_statuses.length === 0;
</script>

<template>
    <Head :title="complaint.title" />
    <OwnerLayout title="Detail komplain" active="komplain">
        <Link href="/app/komplain" class="mb-space-md inline-flex items-center gap-space-xs text-title-md text-primary-container hover:underline">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
            Semua tiket
        </Link>

        <div class="grid gap-space-lg lg:grid-cols-3">
            <div class="grid gap-space-md lg:col-span-2">
                <section class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                    <div class="flex flex-wrap items-start justify-between gap-space-sm">
                        <h2 class="text-headline-sm text-text-primary">{{ complaint.title }}</h2>
                        <StatusBadge :tone="complaint.status_tone" :label="complaint.status_label" />
                    </div>
                    <p class="mt-space-xs text-body-sm text-text-secondary">
                        Kamar {{ complaint.room_number }} · {{ complaint.property_name }} · {{ complaint.resident_name }} · {{ complaint.category_label }}
                    </p>
                    <p class="mt-space-xs text-body-sm text-text-muted">Dibuat {{ formatDateTime(complaint.created_at) }}<template v-if="complaint.closed_at"> · Ditutup {{ formatDateTime(complaint.closed_at) }}</template></p>
                    <p v-if="complaint.is_stale" class="mt-space-sm inline-flex items-center gap-1 rounded-full border border-status-menunggu-border bg-status-menunggu-bg px-2.5 py-1 text-label-sm uppercase text-status-menunggu-text">
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
                        Terbuka lebih dari 3 hari
                    </p>
                    <p class="mt-space-md whitespace-pre-line text-body-md text-text-primary">{{ complaint.description }}</p>

                    <ul v-if="complaint.photos.length" class="mt-space-md flex flex-wrap gap-space-sm">
                        <li v-for="photo in complaint.photos" :key="photo.id">
                            <a :href="photo.url" target="_blank" rel="noopener" class="block overflow-hidden rounded-md border border-border-subtle">
                                <img :src="photo.url" :alt="`Foto komplain: ${photo.original_name}`" class="h-28 w-28 object-cover" loading="lazy" />
                            </a>
                        </li>
                    </ul>
                </section>

                <section class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low" aria-label="Komentar">
                    <h3 class="text-title-md text-text-primary">Komentar</h3>
                    <p v-if="complaint.comments.length === 0" class="mt-space-sm text-body-md text-text-secondary">Belum ada komentar.</p>
                    <ol v-else class="mt-space-sm grid gap-space-sm">
                        <li v-for="comment in complaint.comments" :key="comment.id" class="rounded-md p-space-sm" :class="comment.author_type === 'staff' ? 'bg-bg-subtle' : 'border border-border-subtle'">
                            <p class="text-label-md text-text-primary">
                                {{ comment.author_name ?? 'Pengguna dihapus' }}
                                <span class="text-text-secondary">· {{ comment.author_type === 'staff' ? 'Pengelola' : 'Penghuni' }} · {{ formatDateTime(comment.created_at) }}</span>
                            </p>
                            <p class="mt-space-xs whitespace-pre-line text-body-md text-text-primary">{{ comment.body }}</p>
                        </li>
                    </ol>

                    <form v-if="can.update" class="mt-space-md grid gap-space-sm" @submit.prevent="submitComment">
                        <label for="comment-body" class="text-label-md text-text-primary">Tulis komentar</label>
                        <textarea id="comment-body" v-model="commentForm.body" rows="3" class="rounded-md border border-border-strong bg-bg-surface px-3 py-2 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15" />
                        <p v-if="commentForm.errors.body" class="text-body-sm text-error">{{ commentForm.errors.body }}</p>
                        <div><Button icon="send" :disabled="commentForm.processing">Kirim komentar</Button></div>
                    </form>
                </section>
            </div>

            <aside class="grid content-start gap-space-md">
                <section class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                    <h3 class="text-title-md text-text-primary">Ubah status</h3>
                    <p v-if="isClosed" class="mt-space-sm text-body-md text-text-secondary">Tiket sudah ditutup dan tidak dapat diubah lagi.</p>
                    <div v-else-if="can.update" class="mt-space-sm flex flex-wrap gap-space-sm">
                        <Button
                            v-for="next in complaint.allowed_statuses"
                            :key="next.value"
                            :variant="next.value === 'rejected' ? 'ghost' : 'primary'"
                            @click="changeStatus(next.value)"
                        >
                            {{ next.value === 'in_progress' ? 'Proses' : next.value === 'done' ? 'Tandai selesai' : 'Tolak' }}
                        </Button>
                    </div>
                    <p v-if="statusError" class="mt-space-sm text-body-sm text-error">{{ statusError }}</p>
                </section>

                <section class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                    <h3 class="text-title-md text-text-primary">Penanggung jawab</h3>
                    <p v-if="isClosed || !can.update" class="mt-space-sm text-body-md text-text-secondary">{{ complaint.assignee_name ?? 'Belum ditugaskan' }}</p>
                    <form v-else class="mt-space-sm grid gap-space-sm" @submit.prevent="assign">
                        <SelectField v-model="assignee" label="Ditugaskan ke" placeholder="Belum ditugaskan" :options="staff.map((s) => ({ value: s.id, label: s.name }))" :error="assignError" />
                        <div><Button icon="save">Simpan</Button></div>
                    </form>
                </section>
            </aside>
        </div>
    </OwnerLayout>
</template>
