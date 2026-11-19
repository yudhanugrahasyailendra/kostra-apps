<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { StatusTone } from '@/types';

interface RoomHit {
    id: number;
    number: string;
    property_name: string | null;
    floor: number | null;
    status: string;
    status_label: string;
    status_tone: StatusTone;
    resident_name: string | null;
    href: string;
}

interface ResidentHit {
    id: number;
    name: string;
    is_active: boolean;
    status_label: string;
    room_number: string | null;
    href: string;
}

interface Option {
    id: string;
    group: 'rooms' | 'residents';
    icon: string;
    title: string;
    detail: string;
    badge: { tone: StatusTone; label: string };
    href: string;
}

// Kotak cari di kepala halaman pemilik (bentuk mengikuti desain acuan: ikon, teks petunjuk, pintasan keyboard).
// Mencari kamar dan penghuni lewat GET /app/cari; hasil hanya kolom yang ditampilkan di sini.
const MIN_LENGTH = 2;
const DEBOUNCE_MS = 250;

const root = ref<HTMLElement | null>(null);
const input = ref<HTMLInputElement | null>(null);
const query = ref('');
const open = ref(false);
const loading = ref(false);
const failed = ref(false);
const options = ref<Option[]>([]);
const activeIndex = ref(-1);

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
const shortcut = isMac ? '⌘ K' : 'Ctrl K';

const groups = computed(() =>
    [
        { key: 'rooms', label: 'Kamar' },
        { key: 'residents', label: 'Penghuni' },
    ]
        .map((group) => ({ ...group, items: options.value.filter((option) => option.group === group.key) }))
        .filter((group) => group.items.length > 0),
);
const trimmed = computed(() => query.value.trim());
const showPanel = computed(() => open.value && trimmed.value.length >= MIN_LENGTH);
const activeId = computed(() => (activeIndex.value >= 0 ? options.value[activeIndex.value]?.id : undefined));
const statusText = computed(() => {
    if (!showPanel.value || loading.value) {
        return '';
    }

    return failed.value ? 'Pencarian gagal' : `${options.value.length} hasil`;
});

let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

function toOptions(data: { rooms: RoomHit[]; residents: ResidentHit[] }): Option[] {
    const rooms = data.rooms.map(
        (room): Option => ({
            id: `search-room-${room.id}`,
            group: 'rooms',
            icon: 'meeting_room',
            title: `Kamar ${room.number}`,
            detail: [room.property_name, room.floor !== null ? `Lantai ${room.floor}` : null, room.resident_name].filter(Boolean).join(' · '),
            badge: { tone: room.status_tone, label: room.status_label },
            href: room.href,
        }),
    );
    const residents = data.residents.map(
        (resident): Option => ({
            id: `search-resident-${resident.id}`,
            group: 'residents',
            icon: 'person',
            title: resident.name,
            detail: resident.room_number ? `Kamar ${resident.room_number}` : 'Belum menempati kamar',
            badge: { tone: resident.is_active ? 'lunas' : 'kosong', label: resident.status_label },
            href: resident.href,
        }),
    );

    return [...rooms, ...residents];
}

async function search(q: string): Promise<void> {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    failed.value = false;

    try {
        const { data } = await axios.get('/app/cari', { params: { q }, signal: controller.signal });
        options.value = toOptions(data);
        activeIndex.value = options.value.length > 0 ? 0 : -1;
        loading.value = false;
    } catch (error) {
        if (axios.isCancel(error)) {
            return;
        }
        options.value = [];
        activeIndex.value = -1;
        failed.value = true;
        loading.value = false;
    }
}

watch(trimmed, (q) => {
    clearTimeout(timer);

    if (q.length < MIN_LENGTH) {
        controller?.abort();
        options.value = [];
        activeIndex.value = -1;
        loading.value = false;
        return;
    }

    loading.value = true;
    timer = setTimeout(() => search(q), DEBOUNCE_MS);
});

function close(): void {
    open.value = false;
    activeIndex.value = -1;
}

function choose(option: Option): void {
    close();
    query.value = '';
    input.value?.blur();
    router.visit(option.href);
}

function move(step: number): void {
    if (!showPanel.value) {
        open.value = true;
        return;
    }
    if (options.value.length === 0) {
        return;
    }
    activeIndex.value = (activeIndex.value + step + options.value.length) % options.value.length;
    document.getElementById(options.value[activeIndex.value].id)?.scrollIntoView({ block: 'nearest' });
}

function onInputKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    } else if (event.key === 'Enter') {
        const option = options.value[activeIndex.value];
        if (showPanel.value && option) {
            event.preventDefault();
            choose(option);
        }
    } else if (event.key === 'Escape') {
        if (showPanel.value) {
            close();
        } else {
            query.value = '';
            input.value?.blur();
        }
    }
}

// Pintasan Ctrl+K / Cmd+K memindahkan fokus ke kotak cari dari mana saja.
function onGlobalKeydown(event: KeyboardEvent): void {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        input.value?.focus();
        input.value?.select();
        open.value = true;
    }
}

function onPointerDown(event: PointerEvent): void {
    if (open.value && root.value && !root.value.contains(event.target as Node)) {
        close();
    }
}

onMounted(() => {
    document.addEventListener('keydown', onGlobalKeydown);
    document.addEventListener('pointerdown', onPointerDown);
});

onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
    document.removeEventListener('keydown', onGlobalKeydown);
    document.removeEventListener('pointerdown', onPointerDown);
});

const indexOf = (option: Option) => options.value.indexOf(option);
</script>

<template>
    <div ref="root" class="relative min-w-0 flex-1 sm:w-80 sm:flex-none">
        <label
            class="flex min-w-0 items-center gap-space-sm rounded-full bg-bg-subtle py-2 pl-space-md pr-2 ring-primary-container/40 transition-shadow focus-within:ring-2 lg:bg-bg-surface"
        >
            <span class="material-symbols-outlined shrink-0 text-[20px] text-text-secondary" aria-hidden="true">search</span>
            <span class="sr-only">Cari kamar atau penghuni</span>
            <input
                ref="input"
                v-model="query"
                type="search"
                role="combobox"
                autocomplete="off"
                spellcheck="false"
                maxlength="100"
                placeholder="Cari kamar atau penghuni"
                class="min-w-0 flex-1 bg-transparent py-0.5 text-body-md text-text-primary outline-none placeholder:text-text-muted [&::-webkit-search-cancel-button]:hidden"
                aria-autocomplete="list"
                aria-controls="owner-search-results"
                :aria-expanded="showPanel"
                :aria-activedescendant="showPanel ? activeId : undefined"
                @focus="open = true"
                @keydown="onInputKeydown"
            />
            <kbd
                class="hidden shrink-0 rounded-md border border-border-subtle bg-bg-subtle px-1.5 py-0.5 font-sans text-label-sm text-text-secondary sm:inline-block lg:bg-bg-canvas"
                :title="`Tekan ${shortcut} untuk mencari`"
                aria-hidden="true"
                >{{ shortcut }}</kbd
            >
        </label>
        <p class="sr-only" role="status" aria-live="polite">{{ statusText }}</p>

        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-out"
            enter-from-class="opacity-0 motion-safe:-translate-y-1"
            leave-active-class="motion-safe:transition motion-safe:duration-100 motion-safe:ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-show="showPanel"
                class="fixed inset-x-margin top-[4.5rem] z-50 overflow-hidden rounded-xl border border-border-subtle bg-bg-surface shadow-high sm:absolute sm:inset-x-auto sm:left-0 sm:top-[calc(100%+0.75rem)] sm:w-[26rem]"
            >
                <ul id="owner-search-results" role="listbox" aria-label="Hasil pencarian" class="max-h-[min(26rem,calc(100vh-7rem))] overflow-y-auto py-space-xs">
                    <template v-for="group in groups" :key="group.key">
                        <li role="presentation" class="px-space-md pb-1 pt-space-sm text-label-sm uppercase tracking-[0.15em] text-text-muted">{{ group.label }}</li>
                        <li
                            v-for="option in group.items"
                            :id="option.id"
                            :key="option.id"
                            role="option"
                            :aria-selected="option.id === activeId"
                            class="mx-space-xs flex cursor-pointer items-center gap-space-sm rounded-lg px-space-sm py-space-sm"
                            :class="option.id === activeId ? 'bg-bg-subtle' : ''"
                            @mousemove="activeIndex = indexOf(option)"
                            @mousedown.prevent
                            @click="choose(option)"
                        >
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-container/10 text-primary-container" aria-hidden="true">
                                <span class="material-symbols-outlined text-[18px]">{{ option.icon }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-body-md font-medium text-text-primary">{{ option.title }}</span>
                                <span v-if="option.detail" class="block truncate text-body-sm text-text-muted">{{ option.detail }}</span>
                            </span>
                            <StatusBadge class="shrink-0" :tone="option.badge.tone" :label="option.badge.label" />
                        </li>
                    </template>
                </ul>
                <p v-if="loading && options.length === 0" class="px-space-md py-space-lg text-center text-body-md text-text-secondary">Mencari…</p>
                <p v-else-if="failed" class="px-space-md py-space-lg text-center text-body-md text-status-jatuh-tempo-text">Pencarian gagal. Coba lagi sebentar.</p>
                <p v-else-if="!loading && options.length === 0" class="px-space-md py-space-lg text-center text-body-md text-text-secondary">
                    Tidak ada kamar atau penghuni yang cocok dengan “{{ trimmed }}”.
                </p>
            </div>
        </Transition>
    </div>
</template>
