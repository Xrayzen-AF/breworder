{{-- Kartu pesanan untuk kolom KDS barista — DESIGN.md parchment --}}
<div class="mb-3 rounded border border-[#3D322E] border-l-4 {{ $aksiStatus === 'diproses' ? 'border-l-[#D97706] bg-[#271D19]' : 'border-l-[#554336] bg-[#271D19]' }} p-4">
    <div class="flex items-start justify-between gap-2">
        <div>
            <p class="font-grotesk text-sm font-medium tracking-wide text-[#F1DFD8]">{{ $p->nomor_pesanan }}</p>
            <p class="mt-0.5 font-grotesk text-xs tracking-wide text-[#B8A8A1]">
                {{ $p->tipe_pesanan }}
                @if ($p->meja)
                    • Meja {{ $p->meja->nomor_meja }}
                @endif
                @if ($p->pelanggan)
                    • {{ $p->pelanggan->nama_pelanggan }}
                @endif
            </p>
        </div>
        <span class="whitespace-nowrap font-grotesk text-xs text-[#B8A8A1]">{{ $p->waktu_pesan?->format('H:i') }}</span>
    </div>

    <ul class="mt-3 space-y-1.5 border-t border-dotted border-[#3D322E] pt-3">
        @foreach ($p->detailPesanan as $d)
            <li class="flex items-start justify-between gap-3 text-sm">
                <span class="text-[#B8A8A1]">
                    <span class="font-grotesk font-medium text-[#E0A96D]">{{ $d->jumlah }}×</span>
                    {{ $d->produk?->nama_produk ?? 'Produk #' . $d->id_produk }}
                    @if ($d->catatan_item)
                        <em class="block text-xs text-[#B8A8A1]">"{{ $d->catatan_item }}"</em>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>

    @if ($p->catatan)
        <p class="mt-2 rounded bg-[#2F231D] px-3 py-2 text-xs italic text-[#B8A8A1]">Catatan: {{ $p->catatan }}</p>
    @endif

    <form method="POST" action="{{ route('pesanan.status', $p->id_pesanan) }}" class="mt-3">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status_pesanan" value="{{ $aksiStatus }}">
        <button type="submit"
                class="w-full rounded px-3 py-2 font-grotesk text-[11px] font-semibold uppercase tracking-[0.12em] transition {{ $kelasAksi }}">
            {{ $labelAksi }}
        </button>
    </form>
</div>
