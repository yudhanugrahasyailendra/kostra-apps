<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
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

interface PermissionOption {
    value: string;
    label: string;
}

const props = defineProps<{
    staff: UserData[];
    invitations: InvitationData[];
    permissionOptions: PermissionOption[];
}>();

const page = usePage<SharedProps>();
const invitationLink = computed(() => page.props.flash.invitation_link);

// Undang staf
const inviteOpen = ref(false);
const inviteForm = useForm({ email: '', phone: '', permissions: [] as string[] });

function invite() {
    inviteForm.post('/app/staf/undangan', {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            inviteOpen.value = false;
        },
    });
}

// Edit hak akses
const editing = ref<UserData | null>(null);
const editPermissions = ref<string[]>([]);

function openEdit(member: UserData) {
    editing.value = member;
    editPermissions.value = [...member.permissions];
}

function saveEdit() {
    if (!editing.value) return;
    router.patch(
        `/app/staf/${editing.value.id}`,
        { permissions: editPermissions.value },
        { preserveScroll: true, onSuccess: () => (editing.value = null) },
    );
}

function setActive(member: UserData, active: boolean) {
    router.patch(`/app/staf/${member.id}`, { permissions: member.permissions, is_active: active }, { preserveScroll: true });
}

function remove(member: UserData) {
    if (confirm(`Hapus staf ${member.name}?`)) {
        router.delete(`/app/staf/${member.id}`, { preserveScroll: true });
    }
}

function initials(name: string) {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function formatDate(iso: string) {
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function lastActive(iso: string | null) {
    return iso ? `Terakhir aktif ${new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}` : 'Belum pernah masuk';
}

function labelOf(value: string) {
    return props.permissionOptions.find((o) => o.value === value)?.label ?? value;
}
</script>

<template>
    <Head title="Staf" />
    <OwnerLayout title="Manajemen tim & staf" active="staf">
        <div class="mb-space-lg flex flex-wrap items-center justify-between gap-space-md">
            <p class="max-w-xl text-body-md text-text-secondary">
                Atur hak akses staf kos tanpa memberi izin ke data rekening dan laporan keuangan. Staf selalu dibatasi pada tenant Anda.
            </p>
            <Button icon="person_add" @click="inviteOpen = true">Undang staf baru</Button>
        </div>

        <div v-if="invitationLink" class="mb-space-lg rounded-md border border-status-menunggu-border bg-status-menunggu-bg p-space-md" role="status">
            <p class="text-label-md text-status-menunggu-text">Salin tautan undangan ini sekarang. Tautan tidak akan tampil lagi.</p>
            <p class="mt-space-xs break-all text-body-sm text-text-primary">{{ invitationLink }}</p>
        </div>

        <ul class="grid gap-space-md md:grid-cols-2" aria-label="Anggota tim">
            <li v-for="member in staff" :key="member.id" class="flex flex-col gap-space-md rounded-lg border border-border-subtle bg-bg-surface p-space-md shadow-low">
                <div class="flex items-start justify-between gap-space-sm">
                    <div class="flex items-center gap-space-sm">
                        <span class="flex size-10 items-center justify-center rounded-full bg-primary text-title-md text-on-primary" aria-hidden="true">
                            {{ initials(member.name) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-title-md text-text-primary">{{ member.name }}</p>
                            <p class="truncate text-body-sm text-text-secondary">{{ member.email ?? member.phone }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-space-xs">
                        <StatusBadge :tone="member.role === 'owner' ? 'menunggu' : 'kosong'" :label="member.role_label" />
                        <StatusBadge :tone="member.is_active ? 'lunas' : 'kosong'" :label="member.is_active ? 'Aktif' : 'Nonaktif'" />
                    </div>
                </div>

                <ul v-if="member.role === 'owner'" class="flex flex-wrap gap-space-xs">
                    <li class="rounded-sm bg-status-lunas-bg px-space-sm py-space-xs text-label-md text-status-lunas-text">Akses penuh seluruh fitur</li>
                </ul>
                <ul v-else class="flex flex-wrap gap-space-xs">
                    <li
                        v-for="option in permissionOptions"
                        :key="option.value"
                        class="rounded-sm px-space-sm py-space-xs text-label-md"
                        :class="member.permissions.includes(option.value) ? 'bg-status-lunas-bg text-status-lunas-text' : 'bg-status-kosong-bg text-status-kosong-text line-through'"
                    >
                        {{ member.permissions.includes(option.value) ? '' : 'Tanpa: ' }}{{ option.label }}
                    </li>
                </ul>

                <div class="mt-auto flex flex-wrap items-center justify-between gap-space-sm border-t border-border-subtle pt-space-sm">
                    <span class="text-body-sm text-text-muted">{{ lastActive(member.last_login_at) }}</span>
                    <div v-if="member.role !== 'owner'" class="flex items-center gap-space-xs">
                        <Button variant="ghost" @click="openEdit(member)">Edit hak akses</Button>
                        <Button variant="ghost" @click="setActive(member, !member.is_active)">{{ member.is_active ? 'Nonaktifkan' : 'Aktifkan' }}</Button>
                        <Button variant="ghost" icon="delete" aria-label="Hapus staf" @click="remove(member)" />
                    </div>
                </div>
            </li>
        </ul>

        <section v-if="invitations.length" class="mt-space-xl" aria-labelledby="undangan-heading">
            <h2 id="undangan-heading" class="mb-space-sm text-title-lg text-text-primary">Undangan menunggu</h2>
            <ul class="divide-y divide-border-subtle rounded-lg border border-border-subtle bg-bg-surface">
                <li v-for="invitation in invitations" :key="invitation.id" class="flex flex-wrap items-center justify-between gap-space-sm px-space-md py-space-sm">
                    <span class="text-body-md text-text-primary">{{ invitation.email ?? invitation.phone }}</span>
                    <StatusBadge tone="menunggu" :label="`Berlaku sampai ${formatDate(invitation.expires_at)}`" />
                </li>
            </ul>
        </section>

        <Modal :show="inviteOpen" title="Undang staf baru" @close="inviteOpen = false">
            <form id="invite-form" class="flex flex-col gap-space-md" @submit.prevent="invite">
                <TextField v-model="inviteForm.email" label="Email" type="email" :error="inviteForm.errors.email" hint="Isi email atau nomor WhatsApp." />
                <TextField v-model="inviteForm.phone" label="Nomor WhatsApp" type="tel" :error="inviteForm.errors.phone" hint="Contoh: 081234567890" />
                <fieldset class="flex flex-col gap-space-xs">
                    <legend class="mb-space-xs text-label-md text-text-primary">Izin tambahan</legend>
                    <label v-for="option in permissionOptions" :key="option.value" class="flex items-center gap-space-xs text-body-md text-text-secondary">
                        <input v-model="inviteForm.permissions" type="checkbox" :value="option.value" class="size-[18px] rounded-sm accent-primary-container" />
                        {{ option.label }}
                    </label>
                </fieldset>
            </form>
            <template #footer>
                <Button variant="ghost" @click="inviteOpen = false">Batal</Button>
                <Button type="submit" form="invite-form" :disabled="inviteForm.processing" icon="send">Buat undangan</Button>
            </template>
        </Modal>

        <Modal :show="editing !== null" :title="`Hak akses ${editing?.name ?? ''}`" @close="editing = null">
            <fieldset class="flex flex-col gap-space-sm">
                <legend class="sr-only">Izin</legend>
                <label v-for="option in permissionOptions" :key="option.value" class="flex items-center gap-space-sm text-body-md text-text-primary">
                    <input v-model="editPermissions" type="checkbox" :value="option.value" class="size-[18px] rounded-sm accent-primary-container" />
                    {{ labelOf(option.value) }}
                </label>
            </fieldset>
            <template #footer>
                <Button variant="ghost" @click="editing = null">Batal</Button>
                <Button @click="saveEdit">Simpan</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
