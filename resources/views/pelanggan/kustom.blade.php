@extends('layouts.app')

@section('title', $produk->nama_produk . ' — Kustomisasi')

@php
    use App\Http\Controllers\CartController;
    $UK = CartController::OPSI_UKURAN;
    $SU = CartController::OPSI_SUSU;
    $ES = CartController::OPSI_ES;
    $MA = CartController::OPSI_MANIS;
    $AD = CartController::OPSI_ADDON;
    $rp = fn ($n) => '+Rp ' . number_format($n, 0, ',', '.');
    // Mode ubah: prefill dari baris keranjang lama (old() tetap menang saat validasi gagal).
    $editOpsi = $editBaris['opsi'] ?? [];
    $modeUbah = ! empty($editKey) && ! empty($editBaris);
    $defUkuran = old('ukuran', $editOpsi['ukuran'] ?? 'regular');
    $defSusu = old('susu', $editOpsi['susu'] ?? 'fresh');
    $defEs = old('es', $editOpsi['es'] ?? 'normal');
    $defManis = old('manis', $editOpsi['manis'] ?? 'normal');
    $defAddon = old('addon', $editOpsi['addon'] ?? []);
    $defCatatan = old('catatan', $editBaris['catatan'] ?? '');
    $defJumlah = old('jumlah', $editBaris['jumlah'] ?? 1);
@endphp

@section('content')
<style>
  .cust { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .cust .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .cust .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
  .cust-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .opt { display: block; border: 1px solid #3D322E; border-radius: 6px; padding: 12px 14px; cursor: pointer; transition: .15s; background: #2F231D; }
  .opt:hover { border-color: #6B5A4E; }
  .opt:has(:checked) { border: 1px solid #D97706; background: rgba(217,119,6,.14); }
  .badge-must { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: #D97706; color: #241708; letter-spacing: .04em; }
  .badge-pick { font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: #352A22; color: #F1DFD8; }
  .badge-opt { font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: #2F231D; color: #B8A8A1; }

  /* ——— Layout responsif ——— */
  .cust-wrap { width: 100%; max-width: 640px; margin-inline: auto; }
  @media (min-width: 768px) { .cust-wrap { max-width: 800px; } }
  @media (min-width: 1024px) { .cust-wrap { max-width: 1040px; } }
  @media (min-width: 1280px) { .cust-wrap { max-width: 1120px; } }

  .cust-layout { display: grid; gap: 16px; align-items: start; }
  .cust-right { display: grid; gap: 16px; }
  @media (min-width: 1024px) {
    .cust-layout { grid-template-columns: minmax(0, 380px) minmax(0, 1fr); gap: 24px; }
    .cust-left { position: sticky; top: 16px; }
  }

  /* ——— Bar bawah (sticky) ——— */
  .cust-bar { position: fixed; inset-inline: 0; bottom: 0; z-index: 30; border-top: 1px solid #3D322E; background: rgba(39,29,25,.96); backdrop-filter: blur(8px); }
  .cust-bar-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 12px; align-items: center; }
  .cust-bar-sub { grid-column: 1 / -1; display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
  .cust-bar-btn { grid-column: 1 / -1; width: 100%; border-radius: 6px; background: #D97706; padding: 14px; font-family: 'Lora', serif; font-size: 15px; font-weight: 700; color: #241708; transition: .15s; }
  .cust-bar-btn:hover { background: #B56100; }
  @media (min-width: 900px) {
    .cust-bar-row { grid-template-columns: auto auto minmax(0, 1fr) auto; gap: 20px; }
    .cust-bar-title, .cust-bar-qty, .cust-bar-sub, .cust-bar-btn { grid-column: auto; }
    .cust-bar-sub { justify-content: flex-end; }
    .cust-bar-btn { width: auto; min-width: 260px; }
  }

  /* Cegah iOS auto-zoom */
  @media (max-width: 639.98px) {
    .cust textarea, .cust input[type="text"] { font-size: 16px; }
  }
</style>

<div class="cust min-h-screen pb-56">
    <div class="cust-wrap px-4 pt-4">
        {{-- Topbar alur --}}
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('pelanggan.menu') }}" class="flex items-center gap-1.5 text-[13px] font-semibold text-[#F1DFD8] hover:underline">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Menu
            </a>
            <p class="mono text-[11px] tracking-wide text-[#B8A8A1]">LANGKAH 1 DARI 3 • ATUR PESANAN</p>
        </div>
        <ol class="mt-2 flex items-center gap-1.5 text-[11px] font-semibold">
            <li class="rounded bg-[#D97706] px-2 py-0.5 text-[#241708]">1 Atur</li>
            <li class="text-[#9D8E87]">—</li>
            <li class="rounded bg-[#2F231D] px-2 py-0.5 text-[#B8A8A1]">2 Keranjang</li>
            <li class="text-[#9D8E87]">—</li>
            <li class="rounded bg-[#2F231D] px-2 py-0.5 text-[#B8A8A1]">3 Bayar</li>
        </ol>

        @if ($errors->any())
            <div class="mt-3 rounded-lg border border-[#BA1A1A]/25 bg-[#BA1A1A]/[.06] p-4">
                <ul class="list-inside list-disc space-y-1 text-[13px] font-medium text-[#F87171]">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="cust-layout mt-3">
            {{-- ═══ KIRI: hero produk ═══ --}}
            <div class="cust-left">
                <div class="cust-card overflow-hidden">
                    <div class="relative flex min-h-[220px] max-h-[360px] sm:min-h-[280px] sm:max-h-[420px] items-center justify-center bg-[#241B17] overflow-hidden p-4">
                        @if ($produk->url_gambar)
                            <img src="{{ $produk->url_gambar }}" alt="{{ $produk->nama_produk }}" class="max-h-[340px] sm:max-h-[390px] w-auto max-w-full object-contain mx-auto transition-transform duration-300 hover:scale-105">
                        @else
                            <div class="text-center text-[#9D8E87]">
                                <i data-lucide="coffee" class="mx-auto h-12 w-12"></i>
                                <p class="mono mt-1 text-[11px]">IMG: TEJAS COFFEE {{ strtoupper($produk->nama_produk) }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h1 class="grotesk text-xl font-bold leading-snug text-[#F1DFD8]">{{ $produk->nama_produk }}</h1>
                            <p class="grotesk shrink-0 text-lg font-bold text-[#F1DFD8]">Rp {{ number_format($produk->harga, 0, ',', '.') }}</p>
                        </div>
                        @if ($produk->deskripsi)
                            <p class="mt-1 text-[13px] leading-relaxed text-[#B8A8A1]">{{ $produk->deskripsi }}</p>
                        @endif
                        <p class="mt-2 text-xs text-[#B8A8A1]">Kategori: <span class="font-semibold text-[#F1DFD8]">{{ $produk->kategori->nama_kategori ?? '—' }}</span> • Stok {{ $produk->stok }}</p>
                    </div>
                </div>
            </div>

            {{-- ═══ KANAN: form kustomisasi ═══ --}}
            <div class="cust-right">
                <form method="POST" action="{{ route('pelanggan.keranjang.tambah', $produk->id_produk) }}" id="cust-form" class="grid gap-4">
                    @csrf
                    @if ($modeUbah)
                        <input type="hidden" name="ganti_key" value="{{ $editKey }}">
                        <div class="cust-card border border-[#000] bg-[#1E1511] p-4 text-[13px] font-medium text-[#F1DFD8]">
                            Mode ubah: menyimpan akan <strong>mengganti</strong> baris lama di keranjang, bukan menambah baru.
                        </div>
                    @endif

                    {{-- 1. Ukuran --}}
                    <section class="cust-card p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="grotesk text-[15px] font-bold text-[#F1DFD8]">1. Pilihan Ukuran</h2>
                            <div class="flex gap-1.5"><span class="badge-must">WAJIB</span><span class="badge-pick">Pilih 1</span></div>
                        </div>
                        <div class="mt-3 space-y-2">
                            <label class="opt">
                                <input type="radio" name="ukuran" value="regular" class="sr-only" data-delta="0" data-tag="" {{ $defUkuran === 'regular' ? 'checked' : '' }}>
                                <span class="flex items-center justify-between gap-3">
                                    <span><span class="block text-sm font-semibold text-[#F1DFD8]">350 ml Regular</span>
                                    <span class="mt-0.5 block text-xs text-[#B8A8A1]">Default</span></span>
                                    <span class="text-[13px] font-semibold text-[#B8A8A1]">+Rp 0</span>
                                </span>
                            </label>
                            <label class="opt">
                                <input type="radio" name="ukuran" value="large" class="sr-only" data-delta="6000" data-tag="LARGE" {{ $defUkuran === 'large' ? 'checked' : '' }}>
                                <span class="flex items-center justify-between gap-3">
                                    <span class="block text-sm font-semibold text-[#F1DFD8]">500 ml Large</span>
                                    <span class="text-[13px] font-semibold text-[#F1DFD8]">{{ $rp(6000) }}</span>
                                </span>
                            </label>
                        </div>
                    </section>

                    {{-- 2. Susu --}}
                    <section class="cust-card p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="grotesk text-[15px] font-bold text-[#F1DFD8]">2. Pilihan Susu</h2>
                            <div class="flex gap-1.5"><span class="badge-must">WAJIB</span><span class="badge-pick">Pilih 1</span></div>
                        </div>
                        <div class="mt-3 space-y-2">
                            @php
                                $susuDesc = ['fresh' => 'Standar Barista House Blend', 'oat' => 'Plant-based • Creamy', 'almond' => 'Plant-based • Nutty & Light', 'skim' => 'Low Fat Dairy'];
                                $susuTag = ['fresh' => '', 'oat' => 'OAT', 'almond' => 'ALMOND', 'skim' => ''];
                            @endphp
                            @foreach ($SU as $val => $o)
                                <label class="opt">
                                    <input type="radio" name="susu" value="{{ $val }}" class="sr-only" data-delta="{{ $o['delta'] }}" data-tag="{{ $susuTag[$val] }}" {{ $defSusu === $val ? 'checked' : '' }}>
                                    <span class="flex items-center justify-between gap-3">
                                        <span><span class="block text-sm font-semibold text-[#F1DFD8]">{{ $o['label'] }}</span>
                                        <span class="mt-0.5 block text-xs text-[#B8A8A1]">{{ $susuDesc[$val] }}</span></span>
                                        <span class="text-[13px] font-semibold {{ $o['delta'] ? 'text-[#F1DFD8]' : 'text-[#B8A8A1]' }}">{{ $rp($o['delta']) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- 3. Es --}}
                    <section class="cust-card p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="grotesk text-[15px] font-bold text-[#F1DFD8]">3. Level Es</h2>
                            <div class="flex gap-1.5"><span class="badge-must">WAJIB</span><span class="badge-pick">Pilih 1</span></div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach ($ES as $val => $o)
                                <label class="opt !px-2 text-center">
                                    <input type="radio" name="es" value="{{ $val }}" class="sr-only" {{ $defEs === $val ? 'checked' : '' }}>
                                    <span class="block text-[13px] font-semibold text-[#F1DFD8]">{{ $o['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- 4. Manis --}}
                    <section class="cust-card p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="grotesk text-[15px] font-bold text-[#F1DFD8]">4. Level Manis</h2>
                            <div class="flex gap-1.5"><span class="badge-must">WAJIB</span><span class="badge-pick">Pilih 1</span></div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach ($MA as $val => $o)
                                <label class="opt !px-2 text-center">
                                    <input type="radio" name="manis" value="{{ $val }}" class="sr-only" {{ $defManis === $val ? 'checked' : '' }}>
                                    <span class="block text-[13px] font-semibold text-[#F1DFD8]">{{ $o['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- 5. Add-on --}}
                    <section class="cust-card p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="grotesk text-[15px] font-bold text-[#F1DFD8]">5. Tambahan / Add-ons</h2>
                            <div class="flex gap-1.5"><span class="badge-opt">OPSIONAL</span><span class="badge-pick">Bisa &gt; 1</span></div>
                        </div>
                        <div class="mt-3 space-y-2">
                            @php
                                $adDesc = ['shot' => '+1 Single origin ristretto', 'foam' => 'Cold foam topping velvet texture'];
                                $adTag = ['shot' => 'SHOT', 'foam' => 'FOAM'];
                            @endphp
                            @foreach ($AD as $val => $o)
                                <label class="opt">
                                    <input type="checkbox" name="addon[]" value="{{ $val }}" class="sr-only" data-delta="{{ $o['delta'] }}" data-tag="{{ $adTag[$val] }}" {{ in_array($val, $defAddon) ? 'checked' : '' }}>
                                    <span class="flex items-center justify-between gap-3">
                                        <span><span class="block text-sm font-semibold text-[#F1DFD8]">{{ $o['label'] }}</span>
                                        <span class="mt-0.5 block text-xs text-[#B8A8A1]">{{ $adDesc[$val] }}</span></span>
                                        <span class="text-[13px] font-semibold text-[#F1DFD8]">{{ $rp($o['delta']) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- 6. Catatan --}}
                    <section class="cust-card p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="grotesk text-[15px] font-bold text-[#F1DFD8]">6. Catatan Khusus Barista</h2>
                            <div class="flex gap-1.5"><span class="badge-opt">OPSIONAL</span><span class="badge-pick">Maks. 100 Karakter</span></div>
                        </div>
                        <textarea name="catatan" id="cust-note" rows="2" maxlength="100" placeholder="Contoh: Pisahkan es batu, sedotan ramah lingkungan..."
                                  class="mt-3 w-full rounded-lg border border-[#3D322E] bg-[#1E1511] px-3.5 py-3 text-sm text-[#F1DFD8] outline-none placeholder:text-[#9D8E87] focus:border-[#D97706] focus:bg-[#271D19]">{{ $defCatatan }}</textarea>
                        <p class="mt-1 text-right text-[11px] text-[#B8A8A1]"><span id="cust-count">0</span>/100</p>
                    </section>

                    {{-- Bar bawah: qty, subtotal, submit --}}
                    <div class="cust-bar">
                        <div class="cust-wrap px-4 py-3">
                            <div class="cust-bar-row">
                                <div class="cust-bar-title">
                                    <p class="text-[13px] font-bold text-[#F1DFD8]">Atur Pesanan</p>
                                    <p class="text-[11px] text-[#B8A8A1]">Tejas Coffee</p>
                                </div>
                                <div class="cust-bar-qty flex items-center gap-1.5">
                                    <button type="button" id="qty-min" class="flex h-8 w-8 items-center justify-center rounded-md bg-[#2F231D] text-lg font-bold text-[#F1DFD8] hover:bg-[#352A22]">−</button>
                                    <span id="qty-view" class="grotesk w-6 text-center text-[15px] font-bold">1</span>
                                    <button type="button" id="qty-plus" class="flex h-8 w-8 items-center justify-center rounded-md bg-[#2F231D] text-lg font-bold text-[#F1DFD8] hover:bg-[#352A22]">+</button>
                                </div>
                                <input type="hidden" name="jumlah" id="qty-input" value="{{ $defJumlah }}">
                                <div class="cust-bar-sub">
                                    <span class="text-[11px] text-[#B8A8A1]">Subtotal Pesanan</span>
                                    <span class="grotesk text-xl font-bold text-[#F1DFD8]" id="cust-sub">Rp 0</span>
                                </div>
                                <button type="submit" class="cust-bar-btn">
                                    {{ $modeUbah ? 'Simpan Perubahan' : '+ Tambah ke Keranjang' }}
                                </button>
                            </div>
                            <p class="mono mt-1 truncate text-[11px] text-[#B8A8A1]" id="cust-break">BASE</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
if (window.lucide) lucide.createIcons();
(function () {
  var base = parseFloat('{{ (float) $produk->harga }}');
  var maxStok = parseInt('{{ $produk->stok }}', 10) || 20;
  var qty = Math.min(Math.max(parseInt(document.getElementById('qty-input').value, 10) || 1, 1), Math.min(20, maxStok));
  function kShort(n) {
    if (n >= 1000) {
      var k = n / 1000, s = (k % 1 === 0) ? k.toString() : (Math.round(k * 10) / 10).toString().replace('.', ',');
      return s + 'K';
    }
    return n.toString();
  }
  function rId(n) { return 'Rp ' + n.toLocaleString('id-ID'); }
  function recalc() {
    var total = base, parts = ['BASE: ' + kShort(base)];
    document.querySelectorAll('#cust-form input[type=radio]:checked').forEach(function (el) {
      var d = parseFloat(el.dataset.delta || '0');
      if (d > 0) { total += d; parts.push((el.dataset.tag || 'OPT') + ': ' + kShort(d)); }
    });
    document.querySelectorAll('#cust-form input[type=checkbox]:checked').forEach(function (el) {
      var d = parseFloat(el.dataset.delta || '0');
      total += d; parts.push((el.dataset.tag || 'ADD') + ': ' + kShort(d));
    });
    document.getElementById('qty-view').textContent = qty;
    document.getElementById('qty-input').value = qty;
    document.getElementById('cust-sub').textContent = rId(total * qty);
    document.getElementById('cust-break').textContent = parts.join(' + ') + ' = ' + rId(total * qty);
  }
  document.getElementById('qty-min').addEventListener('click', function () { if (qty > 1) { qty--; recalc(); } });
  document.getElementById('qty-plus').addEventListener('click', function () { if (qty < Math.min(20, maxStok)) { qty++; recalc(); } });
  document.querySelectorAll('#cust-form input[type=radio], #cust-form input[type=checkbox]').forEach(function (el) {
    el.addEventListener('change', recalc);
  });
  var note = document.getElementById('cust-note'), cnt = document.getElementById('cust-count');
  function updCnt() { cnt.textContent = note.value.length; }
  note.addEventListener('input', updCnt); updCnt();
  recalc();
})();
</script>
@endpush
@endsection
