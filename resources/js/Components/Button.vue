<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'accent' | 'ghost';
        type?: 'button' | 'submit' | 'reset';
        icon?: string;
        disabled?: boolean;
    }>(),
    { variant: 'primary', type: 'button', icon: undefined, disabled: false },
);

const variants = {
    primary: 'bg-primary-container text-on-primary hover:bg-primary shadow-low',
    secondary: 'bg-bg-surface text-primary-container border border-primary-container hover:bg-bg-subtle',
    accent: 'bg-secondary-container text-on-secondary-container hover:bg-secondary-fixed',
    ghost: 'bg-transparent text-text-secondary hover:bg-bg-subtle',
};
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex min-h-11 items-center justify-center gap-space-xs rounded-md px-space-md py-2.5 text-title-md transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-container disabled:cursor-not-allowed disabled:opacity-50 motion-safe:transition-transform motion-safe:active:scale-[0.98] lg:min-h-10"
        :class="variants[variant]"
    >
        <span v-if="icon" class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ icon }}</span>
        <slot />
    </button>
</template>
