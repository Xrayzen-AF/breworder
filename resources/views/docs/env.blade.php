@extends('layouts.app')

@section('title', 'Dokumentasi .env')

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Dokumentasi Konfigurasi Lingkungan (.env)</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            File <code class="rounded bg-slate-100 px-1.5 py-0.5">.env</code> memuat seluruh konfigurasi per-lingkungan dan tidak ikut di-commit.
            Nilai dibaca lewat helper <code class="rounded bg-slate-100 px-1.5 py-0.5">env()</code> di file <code class="rounded bg-slate-100 px-1.5 py-0.5">config/</code>,
            lalu diakses di kode aplikasi melalui <code class="rounded bg-slate-100 px-1.5 py-0.5">config()</code> — bukan <code class="rounded bg-slate-100 px-1.5 py-0.5">env()</code> langsung.
        </p>

        @php
            $sections = [
                'Aplikasi' => [
                    'APP_NAME' => 'Nama aplikasi; dipakai di judul halaman, email, dan layout Blade.',
                    'APP_ENV' => 'Lingkungan aktif: local / staging / production. Memengaruhi error page & logging.',
                    'APP_KEY' => 'Kunci enkripsi 32-byte base64. Wajib; generate dengan `php artisan key:generate`.',
                    'APP_DEBUG' => 'true menampilkan halaman error detail — hanya untuk local, jangan pernah di production.',
                    'APP_URL' => 'URL dasar aplikasi untuk pembuatan link (url(), route(), mail).',
                    'APP_TIMEZONE' => 'Zona waktu default, contoh: Asia/Jakarta.',
                ],
                'Database' => [
                    'DB_CONNECTION' => 'Driver aktif: sqlite / mysql / mariadb / pgsql / sqlsrv.',
                    'DB_HOST / DB_PORT' => 'Alamat & port server database (127.0.0.1:3306 untuk MySQL lokal).',
                    'DB_DATABASE' => 'Nama database; untuk SQLite berisi path absolut file .sqlite.',
                    'DB_USERNAME / DB_PASSWORD' => 'Kredensial database — jangan di-commit, isi berbeda per lingkungan.',
                ],
                'Session & Cache' => [
                    'SESSION_DRIVER' => 'Penyimpanan session: file / cookie / database / redis. Default Laravel 12: database.',
                    'SESSION_LIFETIME' => 'Umur session dalam menit (default 120).',
                    'CACHE_STORE' => 'Store cache: database / file / redis. Dipakai healthcheck untuk uji baca-tulis.',
                    'QUEUE_CONNECTION' => 'Driver antrean: database / redis / sync.',
                ],
                'Healthcheck (kustom)' => [
                    'HEALTHCHECK_TOKEN' => 'Jika diisi, endpoint /health mewajibkan header X-Health-Token bernilai sama. Kosongkan untuk lokal.',
                ],
            ];
        @endphp

        <div class="mt-6 space-y-6">
            @foreach ($sections as $section => $vars)
                <section>
                    <h2 class="text-lg font-semibold text-slate-900">{{ $section }}</h2>
                    <div class="mt-2 overflow-hidden rounded-xl border border-slate-200">
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($vars as $key => $desc)
                                    <tr class="even:bg-slate-50">
                                        <td class="w-64 px-4 py-3 align-top font-mono text-xs font-semibold text-indigo-700">{{ $key }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $desc }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <strong>Tips:</strong> setelah mengubah .env, bersihkan cache konfigurasi dengan
            <code class="rounded bg-amber-100 px-1.5 py-0.5">php artisan config:clear</code>
            (dan <code class="rounded bg-amber-100 px-1.5 py-0.5">php artisan config:cache</code> saat deploy).
        </div>
    </div>
@endsection
