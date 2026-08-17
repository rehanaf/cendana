<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dokumen SOP - Cendana Solusindo</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">

    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white">
                    CS
                </span>
                <span class="text-lg font-bold tracking-tight">Cendana Solusindo</span>
            </a>
            <a href="{{ $adminUrl }}"
               class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Masuk ke Admin
            </a>
        </div>
    </header>

    {{-- Daftar SOP Publik --}}
    <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Dokumen SOP Publik</h1>
        <p class="mt-2 text-slate-500">
            Standar Operasional Prosedur yang dapat diakses oleh publik tanpa login.
        </p>

        @if ($sops->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-400">
                Belum ada dokumen SOP publik.
            </div>
        @else
            <div class="mt-8 space-y-4">
                @foreach ($sops as $sop)
                    <article class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-blue-300 hover:shadow-md sm:flex-row sm:items-center">
                        <div class="flex-1">
                            <h2 class="text-lg font-semibold text-slate-900">{{ $sop->title }}</h2>
                            @if ($sop->description)
                                <p class="mt-1 text-sm text-slate-500">{{ $sop->description }}</p>
                            @endif
                            <span class="mt-2 inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                Publik
                            </span>
                        </div>
                        <a href="{{ route('sop.view', $sop) }}" target="_blank"
                           class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/30 transition hover:bg-blue-700">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 12-3 3m0 0-3-3m3 3V2.25m1.5 12h3.75m3.75-6.75v1.5a1.125 1.125 0 0 0 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375v3.75a3.375 3.375 0 0 1-3.375 3.375H8.25a3.375 3.375 0 0 1-3.375-3.375V12"/>
                            </svg>
                            Lihat PDF
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </main>

    {{-- Footer --}}
    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-4xl px-4 py-8 text-center sm:px-6">
            <p class="text-sm text-slate-500">
                &copy; {{ now()->year }} Cendana Solusindo. Seluruh hak cipta dilindungi.
            </p>
        </div>
    </footer>

</body>
</html>
