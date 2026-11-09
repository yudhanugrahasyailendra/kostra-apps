<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';

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
        <div v-if="props.show" class="fixed inset-0 z-[60]">
            <div class="absolute inset-0 bg-inverse-surface/50" aria-hidden="true" @click="emit('close')" />
            <aside
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                class="absolute inset-y-0 flex w-full max-w-sm flex-col bg-bg-surface shadow-high"
                :class="side === 'left' ? 'left-0' : 'right-0'"
            >
                <header class="flex h-16 items-center justify-between border-b border-border-subtle px-space-md">
                    <h2 class="text-headline-sm text-text-primary">{{ title }}</h2>
                    <button
                        type="button"
                        class="rounded-md p-space-xs text-text-secondary hover:bg-bg-subtle"
                        aria-label="Tutup"
                        @click="emit('close')"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">close</span>
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto"><slot /></div>
            </aside>
        </div>
    </Teleport>
</template>
