<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Panel from '@/Components/Panel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDate } from '@/lib/formatDate';
import type { AdminTenantRow, StatusTone } from '@/types';

const props = defineProps<{
    tenants: AdminTenantRow[];
    pagination: { current_page: number; last_page: number; total: number; prev_url: string | null; next_url: string | null };
    filters: { q: string; status: string | null };
    statusOptions: { value: string; label: string }[];
}>();

const q = ref(props.filters.q);
const status = ref(props.filters.status ?? '');

function search() {
    router.get('/admin/tenant', { q: q.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });
}

function tone(row: AdminTenantRow): StatusTone {
    if (row.status === 'suspended' || row.status === 'closed') return 'jatuh-tempo';
    return row.status === 'trial' ? 'menunggu' : 'lunas';
}

function usage(used: number, max: number | null): string {
    return `${used} / ${max ?? '∞'}`;
}
</script>

<template>
    <Head title="Tenant" />
    <AdminLayout title="Tenant">
        <form class="mb-space-md flex flex-wrap items-end gap-space-sm" role="search" @submit.prevent="search">
            <div class="min-w-64 flex-1">
                <TextField v-model="q" label="Cari tenant" placeholder="Nama kos, telepon, atau email pemilik" />
            </div>
            <label class="flex flex-col gap-space-xs text-label-lg text-text-primary">
                Status
                <select v-model="status" class="min-h-11 rounded-md border border-border-strong bg-bg-surface px-space-sm text-body-md lg:min-h-10">
                    <option value="">Semua</option>
                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>
            <Button type="submit" icon="search">Cari</Button>
        </form>

        <Panel :title="`${pagination.total} tenant`">
            <p v-if="tenants.length === 0" class="text-body-md text-text-secondary">Tidak ada tenant yang cocok.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-left text-body-md">
                    <thead class="text-label-md uppercase text-text-secondary">
                        <tr>
                            <th class="py-space-sm pr-space-md">Kos</th>
                            <th class="pr-space-md">Status</th>
                            <th class="pr-space-md">Paket</th>
                            <th class="pr-space-md">Langganan</th>
                            <th class="pr-space-md">Kamar</th>
                            <th class="pr-space-md">Pesan WA</th>
                            <th class="pr-space-md">Daftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tenants" :key="row.id" class="border-t border-border-subtle">
                            <td class="py-space-sm pr-space-md">
                                <Link :href="`/admin/tenant/${row.id}`" class="text-title-md text-primary-container underline">{{ row.name }}</Link>
                            </td>
                            <td class="pr-space-md"><StatusBadge :tone="tone(row)" :label="row.status_label" /></td>
                            <td class="pr-space-md">{{ row.plan_name }}</td>
                            <td class="pr-space-md">
                                {{ row.subscription_status_label ?? '-' }}
                                <span v-if="row.period_end" class="block text-body-sm text-text-secondary">s/d {{ formatDate(row.period_end, 'short') }}</span>
                            </td>
                            <td class="pr-space-md">{{ usage(row.usage.rooms, row.usage.max_rooms) }}</td>
                            <td class="pr-space-md">{{ usage(row.usage.messages, row.usage.message_quota) }}</td>
                            <td class="pr-space-md">{{ formatDate(row.created_at, 'short') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav v-if="pagination.last_page > 1" class="mt-space-md flex items-center justify-between" aria-label="Halaman">
                <Link v-if="pagination.prev_url" :href="pagination.prev_url" class="text-title-md text-primary-container underline">Sebelumnya</Link>
                <span v-else></span>
                <span class="text-body-md text-text-secondary">Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                <Link v-if="pagination.next_url" :href="pagination.next_url" class="text-title-md text-primary-container underline">Berikutnya</Link>
                <span v-else></span>
            </nav>
        </Panel>
    </AdminLayout>
</template>
