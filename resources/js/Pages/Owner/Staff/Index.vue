<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Panel from '@/Components/Panel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import type { SharedProps, UserData } from '@/types';

interface InvitationData {
    id: number;
    email: string | null;
    phone: string | null;
    permissions: string[];
    expires_at: string;
}

defineProps<{
    staff: UserData[];
    invitations: InvitationData[];
    permissionOptions: { value: string; label: string }[];
}>();

const page = usePage<SharedProps>();
const invitationLink = computed(() => page.props.flash.invitation_link);

const form = useForm({ email: '', phone: '', permissions: [] as string[] });

function invite() {
    form.post('/app/staf/undangan', { preserveScroll: true, onSuccess: () => form.reset() });
}

function togglePermission(member: UserData, permission: string, checked: boolean) {
    const next = checked ? [...member.permissions, permission] : member.permissions.filter((p) => p !== permission);
    router.patch(`/app/staf/${member.id}`, { permissions: next }, { preserveScroll: true });
}

function setActive(member: UserData, active: boolean) {
    router.patch(`/app/staf/${member.id}`, { permissions: member.permissions, is_active: active }, { preserveScroll: true });
}

function remove(member: UserData) {
    if (confirm(`Hapus staf ${member.name}?`)) {
        router.delete(`/app/staf/${member.id}`, { preserveScroll: true });
    }
}

function formatDate(iso: string) {
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}
</script>

<template>
    <Head title="Staf" />
    <OwnerLayout title="Staf" active="staf">
        <div class="grid gap-space-lg lg:grid-cols-3">
            <div class="flex flex-col gap-space-lg lg:col-span-2">
                <Panel title="Staf aktif">
                    <p v-if="staff.length === 0" class="text-body-md text-text-secondary">Belum ada staf. Undang staf lewat formulir di samping.</p>
                    <ul v-else class="flex flex-col divide-y divide-border-subtle">
                        <li v-for="member in staff" :key="member.id" class="flex flex-col gap-space-sm py-space-md first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-center justify-between gap-space-sm">
                                <div>
                                    <p class="text-title-md text-text-primary">{{ member.name }}</p>
                                    <p class="text-body-sm text-text-secondary">{{ member.email ?? member.phone }}</p>
                                </div>
                                <div class="flex items-center gap-space-sm">
                                    <StatusBadge :tone="member.is_active ? 'lunas' : 'kosong'" :label="member.is_active ? 'Aktif' : 'Nonaktif'" />
                                    <Button variant="ghost" @click="setActive(member, !member.is_active)">
                                        {{ member.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </Button>
                                    <Button variant="ghost" icon="delete" @click="remove(member)">Hapus</Button>
                                </div>
                            </div>
                            <fieldset class="flex flex-wrap gap-x-space-md gap-y-space-xs">
                                <legend class="sr-only">Izin {{ member.name }}</legend>
                                <label v-for="option in permissionOptions" :key="option.value" class="flex items-center gap-space-xs text-body-md text-text-secondary">
                                    <input
                                        type="checkbox"
                                        class="size-[18px] rounded-sm accent-primary-container"
                                        :checked="member.permissions.includes(option.value)"
                                        @change="togglePermission(member, option.value, ($event.target as HTMLInputElement).checked)"
                                    />
                                    {{ option.label }}
                                </label>
                            </fieldset>
                        </li>
                    </ul>
                </Panel>

                <Panel v-if="invitations.length" title="Undangan menunggu">
                    <ul class="flex flex-col divide-y divide-border-subtle">
                        <li v-for="invitation in invitations" :key="invitation.id" class="flex items-center justify-between py-space-sm first:pt-0 last:pb-0">
                            <span class="text-body-md text-text-primary">{{ invitation.email ?? invitation.phone }}</span>
                            <StatusBadge tone="menunggu" :label="`Sampai ${formatDate(invitation.expires_at)}`" />
                        </li>
                    </ul>
                </Panel>
            </div>

            <Panel title="Undang staf">
                <form class="flex flex-col gap-space-md" @submit.prevent="invite">
                    <TextField v-model="form.email" label="Email" type="email" :error="form.errors.email" hint="Isi email atau nomor WhatsApp." />
                    <TextField v-model="form.phone" label="Nomor WhatsApp" type="tel" :error="form.errors.phone" />
                    <fieldset class="flex flex-col gap-space-xs">
                        <legend class="mb-space-xs text-label-md text-text-primary">Izin tambahan</legend>
                        <label v-for="option in permissionOptions" :key="option.value" class="flex items-center gap-space-xs text-body-md text-text-secondary">
                            <input v-model="form.permissions" type="checkbox" :value="option.value" class="size-[18px] rounded-sm accent-primary-container" />
                            {{ option.label }}
                        </label>
                    </fieldset>
                    <Button type="submit" :disabled="form.processing" icon="send">Buat undangan</Button>
                </form>

                <div v-if="invitationLink" class="mt-space-md rounded-md bg-status-menunggu-bg p-space-sm" role="status">
                    <p class="text-label-md text-status-menunggu-text">Salin tautan ini sekarang, tidak akan tampil lagi:</p>
                    <p class="mt-space-xs break-all text-body-sm text-text-primary">{{ invitationLink }}</p>
                </div>
            </Panel>
        </div>
    </OwnerLayout>
</template>
