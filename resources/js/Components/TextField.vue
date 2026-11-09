<script setup lang="ts">
const model = defineModel<string>({ default: '' });

withDefaults(
    defineProps<{
        label: string;
        type?: string;
        error?: string;
        hint?: string;
        autocomplete?: string;
        required?: boolean;
    }>(),
    { type: 'text', error: undefined, hint: undefined, autocomplete: undefined, required: false },
);

const id = `field-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <div class="flex flex-col gap-space-xs">
        <label :for="id" class="text-label-md text-text-primary">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            :type="type"
            :autocomplete="autocomplete"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined"
            class="h-[42px] rounded-md border bg-bg-surface px-3 text-body-md text-text-primary focus:border-primary-container focus:outline-none focus:ring-[3px] focus:ring-primary-container/15"
            :class="error ? 'border-error' : 'border-border-strong'"
        />
        <p v-if="error" :id="`${id}-error`" class="text-body-sm text-error">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="text-body-sm text-text-secondary">{{ hint }}</p>
    </div>
</template>
