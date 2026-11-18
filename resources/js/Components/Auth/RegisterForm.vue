<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import InsetField from '@/Components/InsetField.vue';

const form = useForm({
    kos_name: '',
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

// FND-01: daftar dengan email atau nomor WhatsApp. Satu metode tampil sekaligus; berganti metode
// mengosongkan kolom lain, supaya yang terkirim hanya metode yang dipilih.
type Method = 'email' | 'phone';
const method = ref<Method>('email');
const otherMethodLabel = computed(() => (method.value === 'email' ? 'Pakai nomor WhatsApp' : 'Pakai email'));

function switchMethod() {
    form[method.value] = '';
    form.clearErrors('email', 'phone');
    method.value = method.value === 'email' ? 'phone' : 'email';
}

function submit() {
    form.post('/daftar', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <div class="flex w-full flex-col items-center text-center">
        <h1 class="text-display-lg-mobile text-text-primary md:text-display-lg">Buat akun</h1>
        <p class="mt-space-sm text-body-md text-text-secondary">Mulai gratis: 1 properti, 10 kamar</p>

        <form class="mt-space-lg flex w-full flex-col gap-space-sm text-left" @submit.prevent="submit">
            <InsetField v-model="form.kos_name" label="Nama kos" icon="home_work" required :error="form.errors.kos_name" />
            <InsetField v-model="form.name" label="Nama Anda" icon="badge" autocomplete="name" required :error="form.errors.name" />
            <InsetField
                v-if="method === 'email'"
                key="email"
                v-model="form.email"
                label="Email"
                type="email"
                icon="mail"
                autocomplete="email"
                inputmode="email"
                required
                :error="form.errors.email"
            />
            <InsetField
                v-else
                key="phone"
                v-model="form.phone"
                label="Nomor WhatsApp (contoh 081234567890)"
                type="tel"
                icon="smartphone"
                autocomplete="tel"
                inputmode="tel"
                required
                :error="form.errors.phone"
            />
            <button type="button" class="-mt-1 self-end text-body-sm text-primary-container underline-offset-2 hover:underline" @click="switchMethod">
                {{ otherMethodLabel }}
            </button>
            <div class="grid gap-space-sm sm:grid-cols-2">
                <InsetField v-model="form.password" label="Kata sandi" placeholder="Min. 8 karakter" type="password" autocomplete="new-password" required :error="form.errors.password" />
                <InsetField v-model="form.password_confirmation" label="Ulangi sandi" type="password" autocomplete="new-password" required />
            </div>
            <Button type="submit" pill :disabled="form.processing" class="mx-auto mt-space-md min-w-40 uppercase tracking-wider shadow-mid">Daftar</Button>
        </form>
    </div>
</template>
