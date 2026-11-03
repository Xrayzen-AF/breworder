@extends('layouts.app')

@section('title', 'Status Pesanan — Tejas Coffee')

@php
    $langkah = [
        'baru' => ['label' => 'Pesanan Diterima', 'ikon' => 'receipt-text', 'urutan' => 1, 'catatan' => 'Pesananmu masuk ke antrean barista'],
        'diproses' => ['label' => 'Sedang Dibuat', 'ikon' => 'cooking-pot', 'urutan' => 2, 'catatan' => 'Barista sedang meracik pesananmu'],
        'quality' => ['label' => 'Quality Check', 'ikon' => 'badge-check', 'urutan' => 3, 'catatan' => 'Pengecekan akhir sebelum disajikan'],
        'selesai' => ['label' => 'Siap Diambil', 'ikon' => 'shopping-bag', 'urutan' => 4, 'catatan' => 'Silakan ambil pesananmu di bar'],
    ];
    $urutanSaatIni = match ($pesanan->status_pesanan) {
        'baru' => 1,
        'diproses' => 2,
        default => 4,
    };
    $dibatalkan = $pesanan->status_pesanan === 'dibatalkan';
    $eta = null;
    if (! $dibatalkan && $pesanan->status_pesanan !== 'selesai' && $pesanan->waktu_pesan) {
        $eta = max(1, 8 - (int) $pesanan->waktu_pesan->diffInMinutes(now()));
    }
    $jmlItem = $pesanan->detailPesanan->sum('jumlah');
    $stBayar = $pesanan->transaksi?->status_pembayaran ?? 'menunggu';
    $bisaBatal = in_array($pesanan->status_pesanan, ['baru', 'diproses'], true) && $stBayar !== 'lunas';
@endphp

@section('content')
<style>
  .st {
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    background: #1E1511;
    color: #F1DFD8;
    font-feature-settings: "cv11", "ss01";
    letter-spacing: -0.005em;
  }
  .st h1, .st h2, .st h3 { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }

  /* Garis & permukaan: hairline 0.5px dengan warna cokelat redup */
  .st-card,
  .head-card {
    background: #241B16;
    border: 0.5px solid rgba(138, 102, 82, 0.32);
    border-radius: var(--radius-surface);
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.28);
  }
  .head-card {
    border-color: rgba(217, 119, 6, 0.30);
    background:
      radial-gradient(120% 140% at 100% 0%, rgba(217, 119, 6, 0.10), transparent 58%),
      #241B16;
  }

  .st-eyebrow {
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #C79B74;
  }
  .st-label {
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.13em;
    text-transform: uppercase;
    color: #9D8E87;
  }
  .st-order {
    font-size: clamp(17px, 4.2vw, 20px);
    font-weight: 600;
    letter-spacing: -0.015em;
    color: #F1DFD8;
    overflow-wrap: anywhere;
  }
  .st-total {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: #F1DFD8;
    font-variant-numeric: tabular-nums;
  }
  .st-meta {
    font-size: 12px;
    line-height: 1.65;
    color: #B8A8A1;
  }

  /* Badge status */
  .st-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 0.5px solid transparent;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.15;
    letter-spacing: -0.003em;
    white-space: nowrap;
  }
  .st-badge svg { width: 13px; height: 13px; flex: none; }
  .st-badge--wait { background: rgba(245, 158, 11, 0.10); border-color: rgba(245, 158, 11, 0.30); color: #EFBE74; }
  .st-badge--paid { background: rgba(74, 222, 128, 0.09); border-color: rgba(74, 222, 128, 0.28); color: #86E0A4; }
  .st-badge--void { background: rgba(248, 113, 113, 0.08); border-color: rgba(248, 113, 113, 0.26); color: #EFA2A2; }
  .st-badge--eta  { background: rgba(217, 119, 6, 0.08); border-color: rgba(217, 119, 6, 0.26); color: #DDA878; }

  /* Timeline */
  .st-steps { margin-top: 18px; }
  .st-step { display: flex; gap: 14px; }
  .st-step-rail { display: flex; flex-direction: column; align-items: center; }
  .st-step-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: 0.5px solid rgba(138, 102, 82, 0.38);
    background: #201912;
    color: #82746E;
  }
  .st-step-dot svg { width: 15px; height: 15px; }
  .st-step-dot--done { background: rgba(217, 119, 6, 0.10); border-color: rgba(217, 119, 6, 0.40); color: #D9A165; }
  .st-step-dot--now  { background: rgba(217, 119, 6, 0.16); border-color: rgba(217, 119, 6, 0.55); color: #F0B063; }
  .st-step-dot--idle { opacity: 0.38; }
  .st-step-line {
    width: 1.5px;
    flex: 1;
    min-height: 26px;
    margin: 6px 0;
    border-radius: 999px;
    background: rgba(138, 102, 82, 0.20);
  }
  .st-step-line.is-done { background: rgba(217, 119, 6, 0.30); }
  .st-step-body { padding: 8px 0 20px; min-width: 0; }
  .st-step:last-child .st-step-body { padding-bottom: 0; }
  .st-step-label { font-size: 13px; font-weight: 600; letter-spacing: -0.008em; color: #8C7C74; }
  .st-step-label--done { color: #CDBAB1; }
  .st-step-label--now { color: #F1DFD8; }
  .st-step-label--idle { color: #9D8E87; opacity: 0.42; }
  .st-step-note { margin-top: 3px; font-size: 11.5px; line-height: 1.55; color: #9D8E87; }
  .st-pulse {
    width: 5px; height: 5px; border-radius: 999px; background: #D97706;
    box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.14);
    animation: st-pulse 1.8s ease-in-out infinite;
  }
  @keyframes st-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }

  /* Kode ambil */
  .st-code {
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.14em;
    color: #F1DFD8;
    background: #201912;
    border: 0.5px solid rgba(138, 102, 82, 0.30);
    border-radius: var(--radius-control);
    padding: 12px 14px;
    text-align: center;
    font-variant-numeric: tabular-nums;
  }
  .st details summary > svg { transition: transform .18s ease; }
  .st details[open] summary > svg { transform: rotate(180deg); }

  /* Banner siap diambil */
  #siap-banner:not(.hidden) { animation: st-ready-glow 2.4s ease-in-out infinite; }
  @keyframes st-ready-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); }
    50% { box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.10); }
  }

  /* Buttons */
  .st-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%;
    padding: 12px 16px;
    border-radius: var(--radius-control);
    border: 0.5px solid transparent;
    font-size: 13px; font-weight: 600; letter-spacing: -0.003em;
    transition: background-color .16s ease, border-color .16s ease, color .16s ease;
    cursor: pointer;
  }
  .st-btn svg { width: 15px; height: 15px; }
  .st-btn-primary { background: #D97706; color: #241708; }
  .st-btn-primary:hover { background: #E38813; }
  .st-btn-ghost { background: transparent; border-color: rgba(138, 102, 82, 0.40); color: #C9B7AE; }
  .st-btn-ghost:hover { border-color: rgba(138, 102, 82, 0.62); color: #F1DFD8; }

  /* Layout responsif */
  .st-wrap { width: 100%; max-width: 480px; margin-inline: auto; }
  @media (min-width: 768px) { .st-wrap { max-width: 720px; } }
  @media (min-width: 1024px) { .st-wrap { max-width: 1040px; } }
  @media (min-width: 1280px) { .st-wrap { max-width: 1120px; } }

  .st-layout { display: grid; gap: 16px; align-items: start; }
  @media (min-width: 1024px) {
    .st-layout { grid-template-columns: minmax(0, 430px) minmax(0, 1fr); gap: 24px; }
    .st-left { position: sticky; top: 16px; }
  }
</style>

<div class="st min-h-screen pb-32"
     data-live-url="{{ route('pelanggan.pesanan.status-live', $pesanan->id_pesanan) }}"
     data-status-awal="{{ $pesanan->status_pesanan }}"
     data-bayar-awal="{{ $stBayar }}">
    <div class="st-wrap px-4 pt-5">
        <div class="st-layout">
            {{-- ═══ KOLOM KIRI: ringkasan + progres ═══ --}}
            <div class="st-left space-y-4">
                {{-- Header pesanan --}}
                <div class="head-card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                        <div class="min-w-0">
                            <p class="st-eyebrow">Status Pesanan</p>
                            <h1 class="st-order mt-1">{{ $pesanan->nomor_pesanan }}</h1>
                        </div>
                        @if ($eta)
                            <span id="eta-badge" class="st-badge st-badge--eta">
                                <i data-lucide="clock"></i>
                                Tiba ~{{ $eta }} mnt
                            </span>
                        @endif
                        <span id="siap-badge"
                              class="st-badge st-badge--paid {{ $pesanan->status_pesanan === 'selesai' ? '' : 'hidden' }}">
                            <i data-lucide="check"></i>
                            Siap diambil
                        </span>
                    </div>

                    <p class="st-meta mt-2">
                        {{ ucfirst($pesanan->tipe_pesanan) }}
                        @if ($pesanan->meja) • Meja {{ $pesanan->meja->nomor_meja }} @endif
                        • {{ $pesanan->waktu_pesan?->format('d M Y, H:i') }} • {{ $jmlItem }} item
                    </p>

                    <div class="mt-4 flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
                        <div>
                            <p class="st-label">Total</p>
                            <p class="st-total mt-1">Rp {{ number_format($pesanan->total_harga, 0, ',', '.') }}</p>
                        </div>
                        <div id="badge-bayar">
                            @if ($stBayar === 'lunas')
                                <span class="st-badge st-badge--paid">
                                    <i data-lucide="check"></i>
                                    Lunas
                                </span>
                            @elseif ($dibatalkan)
                                <span class="st-badge st-badge--void">
                                    <i data-lucide="ban"></i>
                                    Pembayaran dibatalkan
                                </span>
                            @else
                                <span class="st-badge st-badge--wait">
                                    <i data-lucide="clock"></i>
                                    Menunggu pembayaran di kasir
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Banner siap diambil (muncul otomatis saat status berubah) --}}
                <div id="siap-banner"
                     class="st-card {{ $pesanan->status_pesanan === 'selesai' ? '' : 'hidden' }} border-[0.5px] border-[#D97706]/45 p-5 text-center"
                     role="status" aria-live="assertive">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border-[0.5px] border-[#D97706]/50 bg-[#D97706]/15 text-[#F0B063]">
                        <i data-lucide="bell-ring" class="h-5 w-5"></i>
                    </span>
                    <p class="mt-3 text-[15px] font-semibold tracking-[-0.01em] text-[#F1DFD8]">Pesanan siap diambil!</p>
                    <p class="mt-1 text-xs leading-relaxed text-[#B8A8A1]">Silakan ambil pesananmu di bar dan tunjukkan nomor pesanan ini.</p>
                    <button type="button" id="suara-toggle" class="st-btn st-btn-primary mt-3 hidden">
                        <i data-lucide="volume-2"></i>
                        Nyalakan nada dering
                    </button>
                    <p id="suara-status" class="mt-2 text-[11px] text-[#9D8E87]"></p>
                </div>

                @if (session('error'))
                    <div class="st-card border-[0.5px] border-[#BA1A1A]/30 p-4 text-[13px] font-medium text-[#F0A3A3]">{{ session('error') }}</div>
                @endif
                @if (session('success'))
                    <div class="st-card border-[0.5px] border-[#16A34A]/30 p-4 text-[13px] font-medium text-[#86E0A4]">{{ session('success') }}</div>
                @endif

                @if ($dibatalkan)
                    <div class="st-card p-6 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border-[0.5px] border-[#BA1A1A]/30 bg-[#BA1A1A]/[.07] text-[#EFA2A2]">
                            <i data-lucide="ban" class="h-5 w-5"></i>
                        </span>
                        <p class="mt-3 text-[15px] font-semibold tracking-[-0.01em] text-[#F1DFD8]">Pesanan dibatalkan</p>
                        <p class="mt-1 text-xs leading-relaxed text-[#9D8E87]">Silakan buat pesanan baru atau hubungi kasir.</p>
                    </div>
                @else
                    {{-- Progres --}}
                    <div class="st-card p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="st-label">Progres Pesanan</h2>
                            <span id="langkah-counter" class="text-[11px] font-medium text-[#8C7C74]">
                                Langkah {{ $urutanSaatIni }} dari {{ count($langkah) }}
                            </span>
                        </div>

                        <ol class="st-steps">
                            @foreach (array_values($langkah) as $idx => $l)
                                @php
                                    $done = $urutanSaatIni > $l['urutan'];
                                    $now = $urutanSaatIni === $l['urutan'];
                                    $state = $done ? 'done' : ($now ? 'now' : 'idle');
                                @endphp
                                <li class="st-step" data-step="{{ $l['urutan'] }}" data-note="{{ $l['catatan'] }}">
                                    <div class="st-step-rail">
                                        <span class="st-step-dot st-step-dot--{{ $state }}" data-step-dot>
                                            @if ($done)
                                                <i data-lucide="check"></i>
                                            @else
                                                <i data-lucide="{{ $l['ikon'] }}"></i>
                                            @endif
                                        </span>
                                        @if ($idx < count($langkah) - 1)
                                            <span class="st-step-line {{ $urutanSaatIni > $l['urutan'] ? 'is-done' : '' }}" data-step-line></span>
                                        @endif
                                    </div>
                                    <div class="st-step-body">
                                        <div class="flex items-center gap-2">
                                            <p class="st-step-label st-step-label--{{ $state }}" data-step-label>{{ $l['label'] }}</p>
                                            @if ($now)
                                                <span class="st-pulse" data-step-pulse></span>
                                            @endif
                                        </div>
                                        <p class="st-step-note" data-step-note @if (! $now && ! $done) hidden @endif>{{ $now ? $l['catatan'] : ($done ? 'Selesai' : '') }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- Kode ambil --}}
                    <details class="st-card p-4">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-[12px] font-semibold text-[#D8C6BD]">
                                <i data-lucide="qr-code" class="h-4 w-4"></i>
                                Kode ambil
                            </span>
                            <i data-lucide="chevron-down" class="h-4 w-4 text-[#8C7C74]"></i>
                        </summary>
                        <p class="mt-3 text-[11.5px] leading-relaxed text-[#9D8E87]">Tunjukkan kode ini di bar / kasir.</p>
                        <p class="st-code mt-2">{{ $pesanan->nomor_pesanan }}</p>
                    </details>
                @endif
            </div>

            {{-- ═══ KOLOM KANAN: rincian + aksi ═══ --}}
            <div class="st-right space-y-4">
                {{-- Detail item --}}
                <div class="st-card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="st-label">Detail Item</h2>
                        <span class="text-[11px] font-medium text-[#8C7C74]">{{ $jmlItem }} item</span>
                    </div>

                    <ul class="mt-4 divide-y divide-[rgba(138,102,82,0.22)]">
                        @foreach ($pesanan->detailPesanan as $d)
                            <li class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <p class="text-[13px] font-medium leading-snug text-[#E8D8D0]">
                                        <span class="mr-1.5 text-[12px] font-semibold tabular-nums text-[#C79B74]">{{ $d->jumlah }}×</span>{{ $d->produk?->nama_produk ?? 'Produk #' . $d->id_produk }}
                                    </p>
                                    @if ($d->catatan_item)
                                        <p class="mt-1 text-[11px] leading-relaxed text-[#9D8E87]">{{ $d->catatan_item }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 text-[12.5px] font-semibold tabular-nums text-[#D8C6BD]">Rp {{ number_format($d->subtotal, 0, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @if ((float) ($pesanan->diskon ?? 0) > 0)
                        <div class="mt-4 border-t-[0.5px] border-dashed border-[rgba(138,102,82,0.34)] pt-4">
                            <div class="flex items-center justify-between text-[12.5px]">
                                <span class="text-[#9D8E87]">Diskon</span>
                                <span class="font-semibold tabular-nums text-[#EFA2A2]">−Rp {{ number_format($pesanan->diskon, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                <a href="{{ route('struk.show', $pesanan) }}" target="_blank" rel="noopener" class="st-btn st-btn-ghost">
                    <i data-lucide="receipt-text"></i>
                    Lihat struk
                </a>

                <a href="{{ route('pelanggan.menu') }}" class="st-btn st-btn-primary">
                    <i data-lucide="plus"></i>
                    Pesan lagi
                </a>

                @if ($bisaBatal)
                    <form id="batal-wrap" method="POST" action="{{ route('pelanggan.pesanan.batal', $pesanan->id_pesanan) }}" data-confirm="Batalkan pesanan ini?">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="st-btn st-btn-ghost">
                            <i data-lucide="x"></i>
                            Batalkan pesanan
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Bottom nav --}}
    @include('pelanggan._nav')
</div>

@push('scripts')
<script>
(function () {
    var root = document.querySelector('.st');
    if (!root) return;

    if (window.lucide) lucide.createIcons();

    var liveUrl = root.getAttribute('data-live-url');
    var statusSekarang = root.getAttribute('data-status-awal') || 'baru';
    var bayarSekarang = root.getAttribute('data-bayar-awal') || 'menunggu';
    var sudahDiingatkan = false;
    var audioCtx = null;
    var judulAsli = document.title;

    var banner = document.getElementById('siap-banner');
    var badgeSiap = document.getElementById('siap-badge');
    var badgeEta = document.getElementById('eta-badge');
    var tombolSuara = document.getElementById('suara-toggle');
    var statusSuara = document.getElementById('suara-status');
    var jumlahLangkah = document.querySelectorAll('[data-step]').length || 4;

    /* ── Nada dering (Web Audio, tanpa file audio) ── */
    function initAudio() {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        if (!audioCtx) audioCtx = new Ctx();
        return audioCtx;
    }

    function unlockAudio() {
        var ctx = initAudio();
        if (!ctx) return Promise.resolve(false);
        if (ctx.state === 'running') return Promise.resolve(true);
        if (!ctx.resume) return Promise.resolve(false);

        return ctx.resume()
            .then(function () { return ctx.state === 'running'; })
            .catch(function () { return false; });
    }

    function mainkanNada() {
        var ctx = initAudio();
        if (!ctx || ctx.state !== 'running') return false;

        var now = ctx.currentTime;
        [0, 1.15].forEach(function (offset) {
            [783.99, 987.77, 1318.51].forEach(function (freq, i) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                var start = now + offset + (i * 0.28);

                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(0.26, start + 0.035);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.30);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(start);
                osc.stop(start + 0.32);
            });
        });

        return true;
    }

    function bunyikanNadaSiap() {
        if (sudahDiingatkan) return;
        if (mainkanNada()) {
            sudahDiingatkan = true;
            if (tombolSuara) tombolSuara.classList.add('hidden');
            if (statusSuara) statusSuara.textContent = 'Nada dering aktif.';
        }
    }

    function siapkanSuara() {
        unlockAudio().then(function (ok) {
            if (!ok) return;
            if (tombolSuara) tombolSuara.classList.add('hidden');
            if (statusSekarang === 'selesai') bunyikanNadaSiap();
        });
    }

    // Browser hanya mengizinkan audio setelah interaksi pengguna.
    document.addEventListener('pointerdown', siapkanSuara);
    document.addEventListener('keydown', siapkanSuara);

    if (tombolSuara) {
        tombolSuara.addEventListener('click', function (event) {
            event.stopPropagation();
            unlockAudio().then(function (ok) {
                if (!ok) {
                    if (statusSuara) {
                        statusSuara.textContent = 'Browser memblokir suara. Ketuk sekali di halaman lalu coba lagi.';
                    }
                    return;
                }

                bunyikanNadaSiap();
                if (sudahDiingatkan) {
                    tombolSuara.classList.add('hidden');
                } else if (statusSuara) {
                    statusSuara.textContent = 'Suara belum bisa diputar. Coba ketuk lagi.';
                }
            });
        });
    }

    /* ── Update progres tanpa reload ── */
    function terapkanProgres(urutan) {
        var peta = { 1: 'baru', 2: 'diproses', 4: 'selesai' };
        if (peta[urutan]) statusSekarang = peta[urutan];

        var counter = document.getElementById('langkah-counter');
        if (counter) counter.textContent = 'Langkah ' + urutan + ' dari ' + jumlahLangkah;

        document.querySelectorAll('[data-step]').forEach(function (li) {
            var nomor = Number(li.dataset.step);
            var state = nomor < urutan ? 'done' : (nomor === urutan ? 'now' : 'idle');

            var dot = li.querySelector('[data-step-dot]');
            if (dot) {
                dot.className = 'st-step-dot st-step-dot--' + state;
                if (state === 'done') dot.innerHTML = '<i data-lucide="check"></i>';
            }

            var label = li.querySelector('[data-step-label]');
            if (label) {
                label.className = 'st-step-label st-step-label--' + state;

                var pulse = li.querySelector('[data-step-pulse]');
                if (state === 'now' && !pulse) {
                    var span = document.createElement('span');
                    span.className = 'st-pulse';
                    span.setAttribute('data-step-pulse', '');
                    label.insertAdjacentElement('afterend', span);
                } else if (state !== 'now' && pulse) {
                    pulse.remove();
                }
            }

            var line = li.querySelector('[data-step-line]');
            if (line) line.classList.toggle('is-done', nomor < urutan);

            var note = li.querySelector('[data-step-note]');
            if (note) {
                note.hidden = state === 'idle';
                note.textContent = state === 'done'
                    ? 'Selesai'
                    : (state === 'now' ? (li.dataset.note || '') : '');
            }
        });

        if (window.lucide) lucide.createIcons();
    }

    function tandaiLunasBadge() {
        bayarSekarang = 'lunas';
        var badge = document.getElementById('badge-bayar');
        if (!badge) return;

        badge.innerHTML = '<span class="st-badge st-badge--paid"><i data-lucide="check"></i> Lunas</span>';
        if (window.lucide) lucide.createIcons();

        var batal = document.getElementById('batal-wrap');
        if (batal) batal.classList.add('hidden');
    }

    function tandaiSiap() {
        terapkanProgres(4);

        if (banner) banner.classList.remove('hidden');
        if (badgeSiap) badgeSiap.classList.remove('hidden');
        if (badgeEta) badgeEta.classList.add('hidden');
        if (bayarSekarang !== 'lunas') tandaiLunasBadge();

        var batal = document.getElementById('batal-wrap');
        if (batal) batal.classList.add('hidden');

        // Pengingat visual di tab browser.
        document.title = '🔔 Pesanan siap diambil — ' + judulAsli;

        bunyikanNadaSiap();
        if (!sudahDiingatkan && tombolSuara) tombolSuara.classList.remove('hidden');
    }

    /* ── Polling status tiap 5 detik ── */
    function poling() {
        fetch(liveUrl, {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        })
            .then(function (res) {
                if (!res.ok) throw new Error('status');
                return res.json();
            })
            .then(function (data) {
                if (data.status_pesanan === 'dibatalkan') {
                    location.reload();
                    return;
                }

                if (data.status_pesanan === 'selesai') {
                    if (statusSekarang !== 'selesai') tandaiSiap();
                } else if (data.status_pesanan !== statusSekarang) {
                    terapkanProgres(data.status_pesanan === 'diproses' ? 2 : 1);
                }

                if (data.status_pembayaran === 'lunas' && bayarSekarang !== 'lunas') {
                    tandaiLunasBadge();
                }
            })
            .catch(function () {
                // Abaikan gangguan jaringan; dicoba lagi di interval berikutnya.
            })
            .finally(function () {
                if (statusSekarang !== 'selesai') {
                    setTimeout(poling, 5000);
                }
            });
    }

    if (statusSekarang === 'selesai') {
        // Halaman dibuka saat pesanan sudah siap: coba bunyikan,
        // jika diblokir tampilkan tombol aktivasi suara.
        setTimeout(function () {
            bunyikanNadaSiap();
            if (!sudahDiingatkan && tombolSuara) tombolSuara.classList.remove('hidden');
        }, 400);
    } else if (statusSekarang !== 'dibatalkan') {
        setTimeout(poling, 5000);
    }
})();
</script>
@endpush
@endsection
