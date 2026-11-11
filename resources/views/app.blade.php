<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="color-scheme" content="light dark">
        <!-- Pasang tema sebelum render pertama supaya tidak berkedip. Logika sama dengan resources/js/lib/theme.ts -->
        <script>
            (function () {
                var mode = 'system';
                try { mode = localStorage.getItem('kostra-theme') || 'system'; } catch (e) {}
                var dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        <title inertia>{{ config('app.name', 'Kostra') }}</title>

        <!-- Font: Plus Jakarta Sans dan Material Symbols (DESIGN.md) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0..1,0&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/js/app.ts', 'resources/css/app.css'])
        @inertiaHead
    </head>
    <body class="bg-bg-canvas font-sans text-on-surface antialiased">
        @inertia
    </body>
</html>
