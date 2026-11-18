<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

export interface NotificationItem {
    key: string;
    icon: string;
    title: string;
    description: string;
    href: string;
    /** Jumlah hal yang menunggu; dijumlahkan untuk angka di lencana lonceng. */
    count: number;
    tone: 'urgent' | 'attention' | 'info';
}

// Lonceng notifikasi di kepala halaman. Isinya ringkasan hal yang perlu ditindaklanjuti,
// dirangkai oleh halaman dari props yang sudah ada (tidak ada query terpisah).
const props = defineProps<{ items: NotificationItem[] }>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const button = ref<HTMLButtonElement | null>(null);

const total = computed(() => props.items.reduce((sum, item) => sum + item.count, 0));
const badge = computed(() => (total.value > 9 ? '9+' : String(total.value)));
const buttonLabel = computed(() => (total.value > 0 ? `Notifikasi, ${total.value} perlu ditindaklanjuti` : 'Notifikasi, tidak ada yang baru'));

const toneClass: Record<NotificationItem['tone'], string> = {
    urgent: 'bg-status-jatuh-tempo-bg text-status-jatuh-tempo-text',
    attention: 'bg-status-menunggu-bg text-status-menunggu-text',
    info: 'bg-bg-subtle text-primary-container',
};

function close(returnFocus = false): void {
    open.value = false;
    if (returnFocus) {
        button.value?.focus();
    }
}

function onPointerDown(event: PointerEvent): void {
    if (open.value && root.value && !root.value.contains(event.target as Node)) {
        close();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (open.value && event.key === 'Escape') {
        close(true);
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            ref="button"
            type="button"
            class="relative flex size-10 items-center justify-center rounded-full bg-bg-subtle text-primary-container transition-colors hover:bg-primary-container hover:text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-container lg:bg-bg-surface"
            :aria-label="buttonLabel"
            :title="buttonLabel"
            aria-haspopup="true"
            :aria-expanded="open"
            aria-controls="owner-notifications"
            @click="open = !open"
        >
            <span class="material-symbols-outlined text-[20px]" :class="{ 'icon-fill': open }" aria-hidden="true">notifications</span>
            <span
                v-if="total > 0"
                class="absolute -right-0.5 -top-0.5 flex h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full border-2 border-bg-surface bg-error px-1 text-[10px] font-bold leading-none text-on-error"
                aria-hidden="true"
                >{{ badge }}</span
            >
        </button>

        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-out"
            enter-from-class="opacity-0 motion-safe:-translate-y-1"
            leave-active-class="motion-safe:transition motion-safe:duration-100 motion-safe:ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                id="owner-notifications"
                class="fixed inset-x-margin top-[4.5rem] z-50 overflow-hidden rounded-xl border border-border-subtle bg-bg-surface shadow-high sm:absolute sm:inset-x-auto sm:right-0 sm:top-[calc(100%+0.75rem)] sm:w-96"
                role="region"
                aria-label="Notifikasi"
            >
                <div class="flex items-center justify-between gap-space-sm border-b border-border-subtle px-space-md py-space-md">
                    <h2 class="text-title-lg font-medium text-text-primary">Notifikasi</h2>
                    <span v-if="total > 0" class="rounded-full bg-bg-subtle px-space-sm py-0.5 text-label-md text-text-secondary">{{ total }} perlu ditindaklanjuti</span>
                </div>
                <div v-if="items.length === 0" class="flex flex-col items-center gap-space-sm px-space-md py-space-xl text-center">
                    <span class="material-symbols-outlined icon-fill text-[32px] text-primary-container" aria-hidden="true">notifications_off</span>
                    <p class="text-body-md text-text-secondary">Semua beres. Tidak ada yang perlu ditindaklanjuti.</p>
                </div>
                <ul v-else class="max-h-[min(24rem,calc(100vh-7rem))] divide-y divide-border-subtle overflow-y-auto">
                    <li v-for="item in items" :key="item.key">
                        <Link :href="item.href" class="flex items-start gap-space-sm px-space-md py-space-sm transition-colors hover:bg-bg-subtle" @click="close()">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full" :class="toneClass[item.tone]" aria-hidden="true">
                                <span class="material-symbols-outlined icon-fill text-[18px]">{{ item.icon }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-body-md font-medium text-text-primary">{{ item.title }}</span>
                                <span class="block text-body-sm text-text-muted">{{ item.description }}</span>
                            </span>
                            <span class="material-symbols-outlined self-center text-[18px] text-text-muted" aria-hidden="true">chevron_right</span>
                        </Link>
                    </li>
                </ul>
            </div>
        </Transition>
    </div>
</template>
