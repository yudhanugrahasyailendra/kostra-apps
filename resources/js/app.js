import './bootstrap';

// Pembuka dan penutup modal berbasis <dialog> native (tanpa pustaka tambahan).
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        document.getElementById(opener.dataset.modalOpen)?.showModal();
        return;
    }

    const closer = event.target.closest('[data-modal-close]');
    if (closer) {
        closer.closest('dialog')?.close();
    }
});

// Menu dropdown berbasis <details data-dropdown>: tutup saat klik di luar atau tekan Escape.
document.addEventListener('click', (event) => {
    document.querySelectorAll('details[data-dropdown][open]').forEach((menu) => {
        if (!menu.contains(event.target)) {
            menu.open = false;
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    document.querySelectorAll('details[data-dropdown][open]').forEach((menu) => {
        menu.open = false;
        menu.querySelector('summary')?.focus();
    });
});
