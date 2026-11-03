@extends('layouts.app')

@section('title', 'Beranda')

@php
    $nama = auth()->user()->nama_lengkap ?? 'Kawan';
    $namaDepan = explode(' ', trim($nama))[0];
    $jam = (int) now()->format('H');
    $salam = $jam >= 5 && $jam < 11 ? 'pagi' : ($jam < 15 ? 'siang' : ($jam < 18 ? 'sore' : 'malam'));
    $inIsial = strtoupper(implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice(explode(' ', trim($nama)), 0, 2))));
    $stamp = min($selesaiCount ?? 0, 10);
    $memberId = isset($member) ? '#' . str_pad($member->id_pelanggan, 4, '0', STR_PAD_LEFT) . '-VB' : 'GUEST';
    $highlight = $produkUnggulan->first();
    $ikonKategori = function ($nama) {
        $n = strtolower($nama);
        if (str_contains($n, 'espresso') || str_contains($n, 'latte') || str_contains($n, 'kopi')) return 'coffee';
        if (str_contains($n, 'pour') || str_contains($n, 'manual') || str_contains($n, 'filter')) return 'droplet';
        if (str_contains($n, 'cold') || str_contains($n, 'nitro') || str_contains($n, 'ice')) return 'snowflake';
        if (str_contains($n, 'pastry') || str_contains($n, 'bakery') || str_contains($n, 'food') || str_contains($n, 'roti') || str_contains($n, 'croissant')) return 'croissant';
        if (str_contains($n, 'bean') || str_contains($n, 'biji') || str_contains($n, 'merch')) return 'shopping-basket';
        return 'sparkles';
    };
    // Tahapan mengikuti status nyata di database (baru → diproses → selesai).
    $langkah = [
        ['key' => 'baru', 'label' => 'Pesanan Diterima', 'ikon' => 'receipt-text'],
        ['key' => 'diproses', 'label' => 'Sedang Dibuat', 'ikon' => 'cooking-pot'],
        ['key' => 'selesai', 'label' => 'Siap Diambil', 'ikon' => 'shopping-bag'],
    ];
    $idxAktif = 0;
    if ($pesananAktif) {
        $posisi = array_search($pesananAktif->status_pesanan, array_column($langkah, 'key'), true);
        $idxAktif = $posisi === false ? 0 : $posisi;
    }
    $labelStatus = ['done' => 'Selesai', 'active' => 'Berjalan', 'idle' => 'Menunggu'];
    $eta = null;
    if ($pesananAktif && $pesananAktif->waktu_pesan) {
        $lewat = (int) $pesananAktif->waktu_pesan->diffInMinutes(now());
        $eta = max(1, 8 - $lewat);
    }
@endphp

@section('content')
<style>
  /* Inter untuk teks UI (400/500/600/700 tersedia penuh), Lora untuk heading.
     Lato tidak dipakai lagi di sini karena tidak punya weight 500/600 sehingga
     label font-medium/font-semibold dirender dengan berat yang salah. */
  .home { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .home .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .home-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }

  /* ——— Kerangka halaman ——— */
  .home-wrap { width: 100%; max-width: 1120px; margin-inline: auto; }
  .home-layout { display: grid; gap: 16px; }
  .home-side, .home-main { min-width: 0; }
  @media (min-width: 1024px) {
    .home-layout { grid-template-columns: minmax(0, 380px) minmax(0, 1fr); gap: 24px; align-items: start; }
    .home-side { position: sticky; top: 16px; }
    .home-side > .member-card.mt-4 { margin-top: 0; }
  }

  /* ——— Kartu member ——— */
  .member-card {
    background: linear-gradient(135deg, #4A3220 0%, #33241B 58%, #2A1E17 100%);
    border: 1px solid rgba(237, 169, 109, .28);
    border-radius: var(--radius-overlay);
    box-shadow: 0 1px 2px rgba(0, 0, 0, .45);
  }

  /* ——— Kategori cepat ——— */
  .kat { display: flex; flex-direction: column; align-items: center; gap: 8px; min-width: 76px; }
  .kat .ic { width: 52px; height: 52px; border-radius: 9999px; background: #271D19; border: 1px solid #3D322E; display: flex; align-items: center; justify-content: center; color: #E0A96D; transition: .15s; }
  .kat:hover .ic { background: #D97706; border-color: #D97706; color: #241708; }
  .kat small { font-size: 11px; font-weight: 500; color: #B8A8A1; text-align: center; line-height: 1.3; }

  /* ——— Grid produk ——— */
  .home-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
  .home-thumb { position: relative; display: flex; align-items: center; justify-content: center; width: 100%; aspect-ratio: 4 / 3; background: #241B17; padding: 10px; overflow: hidden; }
  .home-thumb img { width: 100%; height: 100%; object-fit: contain; transition: transform .25s ease; }
  .home-thumb:hover img { transform: scale(1.05); }
  @media (max-width: 519.98px) { .home-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (min-width: 1024px) { .home-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; } }

  /* ——— Stepper status pesanan ——— */
  .st-list { list-style: none; margin: 12px 0 0; padding: 0; }
  .st-item { display: flex; align-items: center; gap: 10px; padding: 6px 0; }
  .st-item + .st-item { border-top: 0.5px solid rgba(138, 102, 82, .18); }
  .st-dot { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; flex: none; border-radius: 999px; background: #201912; border: 1px solid rgba(138, 102, 82, .32); color: #82746E; }
  .st-dot svg { width: 14px; height: 14px; }
  .st-item.is-active .st-dot { background: #D97706; border-color: #D97706; color: #241708; box-shadow: 0 0 0 3px rgba(217, 119, 6, .18); }
  .st-item.is-done .st-dot { background: #D97706; border-color: #D97706; color: #241708; }
  .st-label { font-size: 12.5px; font-weight: 600; color: #8C7C74; }
  .st-item.is-active .st-label, .st-item.is-done .st-label { color: #F1DFD8; }
  .st-state { margin-left: auto; font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #82746E; }
  .st-item.is-active .st-state { color: #E8A55A; }
  .st-item.is-done .st-state { color: #86E0A4; }

  /* ——— Panel status meja ——— */
  .meja-backdrop { position: fixed; inset: 0; z-index: 60; display: flex; align-items: flex-end; justify-content: center; padding-top: 48px; background: rgba(12, 9, 7, .72); backdrop-filter: blur(2px); opacity: 0; visibility: hidden; transition: opacity .2s ease, visibility .2s ease; }
  .meja-backdrop.is-open { opacity: 1; visibility: visible; }
  .meja-sheet { width: 100%; max-width: 560px; max-height: 88vh; overflow-y: auto; background: #241B16; border: 1px solid #3D322E; border-bottom: 0; border-radius: var(--radius-overlay) var(--radius-overlay) 0 0; padding-bottom: 8px; transform: translateY(16px); transition: transform .22s ease; }
  .meja-backdrop.is-open .meja-sheet { transform: none; }
  @media (min-width: 640px) { .meja-backdrop { align-items: center; } .meja-sheet { border-bottom: 1px solid #3D322E; border-radius: var(--radius-overlay); } }
  .meja-handle { width: 40px; height: 4px; border-radius: 999px; background: #4A3B35; margin: 8px auto 12px; }
  .meja-close { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; flex: none; border-radius: var(--radius-control); background: #271D19; border: 1px solid #3D322E; color: #D7C7BE; cursor: pointer; }
  .meja-close:hover { color: #F1DFD8; border-color: #554336; }
  .meja-live { display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 600; color: #9D8E87; font-variant-numeric: tabular-nums; }
  .meja-live-dot { width: 5px; height: 5px; border-radius: 999px; background: #4ADE80; animation: mejaPulse 2s ease-in-out infinite; }
  @keyframes mejaPulse { 0%, 100% { opacity: 1; } 50% { opacity: .25; } }
  .meja-grid { display: grid; gap: 8px; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); }
  .meja-chip { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border-radius: var(--radius-surface); background: #271D19; border: 1px solid #3D322E; }
  .meja-chip-top { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
  .meja-chip-nomor { font-family: 'Lora', serif; font-size: 13px; font-weight: 700; color: #F1DFD8; }
  .meja-chip-dot { width: 7px; height: 7px; border-radius: 999px; background: #82746E; flex: none; }
  .meja-chip-kapasitas { font-size: 10.5px; color: #9D8E87; }
  .meja-chip-status { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #9D8E87; }
  .meja-chip[data-status="kosong"] .meja-chip-dot { background: #4ADE80; }
  .meja-chip[data-status="terisi"] .meja-chip-dot { background: #F87171; }
  .meja-chip[data-status="dipesan"] .meja-chip-dot { background: #FBBF24; }
  .meja-chip[data-status="kosong"] .meja-chip-status { color: #86E0A4; }
  .meja-chip[data-status="terisi"] .meja-chip-status { color: #EFA2A2; }
  .meja-chip[data-status="dipesan"] .meja-chip-status { color: #EFBE74; }
</style>

<div class="home min-h-screen pb-32">
    <div class="home-wrap px-4 pt-6">
        {{-- ═══ HEADER ═══ --}}
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-[#3B2A19] text-sm font-bold text-white">{{ $inIsial }}</span>
            <div>
                <p class="text-xs text-[#B8A8A1]">Selamat {{ $salam }}, {{ $tier }} Member</p>
                <h1 class="grotesk text-xl sm:text-2xl font-bold text-[#F1DFD8]">Hai, {{ $namaDepan }}!</h1>
            </div>
            <button type="button" data-meja-open aria-haspopup="dialog" aria-controls="meja-sheet" aria-expanded="false"
                    class="ml-auto flex shrink-0 items-center gap-1.5 rounded-md border border-[#3D322E] bg-[#271D19] px-3 py-2 text-[11px] font-semibold text-[#F1DFD8] transition hover:border-[#554336]">
                <i data-lucide="armchair" class="h-4 w-4 text-[#E0A96D]"></i> Status Meja
            </button>
        </div>

        <div class="home-layout mt-4">
            <div class="home-side">
        {{-- ═══ KARTU MEMBER ═══ --}}
        <div class="member-card mt-4 p-5 text-white">
            <div class="flex items-center justify-between">
                <p class="grotesk text-[15px] font-bold tracking-wide">Velvet Club</p>
                <p class="text-[11px] font-medium tracking-widest text-[#EBC9A4]">MEMBER ID: {{ $memberId }}</p>
            </div>
            <div class="mt-4">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-medium text-white/80">Rewards Stamp</span>
                    <span class="grotesk font-bold">{{ $stamp }} / 10 Menuju Free Latte</span>
                </div>
                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-[#271D19]/15">
                    <div class="h-full rounded-full bg-[#F59E0B]" style="width: {{ $stamp * 10 }}%"></div>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-4">
                <div>
                    <p class="text-[11px] text-white/60">Poin</p>
                    <p class="grotesk text-xl font-bold">{{ number_format($poin, 0, ',', '.') }} <span class="text-xs font-medium text-white/60">pts</span></p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('pelanggan.riwayat') }}" class="rounded-md border border-white/25 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-[#271D19]/10">Riwayat</a>
                    <a href="{{ route('pelanggan.menu') }}" class="rounded-md bg-[#F59E0B] px-3.5 py-2 text-xs font-semibold text-[#241708] transition hover:brightness-110">Pesan</a>
                </div>
            </div>
        </div>

        {{-- ═══ PESANAN AKTIF ═══ --}}
        @if ($pesananAktif)
            <div class="home-card mt-4 p-5 text-white">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#B8A8A1]">Pesanan Aktif #{{ $pesananAktif->nomor_pesanan }}</p>
                        <h2 class="grotesk mt-0.5 text-[16px] font-bold text-[#F1DFD8]">
                            {{ $pesananAktif->status_pesanan === 'baru' ? 'Diterima Barista' : 'Sedang Diseduh' }}
                            @if ($eta) • Tiba ~{{ $eta }} Mnt @endif
                        </h2>
                        <p class="mt-0.5 text-xs text-[#B8A8A1]">
                            {{ ucfirst($pesananAktif->tipe_pesanan) }}@if ($pesananAktif->meja) • Meja {{ $pesananAktif->meja->nomor_meja }}@endif
                            • {{ $pesananAktif->detailPesanan->sum('jumlah') }} item • {{ $pesananAktif->waktu_pesan?->format('H:i') }}
                        </p>
                    </div>
                </div>

                {{-- Stepper 3 tahap — mengikuti status pesanan sebenarnya --}}
                <ol class="st-list" aria-label="Tahapan pesanan {{ $pesananAktif->nomor_pesanan }}">
                    @foreach ($langkah as $i => $l)
                        @php $state = $i < $idxAktif ? 'done' : ($i === $idxAktif ? 'active' : 'idle'); @endphp
                        <li class="st-item is-{{ $state }}">
                            <span class="st-dot" aria-hidden="true">
                                <i data-lucide="{{ $state === 'done' ? 'check' : $l['ikon'] }}"></i>
                            </span>
                            <span class="st-label">{{ $l['label'] }}</span>
                            <span class="st-state">{{ $labelStatus[$state] }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <details class="rounded-md bg-[#2F231D] text-center">
                        <summary class="flex cursor-pointer list-none items-center justify-center gap-1.5 px-3 py-2.5 text-[13px] font-semibold text-[#F1DFD8]">
                            <i data-lucide="qr-code" class="h-4 w-4"></i> QR Ambil
                        </summary>
                        <p class="px-3 pb-3 text-[11px] text-[#B8A8A1]">Tunjukkan kode ini di bar:</p>
                        <p class="grotesk mx-3 mb-3 rounded-lg bg-[#271D19] px-2 py-2 text-sm font-bold tracking-wide text-[#F1DFD8]">{{ $pesananAktif->nomor_pesanan }}</p>
                    </details>
                    <a href="{{ route('pelanggan.status', $pesananAktif->nomor_pesanan) }}"
                       class="flex items-center justify-center gap-1.5 rounded-md bg-[#D97706] px-3 py-2.5 text-[13px] font-semibold text-[#241708] transition hover:bg-[#B56100]">
                        <i data-lucide="receipt-text" class="h-4 w-4"></i> Detail Tiket
                    </a>
                </div>

                @php
                    $bisaBatalAktif = in_array($pesananAktif->status_pesanan, ['baru', 'diproses'], true)
                        && $pesananAktif->transaksi?->status_pembayaran !== 'lunas';
                @endphp
                @if ($bisaBatalAktif)
                    <form method="POST" action="{{ route('pelanggan.pesanan.batal', $pesananAktif->id_pesanan) }}"
                          data-confirm="Batalkan pesanan {{ $pesananAktif->nomor_pesanan }}? Stok akan dikembalikan." class="mt-3">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="flex w-full items-center justify-center gap-1.5 rounded-md border border-[#BA1A1A]/30 px-3 py-2.5 text-[12.5px] font-semibold text-[#F87171] transition hover:bg-[#BA1A1A]/[.07]">
                            <i data-lucide="x" class="h-4 w-4"></i> Batalkan pesanan
                        </button>
                    </form>
                @endif
            </div>
        @endif
            </div>

            <div class="home-main">
        {{-- ═══ REKOMENDASI ═══ --}}
        @if ($highlight)
            <div class="mt-6 flex items-center justify-between">
                <h2 class="grotesk text-[16px] font-bold text-[#F1DFD8]">Rekomendasi Hari Ini</h2>
                <span class="rounded bg-[#E0A96D] px-2 py-0.5 text-[11px] font-semibold text-[#241708]">Seasonal Highlight</span>
            </div>
            <div class="member-card mt-3 overflow-hidden">
                <div class="flex items-center gap-2 px-5 pt-4 text-[#EBC9A4]">
                    <i data-lucide="flame" class="h-4 w-4"></i>
                    <span class="text-[11px] font-bold uppercase tracking-widest">Single Origin Harvest</span>
                </div>
                <div class="px-5 pb-5 pt-2">
                    <h3 class="grotesk text-xl font-bold leading-snug text-white">{{ $highlight->nama_produk }}</h3>
                    @if ($highlight->deskripsi)
                        <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-white/70">{{ $highlight->deskripsi }}</p>
                    @endif
                    <div class="mt-3 flex items-center justify-between">
                        <span class="grotesk text-lg font-bold text-white">Rp {{ number_format($highlight->harga, 0, ',', '.') }}</span>
                        @if ($highlight->status_tersedia && $highlight->stok > 0)
                            <a href="{{ route('pelanggan.menu.kustom', $highlight->id_produk) }}"
                               class="rounded-md bg-[#F59E0B] px-4 py-2 text-xs font-bold text-[#241708] transition hover:bg-[#E0A96D]">Pesan Cepat</a>
                        @else
                            <span class="rounded-lg bg-[#271D19]/15 px-4 py-2 text-xs font-bold text-white/60">Habis</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ═══ KATEGORI CEPAT ═══ --}}
        <div class="mt-6 flex items-center justify-between">
            <h2 class="grotesk text-[16px] font-bold text-[#F1DFD8]">Kategori Menu Cepat</h2>
            <a href="{{ route('pelanggan.menu') }}" class="text-xs font-semibold text-[#F1DFD8] hover:underline">Lihat Semua</a>
        </div>
        <div class="mt-3 flex gap-3 overflow-x-auto pb-2">
            @forelse ($kategori as $k)
                <a href="{{ route('pelanggan.menu', ['kategori' => $k->nama_kategori]) }}" class="kat">
                    <span class="ic"><i data-lucide="{{ $ikonKategori($k->nama_kategori) }}" class="h-5 w-5"></i></span>
                    <small>{{ $k->nama_kategori }}</small>
                </a>
            @empty
                <a href="{{ route('pelanggan.menu') }}" class="kat">
                    <span class="ic"><i data-lucide="coffee" class="h-5 w-5"></i></span>
                    <small>Semua Menu</small>
                </a>
            @endforelse
        </div>

        {{-- ═══ FAVORIT ═══ --}}
        <div class="mt-6 flex items-center justify-between">
            <div>
                <h2 class="grotesk text-[16px] font-bold text-[#F1DFD8]">Daftar Favorit {{ $namaDepan }}</h2>
                <p class="text-[11px] text-[#B8A8A1]">Sering Dipesan</p>
            </div>
        </div>
        <div class="home-grid mt-3">
            @forelse ($produkUnggulan as $p)
                @php $habis = ! $p->status_tersedia || $p->stok <= 0; @endphp
                <div class="home-card flex flex-col overflow-hidden">
                    <div class="home-thumb">
                        @if ($p->url_gambar)
                            <img src="{{ $p->url_gambar }}" alt="{{ $p->nama_produk }}" class="h-full w-full object-contain transition-transform duration-300 hover:scale-105">
                        @else
                            <i data-lucide="coffee" class="h-10 w-10 text-[#9D8E87]"></i>
                        @endif
                        <span class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-[#271D19]/90 text-[#F87171] shadow" title="Favorit pelanggan">
                            <i data-lucide="heart" class="h-4 w-4 fill-current"></i>
                        </span>
                    </div>
                    <div class="flex flex-1 flex-col justify-between p-3 sm:p-3.5">
                        <div>
                            <h3 class="line-clamp-1 text-sm font-semibold text-[#F1DFD8]">{{ $p->nama_produk }}</h3>
                            @if ($p->deskripsi)
                                <p class="mt-0.5 line-clamp-2 min-h-[30px] text-[11px] sm:text-xs leading-snug text-[#B8A8A1]">{{ $p->deskripsi }}</p>
                            @endif
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="grotesk text-sm sm:text-base font-bold text-[#F1DFD8]">Rp {{ number_format($p->harga, 0, ',', '.') }}</span>
                            @if (! $habis)
                                <a href="{{ route('pelanggan.menu.kustom', $p->id_produk) }}"
                                   class="flex h-8 w-8 items-center justify-center rounded-md bg-[#D97706] text-[#241708] transition hover:bg-[#B56100]" title="Atur & tambah">
                                    <i data-lucide="plus" class="h-4 w-4"></i>
                                </a>
                            @else
                                <span class="rounded bg-[#352A22] px-2 py-1 text-[10px] font-bold text-[#9D8E87]">HABIS</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="col-span-full rounded-lg bg-[#271D19] p-8 text-center text-sm text-[#B8A8A1]">Belum ada produk tersedia.</p>
            @endforelse
        </div>

            </div>
        </div>
    </div>

    {{-- ═══ PANEL STATUS MEJA (real-time) ═══ --}}
    <div id="meja-sheet" class="meja-backdrop" aria-hidden="true">
        <div class="meja-sheet" role="dialog" aria-modal="true" aria-labelledby="meja-title">
            <div class="meja-handle" aria-hidden="true"></div>
            <div class="flex items-start justify-between gap-3 px-5 pt-1">
                <div>
                    <h2 id="meja-title" class="grotesk text-[17px] font-bold text-[#F1DFD8]">Status Meja</h2>
                    <p class="mt-0.5 text-[11px] text-[#B8A8A1]">Cek meja yang kosong sebelum duduk.</p>
                </div>
                <button type="button" class="meja-close" data-meja-close aria-label="Tutup panel status meja">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 px-5">
                <div class="flex flex-wrap items-center gap-x-2 text-[11px] text-[#B8A8A1]" data-meja-counts>
                    <span><strong class="text-[#4ADE80]">{{ $mejaBoard['counts']['kosong'] }}</strong> kosong</span>
                    <span>·</span>
                    <span><strong class="text-[#FBBF24]">{{ $mejaBoard['counts']['terisi'] }}</strong> terisi</span>
                    <span>·</span>
                    <span><strong class="text-[#E0A96D]">{{ $mejaBoard['counts']['dipesan'] }}</strong> dipesan</span>
                </div>
                <span class="meja-live" data-meja-live>
                    <span class="meja-live-dot" aria-hidden="true"></span>
                    <span data-meja-updated>Live</span>
                </span>
            </div>
            <div class="meja-grid mt-3 px-5" data-meja-grid role="list" aria-label="Daftar meja dan statusnya">
                @forelse ($mejaBoard['tables'] as $t)
                    <div class="meja-chip" role="listitem" data-meja-chip data-meja-id="{{ $t['id'] }}" data-status="{{ $t['status'] }}">
                        <span class="meja-chip-top">
                            <span class="meja-chip-nomor">{{ $t['nomor'] }}</span>
                            <span class="meja-chip-dot" aria-hidden="true"></span>
                        </span>
                        <span class="meja-chip-kapasitas">{{ $t['kapasitas'] }} kursi</span>
                        <span class="meja-chip-status" data-meja-label>{{ $t['label'] }}</span>
                    </div>
                @empty
                    <p class="col-span-full py-6 text-center text-xs text-[#B8A8A1]" data-meja-empty>Belum ada meja terdaftar.</p>
                @endforelse
            </div>
            <p class="mt-4 px-5 pb-4 text-[11px] leading-relaxed text-[#9D8E87]">
                Status diperbarui otomatis tiap 5 detik.
                <span class="font-semibold text-[#4ADE80]">Kosong</span> siap dipakai,
                <span class="font-semibold text-[#E0A96D]">Dipesan</span> sudah dipesan di kasir,
                <span class="font-semibold text-[#FBBF24]">Terisi</span> sedang ada pesanan aktif.
            </p>
        </div>
    </div>

    {{-- ═══ BOTTOM NAV ═══ --}}
    @include('pelanggan._nav', ['aktif' => 'beranda'])
</div>

@push('scripts')
<script>
(function () {
    var sheet = document.getElementById('meja-sheet');
    if (!sheet) return;

    var trigger = document.querySelector('[data-meja-open]');
    var closeBtn = sheet.querySelector('[data-meja-close]');
    var grid = sheet.querySelector('[data-meja-grid]');
    var counts = sheet.querySelector('[data-meja-counts]');
    var updated = sheet.querySelector('[data-meja-updated]');
    var endpoint = @json(route('pelanggan.meja.status'));
    var timer = null;

    function setOpen(open) {
        sheet.classList.toggle('is-open', open);
        sheet.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.style.overflow = open ? 'hidden' : '';
        if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            refresh();
            timer = window.setInterval(refresh, 5000);
        } else if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    function buatChip(table) {
        var chip = document.createElement('div');
        chip.className = 'meja-chip';
        chip.setAttribute('role', 'listitem');
        chip.setAttribute('data-status', table.status);
        chip.setAttribute('data-meja-id', table.id);

        var top = document.createElement('span');
        top.className = 'meja-chip-top';
        var nomor = document.createElement('span');
        nomor.className = 'meja-chip-nomor';
        nomor.textContent = table.nomor;
        var dot = document.createElement('span');
        dot.className = 'meja-chip-dot';
        dot.setAttribute('aria-hidden', 'true');
        top.appendChild(nomor);
        top.appendChild(dot);

        var kapasitas = document.createElement('span');
        kapasitas.className = 'meja-chip-kapasitas';
        kapasitas.textContent = table.kapasitas + ' kursi';

        var status = document.createElement('span');
        status.className = 'meja-chip-status';
        status.textContent = table.label;

        chip.appendChild(top);
        chip.appendChild(kapasitas);
        chip.appendChild(status);
        return chip;
    }

    function paint(data) {
        var tables = data.tables || [];

        if (grid) {
            grid.innerHTML = '';
            if (!tables.length) {
                var kosong = document.createElement('p');
                kosong.className = 'col-span-full py-6 text-center text-xs text-[#B8A8A1]';
                kosong.textContent = 'Belum ada meja terdaftar.';
                grid.appendChild(kosong);
            } else {
                tables.forEach(function (t) { grid.appendChild(buatChip(t)); });
            }
        }

        if (counts && data.counts) {
            counts.innerHTML = ''
                + '<span><strong class="text-[#4ADE80]">' + Number(data.counts.kosong || 0) + '</strong> kosong</span>'
                + '<span>·</span>'
                + '<span><strong class="text-[#FBBF24]">' + Number(data.counts.terisi || 0) + '</strong> terisi</span>'
                + '<span>·</span>'
                + '<span><strong class="text-[#E0A96D]">' + Number(data.counts.dipesan || 0) + '</strong> dipesan</span>';
        }

        if (updated) {
            updated.textContent = 'Live · ' + new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
            });
        }
    }

    function refresh() {
        if (document.visibilityState !== 'visible') return;

        fetch(endpoint, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(paint)
            .catch(function () { if (updated) updated.textContent = 'Gagal memperbarui'; });
    }

    if (trigger) trigger.addEventListener('click', function () { setOpen(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    sheet.addEventListener('click', function (e) { if (e.target === sheet) setOpen(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sheet.classList.contains('is-open')) setOpen(false);
    });
    document.addEventListener('visibilitychange', function () {
        if (sheet.classList.contains('is-open')) refresh();
    });
})();
</script>
<script>if (window.lucide) lucide.createIcons();</script>
@endpush
@endsection
