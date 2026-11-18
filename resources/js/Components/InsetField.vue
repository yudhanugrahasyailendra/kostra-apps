<script setup lang="ts">
import { computed, ref } from 'vue';

// Kolom isian berlabel di dalam kotak, dengan ikon di kanan (halaman daftar).
// Untuk type="password", ikon diganti tombol tampilkan/sembunyikan kata sandi.
const model = defineModel<string>({ default: '' });

const props = withDefaults(
    defineProps<{
        label: string;
        type?: string;
        icon?: string;
        error?: string;
        hint?: string;
        autocomplete?: string;
        inputmode?: 'text' | 'email' | 'tel' | 'numeric';
        required?: boolean;
        placeholder?: string;
    }>(),
    { type: 'text', icon: undefined, error: undefined, hint: undefined, autocomplete: undefined, inputmode: undefined, required: false, placeholder: undefined },
);

const id = `field-${Math.random().toString(36).slice(2, 9)}`;
const revealed = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type));
const describedBy = computed(() => (props.error ? `${id}-error` : props.hint ? `${id}-hint` : undefined));
</script>

<template>
    <div class="flex flex-col gap-space-xs">
        <div
            class="group flex items-center rounded-lg bg-bg-subtle ring-1 transition-[background-color,box-shadow] focus-within:bg-bg-surface focus-within:shadow-mid focus-within:ring-2"
            :class="error ? 'ring-error focus-within:ring-error' : 'ring-transparent focus-within:ring-primary-container'"
        >
            <div class="flex min-w-0 flex-1 flex-col px-space-md pb-2 pt-2.5">
                <label :for="id" class="text-label-sm text-text-secondary group-focus-within:text-primary-container">{{ label }}</label>
                <input
                    :id="id"
                    v-model="model"
                    :type="inputType"
                    :autocomplete="autocomplete"
                    :inputmode="inputmode"
                    :required="required"
                    :placeholder="placeholder"
                    :aria-invalid="error ? 'true' : undefined"
                    :aria-describedby="describedBy"
                    class="w-full bg-transparent text-title-md text-text-primary outline-none placeholder:font-normal placeholder:text-text-muted"
                />
            </div>
            <button
                v-if="isPassword"
                type="button"
                class="mr-space-sm flex size-9 shrink-0 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-bg-subtle hover:text-primary-container"
                :aria-label="revealed ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                :aria-pressed="revealed"
                @click="revealed = !revealed"
            >
                <span class="material-symbols-outlined" aria-hidden="true">{{ revealed ? 'visibility_off' : 'visibility' }}</span>
            </button>
            <span v-else-if="icon" class="material-symbols-outlined mr-space-md shrink-0 text-text-muted group-focus-within:text-primary-container" aria-hidden="true">{{
                icon
            }}</span>
        </div>
        <p v-if="error" :id="`${id}-error`" class="px-space-xs text-body-sm text-error">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="px-space-xs text-body-sm text-text-secondary">{{ hint }}</p>
    </div>
</template>
