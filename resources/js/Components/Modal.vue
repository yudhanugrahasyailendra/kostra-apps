<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps<{
    show: boolean;
    title: string;
}>();

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
        <div v-if="props.show" class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-space-md">
            <div class="absolute inset-0 bg-inverse-surface/50" aria-hidden="true" @click="emit('close')" />
            <div
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                class="relative max-h-[90vh] w-full overflow-y-auto rounded-t-lg bg-bg-surface shadow-high sm:max-w-lg sm:rounded-lg"
            >
                <header class="flex items-center justify-between border-b border-border-subtle px-space-lg py-space-md">
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
                <div class="p-space-lg"><slot /></div>
                <footer v-if="$slots.footer" class="flex justify-end gap-space-sm border-t border-border-subtle px-space-lg py-space-md">
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
