<script setup lang="ts">
import { computed } from 'vue';
import { useTheme, type ThemeMode } from '@/lib/theme';

// Pilihan tema: terang, gelap, atau ikuti pengaturan komputer. Pilihan disimpan di peramban.
// Satu tombol bulat yang menampilkan mode aktif dan berganti ke mode berikutnya tiap diklik.
// `round`: gaya kepala halaman pemilik (menyatu dengan bilah atas). Tanpa `round`: tombol mengambang
// bergaris tepi untuk halaman di atas latar kanvas (masuk/daftar, portal, admin).
const { mode, setMode } = useTheme();
const props = withDefaults(defineProps<{ round?: boolean }>(), { round: false });

const options: { value: ThemeMode; label: string; icon: string }[] = [
    { value: 'light', label: 'Terang', icon: 'light_mode' },
    { value: 'dark', label: 'Gelap', icon: 'dark_mode' },
    { value: 'system', label: 'Sistem', icon: 'desktop_windows' },
];

const currentIndex = computed(() => Math.max(0, options.findIndex((option) => option.value === mode.value)));
const current = computed(() => options[currentIndex.value]);
const next = computed(() => options[(currentIndex.value + 1) % options.length]);
const cycleLabel = computed(() => `Tema: ${current.value.label}. Klik untuk ganti ke ${next.value.label}.`);

const variant = computed(() =>
    props.round ? 'bg-bg-subtle lg:bg-bg-surface' : 'border border-border-subtle bg-bg-surface/80 shadow-low backdrop-blur-sm hover:border-primary-container',
);
</script>

<template>
    <button
        type="button"
        class="group flex size-10 items-center justify-center overflow-hidden rounded-full text-primary-container transition-colors hover:bg-primary-container hover:text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-container"
        :class="variant"
        :aria-label="cycleLabel"
        :title="cycleLabel"
        @click="setMode(next.value)"
    >
        <!-- Ikon berputar masuk saat mode berganti. -->
        <Transition
            mode="out-in"
            enter-active-class="motion-safe:transition motion-safe:duration-200 motion-safe:ease-out"
            enter-from-class="motion-safe:-rotate-90 motion-safe:scale-50 opacity-0"
            leave-active-class="motion-safe:transition motion-safe:duration-150 motion-safe:ease-in"
            leave-to-class="motion-safe:rotate-90 motion-safe:scale-50 opacity-0"
        >
            <span :key="current.value" class="material-symbols-outlined icon-fill text-[20px]" aria-hidden="true">{{ current.icon }}</span>
        </Transition>
    </button>
</template>
