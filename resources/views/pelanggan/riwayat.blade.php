@extends('layouts.app')

@section('title', 'Riwayat Pesanan')

@section('content')
<style>
  .rw { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .rw .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .rw-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .rw-wrap { width: 100%; max-width: 640px; margin-inline: auto; }
</style>

<div class="rw min-h-screen pb-32">
    <div class="rw-wrap px-4 pt-6">
        {{-- ═══ HEADER ═══ --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('pelanggan.dashboard') }}" aria-label="Kembali ke beranda"
               class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-[#3D322E] bg-[#271D19] text-[#F1DFD8] transition hover:border-[#554336]">
                <i data-lucide="arrow-left" class="h-5 w-5"></i>
            </a>
            <div class="min-w-0">
                <p class="text-xs text-[#B8A8A1]">Velvet Club &bull; {{ $member?->nama_pelanggan ?? auth()->user()->nama_lengkap }}</p>
                <h1 class="grotesk text-xl sm:text-2xl font-bold text-[#F1DFD8]">Riwayat Pesanan</h1>
            </div>
        </div>

        <p class="mt-4 text-[11px] sm:text-xs text-[#B8A8A1]">{{ $riwayatPesanan->total() }} pesanan tercatat</p>

        {{-- ═══ DAFTAR PESANAN ═══ --}}
        <div class="mt-3 space-y-2.5">
            @forelse ($riwayatPesanan as $p)
                <div class="rw-card p-3.5 transition hover:shadow-md">
                    <a href="{{ route('pelanggan.status', $p->nomor_pesanan) }}"
                       class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="grotesk truncate text-[13px] font-bold text-[#F1DFD8]">#{{ $p->nomor_pesanan }}</p>
                            <p class="mt-0.5 text-[11px] text-[#B8A8A1]">
                                {{ $p->waktu_pesan?->format('d M Y H:i') }} • {{ $p->detailPesanan->sum('jumlah') }} item
                                @if ($p->meja)
                                    • Meja {{ $p->meja->nomor_meja }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-[10.5px] text-[#9D8E87]">
                                {{ ucfirst($p->tipe_pesanan) }}
                                @if ($p->transaksi)
                                    • {{ $p->transaksi->status_pembayaran === 'lunas' ? 'Lunas' : ucfirst($p->transaksi->status_pembayaran) }}
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="grotesk text-[13px] font-bold text-[#F1DFD8]">Rp {{ number_format($p->total_harga, 0, ',', '.') }}</p>
                            <span class="mt-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $p->status_pesanan === 'selesai' ? 'bg-[#352A22] text-[#F1DFD8]' : ($p->status_pesanan === 'dibatalkan' ? 'bg-[#BA1A1A]/10 text-[#F87171]' : 'bg-[#D97706] text-[#241708]') }}">
                                {{ $p->status_pesanan }}
                            </span>
                        </div>
                    </a>

                    <div class="mt-2.5 flex items-center justify-end border-t border-dashed border-[#3D322E] pt-2.5">
                        <a href="{{ route('struk.show', $p) }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 rounded-md border border-[#3D322E] bg-[#2F231D] px-2.5 py-1.5 text-[11px] font-semibold text-[#F1DFD8] transition hover:border-[#554336] hover:bg-[#352A22]">
                            <i data-lucide="receipt-text" class="h-3.5 w-3.5"></i>
                            Lihat struk
                        </a>
                    </div>
                </div>
            @empty
                <div class="rw-card p-8 text-center">
                    <p class="text-sm text-[#B8A8A1]">Belum ada pesanan. Yuk pesan menu pertama!</p>
                    <a href="{{ route('pelanggan.menu') }}"
                       class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-[#D97706] px-4 py-2 text-xs font-bold text-[#241708] transition hover:bg-[#B56100]">
                        <i data-lucide="coffee" class="h-4 w-4"></i> Lihat Menu
                    </a>
                </div>
            @endforelse
        </div>

        {{-- ═══ PAGINATION ═══ --}}
        @if ($riwayatPesanan->hasPages())
            <div class="rw-card mt-4 flex items-center justify-between gap-3 px-4 py-3 text-xs text-[#B8A8A1]">
                @if ($riwayatPesanan->previousPageUrl())
                    <a href="{{ $riwayatPesanan->previousPageUrl() }}" class="font-semibold text-[#F1DFD8] hover:underline">&larr; Sebelumnya</a>
                @else
                    <span class="opacity-40">&larr; Sebelumnya</span>
                @endif
                <span>Halaman {{ $riwayatPesanan->currentPage() }} / {{ $riwayatPesanan->lastPage() }}</span>
                @if ($riwayatPesanan->nextPageUrl())
                    <a href="{{ $riwayatPesanan->nextPageUrl() }}" class="font-semibold text-[#F1DFD8] hover:underline">Berikutnya &rarr;</a>
                @else
                    <span class="opacity-40">Berikutnya &rarr;</span>
                @endif
            </div>
        @endif
    </div>

    {{-- ═══ BOTTOM NAV ═══ --}}
    @include('pelanggan._nav', ['aktif' => 'riwayat'])
</div>
@endsection
