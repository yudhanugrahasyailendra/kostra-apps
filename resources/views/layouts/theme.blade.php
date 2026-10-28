{{-- Tema gelap: dipasang di <head> supaya kelas "dark" terpasang sebelum halaman digambar (tanpa kedip). Pilihan disimpan di localStorage; tanpa pilihan, mengikuti pengaturan sistem. --}}
<script>
    (function () {
        var root = document.documentElement;
        var saved = null;
        try { saved = localStorage.getItem('theme'); } catch (e) {}

        function apply(dark) {
            root.classList.toggle('dark', dark);
            document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
                button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            });
        }

        apply(saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);

        document.addEventListener('DOMContentLoaded', function () {
            apply(root.classList.contains('dark'));
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-theme-toggle]')) return;
            var dark = !root.classList.contains('dark');
            try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
            apply(dark);
        });
    })();
</script>
