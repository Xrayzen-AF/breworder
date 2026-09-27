@extends('layouts.app')

@section('title', 'Uji Koneksi')

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Uji Koneksi</h1>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $healthy ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                {{ $healthy ? 'Semua layanan terhubung' : 'Ada layanan bermasalah' }}
            </span>
        </div>
        <p class="mt-2 text-sm text-slate-600">
            Halaman ini menjalankan pemeriksaan nyata terhadap database, cache, dan storage setiap kali dimuat,
            kemudian menampilkan ringkasan konfigurasi <code class="rounded bg-slate-100 px-1.5 py-0.5">.env</code> yang sedang aktif.
        </p>

        <h2 class="mt-8 text-lg font-semibold text-slate-900">Status layanan</h2>
        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Layanan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($checks as $name => $check)
                        <tr>
                            <td class="px-4 py-3 font-semibold capitalize text-slate-900">{{ $name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $check['ok'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                    {{ $check['ok'] ? 'terhubung' : 'gagal' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                @foreach ($check['detail'] as $key => $value)
                                    <span class="mr-3"><span class="font-medium">{{ $key }}:</span> {{ $value }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h2 class="mt-8 text-lg font-semibold text-slate-900">Ringkasan konfigurasi .env aktif</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($envSummary as $key => $value)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="font-mono text-xs font-semibold text-indigo-700">{{ $key }}</div>
                    <div class="mt-1 font-mono text-sm text-slate-900">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
