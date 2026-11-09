<script setup lang="ts">
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Panel from '@/Components/Panel.vue';
import SelectField from '@/Components/SelectField.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatRupiah } from '@/lib/formatRupiah';
import type { RoomData } from '@/types';

const props = defineProps<{
    rooms: RoomData[];
    residents: { id: number; name: string }[];
    defaults: { room_id: number | null; resident_id: number | null; start_date: string };
}>();

const form = useForm({
    room_id: (props.defaults.room_id ?? '') as number | string,
    primary_resident_id: (props.defaults.resident_id ?? '') as number | string,
    resident_ids: [] as number[],
    start_date: props.defaults.start_date,
    duration_months: 12 as number | string,
    rent_amount: '' as number | string,
    due_day: '' as number | string,
    deposit_amount: '' as number | string,
    deposit_received_at: '',
    checkin_notes: '',
    notes: '',
});

const selectedRoom = computed(() => props.rooms.find((room) => room.id === Number(form.room_id)) ?? null);

const roomOptions = computed(() => props.rooms.map((room) => ({ value: room.id, label: `Kamar ${room.number} - ${room.property_name} (${formatRupiah(room.price)})` })));
const residentOptions = computed(() => props.residents.map((resident) => ({ value: resident.id, label: resident.name })));
const partnerOptions = computed(() => props.residents.filter((resident) => resident.id !== Number(form.primary_resident_id)));

// Harga sewa mengikuti harga kamar sampai pemilik mengisinya sendiri.
watch(selectedRoom, (room) => {
    if (room && form.rent_amount === '') form.rent_amount = room.price;
});

function submit() {
    form.post('/app/kontrak', { preserveScroll: true });
}
</script>

<template>
    <Head title="Check-in penghuni" />
    <OwnerLayout title="Check-in penghuni" active="kontrak">
        <Link href="/app/kontrak" class="mb-space-md inline-flex items-center gap-space-xs text-title-md text-primary-container hover:underline">&larr; Semua kontrak</Link>

        <p v-if="rooms.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            Tidak ada kamar kosong. Kosongkan kamar lewat check-out, atau
            <Link href="/app/kamar" class="text-primary-container underline">tambah kamar</Link>.
        </p>
        <p v-else-if="residents.length === 0" class="rounded-lg border border-dashed border-border-strong p-space-xl text-center text-body-md text-text-secondary">
            Semua penghuni sudah punya kontrak aktif atau belum ada penghuni.
            <Link href="/app/penghuni" class="text-primary-container underline">Tambah penghuni</Link> lebih dulu.
        </p>

        <form v-else class="grid gap-space-lg lg:grid-cols-2" @submit.prevent="submit">
            <Panel title="Kamar dan penghuni">
                <div class="flex flex-col gap-space-md">
                    <SelectField v-model="form.room_id" label="Kamar kosong" required placeholder="Pilih kamar" :options="roomOptions" :error="form.errors.room_id" />
                    <SelectField v-model="form.primary_resident_id" label="Penghuni (penanggung jawab tagihan)" required placeholder="Pilih penghuni" :options="residentOptions" :error="form.errors.primary_resident_id" />

                    <fieldset v-if="partnerOptions.length" class="flex flex-col gap-space-xs">
                        <legend class="text-label-md text-text-primary">Penghuni tambahan (opsional)</legend>
                        <p class="text-body-sm text-text-secondary">Untuk pasangan atau kamar berbagi. Tagihan tetap atas nama penanggung jawab.</p>
                        <div class="max-h-40 overflow-y-auto rounded-md border border-border-subtle p-space-sm">
                            <label v-for="resident in partnerOptions" :key="resident.id" class="flex items-center gap-space-sm py-space-xs text-body-md">
                                <input v-model="form.resident_ids" type="checkbox" :value="resident.id" class="h-4 w-4 rounded border-border-strong text-primary-container" />
                                {{ resident.name }}
                            </label>
                        </div>
                        <p v-if="form.errors.resident_ids" class="text-body-sm text-error">{{ form.errors.resident_ids }}</p>
                    </fieldset>

                    <div class="flex flex-col gap-space-xs">
                        <label for="checkin-notes" class="text-label-md text-text-primary">Kondisi kamar saat masuk</label>
                        <textarea
                            id="checkin-notes"
                            v-model="form.checkin_notes"
                            rows="3"
                            placeholder="Contoh: AC berfungsi, dinding bersih, 2 kunci diserahkan"
                            class="rounded-md border border-border-strong bg-bg-surface px-3 py-2 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
                        />
                        <p v-if="form.errors.checkin_notes" class="text-body-sm text-error">{{ form.errors.checkin_notes }}</p>
                    </div>
                </div>
            </Panel>

            <Panel title="Masa sewa, harga, dan deposit">
                <div class="grid gap-space-md sm:grid-cols-2">
                    <TextField v-model="form.start_date" label="Tanggal mulai" type="date" required :error="form.errors.start_date" />
                    <TextField v-model="form.duration_months" label="Durasi (bulan)" type="number" hint="Kosongkan bila tanpa tanggal akhir." :error="form.errors.duration_months" />
                    <TextField v-model="form.rent_amount" label="Harga sewa per bulan (Rp)" type="number" :hint="selectedRoom ? `Harga kamar: ${formatRupiah(selectedRoom.price)}` : undefined" :error="form.errors.rent_amount" />
                    <TextField v-model="form.due_day" label="Tanggal jatuh tempo (1-28)" type="number" hint="Kosongkan untuk mengikuti pengaturan tagihan." :error="form.errors.due_day" />
                    <TextField v-model="form.deposit_amount" label="Deposit (Rp)" type="number" :error="form.errors.deposit_amount" />
                    <TextField v-model="form.deposit_received_at" label="Tanggal deposit diterima" type="date" hint="Kosongkan bila belum diterima." :error="form.errors.deposit_received_at" />
                </div>
                <p class="mt-space-md rounded-md border border-border-subtle bg-bg-subtle p-space-sm text-body-sm text-text-secondary">
                    Deposit dicatat sebagai kewajiban yang dapat dikembalikan. Kostra hanya mencatat; uangnya diterima dan dikembalikan di luar sistem.
                </p>
            </Panel>

            <div class="flex justify-end gap-space-sm lg:col-span-2">
                <Link href="/app/kontrak" class="inline-flex min-h-11 items-center rounded-md px-space-md text-title-md text-text-secondary hover:bg-bg-subtle lg:min-h-10">Batal</Link>
                <Button type="submit" icon="login" :disabled="form.processing">Check-in dan buat kontrak</Button>
            </div>
        </form>
    </OwnerLayout>
</template>
