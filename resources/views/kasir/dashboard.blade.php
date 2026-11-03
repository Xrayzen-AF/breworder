@extends('layouts.app')

@section('title', 'POS Register — Tejas Coffee')

@php
    $idrK = function ($n) {
        $n = (float) $n;
        if ($n >= 1000) {
            $k = $n / 1000;
            $s = (fmod($k, 1) == 0.0) ? number_format($k, 0, ',', '.') : rtrim(rtrim(number_format($k, 1, ',', '.'), '0'), ',');
            return 'IDR ' . $s . 'K';
        }
        return 'IDR ' . number_format($n, 0, ',', '.');
    };
    $idrFull = fn ($n) => 'IDR ' . number_format((float) $n, 0, ',', '.');
    $namaKasir = auth()->user()->nama_lengkap ?? 'Kasir';
    $potong = explode(' ', trim($namaKasir));
    $namaPendek = $potong[0] . (isset($potong[1]) ? ' ' . strtoupper(substr(end($potong), 0, 1)) . '.' : '');
    $metodeLabel = function ($m) {
        return match (strtoupper($m->kode_metode)) {
            'CASH' => 'Tunai',
            'QRIS' => 'QRIS',
            'TRANSFER' => 'Transfer',
            default => $m->nama_metode,
        };
    };
    $tipeLabel = fn ($tipe) => match ($tipe) {
        'dine-in' => 'Makan di Tempat',
        'takeaway' => 'Bawa Pulang',
        default => 'Diantar',
    };
    $hasDraft = count($tiket) > 0;
    $jumlahTiket = array_sum(array_column($tiket, 'jumlah'));
    $biayaLayanan = (float) ($biayaLayanan ?? 0);
    $ids = $pesananAktif->pluck('id_pesanan')->values();
    $nextTicketId = null;
    if ($tiketTerpilih && $ids->count() > 1) {
        $pos = $ids->search($tiketTerpilih->id_pesanan);
        if ($pos !== false) $nextTicketId = $ids[($pos + 1) % $ids->count()];
    }
@endphp

@section('content')
<style>
  .pos { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .pos .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .pos-topbar { background: #221A15; border-bottom: 1px solid #3D322E; }
  .pos-tab { font-size: 14px; color: #B8A8A1; white-space: nowrap; padding: 6px 2px; transition: color .15s; }
  .pos-tab:hover { color: #F1DFD8; }
  .pos-tab.active { color: #F59E0B; font-weight: 600; box-shadow: inset 0 -2px 0 #D97706; }
  .pos-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .pos-pill { background: #2F231D; border: 1px solid #3D322E; border-radius: var(--radius-control); }
  .pos-cat { font-size: 13px; font-weight: 500; padding: 8px 14px; border-radius: 6px; background: #2F231D; color: #B8A8A1; white-space: nowrap; transition: .15s; border: 1px solid #3D322E; }
  .pos-cat:hover { background: #352A22; color: #F1DFD8; }
  .pos-cat.active { background: #D97706; color: #241708; border-color: #D97706; font-weight: 600; }
  .pos-add { width: 32px; height: 32px; border-radius: 6px; background: #D97706; color: #241708; display: flex; align-items: center; justify-content: center; transition: .15s; flex-shrink: 0; }
  .pos-add:hover { background: #F59E0B; }
  .pos-add:disabled { background: #2F231D; color: #7E6D60; cursor: not-allowed; }
  .pos-step { width: 28px; height: 28px; border-radius: 6px; background: #2F231D; color: #F1DFD8; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 1px solid #3D322E; }
  .pos-step:hover { background: #352A22; border-color: #554336; }
  .pay input { position: absolute; opacity: 0; pointer-events: none; }
  .pay span { display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; font-weight: 500; color: #B8A8A1; background: #2F231D; border-radius: 6px; padding: 10px 6px; cursor: pointer; border: 1px solid #3D322E; text-align: center; }
  .pay input:checked + span { background: #D97706; color: #241708; border-color: #D97706; font-weight: 700; }
  .tipe input { position: absolute; opacity: 0; pointer-events: none; }
  .tipe span { display: block; font-size: 13px; font-weight: 600; color: #B8A8A1; background: #2F231D; border-radius: 6px; padding: 10px; cursor: pointer; text-align: center; border: 1px solid #3D322E; }
  .tipe input:checked + span { background: #D97706; color: #241708; border-color: #D97706; }
  .pos-input { width: 100%; background: #221A15; border: 1px solid #3D322E; border-radius: var(--radius-control); padding: 10px 12px; font-size: 13px; color: #F1DFD8; outline: none; }
  .pos-input:focus { background: #2A1F19; border-color: #D97706; box-shadow: 0 0 0 3px rgba(217,119,6,.18); }
  .pos-input::placeholder { color: #82746E; }
  .pos select option { background: #221A15; color: #F1DFD8; }

  /* ── Kalkulator tunai ── */
  .calc-overlay {
      position: fixed; inset: 0; z-index: 60;
      display: flex; align-items: flex-end; justify-content: center;
      background: rgba(12, 8, 6, .74);
      -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
  }
  .calc-overlay.hidden { display: none; }
  .calc-sheet {
      width: 100%; max-width: 460px; max-height: 100dvh; overflow-y: auto;
      background: #271D19; border: 1px solid #3D322E; border-bottom: 0;
      border-radius: var(--radius-overlay) var(--radius-overlay) 0 0;
      box-shadow: 0 -18px 60px rgba(0, 0, 0, .55);
      animation: calc-up .22s ease-out;
  }
  @media (min-width: 640px) {
      .calc-overlay { align-items: center; padding: 24px; }
      .calc-sheet { border-bottom: 1px solid #3D322E; border-radius: var(--radius-overlay); animation: calc-in .18s ease-out; }
  }
  @keyframes calc-up { from { transform: translateY(18px); opacity: 0; } to { transform: none; opacity: 1; } }
  @keyframes calc-in { from { transform: scale(.97); opacity: 0; } to { transform: none; opacity: 1; } }
  .calc-display { background: #1E1511; border: 1px solid #3D322E; border-radius: var(--radius-surface); padding: 12px 16px; }
  .calc-key {
      display: flex; align-items: center; justify-content: center;
      height: 56px; border-radius: 6px;
      background: #2F231D; border: 1px solid #3D322E;
      color: #F1DFD8; font-family: 'Lora', ui-serif, Georgia, serif;
      font-size: 21px; font-weight: 600;
      user-select: none; -webkit-user-select: none;
      touch-action: manipulation; cursor: pointer;
      transition: background .12s ease, transform .08s ease, border-color .12s ease;
  }
  .calc-key:hover { background: #352A22; border-color: #554336; }
  .calc-key:active { transform: scale(.96); background: #3A2C22; }
  .calc-key.util { font-size: 14px; color: #B8A8A1; }
  .calc-key.op { color: #F59E0B; font-size: 24px; }
  .calc-key:focus-visible, .calc-preset:focus-visible, .calc-launch:focus-visible, .calc-confirm:focus-visible {
      outline: 2px solid #D97706; outline-offset: 2px;
  }
  .calc-preset {
      min-height: 44px;
      padding: 9px 6px; border-radius: 6px; border: 1px solid #3D322E;
      background: #221A15; color: #F1DFD8; font-size: 12.5px; font-weight: 600;
      font-variant-numeric: tabular-nums;
      user-select: none; touch-action: manipulation; cursor: pointer;
      transition: background .12s, border-color .12s;
  }
  .calc-preset:hover { background: #2F231D; border-color: #554336; }
  .calc-preset.exact { background: rgba(217, 119, 6, .16); border-color: rgba(217, 119, 6, .5); color: #F59E0B; }
  .calc-launch {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px;
      border-radius: 6px; border: 1px solid rgba(217, 119, 6, .45);
      background: rgba(217, 119, 6, .12); color: #F59E0B;
      font-size: 12.5px; font-weight: 600; padding: 10px 12px;
      user-select: none; touch-action: manipulation; cursor: pointer;
      transition: background .12s, border-color .12s;
  }
  .calc-launch:hover { background: rgba(217, 119, 6, .2); border-color: #D97706; }
  .calc-launch.hidden { display: none; }
  .calc-launch.compact { width: 42px; height: 42px; padding: 0; }
  @media (max-height: 700px) {
      .calc-key { height: 44px; }
      .calc-confirm { min-height: 48px; }
      .calc-tip { display: none; }
      #calc-presets { display: flex; gap: 6px; overflow-x: auto; }
      #calc-presets .calc-preset { flex: 0 0 auto; min-height: 40px; padding-left: 14px; padding-right: 14px; }
  }
  .calc-confirm {
      width: 100%; min-height: 58px; border-radius: 6px;
      background: #D97706; color: #241708;
      font-family: 'Lora', ui-serif, Georgia, serif; font-size: 16px; font-weight: 800;
      touch-action: manipulation; cursor: pointer;
      transition: background .15s, opacity .15s;
      font-variant-numeric: tabular-nums;
  }
  .calc-confirm:hover { background: #B56100; }
  .calc-confirm:disabled { background: #2F231D; color: #9D8E87; cursor: not-allowed; }

  /* ── Papan pemilihan meja ── */
  .meja-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
      gap: 8px;
      max-height: 220px;
      overflow-y: auto;
      padding: 2px;
  }
  .meja-card {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 1px;
      min-height: 74px;
      padding: 9px 10px;
      border: 1px solid #3D322E;
      border-radius: 6px;
      background: #221A15;
      color: #F1DFD8;
      text-align: left;
      cursor: pointer;
      touch-action: manipulation;
      user-select: none;
      -webkit-user-select: none;
      transition: background .15s ease, border-color .15s ease, box-shadow .15s ease, transform .08s ease;
  }
  .meja-card:hover { background: #2A1F19; border-color: #554336; }
  .meja-card:active { transform: scale(.98); }
  .meja-card[data-status="terisi"] { background: rgba(217, 119, 6, .08); border-color: rgba(217, 119, 6, .45); }
  .meja-card[data-status="dipesan"] { background: rgba(224, 169, 109, .07); border-color: rgba(224, 169, 109, .42); }
  .meja-card[aria-selected="true"] {
      border-color: #D97706;
      background: rgba(217, 119, 6, .16);
      box-shadow: 0 0 0 2px rgba(217, 119, 6, .28);
  }
  .meja-card:focus-visible { outline: 2px solid #F59E0B; outline-offset: 2px; }
  .meja-card-top { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 4px; }
  .meja-card-nomor { font-family: 'Lora', ui-serif, Georgia, serif; font-size: 14px; font-weight: 800; letter-spacing: .01em; }
  .meja-card-kapasitas { font-size: 10px; color: #9D8E87; }
  .meja-card-status { margin-top: 3px; font-size: 10.5px; font-weight: 700; color: #4ADE80; }
  .meja-card[data-status="terisi"] .meja-card-status { color: #FBBF24; }
  .meja-card[data-status="dipesan"] .meja-card-status { color: #E0A96D; }
  .meja-card-order { display: block; width: 100%; margin-top: 1px; font-size: 9.5px; font-weight: 500; color: #B8A8A1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .meja-card-order[hidden] { display: none; }
  .meja-dot { width: 7px; height: 7px; flex: 0 0 7px; border-radius: 999px; background: #4ADE80; box-shadow: 0 0 0 3px rgba(74, 222, 128, .12); }
  .meja-card[data-status="terisi"] .meja-dot { background: #F59E0B; box-shadow: 0 0 0 3px rgba(245, 158, 11, .14); }
  .meja-card[data-status="dipesan"] .meja-dot { background: #E0A96D; box-shadow: 0 0 0 3px rgba(224, 169, 109, .14); }
  .meja-live-dot { width: 6px; height: 6px; border-radius: 999px; background: #4ADE80; animation: meja-pulse 1.8s ease-in-out infinite; }
  .meja-live-dot.stale { background: #F87171; animation: none; }
  @keyframes meja-pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .35; transform: scale(.8); } }

  /* ── Modal Midtrans ── */
  .pay-overlay {
      position: fixed; inset: 0; z-index: 70;
      display: flex; align-items: flex-end; justify-content: center;
      background: rgba(12, 8, 6, .78);
      -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
  }
  .pay-overlay.hidden { display: none; }
  .pay-sheet {
      width: 100%; max-width: 480px; max-height: 100dvh; overflow-y: auto;
      background: #271D19; border: 1px solid #3D322E; border-bottom: 0;
      border-radius: var(--radius-overlay) var(--radius-overlay) 0 0;
      box-shadow: 0 -18px 60px rgba(0, 0, 0, .55);
      animation: calc-up .22s ease-out;
  }
  @media (min-width: 640px) {
      .pay-overlay { align-items: center; padding: 24px; }
      .pay-sheet { border-bottom: 1px solid #3D322E; border-radius: var(--radius-overlay); animation: calc-in .18s ease-out; }
  }
  .mt-tab {
      flex: 1 1 0; padding: 10px 8px; border-radius: 6px;
      border: 1px solid #3D322E; background: #221A15; color: #B8A8A1;
      font-size: 12.5px; font-weight: 600; cursor: pointer;
      display: inline-flex; align-items: center; justify-content: center; gap: 6px;
      touch-action: manipulation; transition: .15s;
  }
  .mt-tab:hover { background: #2F231D; color: #F1DFD8; }
  .mt-tab.active { background: rgba(217, 119, 6, .16); border-color: #D97706; color: #F59E0B; }
  .mt-bank {
      display: flex; align-items: center; justify-content: center;
      min-height: 44px; padding: 8px 6px; border-radius: 6px;
      border: 1px solid #3D322E; background: #221A15; color: #F1DFD8;
      font-size: 12px; font-weight: 700; cursor: pointer;
      touch-action: manipulation; transition: .15s;
  }
  .mt-bank:hover { border-color: #554336; background: #2F231D; }
  .mt-bank.active { border-color: #D97706; background: rgba(217, 119, 6, .16); color: #F59E0B; }
  .mt-va {
      font-family: 'Lora', ui-serif, Georgia, serif;
      font-size: 24px; font-weight: 800; letter-spacing: .06em;
      color: #F1DFD8; font-variant-numeric: tabular-nums;
      word-break: break-all;
  }
  .mt-qr { width: 100%; max-width: 232px; margin: 0 auto; display: block; border-radius: var(--radius-surface); background: #fff; padding: 10px; }
  .mt-tab:focus-visible, .mt-bank:focus-visible, .pay-sheet button:focus-visible { outline: 2px solid #D97706; outline-offset: 2px; }
</style>

<div class="pos min-h-screen pb-10">
    {{-- ═══ TOPBAR ═══ --}}
    <div class="pos-topbar sticky top-0 z-20">
        <div class="mx-auto flex max-w-[1400px] flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
            <span class="flex items-center gap-2">
                <img src="{{ asset('images/logo-tejas.png') }}" alt="Tejas Coffee" class="h-6 w-6 object-contain" width="24" height="24">
                <span class="grotesk text-[18px] font-bold text-[#F1DFD8]">TEJAS COFFEE</span>
            </span>
            <nav class="flex items-center gap-5 overflow-x-auto">
                <a href="#antrian" class="pos-tab">Pesanan Dapur</a>
                <a href="{{ route('kasir.dashboard') }}" class="pos-tab active">Kasir</a>
            </nav>
            <div class="ml-auto flex items-center gap-2 sm:gap-3">
                <form method="GET" action="{{ route('kasir.dashboard') }}" class="pos-pill hidden items-center gap-2 px-3 py-2 md:flex">
                    <i data-lucide="search" class="h-3.5 w-3.5 text-[#B8A8A1]"></i>
                    <input type="text" name="lookup" value="{{ $lookup }}" placeholder="Cari nomor pesanan..."
                           class="w-40 bg-transparent text-xs text-[#F1DFD8] outline-none placeholder:text-[#9D8E87]">
                </form>
                <a href="{{ route('kasir.dashboard', ['new' => 1]) }}"
                   class="flex items-center gap-1.5 rounded-md bg-[#D97706] px-3.5 py-2.5 text-[13px] font-medium text-[#241708] transition hover:bg-[#B56100]">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i> Pesanan Baru
                </a>
                <button type="button" class="relative flex h-10 w-10 items-center justify-center rounded-lg text-[#B8A8A1] transition hover:bg-[#2F231D]" title="Notifikasi">
                    <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
                    @if ($pesananAktif->count() > 0)
                        <span class="absolute right-2 top-2 h-2 w-2 rounded-full border border-white bg-[#82746E]"></span>
                    @endif
                </button>
                <div class="hidden items-center gap-2 sm:flex">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#2F231D] text-[13px] font-bold text-[#F1DFD8]">
                        {{ strtoupper(substr($namaKasir, 0, 1)) }}
                    </span>
                    <span class="leading-tight">
                        <span class="block text-[13px] font-semibold text-[#F1DFD8]">{{ $namaPendek }}</span>
                        <span class="block text-[11px] text-[#B8A8A1]">Kasir</span>
                    </span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-lg text-[#B8A8A1] transition hover:bg-[#2F231D] hover:text-[#F87171]" title="Keluar">
                        <i data-lucide="log-out" class="h-[18px] w-[18px]"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if (session('error'))
        <div class="mx-auto max-w-[1400px] px-4 pt-4 sm:px-6">
            <div class="rounded-lg border border-[#BA1A1A]/25 bg-[#BA1A1A]/[.06] px-4 py-3 text-[13px] font-medium text-[#F87171]">
                {{ session('error') }}
            </div>
        </div>
    @endif
    @if (session('success'))
        <div class="mx-auto max-w-[1400px] px-4 pt-4 sm:px-6">
            <div class="rounded-lg border border-[#16A34A]/25 bg-[#16A34A]/[.07] px-4 py-3 text-[13px] font-medium text-[#4ADE80]">
                {{ session('success') }}
            </div>
        </div>
    @endif

    {{-- ═══ MAIN: MENU + PANEL ═══ --}}
    <div class="mx-auto grid max-w-[1400px] grid-cols-1 gap-5 px-4 pt-5 sm:px-6 lg:grid-cols-[1fr_400px]">

        {{-- ── Kolom menu ── --}}
        <div>
            <div class="flex gap-2">
                <form method="GET" action="{{ route('kasir.dashboard') }}" class="flex flex-1 items-center gap-2 rounded-lg bg-[#271D19] px-4 py-3 shadow-[0_1px_2px_rgba(0,0,0,.05)]">
                    @if ($kategoriAktif)<input type="hidden" name="kategori" value="{{ $kategoriAktif }}">@endif
                    <i data-lucide="search" class="h-4 w-4 shrink-0 text-[#9D8E87]"></i>
                    <input id="menu-search" type="text" name="cari" value="{{ $cari }}"
                           placeholder="Cari menu..."
                           class="w-full bg-transparent text-sm text-[#F1DFD8] outline-none placeholder:text-[#9D8E87]">
                    @if ($cari !== '')
                        <a href="{{ route('kasir.dashboard', $kategoriAktif ? ['kategori' => $kategoriAktif] : []) }}"
                           class="text-[#9D8E87] hover:text-[#F1DFD8]" title="Hapus pencarian">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </a>
                    @endif
                </form>
            </div>

            <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
                <a href="{{ route('kasir.dashboard', $cari !== '' ? ['cari' => $cari] : []) }}"
                   class="pos-cat {{ ! $kategoriAktif ? 'active' : '' }}">Semua</a>
                @foreach ($kategori as $k)
                    <a href="{{ route('kasir.dashboard', array_filter(['kategori' => $k->nama_kategori, 'cari' => $cari !== '' ? $cari : null])) }}"
                       class="pos-cat {{ $kategoriAktif === $k->nama_kategori ? 'active' : '' }}">{{ $k->nama_kategori }}</a>
                @endforeach
            </div>

            <div class="mt-4 grid grid-cols-2 gap-4 xl:grid-cols-3">
                @forelse ($produk as $p)
                    @php $habis = ! $p->status_tersedia || $p->stok <= 0; @endphp
                    <div class="pos-card overflow-hidden">
                        <div class="relative w-full items-center justify-center bg-[#241B17] overflow-hidden p-2">
                            @if ($p->url_gambar)
                                <img src="{{ $p->url_gambar }}" alt="{{ $p->nama_produk }}" class="h-full w-full object-contain transition-transform duration-200 hover:scale-105">
                            @else
                                <i data-lucide="coffee" class="h-10 w-10 text-[#9D8E87]"></i>
                            @endif
                            @if ($habis)
                                <span class="absolute left-2 top-2 rounded bg-[#BA1A1A] px-2 py-0.5 text-[11px] font-semibold text-white">Habis</span>
                            @else
                                <span class="absolute left-2 top-2 rounded bg-[#271D19]/90 px-2 py-0.5 text-[11px] font-medium text-[#B8A8A1]">Stok {{ $p->stok }}</span>
                            @endif
                        </div>
                        <div class="p-3.5">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-[#B8A8A1]">{{ $p->kategori->nama_kategori ?? 'House Blend' }}</p>
                            <h3 class="mt-0.5 line-clamp-2 min-h-[40px] text-sm font-medium leading-snug text-[#F1DFD8]">{{ $p->nama_produk }}</h3>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-sm font-semibold text-[#F1DFD8]">{{ $idrK($p->harga) }}</span>
                                <form method="POST" action="{{ route('kasir.ticket.tambah', $p->id_produk) }}">
                                    @csrf
                                    <button type="submit" class="pos-add" {{ $habis ? 'disabled' : '' }} title="Tambah ke tiket">
                                        <i data-lucide="plus" class="h-4 w-4"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="col-span-full rounded-lg bg-[#271D19] p-8 text-center text-sm text-[#B8A8A1]">
                        Tidak ada menu{{ $cari !== '' ? ' untuk "' . $cari . '"' : '' }}{{ $kategoriAktif ? ' pada kategori ' . $kategoriAktif : '' }}.
                    </p>
                @endforelse

            </div>
        </div>

        {{-- ── Panel tiket ── --}}
        <div>
            <div class="pos-card p-5 lg:sticky lg:top-[76px]">
                @if ($hasDraft)
                    {{-- MODE A: draf tiket baru --}}
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="grotesk text-[18px] font-bold text-[#F1DFD8]">Pesanan Baru</h2>
                        <span class="rounded bg-[#352A22] px-2 py-0.5 text-[11px] font-semibold text-[#F1DFD8]">Belum dibayar</span>
                    </div>
                    <p class="mt-1 text-xs text-[#B8A8A1]">Kasir: {{ $namaKasir }}</p>

                    <div class="mt-4 space-y-3">
                        @foreach ($tiket as $i)
                            <div class="flex items-start justify-between gap-3 border-b border-dashed border-[#3D322E] pb-3 last:border-0 last:pb-0">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-[#F1DFD8]">{{ $i['nama'] }}</p>
                                    <p class="mt-0.5 text-xs text-[#B8A8A1]">{{ $idrFull($i['harga']) }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <form method="POST" action="{{ route('kasir.ticket.update', $i['id_produk']) }}">
                                        @csrf
                                        <input type="hidden" name="jumlah" value="{{ $i['jumlah'] - 1 }}">
                                        <button class="pos-step" title="Kurangi">−</button>
                                    </form>
                                    <span class="grotesk w-5 text-center text-sm font-bold">{{ $i['jumlah'] }}</span>
                                    <form method="POST" action="{{ route('kasir.ticket.update', $i['id_produk']) }}">
                                        @csrf
                                        <input type="hidden" name="jumlah" value="{{ $i['jumlah'] + 1 }}">
                                        <button class="pos-step" title="Tambah">+</button>
                                    </form>
                                    <form method="POST" action="{{ route('kasir.ticket.update', $i['id_produk']) }}">
                                        @csrf
                                        <input type="hidden" name="jumlah" value="0">
                                        <button class="ml-1 text-[#9D8E87] hover:text-[#F87171]" title="Hapus">
                                            <i data-lucide="x" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('kasir.ticket.buat') }}" class="mt-4 border-t border-[#3D322E] pt-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="nama_pelanggan" value="{{ old('nama_pelanggan') }}" placeholder="Nama pelanggan (opsional)" class="pos-input">
                            <input type="text" name="nomor_telepon" value="{{ old('nomor_telepon') }}" placeholder="No. HP (opsional)" class="pos-input">
                        </div>
                        @error('nama_pelanggan')<p class="mt-1 text-xs font-semibold text-[#F87171]">{{ $message }}</p>@enderror

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <label class="tipe relative">
                                <input type="radio" name="tipe_pesanan" value="takeaway" {{ old('tipe_pesanan', 'takeaway') === 'takeaway' ? 'checked' : '' }} onchange="document.getElementById('meja-box').classList.add('hidden')">
                                <span>Takeaway</span>
                            </label>
                            <label class="tipe relative">
                                <input type="radio" name="tipe_pesanan" value="dine-in" {{ old('tipe_pesanan') === 'dine-in' ? 'checked' : '' }} onchange="document.getElementById('meja-box').classList.remove('hidden')">
                                <span>Dine In</span>
                            </label>
                        </div>
                        <div id="meja-box" class="mt-2 {{ old('tipe_pesanan') === 'dine-in' ? '' : 'hidden' }}"
                             data-meja-board data-meja-url="{{ route('kasir.meja.status') }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#B8A8A1]">Pilih meja</p>
                                <span class="inline-flex items-center gap-1.5 text-[10.5px] font-medium text-[#9D8E87]">
                                    <span class="meja-live-dot" data-meja-live-dot></span>
                                    <span data-meja-updated>Live</span>
                                </span>
                            </div>

                            <input type="hidden" name="id_meja" id="id_meja" value="{{ old('id_meja') }}" data-meja-input>

                            <div id="meja-grid" class="meja-grid mt-2" role="listbox" aria-label="Pilih meja" data-meja-grid>
                                @foreach ($mejaTersedia as $m)
                                    @php $order = $m['orders'][0] ?? null; @endphp
                                    <button type="button" class="meja-card"
                                            data-meja-card data-meja-id="{{ $m['id'] }}"
                                            data-nomor="{{ $m['nomor'] }}" data-kapasitas="{{ $m['kapasitas'] }}"
                                            data-status="{{ $m['status'] }}" role="option"
                                            aria-selected="{{ (string) old('id_meja') === (string) $m['id'] ? 'true' : 'false' }}"
                                            aria-label="Meja {{ $m['nomor'] }}, {{ $m['kapasitas'] }} orang, {{ $m['label'] }}">
                                        <span class="meja-card-top">
                                            <span class="meja-card-nomor">{{ $m['nomor'] }}</span>
                                            <span class="meja-dot" aria-hidden="true"></span>
                                        </span>
                                        <span class="meja-card-kapasitas">{{ $m['kapasitas'] }} orang</span>
                                        <span class="meja-card-status" data-meja-status>{{ $m['label'] }}</span>
                                        <span class="meja-card-order" data-meja-order @if (! $order) hidden @endif>
                                            @if ($order)
                                                #{{ \Illuminate\Support\Str::limit(preg_replace('/^PSN-/', '', $order['nomor']), 11, '…') }} • {{ $order['since_minutes'] }}m{{ count($m['orders']) > 1 ? ' +' . (count($m['orders']) - 1) : '' }}
                                            @endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>

                            <div class="mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[10.5px]">
                                <span class="text-[#9D8E87]" data-meja-counts>
                                    <strong class="text-[#4ADE80]">{{ $mejaBoard['counts']['kosong'] }}</strong> kosong •
                                    <strong class="text-[#FBBF24]">{{ $mejaBoard['counts']['terisi'] }}</strong> terisi •
                                    <strong class="text-[#E0A96D]">{{ $mejaBoard['counts']['dipesan'] }}</strong> dipesan
                                </span>
                                <span class="font-medium text-[#B8A8A1]" data-meja-selection>Belum dipilih</span>
                            </div>
                            <p class="mt-1 hidden text-[10.5px] font-medium text-[#FBBF24]" data-meja-warning></p>
                            @error('id_meja')<p class="mt-1 text-xs font-semibold text-[#F87171]">{{ $message }}</p>@enderror
                        </div>
                        <input type="text" name="catatan" value="{{ old('catatan') }}" placeholder="Catatan pesanan (opsional)" class="pos-input mt-2">

                        <div class="mt-4 space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-[#B8A8A1]">Subtotal ({{ $jumlahTiket }} item)</span>
                                <span class="font-medium text-[#F1DFD8]">{{ $idrFull($totalTiket) }}</span>
                            </div>
                            @if ($biayaLayanan > 0)
                                <div class="flex justify-between">
                                    <span class="text-[#B8A8A1]">Biaya layanan</span>
                                    <span class="font-medium text-[#F1DFD8]">{{ $idrFull($biayaLayanan) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between border-t border-[#3D322E] pt-2">
                                <span class="font-semibold text-[#F1DFD8]">Total</span>
                                <span class="grotesk text-xl font-bold text-[#F1DFD8]">{{ $idrFull($totalTiket + $biayaLayanan) }}</span>
                            </div>
                        </div>

                        <p class="mb-1.5 mt-4 text-[11px] font-semibold uppercase tracking-wide text-[#B8A8A1]">Cara bayar</p>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="pay relative"><input type="radio" name="metode" value="qris"><span>QRIS</span></label>
                            <label class="pay relative"><input type="radio" name="metode" value="transfer"><span>Transfer</span></label>
                            <label class="pay relative"><input type="radio" name="metode" value="cash" checked><span>Tunai</span></label>
                        </div>
                        <p class="mt-1.5 text-[10.5px] text-[#9D8E87]" data-metode-hint>Tunai dibayar langsung di kasir.</p>

                        <button type="submit" class="grotesk mt-3 w-full rounded-lg bg-[#D97706] py-4 text-[18px] font-bold text-[#241708] transition hover:bg-[#B56100]">
                            Buat Pesanan ({{ $idrFull($totalTiket + $biayaLayanan) }})
                        </button>
                        <p class="mt-2 text-center text-[11px] text-[#9D8E87]">Pesanan langsung masuk ke dapur. QRIS atau transfer bisa dibayar setelah ini.</p>
                    </form>
                @elseif ($tiketTerpilih)
                    {{-- MODE B: tiket antrian terpilih --}}
                    @php $t = $tiketTerpilih; @endphp
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="grotesk text-[18px] font-bold text-[#F1DFD8]">Pesanan #{{ preg_replace('/^PSN-/', '', $t->nomor_pesanan) }}</h2>
                        <span class="flex gap-1">
                            <span class="rounded px-2 py-0.5 text-[11px] font-semibold {{ $t->status_pesanan === 'baru' ? 'bg-[#D97706] text-[#241708]' : 'bg-[#352A22] text-[#F1DFD8]' }}">
                                {{ $t->status_pesanan === 'baru' ? 'Baru' : 'Diproses' }}
                            </span>
                            <span class="rounded px-2 py-0.5 text-[11px] font-semibold {{ ($t->transaksi?->status_pembayaran ?? 'menunggu') === 'lunas' ? 'bg-[#16A34A]/10 text-[#4ADE80]' : 'bg-[#F59E0B]/10 text-[#FBBF24]' }}">
                                {{ ($t->transaksi?->status_pembayaran ?? 'menunggu') === 'lunas' ? 'Lunas' : 'Menunggu' }}
                            </span>
                        </span>
                    </div>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="rounded bg-[#352A22] px-2 py-0.5 text-[11px] font-medium text-[#F1DFD8]">{{ $tipeLabel($t->tipe_pesanan) }}</span>
                        <span class="text-xs text-[#B8A8A1]">@if (($t->pengguna?->peran ?? null) === 'pelanggan') Dipilih pelanggan @else Kasir: {{ $t->pengguna->nama_lengkap ?? $namaKasir }} @endif{{ $t->meja ? ' • Meja ' . $t->meja->nomor_meja : '' }}</span>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2 rounded-lg bg-[#1E1511] p-3">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#2F231D] text-[13px] font-bold text-[#F1DFD8]">
                                {{ strtoupper(substr($t->pelanggan->nama_pelanggan ?? 'G', 0, 1)) }}
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-[#F1DFD8]">{{ $t->pelanggan->nama_pelanggan ?? 'Pelanggan Umum' }}</p>
                                <p class="text-[11px] text-[#B8A8A1]">
                                    @if ($t->pelanggan)
                                        {{ number_format($t->pelanggan->poin_loyalitas ?? 0) }} poin
                                    @else
                                        Bukan member
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if ($nextTicketId)
                            <a href="{{ route('kasir.dashboard', ['ticket' => $nextTicketId]) }}"
                               class="rounded-md bg-[#271D19] px-3 py-1.5 text-xs font-semibold text-[#F1DFD8] shadow-sm transition hover:bg-[#2F231D]">Ganti</a>
                        @endif
                    </div>

                    <div class="mt-3 space-y-3">
                        @foreach ($t->detailPesanan as $d)
                            <div class="flex items-start justify-between gap-3 border-b border-dashed border-[#3D322E] pb-3 last:border-0 last:pb-0">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-[#F1DFD8]">{{ $d->produk?->nama_produk ?? 'Produk #' . $d->id_produk }}</p>
                                    <p class="mt-0.5 text-xs text-[#B8A8A1]">×{{ $d->jumlah }}@if ($d->catatan_item) • "{{ $d->catatan_item }}"@endif</p>
                                </div>
                                <span class="shrink-0 text-sm font-medium text-[#F1DFD8]">{{ $idrFull($d->subtotal) }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3 space-y-1.5 border-t border-[#3D322E] pt-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#B8A8A1]">Subtotal ({{ $t->detailPesanan->sum('jumlah') }} item)</span>
                            <span class="font-medium text-[#F1DFD8]">{{ $idrFull($t->detailPesanan->sum('subtotal')) }}</span>
                        </div>
                        @if ((float) ($t->diskon ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-[#B8A8A1]">Diskon</span>
                                <span class="font-medium text-[#F87171]">-{{ $idrFull($t->diskon) }}</span>
                            </div>
                        @endif
                        @if ((float) ($t->biaya_layanan ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-[#B8A8A1]">Biaya layanan</span>
                                <span class="font-medium text-[#F1DFD8]">{{ $idrFull($t->biaya_layanan) }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-[#F1DFD8]">Total</span>
                            <span class="grotesk text-xl font-bold text-[#F1DFD8]">{{ $idrFull($t->total_harga) }}</span>
                        </div>
                    </div>

                    @php $bayar = $t->transaksi; @endphp
                    @if (! $bayar || $bayar->status_pembayaran !== 'lunas')
                        <form method="POST" action="{{ route('pesanan.lunas', $t->id_pesanan) }}" class="mt-3 rounded-lg bg-[#1E1511] p-3"
                              data-cash-calc data-total="{{ (int) $t->total_harga }}"
                              data-pay-form data-pesanan="{{ $t->id_pesanan }}" data-nomor="{{ $t->nomor_pesanan }}"
                              data-qris-url="{{ route('pesanan.midtrans.qris', $t->id_pesanan) }}"
                              data-transfer-url="{{ route('pesanan.midtrans.transfer', $t->id_pesanan) }}"
                              data-status-url="{{ route('pesanan.midtrans.status', $t->id_pesanan) }}">
                            @csrf
                            @method('PATCH')
                            <p class="text-[11px] font-bold uppercase tracking-wide text-[#B8A8A1]">Cara bayar — tagihan {{ $idrFull($t->total_harga) }}</p>
                            @php $selectedMetodeId = (int) ($t->transaksi?->id_metode ?? 0); @endphp
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                @foreach ($metodeList as $m)
                                    @php $kode = strtoupper($m->kode_metode); @endphp
                                    <label class="pay relative">
                                        <input type="radio" name="id_metode" value="{{ $m->id_metode }}"
                                               data-kode="{{ $kode }}" data-tunai="{{ $m->ada_kembalian ? '1' : '0' }}"
                                               {{ $selectedMetodeId ? ($selectedMetodeId === (int) $m->id_metode ? 'checked' : '') : ($kode === 'CASH' ? 'checked' : '') }}>
                                        <span>{{ $metodeLabel($m) }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div data-cash-panel class="mt-2 grid grid-cols-2 gap-2">
                                <input type="number" name="jumlah_bayar" min="0" step="1" value="{{ (int) $t->total_harga }}" placeholder="Uang diterima" class="pos-input" title="Uang yang diterima">
                                <button type="button" data-calc-open class="calc-launch">
                                    <i data-lucide="calculator" class="h-4 w-4"></i>
                                    Hitung kembalian
                                </button>
                            </div>

                            <div data-midtrans-panel class="mt-2 hidden rounded-lg border border-[#D97706]/35 bg-[#D97706]/[.07] p-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-semibold text-[#F59E0B]" data-midtrans-caption>Bayar QRIS lewat Midtrans</p>
                                    <span class="rounded bg-[#D97706]/15 px-2 py-0.5 text-[10px] font-semibold text-[#F59E0B]">Otomatis</span>
                                </div>
                                <button type="button" data-midtrans-open
                                        class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[#D97706] px-3 py-2.5 text-[12.5px] font-bold text-[#241708] transition hover:bg-[#B56100]">
                                    <i data-lucide="qr-code" class="h-4 w-4"></i>
                                    <span data-midtrans-cta>Buat Kode QR</span>
                                </button>
                                <p class="mt-1 text-center text-[10px] text-[#9D8E87]">Kode QR / nomor pembayaran dibuat otomatis.</p>
                            </div>

                            <input type="text" name="nomor_referensi" placeholder="Nomor bukti bayar (untuk QRIS/transfer manual)" class="pos-input mt-2">
                            <button type="submit" data-pay-submit class="grotesk mt-2 w-full rounded-lg bg-[#D97706] py-3.5 text-[16px] font-bold text-[#241708] transition hover:bg-[#B56100]">
                                Bayar Tunai
                            </button>
                            <p class="mt-1.5 text-center text-[11px] text-[#9D8E87]" data-pay-hint>Masukkan uang yang diterima, kembalian dihitung otomatis.</p>
                        </form>
                    @else
                        <div class="mt-3 rounded-lg bg-[#2F231D] py-3 text-center text-sm font-semibold text-[#F1DFD8]">Lunas ✓ — {{ $idrFull($bayar->total_bayar) }}@if ((float) $bayar->kembalian > 0) (kembali {{ $idrFull($bayar->kembalian) }})@endif</div>
                    @endif
                    <a href="{{ route('struk.show', ['pesanan' => $t->id_pesanan, 'print' => 1]) }}"
                       target="_blank" rel="noopener"
                       class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border border-[#3D322E] bg-[#271D19] px-3 py-3 text-[13px] font-semibold text-[#F1DFD8] transition hover:bg-[#2F231D]">
                        <i data-lucide="printer" class="h-4 w-4"></i> Cetak Struk (80 mm)
                    </a>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        @if ($t->status_pesanan !== 'selesai' && $t->status_pesanan !== 'dibatalkan')
                            @if ($t->status_pesanan === 'baru')
                                <form method="POST" action="{{ route('pesanan.status', $t->id_pesanan) }}" class="col-span-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status_pesanan" value="diproses">
                                    <button type="submit" class="w-full rounded-lg border border-[#D97706]/40 bg-[#271D19] px-3 py-2.5 text-[13px] font-medium text-[#F59E0B] transition hover:bg-[#D97706]/10">Mulai Buat</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('pesanan.status', $t->id_pesanan) }}" class="col-span-1">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status_pesanan" value="selesai">
                                <button type="submit" class="w-full rounded-lg border border-[#3D322E] bg-[#271D19] px-3 py-2.5 text-[13px] font-medium text-[#F1DFD8] transition hover:bg-[#2F231D]">Selesai</button>
                            </form>
                            <form method="POST" action="{{ route('pesanan.status', $t->id_pesanan) }}" onsubmit="return confirm('Batalkan pesanan {{ $t->nomor_pesanan }}? Stok akan dikembalikan.')" class="{{ $t->status_pesanan === 'baru' ? 'col-span-2' : 'col-span-1' }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status_pesanan" value="dibatalkan">
                                <button type="submit" class="w-full rounded-lg border border-[#BA1A1A]/30 bg-[#271D19] px-3 py-2.5 text-[13px] font-medium text-[#F87171] transition hover:bg-[#BA1A1A]/[.06]">Batalkan</button>
                            </form>
                        @endif
                        @if ($nextTicketId)
                            <a href="{{ route('kasir.dashboard', ['ticket' => $nextTicketId]) }}"
                               class="col-span-2 rounded-md bg-[#2F231D] px-3 py-2.5 text-center text-[13px] font-medium text-[#F1DFD8] transition hover:bg-[#352A22]">Hold Order</a>
                        @endif
                    </div>
                    <p class="mt-2 text-center text-[11px] text-[#9D8E87]">Pesanan harus sudah lunas sebelum diselesaikan.</p>
                @else
                    {{-- MODE C: kosong --}}
                    <div class="py-10 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#2F231D] text-[#B8A8A1]">
                            <i data-lucide="receipt-text" class="h-6 w-6"></i>
                        </span>
                        <p class="grotesk mt-3 text-[16px] font-bold text-[#F1DFD8]">Belum ada tiket</p>
                        <p class="mx-auto mt-1 max-w-[240px] text-xs leading-relaxed text-[#B8A8A1]">Pilih menu di kiri untuk meracik tiket baru.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══ ANTRIAN (KDS ORDERS) ═══ --}}
    <div id="antrian" class="mx-auto mt-6 max-w-[1400px] scroll-mt-20 px-4 sm:px-6">
        <div class="pos-card p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="grotesk text-[18px] font-bold text-[#F1DFD8]">Pesanan Masuk</h2>
                <span class="rounded bg-[#D97706] px-2.5 py-0.5 text-xs font-bold text-[#241708]">{{ $pesananAktif->count() }}</span>
                <div class="ml-auto flex flex-wrap gap-x-5 gap-y-1 text-xs text-[#B8A8A1]">
                    <span>Pendapatan hari ini: <strong class="grotesk text-[#F1DFD8]">{{ $idrFull($omzetHariIni) }}</strong></span>
                    <span>Selesai hari ini: <strong class="grotesk text-[#F1DFD8]">{{ $jumlahSelesaiHariIni }}</strong></span>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($pesananAktif as $p)
                    <div class="rounded-lg border p-4 transition {{ $tiketTerpilih && $tiketTerpilih->id_pesanan === $p->id_pesanan ? 'border-[#D97706]' : 'border-[#3D322E]' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <a href="{{ route('kasir.dashboard', ['ticket' => $p->id_pesanan]) }}"
                                   class="grotesk text-sm font-bold text-[#F1DFD8] hover:underline">#{{ $p->nomor_pesanan }}</a>
                                <p class="mt-0.5 text-xs text-[#B8A8A1]">
                                    {{ $tipeLabel($p->tipe_pesanan) }}
                                    @if ($p->meja) • Meja {{ $p->meja->nomor_meja }} @endif
                                    @if ($p->pelanggan) • {{ $p->pelanggan->nama_pelanggan }} @endif
                                    • {{ $p->waktu_pesan?->format('H:i') }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="grotesk text-sm font-bold text-[#F1DFD8]">{{ $idrFull($p->total_harga) }}</p>
                                <span class="mt-1 inline-block rounded px-2 py-0.5 text-[11px] font-semibold {{ $p->status_pesanan === 'baru' ? 'bg-[#D97706] text-[#241708]' : 'bg-[#352A22] text-[#F1DFD8]' }}">
                                    {{ strtoupper($p->status_pesanan) }}
                                </span>
                                <span class="mt-1 inline-block rounded px-2 py-0.5 text-[11px] font-semibold {{ ($p->transaksi?->status_pembayaran ?? 'menunggu') === 'lunas' ? 'bg-[#16A34A]/10 text-[#4ADE80]' : 'bg-[#F59E0B]/10 text-[#FBBF24]' }}">
                                    {{ strtoupper($p->transaksi?->status_pembayaran ?? 'MENUNGGU') }}
                                </span>
                            </div>
                        </div>
                        <ul class="mt-3 space-y-1 border-t border-dashed border-[#3D322E] pt-3 text-[13px] text-[#B8A8A1]">
                            @foreach ($p->detailPesanan as $d)
                                <li class="flex justify-between gap-3">
                                    <span>{{ $d->jumlah }}× {{ $d->produk?->nama_produk ?? 'Produk #' . $d->id_produk }}
                                        @if ($d->catatan_item) <em class="text-xs">"{{ $d->catatan_item }}"</em> @endif
                                    </span>
                                    <span class="text-[#F1DFD8]">{{ $idrFull($d->subtotal) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if (! $p->transaksi || $p->transaksi->status_pembayaran !== 'lunas')
                                <form method="POST" action="{{ route('pesanan.lunas', $p->id_pesanan) }}" class="flex flex-wrap items-center gap-2"
                                      data-cash-calc data-total="{{ (int) $p->total_harga }}"
                                      data-pay-form data-pesanan="{{ $p->id_pesanan }}" data-nomor="{{ $p->nomor_pesanan }}"
                                      data-qris-url="{{ route('pesanan.midtrans.qris', $p->id_pesanan) }}"
                                      data-transfer-url="{{ route('pesanan.midtrans.transfer', $p->id_pesanan) }}"
                                      data-status-url="{{ route('pesanan.midtrans.status', $p->id_pesanan) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="id_metode" class="rounded-md bg-[#2F231D] px-2 py-2 text-[12px]" data-metode-select>
                                        @foreach ($metodeList as $m)
                                            <option value="{{ $m->id_metode }}" data-kode="{{ strtoupper($m->kode_metode) }}" data-tunai="{{ $m->ada_kembalian ? '1' : '0' }}"
                                                {{ (int) ($p->transaksi?->id_metode ?? 0) === (int) $m->id_metode ? 'selected' : '' }}>{{ $metodeLabel($m) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="jumlah_bayar" min="0" step="1" value="{{ (int) $p->total_harga }}" class="w-32 rounded-md bg-[#2F231D] px-2 py-2 text-[12px]" title="Uang diterima">
                                    <button type="button" data-calc-open class="calc-launch compact hidden" title="Kalkulator tunai" aria-label="Buka kalkulator tunai">
                                        <i data-lucide="calculator" class="h-4 w-4"></i>
                                    </button>
                                    <button type="button" data-midtrans-open class="calc-launch compact" title="Bayar QRIS / Transfer" aria-label="Bayar QRIS atau transfer bank">
                                        <i data-lucide="qr-code" class="h-4 w-4"></i>
                                    </button>
                                    <button type="submit" class="rounded-lg bg-[#D97706] px-4 py-2 text-[13px] font-medium text-[#241708] transition hover:bg-[#B56100]">
                                        Bayar
                                    </button>
                                </form>
                            @else
                                <span class="rounded-lg bg-[#2F231D] px-4 py-2 text-[13px] font-semibold text-[#F1DFD8]">Lunas ✓</span>
                            @endif
                            @if ($p->status_pesanan === 'baru')
                                <form method="POST" action="{{ route('pesanan.status', $p->id_pesanan) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status_pesanan" value="diproses">
                                    <button type="submit" class="rounded-lg border border-[#D97706]/40 bg-[#271D19] px-3 py-2 text-[13px] font-medium text-[#F59E0B] transition hover:bg-[#D97706]/10">
                                        Mulai
                                    </button>
                                </form>
                            @endif
                            @if ($p->status_pesanan !== 'selesai')
                                <form method="POST" action="{{ route('pesanan.status', $p->id_pesanan) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status_pesanan" value="selesai">
                                    <button type="submit" class="rounded-lg border border-[#3D322E] bg-[#271D19] px-4 py-2 text-[13px] font-medium text-[#F1DFD8] transition hover:bg-[#2F231D]">
                                        Selesai
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('pesanan.status', $p->id_pesanan) }}" onsubmit="return confirm('Batalkan {{ $p->nomor_pesanan }}?')">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status_pesanan" value="dibatalkan">
                                    <button type="submit" class="rounded-lg border border-[#BA1A1A]/30 bg-[#271D19] px-4 py-2 text-[13px] font-medium text-[#F87171] transition hover:bg-[#BA1A1A]/[.06]">
                                        Batalkan
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg bg-[#2F231D] p-8 text-center text-sm text-[#B8A8A1]">
                        Belum ada pesanan. Pesanan baru dari kasir akan muncul di sini.
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ═══ SELESAI HARI INI — CETAK ULANG STRUK ═══ --}}
    <div id="selesai" class="mx-auto mt-5 max-w-[1400px] scroll-mt-20 px-4 sm:px-6">
        <div class="pos-card p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="grotesk text-[18px] font-bold text-[#F1DFD8]">Selesai Hari Ini</h2>
                <span class="rounded bg-[#352A22] px-2.5 py-0.5 text-xs font-bold text-[#F1DFD8]">{{ $jumlahSelesaiHariIni }}</span>
                <form method="GET" action="{{ route('kasir.dashboard') }}#selesai"
                      class="ml-auto flex items-center gap-2 rounded-lg border border-[#3D322E] bg-[#221A15] px-3 py-2">
                    <i data-lucide="search" class="h-3.5 w-3.5 shrink-0 text-[#9D8E87]"></i>
                    <input type="text" name="selesai_cari" value="{{ $cariSelesai }}"
                           placeholder="Cari nomor pesanan selesai..."
                           class="w-52 bg-transparent text-xs text-[#F1DFD8] outline-none placeholder:text-[#9D8E87]"
                           aria-label="Cari nomor pesanan selesai">
                    @if ($cariSelesai !== '')
                        <a href="{{ route('kasir.dashboard') }}#selesai" class="text-[#9D8E87] hover:text-[#F1DFD8]" title="Hapus pencarian">
                            <i data-lucide="x" class="h-3.5 w-3.5"></i>
                        </a>
                    @endif
                </form>
            </div>
            <p class="mt-2 text-xs text-[#B8A8A1]">Cetak ulang struk pesanan yang sudah diserahterimakan.</p>

            <div class="mt-4 space-y-2">
                @forelse ($selesaiList as $p)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[#3D322E] px-4 py-3">
                        <div class="min-w-0">
                            <p class="grotesk truncate text-[13px] font-bold text-[#F1DFD8]">#{{ $p->nomor_pesanan }}</p>
                            <p class="mt-0.5 text-[11px] text-[#B8A8A1]">
                                {{ $p->waktu_pesan?->format('H:i') }}
                                • {{ $tipeLabel($p->tipe_pesanan) }}
                                @if ($p->meja) • Meja {{ $p->meja->nomor_meja }} @endif
                                @if ($p->pelanggan) • {{ $p->pelanggan->nama_pelanggan }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="grotesk text-[13px] font-bold text-[#F1DFD8]">{{ $idrFull($p->total_harga) }}</span>
                            <a href="{{ route('struk.show', ['pesanan' => $p->id_pesanan, 'print' => 1]) }}"
                               target="_blank" rel="noopener"
                               class="flex items-center gap-1.5 rounded-md border border-[#3D322E] bg-[#271D19] px-3 py-2 text-[12px] font-semibold text-[#F1DFD8] transition hover:bg-[#2F231D] hover:border-[#554336]">
                                <i data-lucide="printer" class="h-3.5 w-3.5"></i> Cetak struk
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg bg-[#2F231D] p-6 text-center text-sm text-[#B8A8A1]">
                        @if ($cariSelesai !== '')
                            Tidak ada pesanan selesai yang cocok dengan "{{ $cariSelesai }}".
                        @else
                            Belum ada pesanan selesai hari ini.
                        @endif
                    </p>
                @endforelse
            </div>

            @if ($cariSelesai !== '')
                <p class="mt-3 text-center text-[11px] text-[#9D8E87]">
                    {{ $selesaiList->count() }} hasil untuk "{{ $cariSelesai }}" dari {{ $jumlahSelesaiHariIni }} pesanan selesai hari ini.
                </p>
            @elseif ($jumlahSelesaiHariIni > $selesaiList->count())
                <p class="mt-3 text-center text-[11px] text-[#9D8E87]">
                    Menampilkan {{ $selesaiList->count() }} dari {{ $jumlahSelesaiHariIni }} pesanan selesai hari ini.
                </p>
            @endif
        </div>
    </div>

    {{-- ═══ KALKULATOR TUNAI ═══ --}}
    <div id="cash-calculator" class="calc-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="calc-title">
        <div class="calc-sheet" role="document">
            <div class="flex items-start justify-between gap-3 border-b border-[#3D322E] px-5 py-4">
                <div>
                    <p class="text-[10.5px] font-semibold uppercase tracking-[0.16em] text-[#D97706]">Pembayaran tunai</p>
                    <h2 id="calc-title" class="grotesk text-[20px] font-bold text-[#F1DFD8]">Kalkulator Kasir</h2>
                </div>
                <button type="button" data-calc-close
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-[#B8A8A1] transition hover:bg-[#2F231D] hover:text-[#F1DFD8]"
                        aria-label="Tutup kalkulator">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="px-5 pt-4">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="text-xs text-[#B8A8A1]">Total tagihan</span>
                    <span id="calc-total" class="grotesk text-sm font-bold text-[#F1DFD8]">IDR 0</span>
                </div>

                <div class="calc-display mt-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="shrink-0 text-[10.5px] font-semibold uppercase tracking-[0.12em] text-[#B8A8A1]">Uang diterima</span>
                        <span id="calc-expression" class="min-w-0 truncate text-right text-[11px] font-medium tabular-nums text-[#9D8E87]"></span>
                    </div>
                    <p id="calc-received" class="grotesk mt-1 text-right text-[32px] font-bold leading-tight text-[#F1DFD8] tabular-nums" aria-live="polite">IDR 0</p>
                </div>

                <div class="mt-2 flex items-center justify-between rounded-lg border border-[#3D322E] bg-[#221A15] px-3.5 py-2.5">
                    <span id="calc-change-label" class="text-xs text-[#B8A8A1]">Kembalian</span>
                    <strong id="calc-change" class="grotesk text-[16px] font-bold tabular-nums text-[#4ADE80]">IDR 0</strong>
                </div>
                <p id="calc-hint" class="mt-1.5 hidden text-right text-[11px] font-semibold text-[#F87171]"></p>

                <div id="calc-presets" class="mt-3 grid grid-cols-3 gap-2"></div>
            </div>

            <div class="px-5 pb-5 pt-4">
                <div class="grid grid-cols-4 gap-2">
                    <button type="button" class="calc-key" data-calc-key="7">7</button>
                    <button type="button" class="calc-key" data-calc-key="8">8</button>
                    <button type="button" class="calc-key" data-calc-key="9">9</button>
                    <button type="button" class="calc-key util" data-calc-key="del" aria-label="Hapus satu digit">⌫</button>

                    <button type="button" class="calc-key" data-calc-key="4">4</button>
                    <button type="button" class="calc-key" data-calc-key="5">5</button>
                    <button type="button" class="calc-key" data-calc-key="6">6</button>
                    <button type="button" class="calc-key util" data-calc-key="clear">C</button>

                    <button type="button" class="calc-key" data-calc-key="1">1</button>
                    <button type="button" class="calc-key" data-calc-key="2">2</button>
                    <button type="button" class="calc-key" data-calc-key="3">3</button>
                    <button type="button" class="calc-key op" data-calc-key="+" aria-label="Tambah">+</button>

                    <button type="button" class="calc-key" data-calc-key="0">0</button>
                    <button type="button" class="calc-key" data-calc-key="00">00</button>
                    <button type="button" class="calc-key" data-calc-key="000">000</button>
                    <button type="button" class="calc-key op" data-calc-key="-" aria-label="Kurang">−</button>
                </div>

                <button type="button" id="calc-confirm" class="calc-confirm mt-2.5">Terima Tunai</button>
                <p class="calc-tip mt-2 text-center text-[10.5px] leading-relaxed text-[#9D8E87]">
                    Ketuk tombol atau pakai keyboard: 0–9, +, −, Backspace, Enter, Esc.
                </p>
            </div>
        </div>
    </div>

    {{-- ═══ MIDTRANS: QRIS / TRANSFER BANK ═══ --}}
    <div id="midtrans-modal" class="pay-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="midtrans-title">
        <div class="pay-sheet" role="document">
            <div class="flex items-start justify-between gap-3 border-b border-[#3D322E] px-5 py-4">
                <div>
                    <p class="text-[10.5px] font-semibold uppercase tracking-[0.16em] text-[#D97706]">Pembayaran QRIS / Transfer</p>
                    <h2 id="midtrans-title" class="grotesk text-[20px] font-bold text-[#F1DFD8]">Bayar Pesanan</h2>
                    <p class="mt-0.5 text-[11.5px] text-[#B8A8A1]"><span data-mt-nomor>—</span> • <span data-mt-total>IDR 0</span></p>
                </div>
                <button type="button" data-mt-close
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-[#B8A8A1] transition hover:bg-[#2F231D] hover:text-[#F1DFD8]"
                        aria-label="Tutup pembayaran Midtrans">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="px-5 py-4">
                <div data-mt-alert class="mb-3 hidden rounded-lg border border-[#F87171]/30 bg-[#F87171]/[.08] px-3 py-2 text-[12px] font-medium text-[#F87171]"></div>

                <div class="flex items-center justify-between gap-3 rounded-lg border border-[#3D322E] bg-[#221A15] px-3.5 py-2.5">
                    <span class="inline-flex items-center gap-2 text-[12px] font-semibold text-[#F1DFD8]">
                        <span data-mt-state-dot class="h-2 w-2 rounded-full bg-[#FBBF24]"></span>
                        <span data-mt-state>Menunggu Pembayaran</span>
                    </span>
                    <span data-mt-countdown class="text-[11px] font-medium tabular-nums text-[#B8A8A1]"></span>
                </div>

                <div data-mt-paid class="mt-3 hidden rounded-lg border border-[#4ADE80]/35 bg-[#4ADE80]/[.08] p-5 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded bg-[#4ADE80] text-[#12210F]">
                        <i data-lucide="check" class="h-7 w-7"></i>
                    </span>
                    <p class="grotesk mt-2 text-[18px] font-bold text-[#4ADE80]">Pembayaran lunas</p>
                    <p class="mt-1 text-[11.5px] text-[#B8A8A1]">Halaman diperbarui otomatis…</p>
                </div>

                <div data-mt-flow>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="mt-tab active" data-mt-tab="qris">
                            <i data-lucide="qr-code" class="h-4 w-4"></i> QRIS
                        </button>
                        <button type="button" class="mt-tab" data-mt-tab="transfer">
                            <i data-lucide="landmark" class="h-4 w-4"></i> Transfer Bank
                        </button>
                    </div>

                    <div class="mt-3" data-mt-panel="qris">
                        <div data-mt-qris-loading class="hidden py-6 text-center">
                            <div class="mx-auto h-44 w-44 animate-pulse rounded-lg bg-[#2F231D]"></div>
                            <p class="mt-2 text-[11.5px] text-[#B8A8A1]">Membuat kode QR…</p>
                        </div>
                        <div data-mt-qris-result class="hidden text-center">
                            <img data-mt-qr-img class="mt-qr" alt="QRIS pembayaran" width="232" height="232">
                            <p class="mt-2 text-[12px] font-semibold text-[#F1DFD8]">Pindai kode ini dengan aplikasi bank atau e-wallet</p>
                            <p class="mt-0.5 text-[10.5px] text-[#9D8E87]">Jumlahnya sudah sesuai, tidak perlu diubah.</p>
                        </div>
                        <div data-mt-qris-empty class="py-2 text-center">
                            <p class="text-[11.5px] leading-relaxed text-[#B8A8A1]">Kode QR berlaku 15 menit untuk satu pesanan.</p>
                            <button type="button" data-mt-create="qris"
                                    class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#D97706] px-4 py-3 text-[13px] font-bold text-[#241708] transition hover:bg-[#B56100]">
                                <i data-lucide="qr-code" class="h-4 w-4"></i> Tampilkan Kode QR
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 hidden" data-mt-panel="transfer">
                        <div data-mt-va-form>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#B8A8A1]">Pilih bank</p>
                            <div class="mt-2 grid grid-cols-3 gap-2" data-mt-banks>
                                <button type="button" class="mt-bank active" data-mt-bank="bca">BCA</button>
                                <button type="button" class="mt-bank" data-mt-bank="bni">BNI</button>
                                <button type="button" class="mt-bank" data-mt-bank="bri">BRI</button>
                                <button type="button" class="mt-bank" data-mt-bank="mandiri">Mandiri</button>
                                <button type="button" class="mt-bank" data-mt-bank="permata">Permata</button>
                            </div>
                            <button type="button" data-mt-create="transfer"
                                    class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#D97706] px-4 py-3 text-[13px] font-bold text-[#241708] transition hover:bg-[#B56100]">
                                <i data-lucide="landmark" class="h-4 w-4"></i> Buat Nomor Pembayaran
                            </button>
                            <p class="mt-1.5 text-center text-[10px] text-[#9D8E87]">Nomor pembayaran berlaku 15 menit.</p>
                        </div>

                        <div data-mt-va-loading class="hidden py-8 text-center">
                            <div class="mx-auto h-24 w-56 animate-pulse rounded-lg bg-[#2F231D]"></div>
                            <p class="mt-2 text-[11.5px] text-[#B8A8A1]">Membuat nomor pembayaran…</p>
                        </div>

                        <div data-mt-va-result class="hidden text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#B8A8A1]">Nomor Pembayaran <span data-mt-bank-name class="text-[#F59E0B]">BCA</span></p>
                            <p class="mt-2 text-[10.5px] text-[#9D8E87]">Salin nomor di bawah ini</p>
                            <p class="mt-1 mt-va" data-mt-va>—</p>
                            <button type="button" data-mt-copy
                                    class="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-[#3D322E] bg-[#221A15] px-3 py-1.5 text-[11px] font-semibold text-[#F1DFD8] transition hover:bg-[#2F231D]">
                                <i data-lucide="copy" class="h-3.5 w-3.5"></i> <span data-mt-copy-label>Salin Nomor</span>
                            </button>
                            <div data-mt-mandiri class="mx-auto mt-3 hidden max-w-[260px] rounded-lg border border-[#3D322E] bg-[#221A15] px-3 py-2 text-left text-[11px] text-[#B8A8A1]">
                                <div class="flex justify-between gap-3"><span>Kode</span><strong class="text-[#F1DFD8]" data-mt-biller>—</strong></div>
                                <div class="mt-1 flex justify-between gap-3"><span>Nomor</span><strong class="text-[#F1DFD8]" data-mt-billkey>—</strong></div>
                            </div>
                            <button type="button" data-mt-new-va class="mt-3 text-[11px] font-medium text-[#9D8E87] underline decoration-dotted hover:text-[#F1DFD8]">Ganti Bank</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-[#3D322E] px-5 py-4">
                <p data-mt-message class="text-center text-[10.5px] leading-relaxed text-[#9D8E87]">Status diperiksa otomatis.</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button type="button" data-mt-check
                            class="rounded-lg border border-[#3D322E] bg-[#271D19] px-3 py-2.5 text-[12.5px] font-medium text-[#F1DFD8] transition hover:bg-[#2F231D]">
                        Periksa Status
                    </button>
                    <button type="button" data-mt-close
                            class="rounded-lg bg-[#2F231D] px-3 py-2.5 text-[12.5px] font-medium text-[#F1DFD8] transition hover:bg-[#352A22]">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('cash-calculator');
    if (!modal) return;

    var byId = function (id) { return document.getElementById(id); };
    var totalEl = byId('calc-total');
    var receivedEl = byId('calc-received');
    var changeEl = byId('calc-change');
    var changeLabel = byId('calc-change-label');
    var hintEl = byId('calc-hint');
    var exprEl = byId('calc-expression');
    var presetsEl = byId('calc-presets');
    var confirmBtn = byId('calc-confirm');

    var idr = new Intl.NumberFormat('id-ID');
    var fmt = function (n) { return 'IDR ' + idr.format(Math.round(n)); };
    var shortFmt = function (n) {
        return n >= 1000 && n % 1000 === 0 ? (n / 1000) + 'K' : idr.format(n);
    };

    var state = { total: 0, entry: '', acc: 0, op: null, replace: true, form: null };
    var lastFocus = null;
    var modalOpen = false;

    var apply = function (a, op, b) {
        return op === '+' ? a + b : Math.max(0, a - b);
    };

    var value = function () {
        var cur = Number(state.entry || 0);
        if (!state.op) return cur;
        return apply(state.acc, state.op, cur);
    };

    function render() {
        var val = value();
        var change = val - state.total;

        totalEl.textContent = fmt(state.total);
        receivedEl.textContent = fmt(val);
        exprEl.textContent = state.op
            ? fmt(state.acc) + ' ' + (state.op === '+' ? '+' : '−') + (state.entry ? ' ' + fmt(Number(state.entry)) : '')
            : '';

        changeLabel.textContent = change < 0 ? 'Kurang' : 'Kembalian';
        changeEl.textContent = fmt(Math.abs(change));
        changeEl.style.color = change < 0 ? '#F87171' : '#4ADE80';

        var kurang = state.total - val;
        if (kurang > 0.5) {
            hintEl.textContent = 'Uang diterima kurang ' + fmt(kurang) + '.';
            hintEl.classList.remove('hidden');
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Uang kurang ' + fmt(kurang);
        } else {
            hintEl.classList.add('hidden');
            confirmBtn.disabled = val <= 0 && state.total > 0;
            confirmBtn.textContent = change > 0.5
                ? 'Terima Tunai — Kembali ' + fmt(change)
                : 'Terima Tunai — Uang Pas';
        }
    }

    function presetsFor(total) {
        var t = Math.round(total);
        var seen = {};
        var list = [];
        var add = function (v, label) {
            v = Math.round(v);
            if (!seen[v]) { seen[v] = true; list.push([v, label]); }
        };

        add(t, 'Uang Pas');
        [5000, 10000, 20000, 50000, 100000].forEach(function (step) {
            var v = Math.ceil(t / step) * step;
            if (v > t) add(v, shortFmt(v));
        });
        [50000, 100000, 150000, 200000].forEach(function (v) {
            if (v >= t) add(v, shortFmt(v));
        });

        list.sort(function (a, b) { return a[0] - b[0]; });
        return list.slice(0, 6);
    }

    function renderPresets() {
        presetsEl.innerHTML = '';
        presetsFor(state.total).forEach(function (item, index) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'calc-preset' + (index === 0 ? ' exact' : '');
            btn.setAttribute('data-preset', String(item[0]));
            btn.textContent = item[1];
            presetsEl.appendChild(btn);
        });
    }

    function press(key) {
        if (/^\d+$/.test(key)) {
            if (state.replace) {
                state.entry = key.replace(/^0+/, '') || '0';
                state.replace = false;
            } else if (state.entry.length < 9) {
                state.entry = (state.entry + key).replace(/^0+(?=\d)/, '');
            }
        } else if (key === 'del') {
            state.entry = state.entry.slice(0, -1);
            state.replace = false;
        } else if (key === 'clear') {
            state.entry = '';
            state.acc = 0;
            state.op = null;
            state.replace = false;
        } else if (key === '+' || key === '-') {
            if (state.entry === '' && state.op) {
                state.op = key;
            } else {
                var cur = Number(state.entry || 0);
                state.acc = state.op ? apply(state.acc, state.op, cur) : cur;
                state.op = key;
                state.entry = '';
            }
            state.replace = false;
        } else if (key === '=') {
            state.acc = value();
            state.entry = String(state.acc);
            state.op = null;
            state.replace = true;
        }
        render();
    }

    function trapFocus(e) {
        var nodes = modal.querySelectorAll('button:not([disabled])');
        var visible = [];
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].offsetParent !== null) visible.push(nodes[i]);
        }
        if (!visible.length) return;
        var first = visible[0];
        var last = visible[visible.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    function open(form) {
        var total = Number(form.getAttribute('data-total') || 0);
        var input = form.querySelector('input[name="jumlah_bayar"]');
        var seed = input && Number(input.value) > 0 ? Math.round(Number(input.value)) : Math.round(total);

        state = { total: total, entry: String(seed), acc: 0, op: null, replace: true, form: form };
        lastFocus = document.activeElement;

        renderPresets();
        render();
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        modalOpen = true;
        window.setTimeout(function () { confirmBtn.focus({ preventScroll: true }); }, 40);
    }

    function close(restoreFocus) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        modalOpen = false;
        state.form = null;
        if (restoreFocus !== false && lastFocus && lastFocus.focus) {
            try { lastFocus.focus({ preventScroll: true }); } catch (err) {}
        }
    }

    function confirmPayment() {
        var form = state.form;
        if (!form) return;

        var val = Math.round(value());
        if (val + 0.5 < state.total) return;

        var input = form.querySelector('input[name="jumlah_bayar"]');
        if (input) input.value = val;

        close(false);
        form.setAttribute('data-cash-bypass', '1');
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function isCash(form) {
        var checked = form.querySelector('input[name="id_metode"]:checked');
        if (checked) return checked.getAttribute('data-tunai') === '1';

        var select = form.querySelector('select[name="id_metode"]');
        if (!select || select.selectedIndex < 0) return false;
        var option = select.options[select.selectedIndex];
        return !!option && option.getAttribute('data-tunai') === '1';
    }

    function syncTriggers(form) {
        var cash = isCash(form);
        var buttons = form.querySelectorAll('[data-calc-open]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].classList.toggle('hidden', !cash);
        }
    }

    var cashForms = document.querySelectorAll('form[data-cash-calc]');
    for (var i = 0; i < cashForms.length; i++) syncTriggers(cashForms[i]);

    document.addEventListener('change', function (e) {
        var target = e.target;
        if (target && target.matches && target.matches('select[name="id_metode"], input[name="id_metode"]')) {
            var form = target.closest('form[data-cash-calc]');
            if (form) syncTriggers(form);
        }
    });

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest ? e.target.closest('[data-calc-open]') : null;
        if (!trigger) return;
        var form = trigger.closest('form');
        if (!form) return;
        e.preventDefault();
        open(form);
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.matches || !form.matches('form[data-cash-calc]')) return;
        if (form.getAttribute('data-cash-bypass') === '1') {
            form.removeAttribute('data-cash-bypass');
            return;
        }
        if (!isCash(form)) return;
        e.preventDefault();
        open(form);
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) { close(); return; }

        var keyBtn = e.target.closest('[data-calc-key]');
        if (keyBtn) {
            e.preventDefault();
            press(keyBtn.getAttribute('data-calc-key'));
            return;
        }

        var presetBtn = e.target.closest('[data-preset]');
        if (presetBtn) {
            e.preventDefault();
            state.entry = presetBtn.getAttribute('data-preset');
            state.acc = 0;
            state.op = null;
            state.replace = true;
            render();
            return;
        }

        if (e.target.closest('[data-calc-close]')) {
            e.preventDefault();
            close();
            return;
        }

        if (e.target.closest('#calc-confirm')) {
            e.preventDefault();
            confirmPayment();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (!modalOpen) return;

        if (e.key === 'Escape') { e.preventDefault(); close(); return; }
        if (e.key === 'Tab') { trapFocus(e); return; }
        if (e.ctrlKey || e.metaKey || e.altKey) return;

        if (e.key === 'Enter') { e.preventDefault(); confirmPayment(); return; }
        if (e.key === 'Backspace') { e.preventDefault(); press('del'); return; }
        if (e.key === 'Delete' || e.key === 'c' || e.key === 'C') { e.preventDefault(); press('clear'); return; }
        if (e.key === '+' || e.key === '=') { e.preventDefault(); press(e.key === '=' ? '=' : '+'); return; }
        if (e.key === '-' || e.key === '–' || e.key === '−') { e.preventDefault(); press('-'); return; }
        if (/^\d$/.test(e.key)) { e.preventDefault(); press(e.key); return; }
        if (e.key === '.' || e.key === ',') { e.preventDefault(); }
    }, true);
})();

/* ── Papan pemilihan meja: status realtime (polling 5 detik) ── */
(function () {
    var board = document.querySelector('[data-meja-board]');
    if (!board) return;

    var grid = board.querySelector('[data-meja-grid]');
    var input = board.querySelector('[data-meja-input]');
    var countsEl = board.querySelector('[data-meja-counts]');
    var selectionEl = board.querySelector('[data-meja-selection]');
    var warningEl = board.querySelector('[data-meja-warning]');
    var updatedEl = board.querySelector('[data-meja-updated]');
    var liveDot = board.querySelector('[data-meja-live-dot]');
    var endpoint = board.getAttribute('data-meja-url');
    var inflight = false;

    function cards() {
        return Array.prototype.slice.call(grid.querySelectorAll('[data-meja-card]'));
    }

    function selectedCard() {
        if (!input.value) return null;
        return grid.querySelector('[data-meja-card][data-meja-id="' + input.value + '"]');
    }

    function syncSelection() {
        var card = selectedCard();
        if (!card) {
            selectionEl.textContent = 'Belum dipilih';
            warningEl.classList.add('hidden');
            return;
        }

        var status = card.getAttribute('data-status');
        var label = card.querySelector('[data-meja-status]').textContent;
        selectionEl.textContent = 'Meja ' + card.getAttribute('data-nomor') + ' • '
            + card.getAttribute('data-kapasitas') + ' orang • ' + label;

        var msg = '';
        if (status === 'terisi') {
            msg = 'Meja ini sedang terisi. Pesanan baru akan digabung ke meja yang sama.';
        } else if (status === 'dipesan') {
            msg = 'Meja ini ditandai terpesan — konfirmasi ke pelanggan sebelum dipakai.';
        }

        if (msg) {
            warningEl.textContent = msg;
            warningEl.classList.remove('hidden');
        } else {
            warningEl.classList.add('hidden');
        }
    }

    function selectCard(card) {
        if (!card) return;
        input.value = card.getAttribute('data-meja-id');
        cards().forEach(function (c) { c.setAttribute('aria-selected', 'false'); });
        card.setAttribute('aria-selected', 'true');
        syncSelection();
    }

    function applyBoard(data) {
        if (!data || !Array.isArray(data.tables)) return;

        data.tables.forEach(function (t) {
            var card = grid.querySelector('[data-meja-card][data-meja-id="' + t.id + '"]');

            if (!card) {
                card = document.createElement('button');
                card.type = 'button';
                card.className = 'meja-card';
                card.setAttribute('data-meja-card', '');
                card.setAttribute('role', 'option');
                card.setAttribute('aria-selected', 'false');
                card.innerHTML = '<span class="meja-card-top"><span class="meja-card-nomor"></span>'
                    + '<span class="meja-dot" aria-hidden="true"></span></span>'
                    + '<span class="meja-card-kapasitas"></span>'
                    + '<span class="meja-card-status" data-meja-status></span>'
                    + '<span class="meja-card-order" data-meja-order hidden></span>';
                grid.appendChild(card);
            }

            card.setAttribute('data-meja-id', t.id);
            card.setAttribute('data-nomor', t.nomor);
            card.setAttribute('data-kapasitas', t.kapasitas);
            card.setAttribute('data-status', t.status);
            card.setAttribute('aria-label', 'Meja ' + t.nomor + ', ' + t.kapasitas + ' orang, ' + t.label);
            card.querySelector('.meja-card-nomor').textContent = t.nomor;
            card.querySelector('.meja-card-kapasitas').textContent = t.kapasitas + ' orang';
            card.querySelector('[data-meja-status]').textContent = t.label;

            var orderEl = card.querySelector('[data-meja-order]');
            var first = t.orders && t.orders[0];
            if (first) {
                var short = String(first.nomor || '').replace(/^PSN-/, '');
                if (short.length > 11) short = short.slice(0, 10) + '…';
                orderEl.textContent = '#' + short + ' • ' + first.since_minutes + 'm'
                    + (t.orders.length > 1 ? ' +' + (t.orders.length - 1) : '');
                orderEl.hidden = false;
            } else {
                orderEl.textContent = '';
                orderEl.hidden = true;
            }
        });

        var ids = data.tables.map(function (t) { return String(t.id); });
        cards().forEach(function (c) {
            if (ids.indexOf(c.getAttribute('data-meja-id')) === -1) c.remove();
        });

        if (countsEl && data.counts) {
            countsEl.innerHTML = '<strong class="text-[#4ADE80]">' + data.counts.kosong + '</strong> kosong • '
                + '<strong class="text-[#FBBF24]">' + data.counts.terisi + '</strong> terisi • '
                + '<strong class="text-[#E0A96D]">' + data.counts.dipesan + '</strong> dipesan';
        }

        if (updatedEl) updatedEl.textContent = 'Live' + (data.updated_at ? ' • ' + data.updated_at : '');
        if (liveDot) liveDot.classList.remove('stale');

        cards().forEach(function (c) {
            c.setAttribute('aria-selected', c.getAttribute('data-meja-id') === String(input.value) ? 'true' : 'false');
        });
        syncSelection();
    }

    function refresh() {
        if (!endpoint || inflight || document.hidden || board.classList.contains('hidden')) return;

        inflight = true;
        fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(applyBoard)
            .catch(function () {
                if (liveDot) liveDot.classList.add('stale');
                if (updatedEl) updatedEl.textContent = 'Offline';
            })
            .finally(function () { inflight = false; });
    }

    grid.addEventListener('click', function (e) {
        var card = e.target.closest ? e.target.closest('[data-meja-card]') : null;
        if (card && grid.contains(card)) selectCard(card);
    });

    grid.addEventListener('keydown', function (e) {
        if (['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'].indexOf(e.key) === -1) return;

        var list = cards();
        var index = list.indexOf(document.activeElement);
        if (index < 0) return;

        var cols = getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length || 1;
        var next = index;

        if (e.key === 'ArrowRight') next = Math.min(list.length - 1, index + 1);
        if (e.key === 'ArrowLeft') next = Math.max(0, index - 1);
        if (e.key === 'ArrowDown') next = Math.min(list.length - 1, index + cols);
        if (e.key === 'ArrowUp') next = Math.max(0, index - cols);

        e.preventDefault();
        if (list[next]) list[next].focus();
    });

    input.addEventListener('change', function () {
        var card = selectedCard();
        if (card) selectCard(card);
        else syncSelection();
    });

    Array.prototype.forEach.call(document.querySelectorAll('input[name="tipe_pesanan"]'), function (radio) {
        radio.addEventListener('change', function () {
            if (radio.checked && radio.value === 'dine-in') {
                window.setTimeout(refresh, 60);
            }
        });
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refresh();
    });

    syncSelection();
    window.setInterval(refresh, 5000);
    refresh();
})();

/* ── Midtrans: QRIS & transfer bank ── */
(function () {
    var modal = document.getElementById('midtrans-modal');
    if (!modal) return;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

    function q(sel) { return modal.querySelector(sel); }
    function qa(sel) { return Array.prototype.slice.call(modal.querySelectorAll(sel)); }
    function idr(n) { return 'IDR ' + new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0)); }

    var state = {
        form: null,
        tab: 'qris',
        bank: 'bca',
        payment: null,
        expires: null,
        poll: null,
        ticker: null,
        reloadTimer: null,
        busy: false,
        paid: false,
        lastFocus: null
    };

    function formCode(form) {
        var checked = form ? form.querySelector('input[name="id_metode"]:checked') : null;
        if (checked) return checked.getAttribute('data-kode') || '';

        var select = form ? form.querySelector('[data-metode-select]') : null;
        if (!select || select.selectedIndex < 0) return '';
        var option = select.options[select.selectedIndex];
        return option ? (option.getAttribute('data-kode') || '') : '';
    }

    function showTab(tab) {
        state.tab = tab;
        qa('[data-mt-tab]').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-mt-tab') === tab);
        });
        qa('[data-mt-panel]').forEach(function (panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-mt-panel') !== tab);
        });
    }

    function setAlert(message) {
        var box = q('[data-mt-alert]');
        if (!message) {
            box.classList.add('hidden');
            box.textContent = '';
            return;
        }
        box.textContent = message;
        box.classList.remove('hidden');
    }

    function setState(text, color) {
        q('[data-mt-state]').textContent = text;
        q('[data-mt-state-dot]').style.background = color;
    }

    function setMessage(text) {
        q('[data-mt-message]').textContent = text;
    }

    function resetUi() {
        setAlert('');
        setState('Menunggu Pembayaran', '#FBBF24');
        setMessage('Status diperiksa otomatis.');
        q('[data-mt-countdown]').textContent = '';
        q('[data-mt-paid]').classList.add('hidden');
        q('[data-mt-flow]').classList.remove('hidden');
        q('[data-mt-qris-loading]').classList.add('hidden');
        q('[data-mt-qris-result]').classList.add('hidden');
        q('[data-mt-qris-empty]').classList.remove('hidden');
        q('[data-mt-va-form]').classList.remove('hidden');
        q('[data-mt-va-loading]').classList.add('hidden');
        q('[data-mt-va-result]').classList.add('hidden');
        q('[data-mt-mandiri]').classList.add('hidden');
        q('[data-mt-new-va]').classList.add('hidden');
        q('[data-mt-qr-img]').removeAttribute('src');
        q('[data-mt-va]').textContent = '—';
        state.bank = 'bca';
        state.payment = null;
        state.expires = null;
        state.paid = false;
        qa('[data-mt-bank]').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-mt-bank') === 'bca');
        });
    }

    function stopPolling() {
        if (state.poll) { clearInterval(state.poll); state.poll = null; }
    }

    function stopTicker() {
        if (state.ticker) { clearInterval(state.ticker); state.ticker = null; }
    }

    function tick() {
        var el = q('[data-mt-countdown]');
        if (!state.expires) { el.textContent = ''; return; }

        var left = state.expires - Date.now();
        if (left <= 0) {
            el.textContent = 'Kedaluwarsa';
            if (!state.paid) setState('Kedaluwarsa', '#F87171');
            return;
        }

        var minutes = Math.floor(left / 60000);
        var seconds = Math.floor((left % 60000) / 1000);
        el.textContent = 'Sisa ' + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }

    function startCountdown() {
        stopTicker();
        tick();
        state.ticker = window.setInterval(tick, 1000);
    }

    function handlePaid() {
        if (state.paid) return;
        state.paid = true;
        stopPolling();
        stopTicker();
        q('[data-mt-flow]').classList.add('hidden');
        q('[data-mt-paid]').classList.remove('hidden');
        q('[data-mt-countdown]').textContent = '';
        setState('Lunas', '#4ADE80');
        setMessage('Pembayaran terverifikasi oleh Midtrans.');
        setAlert('');

        state.reloadTimer = window.setTimeout(function () {
            window.location.reload();
        }, 2200);
    }

    function handleFailed() {
        stopTicker();
        q('[data-mt-countdown]').textContent = '';
        setState('Pembayaran gagal', '#F87171');
        setMessage('Pembayaran gagal atau kedaluwarsa. Buat ulang untuk mencoba lagi.');
        q('[data-mt-new-va]').classList.remove('hidden');
    }

    function renderPayment(payment, statusOverride) {
        if (!payment) return;
        state.payment = payment;

        if (payment.expires_at) {
            state.expires = Date.parse(payment.expires_at);
        }

        var status = statusOverride || payment.status || 'menunggu';
        var method = payment.method || (payment.payment_type === 'qris' ? 'qris' : 'transfer');

        if (method === 'transfer') {
            showTab('transfer');
            q('[data-mt-va-form]').classList.add('hidden');
            q('[data-mt-va-loading]').classList.add('hidden');
            q('[data-mt-va-result]').classList.remove('hidden');
            q('[data-mt-bank-name]').textContent = (payment.bank || state.bank || 'BANK').toUpperCase();
            q('[data-mt-va]').textContent = payment.va_number || 'Membuat nomor…';

            var mandiri = !!(payment.biller_code || payment.bill_key);
            q('[data-mt-mandiri]').classList.toggle('hidden', !mandiri);
            if (mandiri) {
                q('[data-mt-biller]').textContent = payment.biller_code || '—';
                q('[data-mt-billkey]').textContent = payment.bill_key || '—';
            }
        } else {
            showTab('qris');
            q('[data-mt-qris-loading]').classList.add('hidden');
            q('[data-mt-qris-empty]').classList.add('hidden');
            q('[data-mt-qris-result]').classList.remove('hidden');

            var img = q('[data-mt-qr-img]');
            if (payment.qr_url) {
                img.src = payment.qr_url;
                img.classList.remove('hidden');
            } else {
                img.removeAttribute('src');
                img.classList.add('hidden');
            }
        }

        startCountdown();

        if (status === 'lunas') {
            handlePaid();
        } else if (status === 'gagal') {
            handleFailed();
        } else {
            setState('Menunggu Pembayaran', '#FBBF24');
        }
    }

    function requestJson(url, options) {
        return fetch(url, options).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok || data.status === 'error') {
                    throw new Error(data.message || 'Permintaan gagal (HTTP ' + res.status + ').');
                }
                return data;
            });
        });
    }

    function createPayment(kind) {
        if (!state.form || state.busy) return;
        state.busy = true;
        setAlert('');

        if (kind === 'qris') {
            showTab('qris');
            q('[data-mt-qris-empty]').classList.add('hidden');
            q('[data-mt-qris-result]').classList.add('hidden');
            q('[data-mt-qris-loading]').classList.remove('hidden');
        } else {
            showTab('transfer');
            q('[data-mt-va-form]').classList.add('hidden');
            q('[data-mt-va-result]').classList.add('hidden');
            q('[data-mt-va-loading]').classList.remove('hidden');
        }

        var url = kind === 'qris'
            ? state.form.getAttribute('data-qris-url')
            : state.form.getAttribute('data-transfer-url');
        var body = kind === 'transfer' ? JSON.stringify({ bank: state.bank }) : JSON.stringify({});

        requestJson(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: body
        }).then(function (data) {
            renderPayment(data.payment);
            startPolling();
            if (data.reused && data.payment && data.payment.method !== kind) {
                setMessage('Masih ada pembayaran ' + (data.payment.method === 'qris' ? 'QRIS' : 'transfer')
                    + ' yang belum selesai. Selesaikan dulu atau tunggu kedaluwarsa.');
            }
            checkStatus();
        }).catch(function (err) {
            if (kind === 'qris') {
                q('[data-mt-qris-loading]').classList.add('hidden');
                q('[data-mt-qris-empty]').classList.remove('hidden');
            } else {
                q('[data-mt-va-loading]').classList.add('hidden');
                q('[data-mt-va-form]').classList.remove('hidden');
            }
            setAlert(err.message);
            setState('Belum dibuat', '#F87171');
        }).finally(function () {
            state.busy = false;
        });
    }

    function checkStatus() {
        if (!state.form || !state.payment || state.paid) return;

        var url = state.form.getAttribute('data-status-url');
        if (!url) return;

        requestJson(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        }).then(function (data) {
            if (data.payment) renderPayment(data.payment, data.payment_status);
            if (data.payment_status === 'lunas') handlePaid();
            else if (data.payment_status === 'gagal') handleFailed();
            if (data.remote_error) {
                setMessage('Belum bisa menghubungi Midtrans. Status dicek ulang otomatis.');
            }
        }).catch(function () {
            setMessage('Gagal memeriksa status. Mencoba lagi…');
        });
    }

    function startPolling() {
        stopPolling();
        state.poll = window.setInterval(checkStatus, 4000);
    }

    function copyText(text) {
        var label = q('[data-mt-copy-label]');
        var done = function () {
            label.textContent = 'Tersalin ✓';
            window.setTimeout(function () { label.textContent = 'Salin Nomor'; }, 1600);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, done) {
        var area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
        document.body.removeChild(area);
    }

    function open(form) {
        state.form = form;
        state.lastFocus = document.activeElement;
        resetUi();
        stopPolling();
        stopTicker();
        if (state.reloadTimer) { clearTimeout(state.reloadTimer); state.reloadTimer = null; }

        q('[data-mt-nomor]').textContent = form.getAttribute('data-nomor') || '—';
        q('[data-mt-total]').textContent = idr(form.getAttribute('data-total'));

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        var code = formCode(form);
        var kind = code === 'TRANSFER' ? 'transfer' : 'qris';
        showTab(kind);

        window.setTimeout(function () {
            if (state.payment) return;
            if (kind === 'transfer') {
                q('[data-mt-va-form]').classList.remove('hidden');
                var active = q('[data-mt-bank].active') || q('[data-mt-bank]');
                if (active) active.focus();
            } else {
                createPayment('qris');
            }
        }, 80);

        // Lanjutkan pembayaran yang sudah dibuat sebelumnya (jika ada).
        var statusUrl = form.getAttribute('data-status-url');
        if (statusUrl) {
            requestJson(statusUrl, {
                method: 'GET', credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json' }
            }).then(function (data) {
                if (data.payment && data.payment.order_id && data.payment_status === 'menunggu' && !state.paid) {
                    renderPayment(data.payment, data.payment_status);
                    startPolling();
                }
            }).catch(function () {});
        }
    }

    function close() {
        stopPolling();
        stopTicker();
        if (state.reloadTimer) { clearTimeout(state.reloadTimer); state.reloadTimer = null; }
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        state.form = null;
        state.payment = null;
        state.paid = false;
        if (state.lastFocus && state.lastFocus.focus) {
            try { state.lastFocus.focus({ preventScroll: true }); } catch (err) {}
        }
    }

    function trapFocus(e) {
        var focusables = qa('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
            .filter(function (el) { return !el.disabled && el.offsetParent !== null; });
        if (!focusables.length) return;

        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest ? e.target.closest('[data-midtrans-open]') : null;
        if (!trigger) return;
        var form = trigger.closest('form[data-pay-form]');
        if (!form) return;
        e.preventDefault();
        open(form);
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) { close(); return; }
        if (e.target.closest('[data-mt-close]')) { e.preventDefault(); close(); return; }

        var tab = e.target.closest('[data-mt-tab]');
        if (tab) {
            showTab(tab.getAttribute('data-mt-tab'));
            if (state.payment && state.payment.method && state.tab !== state.payment.method && !state.paid) {
                setMessage('Masih ada pembayaran ' + (state.payment.method === 'qris' ? 'QRIS' : 'transfer')
                    + ' yang belum selesai. Selesaikan dulu atau tunggu kedaluwarsa.');
            }
            return;
        }

        var bank = e.target.closest('[data-mt-bank]');
        if (bank) {
            state.bank = bank.getAttribute('data-mt-bank');
            qa('[data-mt-bank]').forEach(function (btn) {
                btn.classList.toggle('active', btn === bank);
            });
            return;
        }

        var create = e.target.closest('[data-mt-create]');
        if (create) {
            e.preventDefault();
            createPayment(create.getAttribute('data-mt-create'));
            return;
        }

        if (e.target.closest('[data-mt-check]')) { e.preventDefault(); checkStatus(); return; }

        if (e.target.closest('[data-mt-copy]')) {
            e.preventDefault();
            if (state.payment && state.payment.va_number) copyText(state.payment.va_number);
            return;
        }

        if (e.target.closest('[data-mt-new-va]')) {
            e.preventDefault();
            state.payment = null;
            state.expires = null;
            q('[data-mt-va-result]').classList.add('hidden');
            q('[data-mt-va-form]').classList.remove('hidden');
            setState('Menunggu Pembayaran', '#FBBF24');
            setMessage('Pilih bank lalu buat nomor pembayaran baru.');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (modal.classList.contains('hidden')) return;
        if (e.key === 'Escape') { e.preventDefault(); close(); return; }
        if (e.key === 'Tab') trapFocus(e);
    }, true);

    // Tampilkan panel yang sesuai dengan metode bayar terpilih.
    function syncPanels() {
        Array.prototype.forEach.call(document.querySelectorAll('form[data-pay-form]'), function (form) {
            var code = formCode(form);
            var nonCash = code === 'QRIS' || code === 'TRANSFER';

            var panel = form.querySelector('[data-midtrans-panel]');
            if (panel) {
                panel.classList.toggle('hidden', !nonCash);
                if (nonCash) {
                    var caption = panel.querySelector('[data-midtrans-caption]');
                    var cta = panel.querySelector('[data-midtrans-cta]');
                    if (caption) caption.textContent = code === 'QRIS' ? 'Bayar QRIS lewat Midtrans' : 'Transfer lewat Midtrans';
                    if (cta) cta.textContent = code === 'QRIS' ? 'Buat Kode QR' : 'Buat Nomor Pembayaran';
                }
            }

            var cashPanel = form.querySelector('[data-cash-panel]');
            if (cashPanel) cashPanel.classList.toggle('hidden', nonCash);

            var submit = form.querySelector('[data-pay-submit]');
            if (submit) submit.textContent = nonCash ? 'Tandai Sudah Dibayar' : 'Bayar Tunai';

            var hint = form.querySelector('[data-pay-hint]');
            if (hint) {
                hint.textContent = nonCash
                    ? 'Buka pembayaran di atas. Tombol ini hanya untuk mencatat pembayaran manual.'
                    : 'Masukkan uang yang diterima, kembalian dihitung otomatis.';
            }

            var calcBtn = form.querySelector('[data-calc-open]');
            if (calcBtn) calcBtn.classList.toggle('hidden', code !== 'CASH');
        });
    }

    function syncDraftHint() {
        var checked = document.querySelector('input[name="metode"]:checked');
        var hint = document.querySelector('[data-metode-hint]');
        if (!checked || !hint) return;

        hint.textContent = checked.value === 'qris'
            ? 'Kode QR muncul setelah pesanan dibuat, lalu dibayar lewat Midtrans.'
            : (checked.value === 'transfer'
                ? 'Nomor pembayaran muncul setelah pesanan dibuat, lalu dibayar lewat Midtrans.'
                : 'Tunai dibayar langsung di kasir.');
    }

    syncPanels();
    syncDraftHint();

    document.addEventListener('change', function (e) {
        if (!e.target || !e.target.matches) return;
        if (e.target.matches('[data-metode-select], input[name="id_metode"]')) syncPanels();
        if (e.target.matches('input[name="metode"]')) syncDraftHint();
    });

    // Setelah "Buat Pesanan" dengan QRIS/Transfer, langsung buka pembayaran.
    (function autoOpen() {
        var params = new URLSearchParams(window.location.search);
        var bayar = (params.get('bayar') || '').toLowerCase();
        if (bayar !== 'qris' && bayar !== 'transfer') return;

        var ticketId = params.get('ticket');
        var target = null;
        Array.prototype.forEach.call(document.querySelectorAll('form[data-pay-form]'), function (form) {
            if (target) return;
            if (!ticketId || form.getAttribute('data-pesanan') === ticketId) target = form;
        });

        if (!target) return;
        var code = bayar === 'qris' ? 'QRIS' : 'TRANSFER';
        window.setTimeout(function () {
            if (formCode(target) !== code) return;
            open(target);

            try {
                var url = new URL(window.location.href);
                url.searchParams.delete('bayar');
                window.history.replaceState({}, '', url);
            } catch (err) {}
        }, 350);
    })();
})();
</script>
<script>if (window.lucide) lucide.createIcons();</script>
@endpush
@endsection
