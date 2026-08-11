<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cendana Solusindo</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
        }
    </style>

    <script>
        // Terapkan tema sebelum halaman dirender agar tidak berkedip (flash).
        (function () {
            const saved = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (saved === 'dark' || (!saved && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="min-h-screen bg-white text-slate-800 antialiased transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100">

    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white">
                    CS
                </span>
                <span class="text-lg font-bold tracking-tight">Cendana Solusindo</span>
            </a>

            <button type="button" id="theme-toggle" aria-label="Ganti tema"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                {{-- Ikon matahari (mode gelap) --}}
                <svg id="icon-sun" class="hidden h-5 w-5 dark:block" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
                </svg>
                {{-- Ikon bulan (mode terang) --}}
                <svg id="icon-moon" class="h-5 w-5 dark:hidden" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/>
                </svg>
            </button>
        </div>
    </header>

    {{-- Hero --}}
    <section class="mx-auto max-w-6xl px-4 pt-16 pb-12 text-center sm:px-6 sm:pt-24">
        <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
            Sistem Manajemen Terpadu
        </span>
        <h1 class="mt-6 text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">
            Selamat Datang di
            <span class="bg-gradient-to-r from-blue-600 to-cyan-500 bg-clip-text text-transparent">
                Cendana Solusindo
            </span>
        </h1>
        <p class="mx-auto mt-4 max-w-2xl text-base text-slate-500 sm:text-lg dark:text-slate-400">
            Kelola operasional bisnis Anda mulai dari keuangan, penjualan, pembelian,
            hingga pelanggan dalam satu platform yang ringkas dan andal.
        </p>
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="{{ $adminUrl }}"
               class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/30 transition hover:bg-blue-700 sm:w-auto">
                Masuk ke Admin
            </a>
            <a href="#divisi"
               class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                Lihat Divisi
            </a>
        </div>
    </section>

    {{-- Fitur Divisi (ikon + nama, mengarah ke admin) --}}
    <section id="divisi" class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
        <div class="text-center">
            <h2 class="text-2xl font-bold sm:text-3xl">Divisi Perusahaan</h2>
            <p class="mx-auto mt-2 max-w-xl text-slate-500 dark:text-slate-400">
                Klik divisi untuk mengakses area kerja Anda di panel administrasi.
            </p>
        </div>

        <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($roleCards as $role)
                <a href="{{ $adminUrl }}"
                   class="group flex flex-col items-center justify-center gap-3 rounded-2xl border border-slate-200 bg-white p-6 text-center transition hover:-translate-y-1 hover:border-blue-300 hover:shadow-lg hover:shadow-blue-600/10 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-700">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 transition group-hover:bg-blue-600 group-hover:text-white dark:bg-slate-800 dark:text-blue-400 dark:group-hover:bg-blue-600 dark:group-hover:text-white">
                        <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                             stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $role['icon'] }}"/>
                        </svg>
                    </span>
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {{ $role['name'] }}
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950">
        <div class="mx-auto max-w-6xl px-4 py-8 text-center sm:px-6">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                &copy; {{ now()->year }} Cendana Solusindo. Seluruh hak cipta dilindungi.
            </p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('theme-toggle');
            toggle.addEventListener('click', function () {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            });
        });
    </script>
</body>
</html>