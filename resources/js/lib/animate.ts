import { onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue';

/**
 * Animasi angka dan grafik dashboard. Semua animasi mati bila pengguna memilih "kurangi gerakan"
 * di perangkatnya (prefers-reduced-motion): nilai akhir langsung tampil.
 */
export function prefersReducedMotion(): boolean {
    return typeof window === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);

/**
 * Angka yang bergerak halus dari nilai sebelumnya ke target (awal: dari 0 saat halaman dibuka).
 * Hasilnya pecahan; bulatkan saat menampilkan (mis. formatRupiah menerima bilangan bulat).
 */
export function useCountUp(target: () => number, { duration = 1000, delay = 0 } = {}): Ref<number> {
    const value = ref(prefersReducedMotion() ? target() : 0);
    let frame = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let settle: ReturnType<typeof setTimeout> | undefined;

    function stop(): void {
        cancelAnimationFrame(frame);
        clearTimeout(timer);
        clearTimeout(settle);
    }

    function run(to: number): void {
        stop();

        if (prefersReducedMotion()) {
            value.value = to;

            return;
        }

        const from = value.value;
        timer = setTimeout(() => {
            const start = performance.now();
            const step = (now: number) => {
                // Cap waktu frame bisa sedikit lebih awal dari `start`; dijepit 0..1 supaya angka tidak minus.
                const t = Math.min(1, Math.max(0, (now - start) / duration));
                value.value = t === 1 ? to : from + (to - from) * easeOutCubic(t);
                if (t < 1) {
                    frame = requestAnimationFrame(step);
                }
            };
            frame = requestAnimationFrame(step);
        }, delay);
        // Pengaman: bila frame animasi dijeda peramban (tab di latar), nilai akhir tetap dipasang tepat waktu.
        settle = setTimeout(() => {
            cancelAnimationFrame(frame);
            value.value = to;
        }, delay + duration + 50);
    }

    onMounted(() => run(target()));
    watch(target, (to) => run(to));
    onBeforeUnmount(stop);

    return value;
}

/**
 * Bernilai false saat komponen pertama kali digambar, lalu true pada frame berikutnya, supaya transisi CSS
 * (tinggi batang, panjang busur) berjalan dari nol. Langsung true bila gerakan dikurangi.
 */
export function useEntered(): Ref<boolean> {
    const entered = ref(prefersReducedMotion());

    onMounted(() => {
        if (!entered.value) {
            requestAnimationFrame(() => requestAnimationFrame(() => (entered.value = true)));
        }
    });

    return entered;
}
