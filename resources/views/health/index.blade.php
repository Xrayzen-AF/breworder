@extends('layouts.app')

@section('title', 'Healthcheck')

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Healthcheck</h1>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $healthy ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                {{ $payload['status'] }}
            </span>
            <code class="ml-auto rounded bg-slate-100 px-2 py-1 text-xs text-slate-500">GET {{ url('health') }}</code>
        </div>
        <p class="mt-2 text-sm text-slate-600">
            Respons JSON dari endpoint ini cocok untuk uptime monitor. Versi JSON dapat diambil dengan
            <code class="rounded bg-slate-100 px-1.5 py-0.5">curl -H "Accept: application/json" {{ url('health') }}</code>.
        </p>

        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
            @foreach (['name' => 'Aplikasi', 'env' => 'Lingkungan', 'laravel' => 'Laravel', 'php' => 'PHP'] as $key => $label)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $payload['app'][$key] }}</dd>
                </div>
            @endforeach
        </dl>

        <h2 class="mt-8 text-lg font-semibold text-slate-900">Hasil pemeriksaan</h2>
        <div class="mt-3 space-y-3">
            @foreach ($checks as $name => $check)
                <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 p-4">
                    <span class="grid h-8 w-8 place-items-center rounded-full text-sm font-bold text-white {{ $check['ok'] ? 'bg-emerald-500' : 'bg-rose-500' }}">
                        {{ $check['ok'] ? '✓' : '✕' }}
                    </span>
                    <span class="font-semibold capitalize text-slate-900">{{ $name }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $check['ok'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                        {{ $check['ok'] ? 'ok' : 'fail' }}
                    </span>
                    <div class="w-full text-xs text-slate-600">
                        @foreach ($check['detail'] as $key => $value)
                            <span class="mr-4"><span class="font-medium">{{ $key }}:</span> {{ $value }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
