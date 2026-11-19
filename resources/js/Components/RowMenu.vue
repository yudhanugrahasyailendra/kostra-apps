<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

export interface RowMenuItem {
    key: string;
    label: string;
    icon: string;
    /** Tautan Inertia; tanpa `href` butir memancarkan `select`. */
    href?: string;
    /** `href` dibuka di tab baru sebagai tautan biasa (berkas, kuitansi PDF), bukan kunjungan Inertia. */
    external?: boolean;
    danger?: boolean;
}

// Menu aksi baris (tombol ⋯) di tabel. Butir disaring pemanggil dari izin `can` server;
// menyembunyikan butir hanya kerapian, otorisasi tetap di server.
const props = defineProps<{ items: RowMenuItem[]; label: string }>();
const emit = defineEmits<{ select: [key: string] }>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
// Menu dipasang ke <body> dengan posisi tetap supaya tidak terpotong wadah tabel yang bisa digulir.
const position = ref<Record<string, string>>({});
const button = ref<HTMLButtonElement | null>(null);
const menu = ref<HTMLElement | null>(null);

function focusItem(index: number): void {
    const items = menu.value?.querySelectorAll<HTMLElement>('[role="menuitem"]');
    if (!items || items.length === 0) return;
    items[(index + items.length) % items.length].focus();
}

function place(): void {
    const rect = button.value?.getBoundingClientRect();
    if (!rect) return;
    const estimated = props.items.length * 40 + 16;
    const below = window.innerHeight - rect.bottom;
    const right = `${Math.max(8, window.innerWidth - rect.right)}px`;
    position.value = below < estimated && rect.top > below ? { bottom: `${window.innerHeight - rect.top + 4}px`, right } : { top: `${rect.bottom + 4}px`, right };
}

async function toggle(): Promise<void> {
    open.value = !open.value;
    if (open.value) {
        place();
        await nextTick();
        focusItem(0);
    }
}

function close(returnFocus = false): void {
    open.value = false;
    if (returnFocus) button.value?.focus();
}

function choose(item: RowMenuItem): void {
    close();
    if (!item.href) emit('select', item.key);
}

function onMenuKeydown(event: KeyboardEvent): void {
    const items = Array.from(menu.value?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? []);
    const current = items.indexOf(document.activeElement as HTMLElement);
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        focusItem(current + 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        focusItem(current - 1);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        close(true);
    } else if (event.key === 'Tab') {
        close();
    }
}

function onPointerDown(event: PointerEvent): void {
    const target = event.target as Node;
    if (open.value && !root.value?.contains(target) && !menu.value?.contains(target)) close();
}

function onViewportChange(): void {
    if (open.value) close();
}

onMounted(() => {
    document.addEventListener('pointerdown', onPointerDown);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown);
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});

const itemClass = (item: RowMenuItem) =>
    item.danger ? 'text-status-jatuh-tempo-text hover:bg-status-jatuh-tempo-bg focus:bg-status-jatuh-tempo-bg' : 'text-text-primary hover:bg-bg-subtle focus:bg-bg-subtle';
</script>

<template>
    <div ref="root" class="relative inline-flex">
        <button
            ref="button"
            type="button"
            class="flex size-9 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-bg-subtle hover:text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-container"
            :aria-label="props.label"
            :title="props.label"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="toggle"
        >
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">more_vert</span>
        </button>
        <Teleport to="body">
        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-out"
            enter-from-class="opacity-0 motion-safe:scale-95"
            leave-active-class="motion-safe:transition motion-safe:duration-100 motion-safe:ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                ref="menu"
                role="menu"
                :aria-label="props.label"
                class="fixed z-[65] min-w-52 overflow-hidden rounded-lg border border-border-subtle bg-bg-surface py-1 text-left shadow-high"
                :style="position"
                @keydown="onMenuKeydown"
            >
                <template v-for="item in props.items" :key="item.key">
                    <a
                        v-if="item.href && item.external"
                        :href="item.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        role="menuitem"
                        tabindex="-1"
                        class="flex items-center gap-space-sm px-space-md py-2 text-body-md outline-none"
                        :class="itemClass(item)"
                        @click="choose(item)"
                    >
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ item.icon }}</span>{{ item.label }}
                    </a>
                    <Link
                        v-else-if="item.href"
                        :href="item.href"
                        role="menuitem"
                        tabindex="-1"
                        class="flex items-center gap-space-sm px-space-md py-2 text-body-md outline-none"
                        :class="itemClass(item)"
                        @click="choose(item)"
                    >
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ item.icon }}</span>{{ item.label }}
                    </Link>
                    <button
                        v-else
                        type="button"
                        role="menuitem"
                        tabindex="-1"
                        class="flex w-full items-center gap-space-sm px-space-md py-2 text-body-md outline-none"
                        :class="itemClass(item)"
                        @click="choose(item)"
                    >
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ item.icon }}</span>{{ item.label }}
                    </button>
                </template>
            </div>
        </Transition>
        </Teleport>
    </div>
</template>
