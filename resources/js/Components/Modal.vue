<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

// Formulir tambah/ubah/hapus: panel mengambang di kanan yang meluncur masuk (gaya desain acuan GIF),
// latar diredupkan dan sedikit diburamkan. Di ponsel muncul sebagai lembar dari bawah.
// API tetap sama (show, title, slot default, slot footer), jadi halaman pemakai tidak berubah.
const props = defineProps<{
    show: boolean;
    title: string;
}>();

const emit = defineEmits<{ close: [] }>();

const panel = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;

function onKeydown(event: KeyboardEvent) {
    if (props.show && event.key === 'Escape' && !event.defaultPrevented) {
        emit('close');
    }
}

watch(
    () => props.show,
    async (show) => {
        if (typeof document === 'undefined') return;

        if (show) {
            returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            // Layar lebar: fokus ke isian pertama. Ponsel: fokus ke panel saja supaya papan ketik tidak langsung muncul.
            const wide = window.matchMedia('(min-width: 640px)').matches;
            const first = wide
                ? panel.value?.querySelector<HTMLElement>('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])')
                : null;
            (first ?? panel.value)?.focus({ preventScroll: true });
        } else {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', onKeydown);
            returnFocus?.focus({ preventScroll: true });
            returnFocus = null;
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="motion-safe:transition-opacity motion-safe:duration-200 motion-safe:ease-out"
            enter-from-class="opacity-0"
            leave-active-class="motion-safe:transition-opacity motion-safe:duration-150 motion-safe:ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="props.show" class="fixed inset-0 z-[60] bg-inverse-surface/50 backdrop-blur-[2px]" aria-hidden="true" @click="emit('close')" />
        </Transition>

        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-300 motion-safe:ease-[cubic-bezier(0.22,1,0.36,1)]"
            enter-from-class="opacity-0 motion-safe:translate-y-8 sm:motion-safe:translate-x-10 sm:motion-safe:translate-y-0"
            leave-active-class="motion-safe:transition motion-safe:duration-200 motion-safe:ease-in"
            leave-to-class="opacity-0 motion-safe:translate-y-8 sm:motion-safe:translate-x-10 sm:motion-safe:translate-y-0"
        >
            <section
                v-if="props.show"
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                tabindex="-1"
                class="fixed inset-x-0 bottom-0 z-[61] flex max-h-[92dvh] flex-col overflow-hidden rounded-t-xl bg-bg-surface shadow-high outline-none sm:inset-x-auto sm:bottom-auto sm:right-space-md sm:top-space-md sm:max-h-[calc(100dvh-2rem)] sm:w-[28rem] sm:rounded-xl"
            >
                <span class="mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-border-strong sm:hidden" aria-hidden="true" />
                <header class="flex shrink-0 items-center gap-space-sm border-b border-border-subtle px-space-lg py-space-md">
                    <h2 class="min-w-0 flex-1 truncate text-title-lg font-semibold text-text-primary">{{ title }}</h2>
                    <button
                        type="button"
                        class="flex size-9 shrink-0 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-bg-subtle hover:text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-container"
                        aria-label="Tutup"
                        @click="emit('close')"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">close</span>
                    </button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-space-lg"><slot /></div>
                <footer v-if="$slots.footer" class="flex shrink-0 justify-end gap-space-sm border-t border-border-subtle bg-bg-surface px-space-lg py-space-md">
                    <slot name="footer" />
                </footer>
            </section>
        </Transition>
    </Teleport>
</template>
