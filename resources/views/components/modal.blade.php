@props(['id', 'title'])

<dialog id="{{ $id }}" {{ $attributes->merge(['class' => 'panel shadow-lg w-full max-w-lg p-0 m-auto backdrop:bg-inverse-surface/40 backdrop:backdrop-blur-sm']) }}>
    <div class="p-space-md flex items-center justify-between">
        <h2 class="panel-title">{{ $title }}</h2>
        <button type="button" data-modal-close class="p-1.5 rounded-lg text-secondary hover:bg-surface-container transition-colors" aria-label="Tutup">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>
    <div class="px-space-md pb-space-md">{{ $slot }}</div>
</dialog>
