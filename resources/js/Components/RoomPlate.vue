<script setup lang="ts">
import RowMenu from '@/Components/RowMenu.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatRupiah } from '@/lib/formatRupiah';
import type { RoomData } from '@/types';

// Kartu kamar (DESIGN.md: Room Unit Card). Nama penghuni dan tagihan menyusul di Fase 4 dan 5.
defineProps<{ room: RoomData; canManage: boolean }>();
const emit = defineEmits<{ edit: [room: RoomData]; remove: [room: RoomData] }>();

const accent: Record<string, string> = {
    kosong: 'border-t-border-strong',
    lunas: 'border-t-status-lunas-text',
    menunggu: 'border-t-secondary',
    'jatuh-tempo': 'border-t-status-jatuh-tempo-text',
};
</script>

<template>
    <article
        class="flex min-w-40 flex-col gap-space-sm rounded-xl border border-border-subtle border-t-4 bg-bg-surface p-space-md transition-shadow hover:border-primary-container hover:shadow-mid"
        :class="accent[room.status_tone]"
    >
        <header class="flex items-start justify-between gap-space-xs">
            <div class="min-w-0">
                <h3 class="text-title-lg text-text-primary">{{ room.number }}</h3>
                <p class="truncate text-body-sm text-text-secondary">{{ room.room_type_name ?? 'Tanpa tipe' }}</p>
            </div>
            <StatusBadge :tone="room.status_tone" :label="room.status_label" />
        </header>

        <div class="flex flex-1 flex-col justify-center rounded-md bg-bg-subtle p-space-sm text-center">
            <span class="material-symbols-outlined mx-auto text-text-muted" aria-hidden="true">
                {{ room.status === 'maintenance' ? 'build' : room.status === 'occupied' ? 'person' : 'meeting_room' }}
            </span>
            <p class="mt-space-xs text-title-md text-text-primary">
                {{ room.status === 'maintenance' ? 'Dalam perawatan' : room.status === 'occupied' ? 'Terisi' : 'Siap huni' }}
            </p>
            <p class="text-body-sm text-text-secondary">{{ formatRupiah(room.price) }} / bulan</p>
        </div>

        <footer v-if="canManage" class="-mb-space-xs -mr-space-xs flex justify-end">
            <RowMenu :items="[{ key: 'edit', label: 'Ubah', icon: 'edit' }, { key: 'delete', label: 'Hapus', icon: 'delete', danger: true }]" :label="`Aksi kamar ${room.number}`" @select="(key) => (key === 'edit' ? emit('edit', room) : emit('remove', room))" />
        </footer>
    </article>
</template>
