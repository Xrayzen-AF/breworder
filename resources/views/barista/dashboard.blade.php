@extends('layouts.app')

@section('title', 'Pesanan Dapur — Tejas Coffee')

@php
    $SLA = 300; // target 5 menit per tiket (detik)
    $now = now();
    $elapsed = fn ($p) => max(0, $p->waktu_pesan ? $p->waktu_pesan->diffInSeconds($now) : 0);
    $fmtElapsed = function ($s) {
        $s = (int) $s;
        if ($s >= 3600) return sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    };
    $semua = $pesananBaru->concat($pesananDiproses);
    $tipeLabel = fn ($p) => $p->tipe_pesanan === 'dine-in' ? 'Dine In' : ($p->tipe_pesanan === 'delivery' ? 'Order Ahead' : 'Takeaway');
    $lokasi = function ($p) {
        if ($p->tipe_pesanan === 'dine-in') return 'Table ' . ($p->meja->nomor_meja ?? '—');
        if ($p->pelanggan) return $p->pelanggan->nama_pelanggan;
        return $p->tipe_pesanan === 'delivery' ? 'Mobile App' : 'Front Register';
    };
    // Urut: pinned dulu, lalu terlama.
    $urut = $semua->sortBy([fn ($p) => in_array($p->id_pesanan, $pinned, true) ? 0 : 1, fn ($p) => $p->waktu_pesan])->values();
    $tampil = match ($filter) {
        'progress' => $urut->where('status_pesanan', 'diproses')->values(),
        'dine-in' => $urut->where('tipe_pesanan', 'dine-in')->values(),
        'takeaway' => $urut->reject(fn ($p) => $p->tipe_pesanan === 'dine-in')->values(),
        default => $urut,
    };
    $overdue = $semua->filter(fn ($p) => $elapsed($p) > $SLA)->count();
    $jmlDineIn = $semua->where('tipe_pesanan', 'dine-in')->count();
    $jmlTakeaway = $semua->count() - $jmlDineIn;
    $totalHariIni = $jumlahSelesaiHariIni + $semua->count();
    $efisiensi = $totalHariIni > 0 ? round($jumlahSelesaiHariIni / $totalHariIni * 100, 1) : 0;
    $readyList = $selesaiList->reject(fn ($p) => in_array($p->id_pesanan, $dismissed, true))->values()->take(3);
    $namaBarista = auth()->user()->nama_lengkap ?? 'Barista';
    $inIsial = strtoupper(implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice(explode(' ', trim($namaBarista)), 0, 2))));
@endphp

@section('content')
<style>
  .kds { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .kds .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .kds-topbar { background: #221A15; border-bottom: 1px solid #3D322E; }
  .kds-tab { font-size: 14px; color: #B8A8A1; white-space: nowrap; padding: 6px 2px; transition: color .15s; }
  .kds-tab:hover { color: #F1DFD8; }
  .kds-tab.active { color: #F59E0B; font-weight: 600; box-shadow: inset 0 -2px 0 #D97706; }
  .kds-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .kds-pill { font-size: 13px; font-weight: 500; padding: 8px 14px; border-radius: 6px; background: #2F231D; color: #B8A8A1; border: 1px solid #3D322E; white-space: nowrap; transition: .15s; }
  .kds-pill:hover { background: #352A22; color: #F1DFD8; }
  .kds-pill.active { background: #D97706; color: #241708; border-color: #D97706; font-weight: 600; }
  .badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; white-space: nowrap; }
  .badge-gray { background: #2F231D; color: #B8A8A1; }
  .badge-black { background: #D97706; color: #241708; }
  .badge-red { background: rgba(248,113,113,.14); color: #F87171; }
  .tick { font-variant-numeric: tabular-nums; }
</style>

<div class="kds min-h-screen pb-10">
    {{-- ═══ TOPBAR ═══ --}}
    <div class="kds-topbar sticky top-0 z-20">
        <div class="mx-auto flex max-w-[1400px] flex-wrap items-center gap-x-5 gap-y-3 px-4 py-3 sm:px-6">
            <span class="flex items-center gap-2">
                <img src="{{ asset('images/logo-tejas.png') }}" alt="Tejas Coffee" class="h-6 w-6 object-contain" width="24" height="24">
                <span class="grotesk text-[16px] font-bold tracking-wide text-[#F1DFD8]">TEJAS COFFEE</span>
            </span>
            <span class="grotesk tick text-[18px] font-bold text-[#F1DFD8]" data-clock>--:--:--</span>
            @if ($overdue > 0)
                <span class="badge badge-red">[WIRE-RUSH ACTIVE]</span>
            @else
                <span class="badge badge-gray">[STEADY FLOW]</span>
            @endif
            <nav class="flex items-center gap-5 overflow-x-auto">
                <a href="{{ route('barista.dashboard') }}" class="kds-tab active">Pesanan Dapur</a>
                <span class="kds-tab" title="Modul kasir">POS Register</span>
                <span class="kds-tab" title="Modul admin">Inventory</span>
                <span class="kds-tab" title="Modul admin">Analytics</span>
                <span class="kds-tab" title="Modul admin">Staff</span>
            </nav>
            <div class="ml-auto flex items-center gap-3">
                <div class="hidden items-center gap-2 sm:flex">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#B56100] text-[12px] font-bold text-[#241708]">{{ $inIsial }}</span>
                    <span class="leading-tight">
                        <span class="block text-[13px] font-semibold text-[#F1DFD8]">{{ $namaBarista }}</span>
                        <span class="block text-[11px] text-[#B8A8A1]">Barista Station 01</span>
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

    <div class="mx-auto max-w-[1400px] px-4 pt-5 sm:px-6">
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-[#BA1A1A]/25 bg-[#BA1A1A]/[.06] px-4 py-3 text-[13px] font-medium text-[#F87171]">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-[#16A34A]/25 bg-[#16A34A]/[.07] px-4 py-3 text-[13px] font-medium text-[#4ADE80]">{{ session('success') }}</div>
        @endif
        {{-- ═══ FILTER + STATS ═══ --}}
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('barista.dashboard') }}" class="kds-pill {{ $filter === 'all' ? 'active' : '' }}">All {{ $semua->count() }}</a>
            <a href="{{ route('barista.dashboard', ['filter' => 'progress']) }}" class="kds-pill {{ $filter === 'progress' ? 'active' : '' }}">Sedang Dibuat {{ $pesananDiproses->count() }}</a>
            <a href="{{ route('barista.dashboard', ['filter' => 'dine-in']) }}" class="kds-pill {{ $filter === 'dine-in' ? 'active' : '' }}">Dine In {{ $jmlDineIn }}</a>
            <a href="{{ route('barista.dashboard', ['filter' => 'takeaway']) }}" class="kds-pill {{ $filter === 'takeaway' ? 'active' : '' }}">Takeaway / Order Ahead {{ $jmlTakeaway }}</a>
            <div class="ml-auto flex flex-wrap gap-x-5 gap-y-1 text-xs text-[#B8A8A1]">
                <span>Avg Wait: <strong class="grotesk text-[#F1DFD8]">
                    @php $avg = $semua->count() ? (int) round($semua->avg(fn ($p) => $elapsed($p))) : 0; @endphp
                    {{ $semua->count() ? $fmtElapsed($avg) : '—' }}
                </strong></span>
                <span>SLA Alerts: <strong class="{{ $overdue > 0 ? 'text-[#F87171]' : 'grotesk text-[#F1DFD8]' }}">{{ $overdue }} Overdue</strong></span>
                <span>Selesai: <strong class="grotesk text-[#F1DFD8]">{{ $jumlahSelesaiHariIni }}</strong></span>
            </div>
        </div>

        {{-- ═══ KARTU ANTRIAN ═══ --}}
        @if ($tampil->count() > 0)
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($tampil as $p)
                    @php
                        $detik = $elapsed($p);
                        $isPinned = in_array($p->id_pesanan, $pinned, true);
                        $prioritas = $detik > $SLA ? 'URGENT / OVERDUE' : ($detik >= 120 ? 'WARNING' : ($p->status_pesanan === 'baru' ? 'QUEUED' : 'NORMAL'));
                        $kelasPrioritas = $detik > $SLA ? 'badge-red' : ($detik >= 120 ? 'badge-black' : 'badge-gray');
                    @endphp
                    <article class="kds-card flex flex-col p-4 {{ $isPinned ? 'outline outline-2 outline-black' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="grotesk truncate text-[15px] font-bold text-[#F1DFD8]">#{{ $p->nomor_pesanan }}</h3>
                            <span class="badge badge-gray">{{ $tipeLabel($p) }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between gap-2 text-xs text-[#B8A8A1]">
                            <span class="truncate">{{ $lokasi($p) }}@if ($p->pelanggan && $p->tipe_pesanan !== 'dine-in') • {{ $p->pelanggan->nama_pelanggan }}@endif</span>
                            <span class="grotesk tick shrink-0 font-bold text-[#F1DFD8]" data-tick="{{ $detik }}">{{ $fmtElapsed($detik) }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span class="badge {{ $kelasPrioritas }}">[{{ $prioritas }}]</span>
                            @if ($isPinned)<span class="badge badge-black">Pinned</span>@endif
                            @php $stBayar = $p->transaksi?->status_pembayaran ?? 'menunggu'; @endphp
                            <span class="badge {{ $stBayar === 'lunas' ? 'badge-black' : 'badge-red' }}">{{ $stBayar === 'lunas' ? 'LUNAS' : 'BELUM BAYAR' }}</span>
                        </div>

                        <ul class="mt-3 flex-1 space-y-2.5">
                            @foreach ($p->detailPesanan as $d)
                                <li class="text-[13px]">
                                    <p class="font-semibold text-[#F1DFD8]">{{ $d->jumlah }}x {{ $d->produk?->nama_produk ?? 'Produk #' . $d->id_produk }}</p>
                                    @if ($d->catatan_item)
                                        <p class="mt-0.5 pl-6 text-xs leading-relaxed text-[#B8A8A1]">{{ $d->catatan_item }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($p->catatan)
                            <div class="mt-3 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2">
                                <p class="text-[11px] font-bold text-[#F1DFD8]">[Barista Note]</p>
                                <p class="mt-0.5 text-xs italic leading-relaxed text-[#B8A8A1]">"{{ $p->catatan }}"</p>
                            </div>
                        @endif

                        @if ($detik > $SLA)
                            <p class="mt-2 text-[11px] font-bold text-[#F87171]">[SLA EXCEEDED: +{{ $fmtElapsed($detik - $SLA) }} over target]</p>
                        @endif

                        <div class="mt-3 flex gap-2">
                            @if ($p->status_pesanan === 'baru')
                                <form method="POST" action="{{ route('pesanan.status', $p->id_pesanan) }}" class="flex-1">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status_pesanan" value="diproses">
                                    <button type="submit" class="w-full rounded-lg bg-[#D97706] px-3 py-2.5 text-[13px] font-semibold text-[#241708] transition hover:bg-[#B56100]">[Pull to Bar]</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('pesanan.status', $p->id_pesanan) }}" class="flex-1">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status_pesanan" value="selesai">
                                    <button type="submit" class="w-full rounded-lg bg-[#D97706] px-3 py-2.5 text-[13px] font-semibold text-[#241708] transition hover:bg-[#B56100]">[Mark Done]</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('barista.bump', $p->id_pesanan) }}">
                                @csrf
                                <button type="submit" title="{{ $isPinned ? 'Lepas sematan' : 'Sematkan ke atas' }}"
                                        class="rounded-lg px-3 py-2.5 text-[13px] font-semibold transition {{ $isPinned ? 'bg-[#D97706] text-[#241708]' : 'bg-[#2F231D] text-[#F1DFD8] hover:bg-[#352A22]' }}">Sematkan</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="kds-card mt-5 p-10 text-center">
                <p class="grotesk text-[16px] font-bold text-[#F1DFD8]">Antrian kosong</p>
                <p class="mt-1 text-xs text-[#B8A8A1]">Tiket baru dari kasir & pelanggan akan muncul di sini.</p>
            </div>
        @endif

        {{-- ═══ DISPATCH STRIP ═══ --}}
        <div class="kds-card mt-5 p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <h2 class="grotesk text-[18px] font-bold text-[#F1DFD8]">Antrean & Pengambilan</h2>
                    <p class="mt-0.5 text-xs text-[#B8A8A1]">Pesanan diperbarui otomatis setiap 2 menit</p>
                </div>
                <div class="ml-auto flex items-center gap-4 text-xs text-[#B8A8A1]">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#D97706]"></span>Dibuat</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#9D8E87]"></span>Menunggu</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#16A34A]"></span>Siap Diambil</span>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-4">
                <div class="rounded-lg bg-[#1E1511] p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#B8A8A1]">Menunggu ({{ $pesananBaru->count() }})</p>
                    <div class="mt-2 space-y-2">
                        @forelse ($pesananBaru->take(2) as $p)
                            <div class="rounded-lg bg-[#271D19] p-3 shadow-sm">
                                <p class="grotesk text-[13px] font-bold text-[#F1DFD8]">#{{ $p->nomor_pesanan }}</p>
                                <p class="mt-0.5 text-[11px] text-[#B8A8A1]">{{ $tipeLabel($p) }} • {{ $p->detailPesanan->sum('jumlah') }} items</p>
                                <p class="mt-0.5 text-[11px] text-[#B8A8A1]">In queue: <span class="tick font-semibold text-[#F1DFD8]" data-tick="{{ $elapsed($p) }}">{{ $fmtElapsed($elapsed($p)) }}</span></p>
                                <form method="POST" action="{{ route('pesanan.status', $p->id_pesanan) }}" class="mt-2">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status_pesanan" value="diproses">
                                    <button class="text-[12px] font-semibold text-[#F1DFD8] hover:underline">Pull to Bar →</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-xs text-[#B8A8A1]">Tidak ada tiket queued.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg bg-[#1E1511] p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#B8A8A1]">Dibuat ({{ $pesananDiproses->count() }})</p>
                    <div class="mt-2 space-y-2">
                        @forelse ($pesananDiproses->take(2) as $p)
                            <div class="rounded-lg bg-[#271D19] p-3 shadow-sm">
                                <p class="grotesk text-[13px] font-bold text-[#F1DFD8]">#{{ $p->nomor_pesanan }}</p>
                                <p class="mt-0.5 text-[11px] text-[#B8A8A1]">{{ $p->detailPesanan->sum('jumlah') }} items • <span class="tick" data-tick="{{ $elapsed($p) }}">{{ $fmtElapsed($elapsed($p)) }}</span></p>
                                <form method="POST" action="{{ route('pesanan.status', $p->id_pesanan) }}" class="mt-2">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status_pesanan" value="selesai">
                                    <button class="text-[12px] font-semibold text-[#F1DFD8] hover:underline">Mark Done →</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-xs text-[#B8A8A1]">Tidak ada yang dimasak.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg bg-[#1E1511] p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#B8A8A1]">Siap Diambil ({{ $readyList->count() }})</p>
                    <div class="mt-2 space-y-2">
                        @forelse ($readyList as $i => $p)
                            <div class="rounded-lg border-2 border-[#F8F9FA] bg-[#271D19] p-3 shadow-sm outline outline-1 outline-[#16A34A]/40">
                                <p class="grotesk text-[13px] font-bold text-[#F1DFD8]">#{{ $p->nomor_pesanan }}</p>
                                <p class="mt-0.5 text-[11px] text-[#B8A8A1]">Shelf Bay #{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }} • {{ $p->detailPesanan->sum('jumlah') }} items</p>
                                <p class="mt-0.5 text-[11px] font-medium text-[#16A34A]">Customer Notified (SMS)</p>
                                <form method="POST" action="{{ route('barista.dismiss', $p->id_pesanan) }}" class="mt-1.5">
                                    @csrf
                                    <button class="text-[12px] font-semibold text-[#B8A8A1] hover:text-[#F87171]">Sembunyikan</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-xs text-[#B8A8A1]">Belum ada yang siap diambil.</p>
                        @endforelse
                    </div>
                </div>

                <div class="flex flex-col justify-center rounded-lg bg-[#B56100] p-5 text-[#241708]">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#241708]/75">Station Efficiency</p>
                    <p class="grotesk tick mt-1 text-4xl font-bold">{{ number_format($efisiensi, 1) }}%</p>
                    <p class="mt-1 text-xs text-[#241708]/75">{{ $jumlahSelesaiHariIni }} selesai • {{ $semua->count() }} aktif hari ini</p>
                    <p class="mt-3 text-xs font-semibold">Target: Optimal</p>
                </div>
            </div>
        </div>

        {{-- ═══ PETA STATUS MEJA ═══ --}}
        @php
            $mejaPerStatus = ['kosong' => 0, 'terisi' => 0, 'dipesan' => 0];
            foreach ($meja as $m) {
                $st = $m->pesanan_count > 0 ? 'terisi' : $m->status;
                $mejaPerStatus[$st] = ($mejaPerStatus[$st] ?? 0) + 1;
            }
        @endphp
        <div class="kds-card mt-5 p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <h2 class="grotesk text-[18px] font-bold text-[#F1DFD8]">Status Meja</h2>
                    <p class="mt-0.5 text-xs text-[#B8A8A1]">Tandai meja kosong, terisi, atau dipesan — papan kasir ikut menyesuaikan.</p>
                </div>
                <div class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                    <span class="flex items-center gap-1.5 text-[#4ADE80]"><span class="h-2 w-2 rounded-full bg-[#4ADE80]"></span>Kosong {{ $mejaPerStatus['kosong'] }}</span>
                    <span class="flex items-center gap-1.5 text-[#F59E0B]"><span class="h-2 w-2 rounded-full bg-[#F59E0B]"></span>Terisi {{ $mejaPerStatus['terisi'] }}</span>
                    <span class="flex items-center gap-1.5 text-[#B8A8A1]"><span class="h-2 w-2 rounded-full bg-[#9D8E87]"></span>Dipesan {{ $mejaPerStatus['dipesan'] }}</span>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse ($meja as $m)
                    @php
                        $aktif = (int) $m->pesanan_count;
                        $st = $aktif > 0 ? 'terisi' : $m->status;
                        $badge = $st === 'terisi'
                            ? 'bg-[#D97706] text-[#241708]'
                            : ($st === 'dipesan' ? 'bg-[#2F231D] text-[#B8A8A1]' : 'bg-[#4ADE80]/15 text-[#4ADE80]');
                        $reservasi = $m->reservasiAktif;
                        $sisaMenit = $reservasi
                            ? max(0, (int) now()->diffInMinutes($reservasi->berakhir_pada, false))
                            : 0;
                        $sisaTeks = $sisaMenit >= 60
                            ? intdiv($sisaMenit, 60) . ' j' . ($sisaMenit % 60 ? ' ' . ($sisaMenit % 60) . ' mnt' : '')
                            : $sisaMenit . ' mnt';
                    @endphp
                    <div class="rounded-lg border border-[#3D322E] bg-[#1E1511] p-3.5">
                        <div class="flex items-center justify-between gap-2">
                            <p class="grotesk text-[15px] font-bold text-[#F1DFD8]">{{ $m->nomor_meja }}</p>
                            <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badge }}">{{ $st }}</span>
                        </div>
                        <p class="mt-0.5 text-[11px] text-[#B8A8A1]">
                            Kapasitas {{ $m->kapasitas }} orang • {{ $aktif }} pesanan aktif
                        </p>

                        @if ($reservasi)
                            <p class="mt-1 flex items-center gap-1.5 text-[11px] text-[#E0A96D]">
                                <i data-lucide="user-round" class="h-3 w-3 shrink-0"></i>
                                <span class="truncate font-semibold">{{ $reservasi->pelanggan?->nama_pelanggan ?? 'Tamu' }}</span>
                                <span class="text-[#82746E]">•</span>
                                <span class="shrink-0" data-sisa-sesi
                                      data-berakhir="{{ $reservasi->berakhir_pada->toIso8601String() }}">sisa {{ $sisaTeks }}</span>
                            </p>
                        @endif

                        <div class="mt-2.5 grid grid-cols-3 gap-1.5">
                            @foreach ($statusMeja as $s)
                                @php
                                    $aktifIni = $st === $s;
                                    $terkunci = $aktif > 0 && $s !== 'terisi';
                                @endphp
                                <form method="POST" action="{{ route('barista.meja.status.update', $m) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $s }}">
                                    <button type="submit"
                                            @disabled($aktifIni || $terkunci)
                                            title="{{ $terkunci ? 'Masih ada pesanan aktif di meja ini' : 'Tandai ' . $s }}"
                                            class="w-full rounded-md border px-1.5 py-1.5 text-[11px] font-semibold transition
                                                {{ $aktifIni ? 'border-[#D97706] bg-[#D97706] text-[#241708]' : 'border-[#3D322E] bg-[#2F231D] text-[#B8A8A1] hover:bg-[#352A22] hover:text-[#F1DFD8]' }}
                                                {{ $terkunci ? 'cursor-not-allowed opacity-40' : '' }}">
                                        {{ ucfirst($s) }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="col-span-full rounded-lg bg-[#1E1511] p-6 text-center text-xs text-[#B8A8A1]">
                        Belum ada meja terdaftar. Tambahkan lewat panel admin → Master Meja.
                    </p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
if (window.lucide) lucide.createIcons();
function kdsFmt(s) {
  s = Math.max(0, parseInt(s, 10) || 0);
  var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), ss = s % 60;
  function p(n) { return (n < 10 ? '0' : '') + n; }
  return h > 0 ? h + ':' + p(m) + ':' + p(ss) : p(m) + ':' + p(ss);
}
document.querySelectorAll('[data-tick]').forEach(function (el) {
  var s = parseInt(el.getAttribute('data-tick'), 10) || 0;
  setInterval(function () { s += 1; el.textContent = kdsFmt(s); }, 1000);
});
function kdsClock() {
  var el = document.querySelector('[data-clock]');
  if (el) el.textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
kdsClock(); setInterval(kdsClock, 1000);
function kdsSisaFmt(m) {
  if (m >= 60) {
    var j = Math.floor(m / 60), sisa = m % 60;
    return j + ' j' + (sisa ? ' ' + sisa + ' mnt' : '');
  }
  return m + ' mnt';
}
document.querySelectorAll('[data-sisa-sesi]').forEach(function (el) {
  var akhir = new Date(el.getAttribute('data-berakhir')).getTime();
  if (isNaN(akhir)) return;
  function paint() {
    var menit = Math.max(0, Math.ceil((akhir - Date.now()) / 60000));
    el.textContent = menit > 0 ? 'sisa ' + kdsSisaFmt(menit) : 'sesi berakhir';
  }
  paint();
  setInterval(paint, 30000);
});
setTimeout(function () { location.reload(); }, 120000);
</script>
@endpush
@endsection
