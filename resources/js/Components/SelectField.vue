<script setup lang="ts">
const model = defineModel<string | number>({ default: '' });

withDefaults(
    defineProps<{
        label: string;
        options: { value: string | number; label: string }[];
        placeholder?: string;
        error?: string;
        required?: boolean;
    }>(),
    { placeholder: undefined, error: undefined, required: false },
);

const id = `select-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <div class="flex flex-col gap-space-xs">
        <label :for="id" class="text-label-md text-text-primary">{{ label }}</label>
        <select
            :id="id"
            v-model="model"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            class="h-[42px] rounded-md border bg-bg-surface px-3 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
            :class="error ? 'border-error' : 'border-border-strong'"
        >
            <option v-if="placeholder !== undefined" value="">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="error" class="text-body-sm text-error">{{ error }}</p>
    </div>
</template>
