<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';

// Laci samping (menu ponsel). Meluncur masuk dari sisinya dengan latar diredupkan, seperti panel formulir.
const props = withDefaults(
    defineProps<{
        show: boolean;
        title: string;
        side?: 'left' | 'right';
    }>(),
    { side: 'right' },
);

const emit = defineEmits<{ close: [] }>();

function onKeydown(event: KeyboardEvent) {
    if (props.show && event.key === 'Escape') {
        emit('close');
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="motion-safe:transition-opacity motion-safe:duration-200"
            enter-from-class="opacity-0"
            leave-active-class="motion-safe:transition-opacity motion-safe:duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="props.show" class="fixed inset-0 z-[60] bg-inverse-surface/50 backdrop-blur-[2px]" aria-hidden="true" @click="emit('close')" />
        </Transition>
        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-300 motion-safe:ease-[cubic-bezier(0.22,1,0.36,1)]"
            :enter-from-class="side === 'left' ? 'opacity-0 motion-safe:-translate-x-8' : 'opacity-0 motion-safe:translate-x-8'"
            leave-active-class="motion-safe:transition motion-safe:duration-200 motion-safe:ease-in"
            :leave-to-class="side === 'left' ? 'opacity-0 motion-safe:-translate-x-8' : 'opacity-0 motion-safe:translate-x-8'"
        >
            <aside
                v-if="props.show"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                class="fixed inset-y-space-sm z-[61] flex w-[calc(100%-1rem)] max-w-sm flex-col overflow-hidden rounded-xl bg-bg-surface shadow-high"
                :class="side === 'left' ? 'left-space-sm' : 'right-space-sm'"
            >
                <header class="flex h-14 shrink-0 items-center justify-between border-b border-border-subtle px-space-md">
                    <h2 class="text-title-lg font-semibold text-text-primary">{{ title }}</h2>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-full text-text-secondary hover:bg-bg-subtle"
                        aria-label="Tutup"
                        @click="emit('close')"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">close</span>
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto"><slot /></div>
            </aside>
        </Transition>
    </Teleport>
</template>
