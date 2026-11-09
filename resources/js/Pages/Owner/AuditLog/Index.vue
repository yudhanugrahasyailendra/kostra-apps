<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Panel from '@/Components/Panel.vue';
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

defineProps<{ logs: AuditLogData[] }>();

const eventLabels: Record<string, string> = {
    created: 'Dibuat',
    updated: 'Diubah',
    deleted: 'Dihapus',
    invited_staff: 'Staf diundang',
    exported_data: 'Data diekspor',
};

function formatTime(iso: string | null) {
    return iso ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

function summary(values: Record<string, unknown> | null) {
    return values ? Object.entries(values).map(([key, value]) => `${key}: ${value}`).join(', ') : '-';
}
</script>

<template>
    <Head title="Log Audit" />
    <OwnerLayout title="Log Audit" active="audit">
        <Panel>
            <p v-if="logs.length === 0" class="text-body-md text-text-secondary">Belum ada catatan.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-body-md">
                    <thead class="bg-bg-subtle text-label-md text-text-secondary">
                        <tr>
                            <th scope="col" class="px-space-sm py-space-sm">Waktu</th>
                            <th scope="col" class="px-space-sm py-space-sm">Pengguna</th>
                            <th scope="col" class="px-space-sm py-space-sm">Aksi</th>
                            <th scope="col" class="px-space-sm py-space-sm">Objek</th>
                            <th scope="col" class="px-space-sm py-space-sm">Sebelum</th>
                            <th scope="col" class="px-space-sm py-space-sm">Sesudah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-text-primary">
                        <tr v-for="log in logs" :key="log.id">
                            <td class="whitespace-nowrap px-space-sm py-space-sm">{{ formatTime(log.created_at) }}</td>
                            <td class="px-space-sm py-space-sm">{{ log.user_name ?? 'Sistem' }}</td>
                            <td class="px-space-sm py-space-sm">{{ eventLabels[log.event] ?? log.event }}</td>
                            <td class="px-space-sm py-space-sm">{{ log.subject ?? '-' }}</td>
                            <td class="max-w-64 break-words px-space-sm py-space-sm text-body-sm text-text-secondary">{{ summary(log.old_values) }}</td>
                            <td class="max-w-64 break-words px-space-sm py-space-sm text-body-sm text-text-secondary">{{ summary(log.new_values) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Panel>
    </OwnerLayout>
</template>
