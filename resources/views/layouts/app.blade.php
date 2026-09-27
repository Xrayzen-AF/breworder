<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS via CDN: cukup untuk pengembangan; gunakan build Vite untuk produksi -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
    <!-- ===== Navbar ===== -->
    <header class="border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold text-slate-900">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-sm font-bold text-white">LD</span>
                <span>{{ config('app.name') }}</span>
            </a>

            <div class="flex items-center gap-1 text-sm">
                <a href="{{ route('home') }}"
                   class="rounded-lg px-3 py-2 font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    Beranda
                </a>
                <a href="{{ route('uji-koneksi') }}"
                   class="rounded-lg px-3 py-2 font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    Uji Koneksi
                </a>
                <a href="{{ route('dokumentasi-env') }}"
                   class="rounded-lg px-3 py-2 font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    Dokumentasi .env
                </a>
                <a href="{{ route('health') }}"
                   class="ml-2 rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                    /health
                </a>
            </div>
        </nav>
    </header>

    <!-- ===== Banner status aplikasi ===== -->
    <div class="border-b border-emerald-100 bg-emerald-50">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-xs text-emerald-800">
            <span class="font-semibold">{{ config('app.name') }}</span>
            <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-emerald-200">env: {{ app()->environment() }}</span>
            <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-emerald-200">Laravel {{ app()->version() }}</span>
            <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-emerald-200">PHP {{ PHP_VERSION }}</span>
            <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-emerald-200">DB: {{ config('database.default') }}</span>
            <span class="ml-auto">{{ now()->translatedFormat('d F Y, H:i') }}</span>
        </div>
    </div>

    <!-- ===== Konten utama ===== -->
    <main class="mx-auto max-w-6xl px-4 py-8">
        @yield('content')
    </main>

    <!-- ===== Footer ===== -->
    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }} — Contoh dokumentasi konfigurasi lingkungan &amp; healthcheck.</span>
            <span>APP_URL: {{ config('app.url') }}</span>
        </div>
    </footer>
</body>
</html>
