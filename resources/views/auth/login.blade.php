@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4" style="background-color:#1E1511;">
    <div class="w-full max-w-[460px] bg-[#271D19] border border-[#3D322E] rounded-lg px-8 py-10 sm:px-12 sm:py-12" style="box-shadow:0 4px 20px -2px rgba(28,25,23,0.06);">

        {{-- Logo --}}
        <div class="flex justify-center mb-8">
            <img src="{{ asset('images/logo-tejas.png') }}" alt="Tejas Coffee"
                 class="h-16 w-16 object-contain" width="64" height="64">
        </div>

        <div class="text-center mb-8">
            <p class="font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] text-[#E0A96D] mb-3">No. 01 — Signature</p>
            <h1 class="font-serif text-[22px] tracking-[0.2em] uppercase mb-4 text-[#F1DFD8]">
                Tejas Coffee
            </h1>
            <h2 class="font-serif text-xl mb-3 text-[#F1DFD8]">
                Masuk Akun
            </h2>
            <p class="text-[13px] text-[#B8A8A1] leading-relaxed">
                Masukkan email dan password<br class="hidden sm:block" /> Anda untuk masuk.
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-[#4A2A28] bg-[#4A2A28]/30 p-4">
                <div class="flex items-start gap-2">
                    <i data-lucide="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-[#F87171]"></i>
                    <ul class="list-inside list-disc space-y-1 text-[13px] text-[#FCA5A5]">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            {{-- Email / No. HP --}}
            <div>
                <label for="email" class="block font-grotesk text-[10px] font-semibold text-[#B8A8A1] uppercase tracking-[0.12em] mb-2">
                    Email / No. Handphone
                </label>
                <input type="text" id="email" name="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" inputmode="email" placeholder="nama@domain.com / 0812..."
                        class="w-full px-4 py-3 border border-[#3D322E] bg-[#1E1511] rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-[#D97706] focus:border-[#D97706] placeholder-[#82746E] transition-colors">
            </div>

            {{-- Password --}}
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label for="password" class="block font-grotesk text-[10px] font-semibold text-[#B8A8A1] uppercase tracking-[0.12em]">
                        Password
                    </label>
                    <a href="#" class="font-grotesk text-[10px] font-semibold text-[#E0A96D] uppercase tracking-[0.12em] hover:underline"
                       title="Fitur lupa password belum tersedia">
                        Lupa Password?
                    </a>
                </div>
                <div class="relative">
                    <input type="password" id="password" name="password" required
                           autocomplete="current-password" placeholder="••••••••"
                            class="w-full px-4 py-3 border border-[#3D322E] bg-[#1E1511] rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-[#D97706] focus:border-[#D97706] placeholder-[#82746E] transition-colors pr-10">
                    <button type="button" data-toggle-password="password"
                            aria-label="Tampilkan password" aria-pressed="false"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#B8A8A1] hover:text-[#F1DFD8] focus:outline-none">
                        <span data-eye-open><i data-lucide="eye" class="h-[18px] w-[18px]"></i></span>
                        <span data-eye-closed class="hidden"><i data-lucide="eye-off" class="h-[18px] w-[18px]"></i></span>
                    </button>
                </div>
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-md font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] text-[#241708] bg-[#B56100] hover:bg-[#D97706] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#D97706] transition-colors">
                    MASUK
                </button>
            </div>
        </form>

        <div class="mt-8 border-t border-[#3D322E] pt-6">
            <p class="text-center text-[13px] text-[#B8A8A1]">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-grotesk font-semibold text-[#E0A96D] uppercase text-[11px] tracking-[0.12em] ml-1 hover:underline">
                    Daftar
                </a>
            </p>

        </div>

    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-password'));
            var tampil = input.type === 'password';

            input.type = tampil ? 'text' : 'password';
            btn.setAttribute('aria-pressed', tampil ? 'true' : 'false');

            var mataBuka = btn.querySelector('[data-eye-open]');
            var mataTutup = btn.querySelector('[data-eye-closed]');
            if (mataBuka) mataBuka.classList.toggle('hidden', tampil);
            if (mataTutup) mataTutup.classList.toggle('hidden', !tampil);
        });
    });

</script>
@endpush
@endsection
