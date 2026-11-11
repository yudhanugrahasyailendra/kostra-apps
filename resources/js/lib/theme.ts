import { readonly, ref } from 'vue';

export type ThemeMode = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'kostra-theme';
const modes: ThemeMode[] = ['light', 'dark', 'system'];

const mode = ref<ThemeMode>('system');
let started = false;

function stored(): ThemeMode {
    try {
        const value = localStorage.getItem(STORAGE_KEY);

        return modes.includes(value as ThemeMode) ? (value as ThemeMode) : 'system';
    } catch {
        return 'system';
    }
}

function apply(): void {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', mode.value === 'dark' || (mode.value === 'system' && prefersDark));
}

function start(): void {
    if (started || typeof window === 'undefined') {
        return;
    }

    started = true;
    mode.value = stored();
    apply();
    // Mode "sistem" mengikuti perubahan pengaturan komputer secara langsung.
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', apply);
}

export function useTheme() {
    start();

    function setMode(next: ThemeMode): void {
        mode.value = next;

        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Penyimpanan diblokir: tema tetap berlaku untuk sesi ini.
        }

        apply();
    }

    return { mode: readonly(mode), setMode };
}
