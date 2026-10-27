@props(['id', 'title'])

<dialog id="{{ $id }}" {{ $attributes->merge(['class' => 'panel w-full max-w-lg p-0 m-auto backdrop:bg-inverse-surface/40 backdrop:backdrop-blur-sm']) }}>
    <div class="p-space-md flex items-center justify-between border-b border-outline-variant/40">
        <h2 class="font-headline-md text-headline-md">{{ $title }}</h2>
        <button type="button" data-modal-close class="p-1 rounded-lg text-secondary hover:bg-surface-container" aria-label="Tutup">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>
    <div class="p-space-md">{{ $slot }}</div>
</dialog>
