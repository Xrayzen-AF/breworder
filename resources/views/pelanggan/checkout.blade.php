@extends('layouts.app')

@section('title', 'Konfirmasi Pesanan — Tejas Coffee')

@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $jmlItem = array_sum(array_column($keranjang, 'jumlah'));
    $hemat = $ringkasan['diskon_promo'] + $ringkasan['diskon_poin'];
    $tipeAwal = old('tipe_pesanan', session('checkout_tipe', 'dine-in'));
@endphp

@section('content')
<style>
  .co { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .co .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .co .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
  .co-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .tipe input, .pay input { position: absolute; opacity: 0; pointer-events: none; }
  .tipe span.box { display: block; border: 1px solid #3D322E; border-radius: 6px; padding: 12px; cursor: pointer; text-align: center; transition: .15s; background: #2F231D; color: #B8A8A1; }
  .tipe input:checked + span.box { border: 1px solid #D97706; background: rgba(217,119,6,.16); color: #F6C89A; font-weight: 600; }
  .pay label { display: flex; align-items: center; gap: 12px; border: 1px solid #3D322E; border-radius: 6px; padding: 12px 14px; cursor: pointer; transition: .15s; background: #2F231D; color: #D4C3BC; }
  .pay label:has(input:checked) { border: 1px solid #D97706; background: rgba(217,119,6,.16); }
  .pay label.disabled { opacity: .45; cursor: not-allowed; background: #221A15; }
  .co-input { width: 100%; background: #221A15; border: 1px solid #3D322E; border-radius: var(--radius-control); padding: 10px 12px; font-size: 13px; color: #F1DFD8; outline: none; }
  .co-input:focus { background: #2A1F19; border-color: #D97706; box-shadow: 0 0 0 3px rgba(217,119,6,.18); }
  .co-input::placeholder { color: #82746E; }
  .co select option, .pay select option { background: #221A15; color: #F1DFD8; }
</style>

<div class="co min-h-screen pb-56">
    <div class="mx-auto max-w-2xl px-4 pt-4">
        {{-- Header --}}
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('pelanggan.keranjang.index') }}" class="flex items-center gap-1.5 text-[13px] font-semibold text-[#F1DFD8] hover:underline">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Keranjang
            </a>
            <p class="mono text-[11px] tracking-wide text-[#B8A8A1]">LANGKAH 3 DARI 3 • BAYAR</p>
        </div>
        <div class="mt-2 flex items-start justify-between gap-3">
            <div>
                <h1 class="grotesk text-xl font-bold text-[#F1DFD8]">Konfirmasi Pesanan</h1>
                <p class="mt-0.5 text-[13px] text-[#B8A8A1]">Periksa pesanan lalu bayar di kasir.</p>
            </div>
            <span class="flex shrink-0 items-center gap-1.5 rounded bg-[#2F231D] px-2 py-1 text-[11px] font-semibold text-[#B8A8A1]">
                <i data-lucide="lock" class="h-3.5 w-3.5"></i> Pembayaran Aman
            </span>
        </div>

        @if (session('error'))
            <div class="mt-3 rounded-lg border border-[#BA1A1A]/25 bg-[#BA1A1A]/[.06] px-4 py-3 text-[13px] font-medium text-[#F87171]">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-3 rounded-lg border border-[#BA1A1A]/25 bg-[#BA1A1A]/[.06] p-4">
                <ul class="list-inside list-disc space-y-1 text-[13px] font-medium text-[#F87171]">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('pelanggan.checkout.simpan') }}">
            @csrf

            {{-- Tipe pemesanan --}}
            <section class="co-card mt-4 p-4 sm:p-5">
                <h2 class="text-sm font-bold tracking-wide text-[#F1DFD8]">Jenis Pesanan</h2>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <label class="tipe relative">
                        <input type="radio" name="tipe_pesanan" value="dine-in" {{ $tipeAwal === 'dine-in' ? 'checked' : '' }} onchange="document.getElementById('meja-box').classList.remove('hidden')">
                        <span class="box">
                            <span class="block text-sm font-bold text-[#F1DFD8]">Makan di Tempat</span>
                            <span class="mt-0.5 block text-[11px] text-[#B8A8A1]">5–8 Menit</span>
                        </span>
                    </label>
                    <label class="tipe relative">
                        <input type="radio" name="tipe_pesanan" value="takeaway" {{ $tipeAwal === 'takeaway' ? 'checked' : '' }} onchange="document.getElementById('meja-box').classList.add('hidden')">
                        <span class="box">
                            <span class="block text-sm font-bold text-[#F1DFD8]">Bawa Pulang</span>
                            <span class="mt-0.5 block text-[11px] text-[#B8A8A1]">Ambil di kasir</span>
                        </span>
                    </label>
                </div>
                <div id="meja-box" class="mt-2 {{ $tipeAwal === 'dine-in' ? '' : 'hidden' }}">
                    <label for="checkout-meja-trigger" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-[#B8A8A1]">Nomor Meja</label>
                    @include('pelanggan._pemilih-meja', [
                        'mpId' => 'checkout-meja',
                        'mejaTersedia' => $mejaTersedia,
                        'terpilih' => old('id_meja', session('checkout_meja')),
                    ])
                </div>
            </section>

            {{-- Daftar item --}}
            <section class="co-card mt-4 p-4 sm:p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold tracking-wide text-[#F1DFD8]">Daftar Item ({{ $jmlItem }})</h2>
                    <a href="{{ route('pelanggan.keranjang.index') }}" class="text-xs font-bold text-[#F1DFD8] hover:underline">Ubah Pesanan</a>
                </div>
                <ul class="mt-3 space-y-3">
                    @foreach ($keranjang as $i)
                        <li class="flex gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#241B17] p-1 border border-[#3D322E]">
                                @if (! empty($i['gambar']))
                                    <img src="{{ $i['gambar'] }}" alt="{{ $i['nama'] }}" class="h-full w-full object-contain">
                                @else
                                    <i data-lucide="coffee" class="h-5 w-5 text-[#9D8E87]"></i>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#F1DFD8]">{{ $i['jumlah'] }}x {{ $i['nama'] }}</p>
                                @if (! empty($i['opsi_text']))
                                    <p class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-[#B8A8A1]">{{ $i['opsi_text'] }}</p>
                                @endif
                            </div>
                            <p class="grotesk shrink-0 text-sm font-bold text-[#F1DFD8]">{{ $rp($i['harga'] * $i['jumlah']) }}</p>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3">
                    <label for="catatan" class="block text-[11px] font-semibold uppercase tracking-wide text-[#B8A8A1]">Catatan Pesanan (opsional)</label>
                    <textarea name="catatan" id="catatan" rows="2" maxlength="500" placeholder="cth: less sugar, tanpa es, ..."
                              class="co-input mt-1.5">{{ old('catatan') }}</textarea>
                </div>
            </section>

            {{-- Metode pembayaran --}}
            <section class="co-card mt-4 p-4 sm:p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold tracking-wide text-[#F1DFD8]">Metode Pembayaran</h2>
                    <span class="text-[11px] text-[#B8A8A1]">Pembayaran dilakukan di kasir</span>
                </div>
                <div class="pay mt-3 space-y-2">
                    <label title="Bayar di kasir">
                        <input type="radio" name="metode" value="tunai" checked>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#D97706] text-[#241708]">
                            <i data-lucide="banknote" class="h-4 w-4"></i>
                        </span>
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-[#F1DFD8]">Bayar di Kasir</span>
                            <span class="block text-[11px] text-[#B8A8A1]">Tunjukkan nomor pesanan ke kasir</span>
                        </span>
                    </label>
                </div>
            </section>

            {{-- Kupon & loyalty --}}
            <section class="co-card mt-4 p-4 sm:p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold tracking-wide text-[#F1DFD8]">Kupon & Poin</h2>
                    <a href="{{ route('pelanggan.keranjang.index') }}" class="text-xs font-bold text-[#F1DFD8] hover:underline">Ganti</a>
                </div>
                @if ($ringkasan['promo'] || $ringkasan['poin_pakai'] > 0)
                    <p class="mt-1.5 text-[13px] text-[#B8A8A1]">
                        @if ($ringkasan['promo'])<strong class="grotesk tracking-widest text-[#F1DFD8]">{{ $ringkasan['promo']->kode_promo }}</strong>@endif
                        @if ($ringkasan['promo'] && $ringkasan['poin_pakai'] > 0) + @endif
                        @if ($ringkasan['poin_pakai'] > 0){{ number_format($ringkasan['poin_pakai'], 0, ',', '.') }} poin @endif
                    </p>
                @else
                    <p class="mt-1.5 text-[13px] text-[#B8A8A1]">Belum ada kupon atau poin yang dipakai.</p>
                @endif
            </section>

            {{-- Rincian --}}
            <section class="co-card mt-4 p-4 sm:p-5">
                <h2 class="text-sm font-bold tracking-wide text-[#F1DFD8]">Rincian Pembayaran</h2>
                <dl class="mt-2.5 space-y-1.5 text-[13px]">
                    <div class="flex justify-between"><dt class="text-[#B8A8A1]">Subtotal Menu ({{ $jmlItem }} item)</dt><dd class="font-semibold text-[#F1DFD8]">{{ $rp($ringkasan['subtotal']) }}</dd></div>
                    @if ($ringkasan['diskon_promo'] > 0)
                        <div class="flex justify-between">
                            <dt class="text-[#B8A8A1]">Diskon Promo {{ $ringkasan['promo']->kode_promo }} ({{ $ringkasan['promo']->tipe_diskon === 'persen' ? rtrim(rtrim($ringkasan['promo']->nilai_diskon, '0'), '.') . '%' : 'nominal' }})</dt>
                            <dd class="font-semibold text-[#F87171]">−{{ $rp($ringkasan['diskon_promo']) }}</dd>
                        </div>
                    @endif
                    @if ($ringkasan['poin_pakai'] > 0)
                        <div class="flex justify-between"><dt class="text-[#B8A8A1]">Poin Ditukar ({{ number_format($ringkasan['poin_pakai'], 0, ',', '.') }} pts)</dt><dd class="font-semibold text-[#F87171]">−{{ $rp($ringkasan['diskon_poin']) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-[#B8A8A1]">Biaya Layanan</dt><dd class="font-medium text-[#B8A8A1]">{{ $ringkasan['biaya_layanan'] > 0 ? $rp($ringkasan['biaya_layanan']) : 'Rp 0 (Free)' }}</dd></div>
                </dl>
                <div class="mt-3 flex items-end justify-between border-t border-[#3D322E] pt-3">
                    <div>
                        <p class="text-sm font-bold text-[#F1DFD8]">Total Pembayaran</p>
                        <p class="text-[11px] text-[#B8A8A1]">Termasuk pajak restoran pemerintah</p>
                    </div>
                    <p class="grotesk text-2xl font-bold text-[#F1DFD8]">{{ $rp($ringkasan['total']) }}</p>
                </div>
            </section>

            <div class="mt-4 flex items-start gap-2.5 rounded-lg bg-[#2F231D] px-4 py-3">
                <i data-lucide="zap" class="mt-0.5 h-4 w-4 shrink-0 text-[#F1DFD8]"></i>
                <p class="text-xs leading-relaxed text-[#B8A8A1]">Pesanan diteruskan ke mesin KDS Barista secara otomatis segera setelah dibuat. Tunjukkan nomor tiket di kasir untuk membayar.</p>
            </div>

            {{-- Footer sticky --}}
            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-[#3D322E] bg-[#271D19] shadow-[0_-4px_16px_rgba(0,0,0,.05)]">
                <div class="mx-auto max-w-2xl px-4 py-3">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#B8A8A1]">Total Tagihan</p>
                            <p class="grotesk text-2xl font-bold leading-none text-[#F1DFD8]">{{ $rp($ringkasan['total']) }}</p>
                        </div>
                        @if ($hemat > 0)
                            <p class="rounded bg-[#D97706] px-2.5 py-1 text-[11px] font-bold text-[#241708]">Hemat {{ $rp($hemat) }}</p>
                        @endif
                    </div>
                    <button type="submit" class="grotesk mt-2.5 w-full rounded-lg bg-[#D97706] py-4 text-[16px] font-bold text-[#241708] transition hover:bg-[#B56100]">
                        Buat Pesanan & Kirim ke Barista ({{ $rp($ringkasan['total']) }})
                    </button>
                    <p class="mt-1.5 text-center text-[11px] text-[#B8A8A1]">Bayar tunai di kasir setelah pesanan dibuat.</p>
                </div>
            </div>
        </form>

        {{-- Batalkan proses pemesanan --}}
        <form method="POST" action="{{ route('pelanggan.keranjang.batal') }}"
              data-confirm="Batalkan pesanan ini? Keranjang, voucher, dan poin yang dipakai akan dikosongkan." class="mt-3 pb-2">
            @csrf
            <button type="submit"
                    class="flex w-full items-center justify-center gap-1.5 rounded-md border border-[#BA1A1A]/30 px-3 py-2.5 text-[12.5px] font-semibold text-[#F87171] transition hover:bg-[#BA1A1A]/[.07]">
                <i data-lucide="x" class="h-4 w-4"></i> Batalkan pesanan
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>if (window.lucide) lucide.createIcons();</script>
@endpush
@endsection
