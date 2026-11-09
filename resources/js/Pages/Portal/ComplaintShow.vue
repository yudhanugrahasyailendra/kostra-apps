<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { formatDate } from '@/lib/formatDate';
import { portalBase } from '@/lib/portal';
import type { PortalComplaintData, PortalShell } from '@/types';

const props = defineProps<{ shell: PortalShell; complaint: PortalComplaintData }>();

const base = portalBase();
const form = useForm({ body: '' });

function submit() {
    form.post(`${base}/komplain/${props.complaint.id}/komentar`, { preserveScroll: true, onSuccess: () => form.reset() });
}

function time(value: string): string {
    return new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head :title="complaint.title" />
    <PortalLayout :shell="shell" active="komplain">
        <Link :href="`${base}/komplain`" class="mb-space-md inline-flex min-h-11 items-center gap-space-xs text-title-md text-primary-container">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span>
            Semua laporan
        </Link>

        <section class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
            <div class="flex items-start justify-between gap-space-sm">
                <h2 class="text-title-lg text-text-primary">{{ complaint.title }}</h2>
                <StatusBadge :tone="complaint.status_tone" :label="complaint.status_label" />
            </div>
            <p class="text-body-sm text-text-secondary">{{ complaint.category_label }} · {{ formatDate(complaint.created_at) }}</p>
            <p class="mt-space-sm whitespace-pre-line text-body-md text-text-primary">{{ complaint.description }}</p>
        </section>

        <section class="mt-space-md rounded-lg border border-border-subtle bg-bg-surface p-space-md" aria-label="Percakapan">
            <h2 class="text-title-lg text-text-primary">Percakapan</h2>
            <p v-if="!complaint.comments?.length" class="mt-space-sm text-body-md text-text-secondary">Belum ada pesan.</p>
            <ol v-else class="mt-space-sm grid gap-space-sm">
                <li v-for="comment in complaint.comments" :key="comment.id" class="rounded-md p-space-sm" :class="comment.from_resident ? 'border border-border-subtle' : 'bg-bg-subtle'">
                    <p class="text-label-md text-text-primary">{{ comment.author_name }} <span class="text-text-secondary">· {{ time(comment.created_at) }}</span></p>
                    <p class="mt-space-xs whitespace-pre-line text-body-md text-text-primary">{{ comment.body }}</p>
                </li>
            </ol>

            <form v-if="!complaint.is_closed && !shell.read_only" class="mt-space-md grid gap-space-sm" @submit.prevent="submit">
                <label for="portal-comment" class="text-label-md text-text-primary">Tulis pesan</label>
                <textarea id="portal-comment" v-model="form.body" rows="3" class="rounded-md border border-border-strong bg-bg-surface px-3 py-2 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15" />
                <p v-if="form.errors.body" class="text-body-sm text-error">{{ form.errors.body }}</p>
                <Button type="submit" icon="send" :disabled="form.processing">Kirim pesan</Button>
            </form>
            <p v-else-if="complaint.is_closed" class="mt-space-md text-body-sm text-text-secondary">Tiket ini sudah ditutup.</p>
        </section>
    </PortalLayout>
</template>
