<script setup lang="ts">
// `soft`: kartu dashboard tanpa garis pemisah header, sudut lebih bulat, dan bantalan lebih lega.
withDefaults(
    defineProps<{
        title?: string;
        soft?: boolean;
    }>(),
    { title: undefined, soft: false },
);
</script>

<template>
    <section
        v-if="soft"
        class="flex flex-col rounded-xl bg-bg-surface p-space-lg shadow-low ring-1 ring-primary-container/[0.06] transition-shadow hover:shadow-mid dark:bg-surface-container-high"
    >
        <header v-if="title || $slots.actions" class="mb-space-md flex items-center justify-between gap-space-md">
            <h2 class="text-title-lg font-medium text-text-primary">{{ title }}</h2>
            <slot name="actions" />
        </header>
        <slot />
    </section>
    <section v-else class="rounded-lg border border-border-subtle bg-bg-surface shadow-low">
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
