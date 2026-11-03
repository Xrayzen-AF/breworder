@extends('layouts.app')

@section('title', 'Keranjang — Tejas Coffee')

@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $jmlItem = array_sum(array_column($keranjang, 'jumlah'));
@endphp

@section('content')
<style>
  .cart { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .cart .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .cart .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
  .cart-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .tipe input { position: absolute; opacity: 0; pointer-events: none; }
  .tipe span.box { display: block; border: 1px solid #3D322E; border-radius: 6px; padding: 10px; cursor: pointer; text-align: center; transition: .15s; background: #2F231D; color: #B8A8A1; }
  .tipe input:checked + span.box { border: 1px solid #D97706; background: #D97706; color: #241708; font-weight: 600; }
  .cart-input { width: 100%; background: #221A15; border: 1px solid #3D322E; border-radius: var(--radius-control); padding: 10px 12px; font-size: 13px; color: #F1DFD8; outline: none; }
  .cart-input:focus { background: #2A1F19; border-color: #D97706; box-shadow: 0 0 0 3px rgba(217,119,6,.18); }
  .cart-input::placeholder { color: #82746E; }
  .step { width: 28px; height: 28px; border-radius: 6px; background: #2F231D; color: #F1DFD8; border: 1px solid #3D322E; display: flex; align-items: center; justify-content: center; font-weight: 700; }
  .step:hover { background: #352A22; border-color: #554336; }
  .cart select option { background: #221A15; color: #F1DFD8; }
</style>

<div class="cart min-h-screen pb-56">
    <div class="mx-auto max-w-2xl px-4 pt-4">
        {{-- Header Step 02 --}}
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('pelanggan.menu') }}" class="flex items-center gap-1.5 text-[13px] font-semibold text-[#F1DFD8] hover:underline">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Menu
            </a>
            <p class="mono text-[11px] tracking-wide text-[#B8A8A1]">LANGKAH 2 DARI 3 • PERIKSA PESANAN</p>
        </div>
        <div class="mt-2 flex items-center gap-2">
            <h1 class="grotesk text-xl font-bold text-[#F1DFD8]">Keranjang Pesanan</h1>
            <span class="rounded bg-[#D97706] px-2 py-0.5 text-[11px] font-bold text-[#241708]">{{ $jmlItem }} Menu Dipilih</span>
        </div>

        @if (session('error'))
            <div class="mt-3 rounded-lg border border-[#BA1A1A]/25 bg-[#BA1A1A]/[.06] px-4 py-3 text-[13px] font-medium text-[#F87171]">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="mt-3 rounded-lg border border-[#16A34A]/25 bg-[#16A34A]/[.07] px-4 py-3 text-[13px] font-medium text-[#4ADE80]">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-3 rounded-lg border border-[#BA1A1A]/25 bg-[#BA1A1A]/[.06] p-4">
                <ul class="list-inside list-disc space-y-1 text-[13px] font-medium text-[#F87171]">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if (count($keranjang) > 0)
            {{-- ═══ TIPE PEMESANAN ═══ --}}
            <section class="cart-card mt-4 p-4 sm:p-5">
                <h2 class="text-sm font-bold text-[#F1DFD8]">Jenis Pesanan</h2>
                <form id="tipe-form" method="POST" action="{{ route('pelanggan.keranjang.tipe') }}">
                    @csrf
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <label class="tipe relative">
                            <input type="radio" name="tipe_pesanan" value="dine-in" {{ old('tipe_pesanan', $tipeDipilih) === 'dine-in' ? 'checked' : '' }} onchange="document.getElementById('meja-box').classList.remove('hidden')">
                            <span class="box">
                                <span class="block text-sm font-bold text-[#F1DFD8]">Makan di Tempat</span>
                                <span class="mt-0.5 block text-[11px] text-[#B8A8A1]">{{ $mejaDipilihNomor ? 'Meja ' . $mejaDipilihNomor : 'Pilih meja' }}</span>
                            </span>
                        </label>
                        <label class="tipe relative">
                            <input type="radio" name="tipe_pesanan" value="takeaway" {{ old('tipe_pesanan', $tipeDipilih) === 'takeaway' ? 'checked' : '' }} onchange="document.getElementById('meja-box').classList.add('hidden')">
                            <span class="box">
                                <span class="block text-sm font-bold text-[#F1DFD8]">Bawa Pulang</span>
                                <span class="mt-0.5 block text-[11px] text-[#B8A8A1]">Ambil di kasir</span>
                            </span>
                        </label>
                    </div>
                    <div id="meja-box" class="mt-2 {{ old('tipe_pesanan', $tipeDipilih) === 'dine-in' ? '' : 'hidden' }}">
                        <label for="id_meja-trigger" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-[#B8A8A1]">Nomor Meja</label>
                        @include('pelanggan._pemilih-meja', [
                            'mpId' => 'id_meja',
                            'mejaTersedia' => $mejaTersedia,
                            'terpilih' => old('id_meja', $mejaDipilih),
                        ])
                    </div>
                </form>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-[#3D322E] pt-3 text-[11px] text-[#B8A8A1]">
                    <span>Tejas Coffee</span>
                    <span>Est. Waktu Seduh: 5–8 Menit</span>
                </div>
            </section>

            {{-- ═══ DAFTAR MENU ═══ --}}
            <div class="mt-4 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#F1DFD8]">Daftar Menu</h2>
                <span class="rounded bg-[#2F231D] px-2 py-0.5 text-[11px] font-semibold text-[#B8A8A1]">{{ count($keranjang) }} item di keranjang</span>
            </div>
            <div class="mt-2 space-y-3">
                @foreach ($keranjang as $i)
                    <article class="cart-card flex gap-3 p-3.5">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#241B17] p-1 border border-[#3D322E]">
                            @if (! empty($i['gambar']))
                                <img src="{{ $i['gambar'] }}" alt="{{ $i['nama'] }}" class="h-full w-full object-contain">
                            @else
                                <i data-lucide="coffee" class="h-7 w-7 text-[#9D8E87]"></i>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-sm font-semibold leading-snug text-[#F1DFD8]">{{ $i['nama'] }}</h3>
                                <p class="grotesk shrink-0 text-sm font-bold text-[#F1DFD8]">{{ $rp($i['harga'] * $i['jumlah']) }}</p>
                            </div>
                            <p class="mt-0.5 text-xs text-[#B8A8A1]">{{ $rp($i['harga']) }} / item</p>
                            @if (! empty($i['opsi_text']))
                                <p class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-[#F1DFD8]">{{ $i['opsi_text'] }}</p>
                            @endif
                            <p class="mono mt-0.5 text-[10px] text-[#9D8E87]">Kode: {{ $i['sku'] ?? '—' }}</p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 text-xs font-semibold">
                                    <a href="{{ route('pelanggan.menu.kustom', ['produk' => $i['id_produk'], 'key' => $i['key'] ?? $i['id_produk']]) }}" class="text-[#F1DFD8] hover:underline">Ubah Pesanan</a>
                                    <span class="text-[#352A22]">|</span>
                                    <form method="POST" action="{{ route('pelanggan.keranjang.hapus', $i['key'] ?? $i['id_produk']) }}" onsubmit="return confirm('Hapus {{ $i['nama'] }} dari keranjang?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-[#F87171] hover:underline">Hapus</button>
                                    </form>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <form method="POST" action="{{ route('pelanggan.keranjang.update', $i['key'] ?? $i['id_produk']) }}">
                                        @csrf
                                        <input type="hidden" name="jumlah" value="{{ max(0, $i['jumlah'] - 1) }}">
                                        <button class="step">−</button>
                                    </form>
                                    <span class="grotesk w-5 text-center text-sm font-bold">{{ $i['jumlah'] }}</span>
                                    <form method="POST" action="{{ route('pelanggan.keranjang.update', $i['key'] ?? $i['id_produk']) }}">
                                        @csrf
                                        <input type="hidden" name="jumlah" value="{{ $i['jumlah'] + 1 }}">
                                        <button class="step">+</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- ═══ REKOMENDASI ═══ --}}
            @if ($rekomendasi->count() > 0)
                <div class="mt-6 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[#F1DFD8]">Pelengkap Ngopi Hemat</h2>
                    <span class="rounded bg-[#E0A96D] px-2 py-0.5 text-[11px] font-semibold text-[#241708]">REKOMENDASI</span>
                </div>
                <div class="mt-2 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 gap-2.5">
                    @foreach ($rekomendasi as $r)
                        <div class="cart-card flex flex-col overflow-hidden">
                            <div class="relative w-full items-center justify-center bg-[#241B17] overflow-hidden p-2">
                                @if ($r->url_gambar)
                                    <img src="{{ $r->url_gambar }}" alt="{{ $r->nama_produk }}" class="h-full w-full object-contain transition-transform duration-200 hover:scale-105">
                                @else
                                    <i data-lucide="coffee" class="h-6 w-6 text-[#9D8E87]"></i>
                                @endif
                            </div>
                            <div class="p-2.5">
                                <h3 class="line-clamp-1 text-xs font-semibold text-[#F1DFD8]">{{ $r->nama_produk }}</h3>
                                <p class="mt-0.5 line-clamp-1 text-[11px] text-[#B8A8A1]">{{ $r->deskripsi ?? $r->kategori->nama_kategori ?? '' }}</p>
                                <form method="POST" action="{{ route('pelanggan.keranjang.tambah', $r->id_produk) }}" class="mt-1.5">
                                    @csrf
                                    <input type="hidden" name="jumlah" value="1">
                                    <button type="submit" class="w-full rounded-lg bg-[#D97706] py-1.5 text-[11px] font-bold text-[#241708] transition hover:bg-[#B56100]">+ {{ $rp($r->harga) }}</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ═══ VOUCHER ═══ --}}
            <section class="cart-card mt-4 p-4 sm:p-5">
                <h2 class="text-sm font-bold tracking-wide text-[#F1DFD8]">Voucher & Diskon Promo</h2>
                @if ($ringkasan['promo'])
                    <div class="mt-2.5 flex items-center justify-between gap-2 rounded-lg bg-[#2F231D] px-3.5 py-3">
                        <div>
                            <p class="grotesk text-sm font-bold tracking-widest text-[#F1DFD8]">{{ $ringkasan['promo']->kode_promo }}</p>
                            <p class="mt-0.5 text-[11px] text-[#B8A8A1]">
                                {{ $ringkasan['promo']->tipe_diskon === 'persen' ? 'Diskon ' . rtrim(rtrim($ringkasan['promo']->nilai_diskon, '0'), '.') . '% aktif' : 'Potongan ' . $rp($ringkasan['promo']->nilai_diskon) . ' aktif' }}
                                (−{{ $rp($ringkasan['diskon_promo']) }})
                            </p>
                        </div>
                        <form method="POST" action="{{ route('pelanggan.keranjang.voucher') }}">
                            @csrf
                            <input type="hidden" name="aksi" value="hapus">
                            <button class="text-xs font-bold text-[#F1DFD8] hover:underline">Ganti</button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('pelanggan.keranjang.voucher') }}" class="mt-2.5 flex gap-2">
                        @csrf
                        <input type="hidden" name="aksi" value="terapkan">
                        <input type="text" name="kode" value="{{ old('kode') }}" placeholder="Kode voucher (mis. WELCOME10)" class="cart-input uppercase">
                        <button type="submit" class="shrink-0 rounded-lg bg-[#D97706] px-4 text-[13px] font-semibold text-[#241708] transition hover:bg-[#B56100]">Terapkan</button>
                    </form>
                @endif
            </section>

            {{-- ═══ POIN ═══ --}}
            <section class="cart-card mt-4 p-4 sm:p-5">
                <h2 class="text-sm font-bold tracking-wide text-[#F1DFD8]">Pakai Poin</h2>
                @if (($member?->poin_loyalitas ?? 0) > 0)
                    @if ($ringkasan['tukar_poin'])
                        <div class="mt-2.5 flex items-center justify-between gap-2 rounded-lg bg-[#2F231D] px-3.5 py-3">
                            <p class="text-xs text-[#B8A8A1]">Gunakan <strong class="text-[#F1DFD8]">{{ number_format($ringkasan['poin_pakai'], 0, ',', '.') }} poin</strong>
                                (−{{ $rp($ringkasan['diskon_poin']) }})<br>Sisa saldo: {{ number_format($ringkasan['poin_tersedia'] - $ringkasan['poin_pakai'], 0, ',', '.') }} pts</p>
                            <form method="POST" action="{{ route('pelanggan.keranjang.poin') }}">
                                @csrf
                                <button class="shrink-0 text-xs font-bold text-[#F87171] hover:underline">Batalkan</button>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('pelanggan.keranjang.poin') }}" class="mt-2.5 flex items-center justify-between gap-2 rounded-lg border border-dashed border-[#554336] px-3.5 py-3">
                            @csrf
                            <p class="text-xs text-[#B8A8A1]">Gunakan poin (100 pts = Rp 1.000)<br>Saldo: <strong class="text-[#F1DFD8]">{{ number_format($ringkasan['poin_tersedia'], 0, ',', '.') }} pts</strong></p>
                            <button class="shrink-0 rounded-lg bg-[#D97706] px-4 py-2 text-xs font-bold text-[#241708] transition hover:bg-[#B56100]">Gunakan</button>
                        </form>
                    @endif
                @else
                    <p class="mt-2 text-xs text-[#B8A8A1]">Belum ada poin. Poin didapat dari pesanan & promo member.</p>
                @endif
            </section>

            {{-- ═══ RINGKASAN ═══ --}}
            <section class="cart-card mt-4 p-4 sm:p-5">
                <h2 class="text-sm font-bold tracking-wide text-[#F1DFD8]">Ringkasan Biaya</h2>
                <dl class="mt-2.5 space-y-1.5 text-[13px]">
                    <div class="flex justify-between"><dt class="text-[#B8A8A1]">Subtotal Menu ({{ $jmlItem }} item)</dt><dd class="font-semibold text-[#F1DFD8]">{{ $rp($ringkasan['subtotal']) }}</dd></div>
                    @if ($ringkasan['diskon_promo'] > 0)
                        <div class="flex justify-between"><dt class="text-[#B8A8A1]">Potongan Voucher {{ $ringkasan['promo']->kode_promo }}</dt><dd class="font-semibold text-[#F87171]">−{{ $rp($ringkasan['diskon_promo']) }}</dd></div>
                    @endif
                    @if ($ringkasan['poin_pakai'] > 0)
                        <div class="flex justify-between"><dt class="text-[#B8A8A1]">Potongan Poin</dt><dd class="font-semibold text-[#F87171]">−{{ $rp($ringkasan['diskon_poin']) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-[#B8A8A1]">PB1 (Pajak Restoran 10%)</dt><dd class="font-medium text-[#B8A8A1]">Termasuk</dd></div>
                    <div class="flex justify-between"><dt class="text-[#B8A8A1]">Biaya Layanan</dt><dd class="font-medium text-[#B8A8A1]">{{ $ringkasan['biaya_layanan'] > 0 ? $rp($ringkasan['biaya_layanan']) : 'Rp 0 (Free)' }}</dd></div>
                </dl>
                <div class="mt-3 flex items-end justify-between border-t border-[#3D322E] pt-3">
                    <div>
                        <p class="text-sm font-bold text-[#F1DFD8]">Total Pembayaran</p>
                        <p class="text-[11px] text-[#B8A8A1]">{{ $jmlItem }} item • {{ $tipeDipilih === 'dine-in' ? 'Makan di Tempat' . ($mejaDipilih ? ' • Meja ' . $mejaDipilih : '') : 'Bawa Pulang' }}</p>
                    </div>
                    <p class="grotesk text-2xl font-bold text-[#F1DFD8]">{{ $rp($ringkasan['total']) }}</p>
                </div>
            </section>

            {{-- Batalkan proses pemesanan --}}
            <form method="POST" action="{{ route('pelanggan.keranjang.batal') }}"
                  data-confirm="Batalkan pesanan ini? Keranjang, voucher, dan poin yang dipakai akan dikosongkan." class="mt-3">
                @csrf
                <button type="submit"
                        class="flex w-full items-center justify-center gap-1.5 rounded-md border border-[#BA1A1A]/30 px-3 py-2.5 text-[12.5px] font-semibold text-[#F87171] transition hover:bg-[#BA1A1A]/[.07]">
                    <i data-lucide="x" class="h-4 w-4"></i> Batalkan pesanan
                </button>
            </form>

            {{-- Sticky CTA --}}
            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-[#3D322E] bg-[#271D19]/95 backdrop-blur">
                <div class="mx-auto max-w-2xl px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="grotesk text-lg font-bold text-[#F1DFD8]">{{ $rp($ringkasan['total']) }}</p>
                        <button type="submit" form="tipe-form" class="grotesk flex-1 rounded-lg bg-[#D97706] py-3.5 text-[15px] font-bold text-[#241708] transition hover:bg-[#B56100]">
                            Lanjut ke Pembayaran
                        </button>
                    </div>
                    <p class="mt-1.5 text-center text-[11px] text-[#B8A8A1]">Pesanan dibuat di langkah berikutnya, dibayar di kasir.</p>
                </div>
            </div>
        @else
            <div class="cart-card mt-4 p-10 text-center">
                <i data-lucide="shopping-basket" class="mx-auto h-9 w-9 text-[#9D8E87]"></i>
                <p class="mt-3 text-sm text-[#B8A8A1]">Keranjang masih kosong.</p>
                <a href="{{ route('pelanggan.menu') }}" class="mt-4 inline-block rounded-md bg-[#D97706] px-5 py-2.5 text-[13px] font-semibold text-[#241708] hover:bg-[#B56100]">Lihat Menu</a>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>if (window.lucide) lucide.createIcons();</script>
@endpush
@endsection
