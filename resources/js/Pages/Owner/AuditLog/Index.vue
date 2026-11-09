<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

interface AuditLogData {
    id: number;
    event: string;
    subject: string | null;
    user_name: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    created_at: string | null;
}

const props = defineProps<{
    logs: AuditLogData[];
    pagination: { current_page: number; last_page: number; total: number; prev_url: string | null; next_url: string | null };
    stats: { total: number; changes: number };
    filters: { user_id: number | null; event: string | null; month: string | null };
    users: { id: number; name: string }[];
}>();

// Hanya log daftar aktivitas (FND-06). Verifikasi hash, sesi login aktif, dan klaim keamanan
// ditunda sampai benar-benar diimplementasikan.
const events: Record<string, string> = {
    created: 'Dibuat',
    updated: 'Diubah',
    deleted: 'Dihapus',
    invited_staff: 'Staf diundang',
    exported_data: 'Data diekspor',
};

const filter = reactive({
    user_id: props.filters.user_id ?? '',
    event: props.filters.event ?? '',
    month: props.filters.month ?? '',
});

function apply() {
    const query = Object.fromEntries(Object.entries(filter).filter(([, value]) => value !== ''));
    router.get('/app/log-audit', query, { preserveState: true, preserveScroll: true });
}

function reset() {
    filter.user_id = '';
    filter.event = '';
    filter.month = '';
    apply();
}

function formatTime(iso: string | null) {
    return iso ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

function changes(values: Record<string, unknown> | null) {
    return values ? Object.entries(values).map(([key, value]) => ({ key, value: formatValue(value) })) : [];
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '-';
    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}
</script>

<template>
    <Head title="Log Audit" />
    <OwnerLayout title="Log audit aktivitas" active="audit">
        <p class="mb-space-lg max-w-2xl text-body-md text-text-secondary">
            Catatan perubahan data penting di kos Anda: siapa melakukan apa, kapan, beserta nilai sebelum dan sesudahnya.
        </p>

        <div class="mb-space-lg grid gap-space-md sm:grid-cols-2">
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <p class="text-label-sm uppercase text-text-secondary">Aktivitas tercatat</p>
                <p class="mt-space-xs text-display-lg-mobile text-text-primary">{{ stats.total }} <span class="text-body-md font-normal text-text-secondary">kejadian</span></p>
            </div>
            <div class="rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <p class="text-label-sm uppercase text-text-secondary">Perubahan data</p>
                <p class="mt-space-xs text-display-lg-mobile text-text-primary">{{ stats.changes }} <span class="text-body-md font-normal text-text-secondary">perubahan</span></p>
            </div>
        </div>

        <form class="mb-space-md flex flex-wrap items-end gap-space-sm rounded-lg border border-border-subtle bg-bg-surface p-space-md" @submit.prevent="apply">
            <label class="flex flex-col gap-space-xs text-label-md text-text-primary">
                Pengguna
                <select v-model="filter.user_id" class="h-[42px] rounded-md border border-border-strong bg-bg-surface px-3 text-body-md">
                    <option value="">Semua pengguna</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
            </label>
            <label class="flex flex-col gap-space-xs text-label-md text-text-primary">
                Aksi
                <select v-model="filter.event" class="h-[42px] rounded-md border border-border-strong bg-bg-surface px-3 text-body-md">
                    <option value="">Semua aksi</option>
                    <option v-for="(label, key) in events" :key="key" :value="key">{{ label }}</option>
                </select>
            </label>
            <label class="flex flex-col gap-space-xs text-label-md text-text-primary">
                Bulan
                <input v-model="filter.month" type="month" class="h-[42px] rounded-md border border-border-strong bg-bg-surface px-3 text-body-md" />
            </label>
            <button type="submit" class="h-[42px] rounded-md bg-primary-container px-space-md text-title-md text-on-primary hover:bg-primary">Terapkan</button>
            <button type="button" class="h-[42px] rounded-md px-space-md text-title-md text-text-secondary hover:bg-bg-subtle" @click="reset">Atur ulang</button>
        </form>

        <section class="rounded-lg border border-border-subtle bg-bg-surface shadow-low" aria-label="Daftar aktivitas">
            <p v-if="logs.length === 0" class="p-space-lg text-body-md text-text-secondary">Tidak ada catatan yang cocok.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-body-md">
                    <thead class="bg-bg-subtle text-label-md uppercase text-text-secondary">
                        <tr>
                            <th scope="col" class="px-space-md py-space-sm">Waktu</th>
                            <th scope="col" class="px-space-md py-space-sm">Pelaksana</th>
                            <th scope="col" class="px-space-md py-space-sm">Aksi</th>
                            <th scope="col" class="px-space-md py-space-sm">Sasaran</th>
                            <th scope="col" class="px-space-md py-space-sm">Perubahan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-text-primary">
                        <tr v-for="log in logs" :key="log.id" class="align-top">
                            <td class="whitespace-nowrap px-space-md py-space-sm">
                                <span class="block text-label-md text-text-secondary">#LOG-{{ log.id }}</span>
                                {{ formatTime(log.created_at) }}
                            </td>
                            <td class="px-space-md py-space-sm">{{ log.user_name ?? 'Sistem' }}</td>
                            <td class="px-space-md py-space-sm">{{ events[log.event] ?? log.event }}</td>
                            <td class="px-space-md py-space-sm">{{ log.subject ?? '-' }}</td>
                            <td class="px-space-md py-space-sm">
                                <ul class="flex flex-col gap-space-xs">
                                    <li v-for="change in changes(log.new_values)" :key="change.key" class="flex flex-wrap items-center gap-space-xs text-body-sm">
                                        <span class="text-text-secondary">{{ change.key }}:</span>
                                        <template v-if="log.old_values && change.key in log.old_values">
                                            <span class="rounded-sm bg-status-menunggu-bg px-space-xs text-status-menunggu-text line-through">{{ formatValue(log.old_values[change.key]) }}</span>
                                            <span class="material-symbols-outlined text-[16px] text-text-muted" aria-label="menjadi">arrow_forward</span>
                                        </template>
                                        <span class="rounded-sm bg-status-lunas-bg px-space-xs text-status-lunas-text">{{ change.value }}</span>
                                    </li>
                                    <li v-if="!log.new_values" class="text-body-sm text-text-secondary">
                                        <template v-if="log.old_values">Data dihapus</template>
                                        <template v-else>-</template>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav class="flex items-center justify-between border-t border-border-subtle px-space-md py-space-sm text-body-sm text-text-secondary" aria-label="Halaman">
                <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} catatan)</span>
                <div class="flex gap-space-xs">
                    <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Sebelumnya</Link>
                    <Link v-if="pagination.next_url" :href="pagination.next_url" class="rounded-md px-space-sm py-space-xs hover:bg-bg-subtle">Selanjutnya</Link>
                </div>
            </nav>
        </section>
    </OwnerLayout>
</template>
