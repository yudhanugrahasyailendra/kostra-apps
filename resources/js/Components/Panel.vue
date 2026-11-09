<script setup lang="ts">
defineProps<{
    title?: string;
}>();
</script>

<template>
    <section class="rounded-lg border border-border-subtle bg-bg-surface shadow-low">
        <header
            v-if="title || $slots.actions"
            class="flex items-center justify-between gap-space-md border-b border-border-subtle px-space-md py-space-md"
        >
            <h2 class="text-title-lg text-text-primary">{{ title }}</h2>
            <slot name="actions" />
        </header>
        <div class="p-space-md">
            <slot />
        </div>
    </section>
</template>
