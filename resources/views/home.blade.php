@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            Selamat datang di {{ config('app.name') }}
        </h1>
        <p class="mt-3 max-w-3xl text-slate-600">
            Proyek contoh yang mendokumentasikan tiga komponen inti: konfigurasi lingkungan
            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-sm">.env</code>,
            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-sm">HealthController</code> untuk uji koneksi &amp; healthcheck,
            serta master layout Blade pada
            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-sm">resources/views/layouts/app.blade.php</code>.
        </p>

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <a href="{{ route('dokumentasi-env') }}"
               class="group rounded-xl border border-slate-200 bg-slate-50 p-5 transition hover:border-indigo-300 hover:bg-indigo-50">
                <div class="text-2xl">⚙️</div>
                <h2 class="mt-2 font-semibold text-slate-900 group-hover:text-indigo-700">Dokumentasi .env</h2>
                <p class="mt-1 text-sm text-slate-600">Penjelasan tiap variabel lingkungan yang dipakai aplikasi.</p>
            </a>
            <a href="{{ route('uji-koneksi') }}"
               class="group rounded-xl border border-slate-200 bg-slate-50 p-5 transition hover:border-indigo-300 hover:bg-indigo-50">
                <div class="text-2xl">🔌</div>
                <h2 class="mt-2 font-semibold text-slate-900 group-hover:text-indigo-700">Uji Koneksi</h2>
                <p class="mt-1 text-sm text-slate-600">Cek database, cache, dan storage langsung dari browser.</p>
            </a>
            <a href="{{ route('health') }}"
               class="group rounded-xl border border-slate-200 bg-slate-50 p-5 transition hover:border-indigo-300 hover:bg-indigo-50">
                <div class="text-2xl">🩺</div>
                <h2 class="mt-2 font-semibold text-slate-900 group-hover:text-indigo-700">Healthcheck /health</h2>
                <p class="mt-1 text-sm text-slate-600">Endpoint JSON siap dipakai monitoring &amp; load balancer.</p>
            </a>
        </div>
    </div>
@endsection
