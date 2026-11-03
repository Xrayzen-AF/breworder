@extends('layouts.app')

@section('title', 'Daftar')

@section('content')
    <div class="flex flex-1 items-center justify-center px-4 py-10 sm:px-6 lg:px-8" style="background-color:#1E1511;">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <img src="{{ asset('images/logo-tejas.png') }}" alt="Tejas Coffee"
                     class="mx-auto mb-4 h-12 w-12 object-contain" width="48" height="48">
                <p class="font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] text-[#E0A96D]">No. 01 — Signature</p>
                <h1 class="mt-2 font-serif text-2xl font-medium tracking-tight text-[#F1DFD8]">Buat akun Pelanggan</h1>
                <p class="mt-2 text-sm leading-relaxed text-[#B8A8A1]">
                    Daftar untuk memesan menu favorit Anda di Tejas Coffee.
                </p>
            </div>

            <div class="rounded-lg border border-[#3D322E] bg-[#271D19] p-6 sm:p-8" style="box-shadow:0 4px 20px -2px rgba(28,25,23,0.06);">
                @if ($errors->any())
                    <div class="mb-5 rounded-lg border border-[#4A2A28] bg-[#4A2A28]/30 p-4">
                        <div class="flex items-start gap-2">
                            <i data-lucide="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-[#F87171]"></i>
                            <ul class="list-inside list-disc space-y-1 text-sm text-[#FCA5A5]">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('register') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="nama_lengkap" class="block font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] text-[#B8A8A1]">Nama Lengkap</label>
                        <input id="nama_lengkap" type="text" name="nama_lengkap" value="{{ old('nama_lengkap') }}"
                            required autofocus autocomplete="name" placeholder="cth. Budi Santoso"
                            class="mt-1.5 block w-full rounded-md border-[#3D322E] bg-[#1E1511] transition focus:border-[#D97706] focus:ring-[#D97706]">
                    </div>

                    <div>
                        <label for="email" class="block font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] text-[#B8A8A1]">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                            autocomplete="email" placeholder="nama@email.com"
                            class="mt-1.5 block w-full rounded-md border-[#3D322E] bg-[#1E1511] transition focus:border-[#D97706] focus:ring-[#D97706]">
                    </div>

                    <div>
                        <label for="nomor_telepon" class="block font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] text-[#B8A8A1]">Nomor HP (ID Member)</label>
                        <input id="nomor_telepon" type="tel" name="nomor_telepon" value="{{ old('nomor_telepon') }}" required
                            autocomplete="tel" placeholder="cth. 081234567890"
                            class="mt-1.5 block w-full rounded-md border-[#3D322E] bg-[#1E1511] transition focus:border-[#D97706] focus:ring-[#D97706]">
                        <p class="mt-1 text-xs text-[#B8A8A1]">Dipakai untuk poin loyalitas & riwayat pesanan.</p>
                    </div>

                    <div>
                        <label for="password" class="block font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] text-[#B8A8A1]">Password</label>
                        <input id="password" type="password" name="password" required autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            class="mt-1.5 block w-full rounded-md border-[#3D322E] bg-[#1E1511] transition focus:border-[#D97706] focus:ring-[#D97706]">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] text-[#B8A8A1]">Konfirmasi
                            Password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" required
                            autocomplete="new-password" placeholder="Ulangi password"
                            class="mt-1.5 block w-full rounded-md border-[#3D322E] bg-[#1E1511] transition focus:border-[#D97706] focus:ring-[#D97706]">
                    </div>

                    <button type="submit"
                        class="w-full rounded-md bg-[#B56100] px-4 py-3.5 font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] text-[#241708] transition hover:bg-[#D97706] focus:outline-none focus:ring-2 focus:ring-[#D97706] focus:ring-offset-2 active:scale-[0.99]">
                        Daftar Sekarang
                    </button>
                </form>

                <p class="mt-4 text-center text-xs text-[#B8A8A1]">
                    Pendaftaran otomatis membuat akun dengan role <span
                        class="font-semibold text-[#B8A8A1]">Pelanggan</span>.
                </p>
            </div>

            <p class="mt-6 text-center text-sm text-[#B8A8A1]">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-grotesk font-semibold uppercase tracking-[0.12em] text-[11px] text-[#E0A96D] hover:underline">
                    Masuk di sini
                </a>
            </p>
        </div>
    </div>
@endsection
