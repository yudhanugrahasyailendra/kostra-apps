<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import Panel from '@/Components/Panel.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import type { PaymentAccountData } from '@/types';

const props = defineProps<{
    accounts: PaymentAccountData[];
    properties: { id: number; name: string }[];
    types: { value: string; label: string }[];
    can: { manage: boolean };
}>();

const open = ref(false);
const editing = ref<PaymentAccountData | null>(null);

const form = useForm({
    type: 'bank',
    property_id: '' as number | string,
    bank_name: '',
    account_number: '',
    account_name: '',
    instructions: '',
    is_default: false,
    is_active: true,
});

const propertyOptions = computed(() => [{ value: '', label: 'Semua properti' }, ...props.properties.map((p) => ({ value: p.id, label: p.name }))]);
const needsDetails = computed(() => form.type !== 'qris');

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(account: PaymentAccountData) {
    editing.value = account;
    form.clearErrors();
    form.type = account.type;
    form.property_id = account.property_id ?? '';
    form.bank_name = account.bank_name ?? '';
    form.account_number = account.account_number ?? '';
    form.account_name = account.account_name ?? '';
    form.instructions = account.instructions ?? '';
    form.is_default = account.is_default;
    form.is_active = account.is_active;
    open.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    form.transform((data) => ({ ...data, property_id: data.property_id === '' ? null : data.property_id }));

    if (editing.value) {
        form.patch(`/app/pengaturan/rekening/${editing.value.id}`, options);
    } else {
        form.post('/app/pengaturan/rekening', options);
    }
}

function toggleActive(account: PaymentAccountData) {
    // Rekening nonaktif tidak boleh menjadi bawaan, jadi status bawaan dilepas saat dinonaktifkan.
    useForm({
        type: account.type,
        property_id: account.property_id,
        bank_name: account.bank_name,
        account_number: account.account_number,
        account_name: account.account_name,
        instructions: account.instructions,
        is_default: account.is_active ? false : account.is_default,
        is_active: !account.is_active,
    }).patch(`/app/pengaturan/rekening/${account.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Rekening pembayaran" />
    <OwnerLayout title="Rekening pembayaran" active="rekening">
        <div class="mb-space-lg flex flex-wrap items-start justify-between gap-space-md">
            <p class="max-w-2xl text-body-md text-text-secondary">
                Rekening bank, QRIS statis, atau dompet digital Anda. Ditampilkan di tagihan dan portal penghuni. Kostra tidak menerima atau menahan dana; penghuni membayar langsung ke Anda.
            </p>
            <Button v-if="can.manage" icon="add" @click="openCreate">Tambah rekening</Button>
        </div>

        <p v-if="accounts.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            Belum ada rekening. Tambahkan minimal satu rekening supaya penghuni tahu ke mana harus membayar.
        </p>

        <div class="grid gap-space-md md:grid-cols-2">
            <Panel v-for="account in accounts" :key="account.id" :title="account.label">
                <template #actions>
                    <div class="flex gap-space-xs">
                        <StatusBadge v-if="account.is_default" tone="lunas" label="Bawaan" />
                        <StatusBadge v-if="!account.is_active" tone="kosong" label="Nonaktif" />
                    </div>
                </template>
                <dl class="flex flex-col gap-space-xs text-body-md">
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Jenis</dt><dd>{{ account.type_label }}</dd></div>
                    <div v-if="account.account_name" class="flex justify-between gap-space-md"><dt class="text-text-secondary">Atas nama</dt><dd>{{ account.account_name }}</dd></div>
                    <div class="flex justify-between gap-space-md"><dt class="text-text-secondary">Berlaku untuk</dt><dd>{{ account.property_name ?? 'Semua properti' }}</dd></div>
                    <p v-if="account.instructions" class="text-body-sm text-text-secondary">{{ account.instructions }}</p>
                </dl>
                <div v-if="can.manage" class="mt-space-md flex gap-space-sm">
                    <Button variant="secondary" icon="edit" @click="openEdit(account)">Ubah</Button>
                    <Button variant="ghost" :icon="account.is_active ? 'visibility_off' : 'visibility'" @click="toggleActive(account)">{{ account.is_active ? 'Nonaktifkan' : 'Aktifkan' }}</Button>
                </div>
            </Panel>
        </div>

        <Modal :show="open" :title="editing ? 'Ubah rekening' : 'Tambah rekening'" @close="open = false">
            <form class="flex flex-col gap-space-md" @submit.prevent="submit">
                <SelectField v-model="form.type" label="Jenis" :options="types" :error="form.errors.type" required />
                <SelectField v-model="form.property_id" label="Berlaku untuk" :options="propertyOptions" :error="form.errors.property_id" />
                <template v-if="needsDetails">
                    <TextField v-model="form.bank_name" :label="form.type === 'bank' ? 'Nama bank' : 'Nama dompet digital'" :error="form.errors.bank_name" required />
                    <TextField v-model="form.account_number" label="Nomor rekening atau akun" :error="form.errors.account_number" required />
                    <TextField v-model="form.account_name" label="Atas nama" :error="form.errors.account_name" required />
                </template>
                <TextField v-model="form.instructions" label="Catatan untuk penghuni (opsional)" :error="form.errors.instructions" />
                <label class="flex items-center gap-space-sm text-body-md text-text-primary">
                    <input v-model="form.is_default" type="checkbox" class="size-5 accent-primary-container" />
                    Jadikan rekening bawaan
                </label>
                <p v-if="form.errors.is_default" class="text-body-sm text-error">{{ form.errors.is_default }}</p>
            </form>
            <template #footer>
                <Button variant="ghost" @click="open = false">Batal</Button>
                <Button icon="save" :disabled="form.processing" @click="submit">Simpan</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
