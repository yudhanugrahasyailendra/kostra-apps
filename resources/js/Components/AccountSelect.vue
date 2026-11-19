<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { PaymentAccountData } from '@/types';

// Pilihan rekening tujuan berbentuk daftar berikon (seperti "Select Account" pada desain acuan).
// Nilai '' berarti tanpa rekening (uang diterima tunai).
const props = defineProps<{ accounts: PaymentAccountData[]; id: string; error?: string }>();
const model = defineModel<number | ''>({ required: true });

interface Option {
    value: number | '';
    label: string;
    detail: string | null;
    icon: string;
    tone: string;
    isDefault: boolean;
}

const typeIcon: Record<string, { icon: string; tone: string }> = {
    bank: { icon: 'account_balance', tone: 'bg-primary-container/10 text-primary-container' },
    qris: { icon: 'qr_code_2', tone: 'bg-tertiary-container/10 text-tertiary-container' },
    ewallet: { icon: 'account_balance_wallet', tone: 'bg-secondary-container/20 text-on-secondary-container' },
};

const options = computed<Option[]>(() => [
    ...props.accounts.map((account) => ({
        value: account.id,
        label: account.label,
        detail: account.account_name,
        isDefault: account.is_default,
        ...(typeIcon[account.type] ?? typeIcon.bank),
    })),
    { value: '', label: 'Tanpa rekening (tunai)', detail: 'Uang diterima langsung', icon: 'payments', tone: 'bg-status-lunas-bg text-status-lunas-text', isDefault: false },
]);

const selected = computed(() => options.value.find((option) => option.value === model.value) ?? options.value[options.value.length - 1]);

const open = ref(false);
const active = ref(0);
const root = ref<HTMLElement | null>(null);
const list = ref<HTMLElement | null>(null);
const button = ref<HTMLButtonElement | null>(null);

async function toggle(): Promise<void> {
    open.value = !open.value;
    if (open.value) {
        active.value = Math.max(0, options.value.indexOf(selected.value));
        await nextTick();
        list.value?.focus();
    }
}

function choose(option: Option): void {
    model.value = option.value;
    open.value = false;
    button.value?.focus();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = (active.value + (event.key === 'ArrowDown' ? 1 : -1) + options.value.length) % options.value.length;
    } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        choose(options.value[active.value]);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        open.value = false;
        button.value?.focus();
    } else if (event.key === 'Tab') {
        open.value = false;
    }
}

function onPointerDown(event: PointerEvent): void {
    if (open.value && root.value && !root.value.contains(event.target as Node)) open.value = false;
}

onMounted(() => document.addEventListener('pointerdown', onPointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDown));
</script>

<template>
    <div ref="root" class="relative">
        <button
            :id="id"
            ref="button"
            type="button"
            class="flex w-full items-center gap-space-sm rounded-lg border bg-bg-surface px-space-sm py-2 text-left transition-colors hover:border-primary-container focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-container"
            :class="error ? 'border-status-jatuh-tempo-border' : open ? 'border-primary-container' : 'border-border-strong'"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-controls="`${id}-list`"
            @click="toggle"
        >
            <span class="flex size-7 shrink-0 items-center justify-center rounded-md" :class="selected.tone" aria-hidden="true">
                <span class="material-symbols-outlined text-[16px]">{{ selected.icon }}</span>
            </span>
            <span class="min-w-0 flex-1 truncate text-body-md text-text-primary">{{ selected.label }}</span>
            <span v-if="selected.isDefault" class="shrink-0 rounded-sm border border-status-lunas-border bg-status-lunas-bg px-1 text-label-sm uppercase text-status-lunas-text">Utama</span>
            <span class="material-symbols-outlined shrink-0 text-[20px] text-text-muted motion-safe:transition-transform" :class="{ 'rotate-180': open }" aria-hidden="true">expand_more</span>
        </button>
        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-out"
            enter-from-class="opacity-0 motion-safe:-translate-y-1"
            leave-active-class="motion-safe:transition motion-safe:duration-100 motion-safe:ease-in"
            leave-to-class="opacity-0"
        >
            <ul
                v-if="open"
                :id="`${id}-list`"
                ref="list"
                role="listbox"
                tabindex="-1"
                :aria-activedescendant="`${id}-opt-${active}`"
                aria-label="Rekening tujuan"
                class="absolute inset-x-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-lg border border-border-subtle bg-bg-surface py-1 shadow-high outline-none"
                @keydown="onKeydown"
            >
                <li
                    v-for="(option, index) in options"
                    :id="`${id}-opt-${index}`"
                    :key="String(option.value)"
                    role="option"
                    :aria-selected="option.value === model"
                    class="mx-1 flex cursor-pointer items-center gap-space-sm rounded-md px-space-sm py-1.5"
                    :class="index === active ? 'bg-bg-subtle' : ''"
                    @mousemove="active = index"
                    @mousedown.prevent
                    @click="choose(option)"
                >
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-md" :class="option.tone" aria-hidden="true">
                        <span class="material-symbols-outlined text-[16px]">{{ option.icon }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-body-md text-text-primary">{{ option.label }}</span>
                        <span v-if="option.detail" class="block truncate text-body-sm text-text-muted">{{ option.detail }}</span>
                    </span>
                    <span v-if="option.isDefault" class="shrink-0 rounded-sm border border-status-lunas-border bg-status-lunas-bg px-1 text-label-sm uppercase text-status-lunas-text">Utama</span>
                    <span v-if="option.value === model" class="material-symbols-outlined shrink-0 text-[18px] text-primary-container" aria-hidden="true">check</span>
                </li>
            </ul>
        </Transition>
        <p v-if="error" class="mt-1 text-body-sm text-status-jatuh-tempo-text">{{ error }}</p>
    </div>
</template>
