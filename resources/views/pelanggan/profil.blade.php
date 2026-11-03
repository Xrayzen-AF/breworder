@extends('layouts.app')

@section('title', 'Profil — Tejas Coffee')

@php
    $pengguna = auth()->user();
    $nama = $pengguna->nama_lengkap ?? 'Kawan';
    $inIsial = strtoupper(implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice(explode(' ', trim($nama)), 0, 2))));
    $stamp = min($selesaiCount ?? 0, 10);
    $memberId = isset($member) ? '#' . str_pad($member->id_pelanggan, 4, '0', STR_PAD_LEFT) . '-VB' : 'GUEST';
@endphp

@section('content')
<style>
  .prof { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .prof .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .prof-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .member-card { background: #2F231D; border: 1px solid rgba(237,169,109,.3); border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .prof-input { width: 100%; background: #221A15; border: 1px solid #3D322E; border-radius: var(--radius-control); padding: 11px 12px; font-size: 14px; color: #F1DFD8; outline: none; transition: .15s; }
  .prof-input:focus { background: #2A1F19; border-color: #D97706; box-shadow: 0 0 0 3px rgba(217,119,6,.18); }
  .prof-input::placeholder { color: #82746E; }
  .prof-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #B8A8A1; margin-bottom: 6px; }

  /* ——— Layout responsif ——— */
  .prof-wrap { width: 100%; max-width: 480px; margin-inline: auto; }
  @media (min-width: 768px) { .prof-wrap { max-width: 720px; } }
  @media (min-width: 1024px) { .prof-wrap { max-width: 1040px; } }
  @media (min-width: 1280px) { .prof-wrap { max-width: 1120px; } }

  .prof-layout { display: grid; gap: 12px; }
  @media (min-width: 1024px) {
    .prof-layout {
      grid-template-columns: minmax(320px, 400px) minmax(0, 1fr);
      grid-template-areas:
        "ident  form"
        "member form"
        "short  form"
        "logout form";
      column-gap: 20px;
      align-items: start;
    }
    .p-ident { grid-area: ident; }
    .p-member { grid-area: member; }
    .p-form { grid-area: form; align-self: start; }
    .p-short { grid-area: short; }
    .p-logout { grid-area: logout; }
  }

  /* Kolom password: satu kolom di HP agar placeholder tidak terpotong */
  .prof-pass-grid { display: grid; gap: 10px; grid-template-columns: minmax(0, 1fr); }
  @media (min-width: 560px) { .prof-pass-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }

  /* Cegah iOS auto-zoom saat fokus input */
  @media (max-width: 639.98px) { .prof-input { font-size: 16px; } }
</style>

<div class="prof min-h-screen pb-32">
    <div class="prof-wrap px-4 pt-5">
        {{-- ═══ HEADER ═══ --}}
        <div class="flex items-center gap-2.5">
            <img src="{{ asset('images/logo-tejas.png') }}" alt="Tejas Coffee"
                 class="h-9 w-9 shrink-0 object-contain" width="36" height="36">
            <div>
                <p class="text-xs text-[#B8A8A1]">Tejas Coffee &bull; Akun</p>
                <h1 class="grotesk text-xl sm:text-2xl font-bold text-[#F1DFD8]">Profil</h1>
            </div>
        </div>

        <div class="prof-layout mt-4">
            {{-- ═══ KARTU IDENTITAS ═══ --}}
            <div class="prof-card p-ident flex items-center gap-3.5 p-4 sm:p-5">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#3B2A19] text-lg font-bold text-white">{{ $inIsial }}</span>
                <div class="min-w-0">
                    <p class="grotesk truncate text-[17px] font-bold text-[#F1DFD8]">{{ $nama }}</p>
                    <p class="mt-0.5 truncate text-[13px] text-[#B8A8A1]">{{ $pengguna->email }}</p>
                    @if ($pengguna->nomor_telepon)
                        <p class="mt-0.5 truncate text-[13px] text-[#B8A8A1]">{{ $pengguna->nomor_telepon }}</p>
                    @endif
                    <p class="mt-1.5 inline-block rounded bg-[#D97706] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#241708]">{{ $tier }} Member</p>
                </div>
            </div>

            {{-- ═══ KARTU MEMBER ═══ --}}
            <div class="member-card p-member p-5 text-white">
                <div class="flex items-center justify-between gap-3">
                    <p class="grotesk text-[15px] font-bold tracking-wide">Velvet Club</p>
                    <p class="truncate text-[11px] font-medium tracking-widest text-[#EBC9A4]">MEMBER ID: {{ $memberId }}</p>
                </div>
                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-white/80">Rewards Stamp</span>
                        <span class="grotesk font-bold">{{ $stamp }} / 10</span>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-[#271D19]/15">
                        <div class="h-full rounded-full bg-[#F59E0B]" style="width: {{ $stamp * 10 }}%"></div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-4 text-center">
                    <div><p class="grotesk text-lg font-bold">{{ number_format($poin, 0, ',', '.') }}</p><p class="text-[11px] text-white/60">Poin</p></div>
                    <div><p class="grotesk text-lg font-bold">{{ $selesaiCount }}</p><p class="text-[11px] text-white/60">Pesanan Selesai</p></div>
                    <div><p class="grotesk text-lg font-bold">Rp {{ number_format($totalBelanja / 1000, 0, ',', '.') }}K</p><p class="text-[11px] text-white/60">Total Belanja</p></div>
                </div>
            </div>

            {{-- ═══ EDIT PROFIL ═══ --}}
            <div class="p-form space-y-3">
                @if (session('error'))
                    <div class="rounded-lg border border-[#BA1A1A]/25 bg-[#BA1A1A]/[.06] px-4 py-3 text-[13px] font-medium text-[#F87171]">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="rounded-lg border border-[#BA1A1A]/25 bg-[#BA1A1A]/[.06] p-4">
                        <ul class="list-inside list-disc space-y-1 text-[13px] font-medium text-[#F87171]">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <section class="prof-card p-4 sm:p-6">
                    <h2 class="grotesk text-[17px] font-bold text-[#F1DFD8]">Edit Profil</h2>
                    <p class="mt-1 text-[12px] sm:text-[13px] leading-relaxed text-[#B8A8A1]">No. HP dipakai sebagai identitas member, poin &amp; riwayat pesanan.</p>

                    <form method="POST" action="{{ route('pelanggan.profil.update') }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="prof-label" for="nama_lengkap">Nama Lengkap</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" value="{{ old('nama_lengkap', $pengguna->nama_lengkap) }}" required maxlength="255" class="prof-input">
                        </div>
                        <div>
                            <label class="prof-label" for="email">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $pengguna->email) }}" required maxlength="255" class="prof-input">
                        </div>
                        <div>
                            <label class="prof-label" for="nomor_telepon">No. Handphone</label>
                            <input type="text" id="nomor_telepon" name="nomor_telepon" value="{{ old('nomor_telepon', $pengguna->nomor_telepon) }}" maxlength="20" inputmode="tel" placeholder="08xx • kosongkan bila belum ada" class="prof-input">
                        </div>

                        <div class="rounded-lg bg-[#1E1511] p-4">
                            <p class="text-[13px] font-bold text-[#F1DFD8]">Ganti Password <span class="font-medium text-[#B8A8A1]">(opsional)</span></p>
                            <div class="prof-pass-grid mt-3">
                                <input type="password" name="password_saat_ini" autocomplete="current-password" placeholder="Password saat ini" class="prof-input sm:col-span-2">
                                <input type="password" name="password" autocomplete="new-password" placeholder="Password baru (min. 8)" class="prof-input">
                                <input type="password" name="password_confirmation" autocomplete="new-password" placeholder="Ulangi password baru" class="prof-input">
                            </div>
                        </div>

                        <button type="submit" class="grotesk w-full rounded-lg bg-[#D97706] py-3.5 text-[15px] font-bold text-[#241708] transition hover:bg-[#B56100]">Simpan Perubahan</button>
                    </form>
                </section>
            </div>

            {{-- ═══ PINTASAN ═══ --}}
            <div class="p-short grid grid-cols-2 gap-2.5">
                <a href="{{ route('pelanggan.riwayat') }}" class="prof-card flex items-center gap-2.5 p-3.5 transition hover:shadow-md">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#2F231D] text-[#F1DFD8]"><i data-lucide="history" class="h-4 w-4"></i></span>
                    <span class="text-[13px] font-semibold text-[#F1DFD8]">Riwayat Pesanan</span>
                </a>
                <a href="{{ route('pelanggan.menu') }}" class="prof-card flex items-center gap-2.5 p-3.5 transition hover:shadow-md">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#2F231D] text-[#F1DFD8]"><i data-lucide="book-open" class="h-4 w-4"></i></span>
                    <span class="text-[13px] font-semibold text-[#F1DFD8]">Pesan Lagi</span>
                </a>
            </div>

            {{-- ═══ KELUAR ═══ --}}
            <form method="POST" action="{{ route('logout') }}" class="p-logout">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-md border border-[#BA1A1A]/30 bg-[#271D19] py-3 text-sm font-semibold text-[#F87171] transition hover:bg-[#BA1A1A] hover:text-white">
                    <i data-lucide="log-out" class="h-4 w-4"></i> Keluar Akun
                </button>
            </form>
        </div>
    </div>

    {{-- ═══ BOTTOM NAV ═══ --}}
    @include('pelanggan._nav', ['aktif' => 'profil'])
</div>

@push('scripts')
<script>if (window.lucide) lucide.createIcons();</script>
@endpush
@endsection
