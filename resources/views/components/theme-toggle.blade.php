{{-- Tombol ganti tema terang/gelap. Perilakunya ada di layouts/theme.blade.php. Dua ikon menempati sel grid yang sama dan bertukar dengan putar + pudar. --}}
<button type="button" data-theme-toggle aria-pressed="false" aria-label="Mode gelap" title="Mode gelap"
    {{ $attributes->class('grid place-items-center self-center w-10 h-10 shrink-0 p-0 leading-none rounded-xl text-secondary hover:bg-surface-container motion-safe:transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 motion-safe:active:scale-90 motion-safe:transition-transform') }}>
    <span class="material-symbols-outlined col-start-1 row-start-1 block leading-none motion-safe:transition-all motion-safe:duration-300 motion-safe:ease-out rotate-0 scale-100 opacity-100 dark:-rotate-90 dark:scale-50 dark:opacity-0" aria-hidden="true">dark_mode</span>
    <span class="material-symbols-outlined col-start-1 row-start-1 block leading-none motion-safe:transition-all motion-safe:duration-300 motion-safe:ease-out rotate-90 scale-50 opacity-0 dark:rotate-0 dark:scale-100 dark:opacity-100" aria-hidden="true">light_mode</span>
</button>
