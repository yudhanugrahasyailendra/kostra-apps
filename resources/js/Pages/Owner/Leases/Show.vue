<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import Panel from '@/Components/Panel.vue';
import SelectField from '@/Components/SelectField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextField from '@/Components/TextField.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import { formatDate, remainingLabel } from '@/lib/formatDate';
import { formatPhone, whatsappLink } from '@/lib/formatPhone';
import { formatRupiah } from '@/lib/formatRupiah';
import type { LeaseDetailData, RoomData } from '@/types';

const props = defineProps<{
    lease: LeaseDetailData;
    vacantRooms: RoomData[];
    renewal: { default_months: number | null; next_start_date: string | null };
    chain: { previous_id: number | null; next_id: number | null };
}>();

const isActive = computed(() => props.lease.status === 'active');
const canRenew = computed(() => isActive.value && props.lease.can.renew && props.lease.end_date !== null);
const canTransfer = computed(() => isActive.value && props.lease.can.transfer);

// Perpanjang
const renewOpen = ref(false);
const renewForm = useForm({ duration_months: (props.renewal.default_months ?? 12) as number | string, rent_amount: props.lease.rent_amount as number | string, notes: '' });

const renewLeaseError = computed(() => (renewForm.errors as Record<string, string>).lease);

function renew() {
    renewForm.post(`/app/kontrak/${props.lease.id}/perpanjang`, { preserveScroll: true, onSuccess: () => (renewOpen.value = false) });
}

// Pindah kamar
const transferOpen = ref(false);
const transferForm = useForm({ to_room_id: '' as number | string, transferred_on: new Date().toLocaleDateString('en-CA'), new_rent_amount: '' as number | string, reason: '' });
const roomOptions = computed(() => props.vacantRooms.map((room) => ({ value: room.id, label: `Kamar ${room.number} - ${room.property_name} (${formatRupiah(room.price)})` })));

const transferLeaseError = computed(() => (transferForm.errors as Record<string, string>).lease);

function pickRoom() {
    const room = props.vacantRooms.find((r) => r.id === Number(transferForm.to_room_id));
    transferForm.new_rent_amount = room ? room.price : '';
}

function transfer() {
    transferForm.post(`/app/kontrak/${props.lease.id}/pindah-kamar`, { preserveScroll: true, onSuccess: () => (transferOpen.value = false) });
}
</script>

<template>
    <Head :title="`Kontrak Kamar ${lease.room_number}`" />
    <OwnerLayout :title="`Kontrak Kamar ${lease.room_number}`" active="kontrak">
        <Link href="/app/kontrak" class="mb-space-md inline-flex items-center gap-space-xs text-title-md text-primary-container hover:underline">&larr; Semua kontrak</Link>

        <div class="mb-space-lg flex flex-wrap items-center justify-between gap-space-md">
            <div class="flex flex-wrap items-center gap-space-sm">
                <StatusBadge :tone="lease.status_tone" :label="lease.status_label" />
                <StatusBadge v-if="lease.is_overdue" tone="jatuh-tempo" label="Lewat masa sewa" />
                <StatusBadge v-else-if="lease.expires_soon" tone="menunggu" label="Segera habis" />
                <span v-if="isActive" class="text-body-sm text-text-secondary">{{ remainingLabel(lease.days_remaining) }}</span>
                <span v-else-if="lease.ended_reason_label" class="text-body-sm text-text-secondary">{{ lease.ended_reason_label }}</span>
            </div>
            <div v-if="isActive" class="flex flex-wrap gap-space-sm">
                <Button v-if="canTransfer" variant="secondary" icon="swap_horiz" @click="transferOpen = true">Pindah kamar</Button>
                <Button v-if="canRenew" variant="secondary" icon="autorenew" @click="renewOpen = true">Perpanjang</Button>
                <Link
                    v-if="lease.can.check_out"
                    :href="`/app/kontrak/${lease.id}/check-out`"
                    class="inline-flex min-h-11 items-center justify-center gap-space-xs rounded-md bg-primary-container px-space-md py-2.5 text-title-md text-on-primary shadow-low hover:bg-primary lg:min-h-10"
                >
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">logout</span>
                    Check-out
                </Link>
            </div>
        </div>

        <p v-if="chain.previous_id || chain.next_id" class="mb-space-md flex flex-wrap gap-space-md text-body-sm">
            <Link v-if="chain.previous_id" :href="`/app/kontrak/${chain.previous_id}`" class="text-primary-container hover:underline">&larr; Kontrak sebelumnya (diperpanjang dari)</Link>
            <Link v-if="chain.next_id" :href="`/app/kontrak/${chain.next_id}`" class="text-primary-container hover:underline">Kontrak lanjutan (hasil perpanjangan) &rarr;</Link>
        </p>

        <div class="grid gap-space-lg lg:grid-cols-2">
            <Panel title="Ringkasan kontrak">
                <dl class="grid gap-space-md text-body-md sm:grid-cols-2">
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Kamar</dt>
                        <dd>Kamar {{ lease.room_number }}<span class="block text-body-sm text-text-secondary">{{ lease.property_name }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Harga sewa</dt>
                        <dd>{{ formatRupiah(lease.rent_amount) }} / bln<span class="block text-body-sm text-text-secondary">Siklus {{ lease.billing_cycle_label.toLowerCase() }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Mulai</dt>
                        <dd>{{ formatDate(lease.start_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Berakhir</dt>
                        <dd>{{ lease.end_date ? formatDate(lease.end_date) : 'Tanpa tanggal akhir' }}</dd>
                    </div>
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Jatuh tempo tagihan</dt>
                        <dd>{{ lease.due_day ? `Tanggal ${lease.due_day} tiap bulan` : 'Mengikuti pengaturan tagihan' }}</dd>
                    </div>
                    <div>
                        <dt class="text-label-md uppercase text-text-secondary">Check-in</dt>
                        <dd>{{ formatDate(lease.checked_in_at) }}</dd>
                    </div>
                    <div v-if="lease.checked_out_at">
                        <dt class="text-label-md uppercase text-text-secondary">Check-out</dt>
                        <dd>{{ formatDate(lease.checked_out_at) }}</dd>
                    </div>
                    <div v-if="lease.checkin_notes" class="sm:col-span-2">
                        <dt class="text-label-md uppercase text-text-secondary">Kondisi kamar saat masuk</dt>
                        <dd class="whitespace-pre-line">{{ lease.checkin_notes }}</dd>
                    </div>
                    <div v-if="lease.checkout_notes" class="sm:col-span-2">
                        <dt class="text-label-md uppercase text-text-secondary">Kondisi kamar saat keluar</dt>
                        <dd class="whitespace-pre-line">{{ lease.checkout_notes }}</dd>
                    </div>
                    <div v-if="lease.notes" class="sm:col-span-2">
                        <dt class="text-label-md uppercase text-text-secondary">Catatan kontrak</dt>
                        <dd class="whitespace-pre-line">{{ lease.notes }}</dd>
                    </div>
                </dl>
            </Panel>

            <div class="flex flex-col gap-space-lg">
                <Panel title="Penghuni dalam kontrak">
                    <ul class="divide-y divide-border-subtle">
                        <li v-for="member in lease.residents" :key="member.id" class="flex flex-wrap items-center justify-between gap-space-sm py-space-sm">
                            <div>
                                <Link :href="`/app/penghuni/${member.id}`" class="text-title-md text-primary-container hover:underline">{{ member.name }}</Link>
                                <a :href="whatsappLink(member.phone)" target="_blank" rel="noopener noreferrer" class="block text-body-sm text-text-secondary hover:underline">{{ formatPhone(member.phone) }}</a>
                            </div>
                            <StatusBadge v-if="member.is_primary" tone="lunas" label="Penanggung jawab" />
                        </li>
                    </ul>
                </Panel>

                <Panel v-if="lease.deposit && lease.deposit_detail" title="Deposit tercatat">
                    <div class="flex flex-wrap items-center justify-between gap-space-sm">
                        <p class="text-headline-md text-text-primary">{{ formatRupiah(lease.deposit_detail.amount) }}</p>
                        <StatusBadge :tone="lease.deposit.status_tone" :label="lease.deposit.status_label" />
                    </div>
                    <p class="mt-space-xs text-body-sm text-text-secondary">
                        <template v-if="lease.deposit_detail.received_at">Diterima {{ formatDate(lease.deposit_detail.received_at) }}.</template>
                        <template v-else>Belum diterima.</template>
                        Kostra hanya mencatat; uangnya ada di luar sistem.
                    </p>
                    <dl v-if="lease.deposit_detail.refunded_at" class="mt-space-md grid gap-space-sm border-t border-border-subtle pt-space-md text-body-md sm:grid-cols-2">
                        <div>
                            <dt class="text-label-md uppercase text-text-secondary">Total potongan</dt>
                            <dd>{{ formatRupiah(lease.deposit_detail.deduction_amount) }}</dd>
                        </div>
                        <div>
                            <dt class="text-label-md uppercase text-text-secondary">Dikembalikan ({{ formatDate(lease.deposit_detail.refunded_at) }})</dt>
                            <dd>{{ formatRupiah(lease.deposit_detail.refund_amount) }}</dd>
                        </div>
                        <div v-if="lease.deposit_detail.deduction_note" class="sm:col-span-2">
                            <dt class="text-label-md uppercase text-text-secondary">Rincian potongan</dt>
                            <dd class="whitespace-pre-line">{{ lease.deposit_detail.deduction_note }}</dd>
                        </div>
                    </dl>
                </Panel>

                <Panel v-if="lease.transfers.length" title="Riwayat pindah kamar">
                    <ul class="divide-y divide-border-subtle">
                        <li v-for="item in lease.transfers" :key="item.id" class="py-space-sm text-body-md">
                            <p class="text-title-md text-text-primary">Kamar {{ item.from_room_number }} &rarr; Kamar {{ item.to_room_number }}</p>
                            <p class="text-body-sm text-text-secondary">
                                {{ formatDate(item.transferred_on) }}
                                <template v-if="item.new_rent_amount !== null"> &middot; harga baru {{ formatRupiah(item.new_rent_amount) }} / bln</template>
                                <template v-if="item.reason"> &middot; {{ item.reason }}</template>
                            </p>
                        </li>
                    </ul>
                </Panel>
            </div>
        </div>

        <Modal :show="renewOpen" title="Perpanjang kontrak" @close="renewOpen = false">
            <form id="renew-form" class="flex flex-col gap-space-md" @submit.prevent="renew">
                <p class="text-body-md text-text-secondary">
                    Kontrak baru menyambung mulai {{ formatDate(renewal.next_start_date) }} dengan penghuni, kamar, dan deposit yang sama.
                </p>
                <TextField v-model="renewForm.duration_months" label="Durasi (bulan)" type="number" hint="Kosongkan bila tanpa tanggal akhir." :error="renewForm.errors.duration_months" />
                <TextField v-model="renewForm.rent_amount" label="Harga sewa per bulan (Rp)" type="number" :error="renewForm.errors.rent_amount" />
                <TextField v-model="renewForm.notes" label="Catatan (opsional)" :error="renewForm.errors.notes" />
                <p v-if="renewLeaseError" class="text-body-sm text-error">{{ renewLeaseError }}</p>
            </form>
            <template #footer>
                <Button variant="ghost" @click="renewOpen = false">Batal</Button>
                <Button type="submit" form="renew-form" icon="autorenew" :disabled="renewForm.processing">Perpanjang</Button>
            </template>
        </Modal>

        <Modal :show="transferOpen" title="Pindah kamar" @close="transferOpen = false">
            <form id="transfer-form" class="flex flex-col gap-space-md" @submit.prevent="transfer">
                <p class="text-body-md text-text-secondary">
                    Kamar {{ lease.room_number }} menjadi kosong dan kamar tujuan menjadi terisi. Harga baru berlaku untuk tagihan berikutnya.
                </p>
                <SelectField v-model="transferForm.to_room_id" label="Kamar tujuan (kosong)" required placeholder="Pilih kamar" :options="roomOptions" :error="transferForm.errors.to_room_id" @change="pickRoom" />
                <TextField v-model="transferForm.transferred_on" label="Tanggal pindah" type="date" :error="transferForm.errors.transferred_on" />
                <TextField v-model="transferForm.new_rent_amount" label="Harga sewa baru per bulan (Rp)" type="number" hint="Kosongkan untuk mempertahankan harga sekarang." :error="transferForm.errors.new_rent_amount" />
                <TextField v-model="transferForm.reason" label="Alasan (opsional)" :error="transferForm.errors.reason" />
                <p v-if="transferLeaseError" class="text-body-sm text-error">{{ transferLeaseError }}</p>
            </form>
            <template #footer>
                <Button variant="ghost" @click="transferOpen = false">Batal</Button>
                <Button type="submit" form="transfer-form" icon="swap_horiz" :disabled="transferForm.processing">Pindahkan</Button>
            </template>
        </Modal>
    </OwnerLayout>
</template>
