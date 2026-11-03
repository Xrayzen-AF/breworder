@extends('layouts.app')

@section('title', 'Menu — Tejas Coffee')

@php
    $user = auth()->user();
    $nama = $user->nama_lengkap ?? 'Kawan';
    $namaDepan = explode(' ', trim($nama))[0] ?: 'Kawan';
    $ikonKategori = function ($nama) {
        $n = strtolower($nama);
        if (str_contains($n, 'tea') || str_contains($n, 'teh')) return 'cup-soda';
        if (str_contains($n, 'espresso') || str_contains($n, 'latte') || str_contains($n, 'kopi')) return 'coffee';
        if (str_contains($n, 'pour') || str_contains($n, 'manual') || str_contains($n, 'filter')) return 'droplet';
        if (str_contains($n, 'cold') || str_contains($n, 'nitro') || str_contains($n, 'ice')) return 'snowflake';
        if (str_contains($n, 'pastry') || str_contains($n, 'bakery') || str_contains($n, 'food') || str_contains($n, 'roti') || str_contains($n, 'croissant') || str_contains($n, 'snack')) return 'croissant';
        if (str_contains($n, 'bean') || str_contains($n, 'biji') || str_contains($n, 'merch')) return 'shopping-basket';
        return 'sparkles';
    };
@endphp

@section('content')
<style>
  .menu-page { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1E1511; color: #F1DFD8; }
  .menu-page .grotesk { font-family: 'Lora', ui-serif, Georgia, serif; }
  .menu-card { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); box-shadow: 0 1px 2px rgba(0,0,0,.45); }
  .menu-search { background: #271D19; border: 1px solid #3D322E; border-radius: var(--radius-surface); }
  .menu-search input { background: transparent; border: 0; outline: none; color: #F1DFD8; width: 100%; font-size: 13px; }
  .menu-search input::placeholder { color: #82746E; }
  .kat { display: flex; flex-direction: column; align-items: center; gap: 8px; min-width: 76px; }
  .kat span.ic { width: 52px; height: 52px; border-radius: 9999px; background: #271D19; border: 1px solid #3D322E; display: flex; align-items: center; justify-content: center; color: #E0A96D; transition: .15s; }
  .kat:hover span.ic, .kat.active span.ic { background: #D97706; border-color: #D97706; color: #241708; }
  .kat small { font-size: 11px; font-weight: 500; color: #B8A8A1; text-align: center; line-height: 1.3; }
  .kat.active small { color: #F1DFD8; font-weight: 700; }

  /* ——— Grid katalog: CSS Grid auto-fill ———
     HP kecil (<520px) dipaksa tepat 2 kolom (kartu ±150–170px).
     Di atasnya kolom bertambah otomatis 3 → 4 → 5 sesuai lebar layar. */
  .menu-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  }
  @media (max-width: 519.98px) {
    .menu-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .mnu-nama { font-size: 13px; }
    .mnu-desc { font-size: 11.5px; line-height: 1.45; }
  }
  @media (min-width: 640px) {
    .menu-grid { gap: 16px; }
  }
  /* Maksimum 5 kolom di layar lebar agar kartu tidak terlalu kecil */
  @media (min-width: 1024px) {
    .menu-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
  }
  /* Tombol lebih besar agar nyaman ditekan jari di HP/tablet */
  @media (max-width: 639.98px) {
    .mnu-tambah { width: 38px; height: 38px; }
  }
  .mnu-thumb { position: relative; display: flex; align-items: center; justify-content: center; width: 100%; aspect-ratio: 4 / 3; background: #241B17; padding: 10px; overflow: hidden; border-radius: 0; }
  .mnu-thumb img { width: 100%; height: 100%; object-fit: contain; transition: transform .25s ease; cursor: zoom-in; }
  .mnu-thumb:hover img { transform: scale(1.05); }

  /* ——— Modal detail produk ——— */
  .produk-modal { position: fixed; inset: 0; z-index: 9998; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(12,9,7,.78); backdrop-filter: blur(3px); }
  .produk-modal.is-open { display: flex; }
  .produk-modal-panel { width: 100%; max-width: 440px; max-height: 88vh; overflow-y: auto; background: #231A15; border: 1px solid #3D322E; border-radius: var(--radius-overlay); box-shadow: 0 30px 70px -30px rgba(0,0,0,.85); animation: produk-modal-in .18s ease-out; }
  .produk-modal-gambar { display: flex; align-items: center; justify-content: center; background: #241B17; border-bottom: 1px solid #3D322E; border-radius: var(--radius-overlay) var(--radius-overlay) 0 0; padding: 16px; }
  .produk-modal-gambar img { width: 100%; max-height: 260px; object-fit: contain; }
  .produk-modal-body { padding: 18px 20px 20px; }
  .produk-modal-title { font-family: 'Lora', serif; font-weight: 700; font-size: 20px; line-height: 1.3; color: #F1DFD8; }
  .produk-modal-desc { margin-top: 6px; font-size: 13px; line-height: 1.7; color: #B8A8A1; }
  .produk-modal-harga { margin-top: 14px; font-family: 'Lora', serif; font-weight: 700; font-size: 20px; color: #F59E0B; }
  .produk-modal-aksi { display: flex; gap: 10px; margin-top: 16px; }
  .produk-modal-aksi a, .produk-modal-aksi button { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 11px 14px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; transition: .15s; border: 1px solid transparent; }
  .produk-modal-tutup { background: transparent; border-color: #4A3B35; color: #D7C7BE; }
  .produk-modal-tutup:hover { border-color: #5F4B44; color: #F1DFD8; }
  .produk-modal-pesan { background: #D97706; color: #241708; }
  .produk-modal-pesan:hover { filter: brightness(1.06); }
  .produk-modal-pesan[aria-disabled="true"] { background: #352A22; color: #9D8E87; cursor: not-allowed; filter: none; }
  .produk-modal-x { position: absolute; top: 10px; right: 12px; width: 32px; height: 32px; border-radius: 6px; background: #271D19; border: 1px solid #3D322E; color: #D7C7BE; display: flex; align-items: center; justify-content: center; font-size: 18px; line-height: 1; cursor: pointer; }
  @keyframes produk-modal-in { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
</style>

<div class="menu-page min-h-screen pb-32">
    <div class="mx-auto max-w-2xl sm:max-w-3xl md:max-w-4xl lg:max-w-5xl xl:max-w-6xl px-4 pt-5">
        {{-- ═══ HEADER ═══ --}}
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <img src="{{ asset('images/logo-tejas.png') }}" alt="Tejas Coffee"
                     class="h-9 w-9 shrink-0 object-contain" width="36" height="36">
                <div>
                    <p class="text-xs text-[#B8A8A1]">Tejas Coffee &bull; Katalog Menu</p>
                    <h1 class="grotesk text-xl sm:text-2xl font-bold text-[#F1DFD8]">Mau ngopi apa, {{ $namaDepan }}?</h1>
                </div>
            </div>
            <a href="{{ route('pelanggan.keranjang.index') }}"
               class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-[#271D19] border border-[#3D322E] text-[#F1DFD8]"
               title="Keranjang">
                <i data-lucide="shopping-basket" class="h-5 w-5"></i>
                @if ($jumlahItemKeranjang > 0)
                    <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#D97706] px-1 text-[10px] font-bold text-[#241708]">{{ $jumlahItemKeranjang }}</span>
                @endif
            </a>
        </div>

        {{-- ═══ PENCARIAN ═══ --}}
        <form method="GET" action="{{ route('pelanggan.menu') }}" class="menu-search mt-4 flex items-center gap-2 px-4 py-3">
            @if ($kategoriAktif)
                <input type="hidden" name="kategori" value="{{ $kategoriAktif }}">
            @endif
            <i data-lucide="search" class="h-4 w-4 shrink-0 text-[#9D8E87]"></i>
            <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari menu, rasa, kategori...">
            @if ($cari !== '')
                <a href="{{ route('pelanggan.menu', array_filter(['kategori' => $kategoriAktif])) }}"
                   class="shrink-0 text-[#9D8E87] hover:text-[#F1DFD8]" title="Hapus pencarian">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </a>
            @endif
        </form>

        {{-- ═══ KATEGORI ═══ --}}
        <div class="mt-4 flex gap-3 overflow-x-auto pb-2">
            <a href="{{ route('pelanggan.menu', array_filter(['cari' => $cari !== '' ? $cari : null])) }}"
               class="kat {{ ! $kategoriAktif ? 'active' : '' }}">
                <span class="ic"><i data-lucide="sparkles" class="h-5 w-5"></i></span>
                <small>Semua</small>
            </a>
            @foreach ($kategori as $k)
                <a href="{{ route('pelanggan.menu', array_filter(['kategori' => $k->nama_kategori, 'cari' => $cari !== '' ? $cari : null])) }}"
                   class="kat {{ $kategoriAktif === $k->nama_kategori ? 'active' : '' }}">
                    <span class="ic"><i data-lucide="{{ $ikonKategori($k->nama_kategori) }}" class="h-5 w-5"></i></span>
                    <small>{{ $k->nama_kategori }}</small>
                </a>
            @endforeach
        </div>

        {{-- ═══ INFO HASIL ═══ --}}
        <p class="mt-3 text-[11px] sm:text-xs text-[#B8A8A1]">
            {{ $produk->count() }} menu{{ $kategoriAktif ? ' • ' . $kategoriAktif : '' }}{{ $cari !== '' ? ' • "' . $cari . '"' : '' }}
        </p>

        {{-- ═══ GRID MENU ═══ --}}
        <div class="menu-grid mt-3">
            @forelse ($produk as $p)
                @php $habis = ! $p->status_tersedia || $p->stok <= 0; @endphp
                <div class="menu-card flex flex-col overflow-hidden transition hover:border-[#554336]">
                    {{-- Gambar tidak terpotong (object-fit: contain) — klik untuk lihat detail --}}
                    <button type="button"
                            class="mnu-thumb text-left"
                            data-produk
                            data-nama="{{ $p->nama_produk }}"
                            data-deskripsi="{{ $p->deskripsi ?? '' }}"
                            data-harga="Rp {{ number_format($p->harga, 0, ',', '.') }}"
                            data-gambar="{{ $p->url_gambar ?? '' }}"
                            data-pesan="{{ $habis ? '' : route('pelanggan.menu.kustom', $p->id_produk) }}"
                            aria-label="Lihat detail {{ $p->nama_produk }}">
                        @if ($p->url_gambar)
                            <img src="{{ $p->url_gambar }}" alt="{{ $p->nama_produk }}">
                        @else
                            <span class="flex h-full w-full items-center justify-center text-[#9D8E87]">
                                <i data-lucide="coffee" class="h-10 w-10"></i>
                            </span>
                        @endif

                        <span class="pointer-events-none absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-[#271D19]/90 text-[#F87171] shadow" title="Favorit pelanggan">
                            <i data-lucide="heart" class="h-4 w-4 fill-current"></i>
                        </span>
                        @if ($habis)
                            <span class="pointer-events-none absolute left-2 top-2 rounded bg-[#BA1A1A] px-2 py-0.5 text-[10px] font-bold text-white shadow">HABIS</span>
                        @endif
                    </button>

                    <div class="flex flex-1 flex-col justify-between p-3 sm:p-3.5">
                        <div>
                            <h3 class="mnu-nama grotesk line-clamp-1 text-sm font-semibold text-[#F1DFD8]">{{ $p->nama_produk }}</h3>
                            @if ($p->deskripsi)
                                <p class="mnu-desc mt-0.5 line-clamp-2 min-h-[30px] text-[11px] sm:text-xs leading-snug text-[#B8A8A1]">{{ $p->deskripsi }}</p>
                            @endif
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="grotesk text-sm sm:text-base font-bold text-[#F1DFD8]">Rp {{ number_format($p->harga, 0, ',', '.') }}</span>
                            @if (! $habis)
                                <a href="{{ route('pelanggan.menu.kustom', $p->id_produk) }}"
                                   class="mnu-tambah flex h-8 w-8 items-center justify-center rounded-md bg-[#D97706] text-[#241708] transition hover:bg-[#B56100]" title="Atur &amp; tambah">
                                    <i data-lucide="plus" class="h-4 w-4"></i>
                                </a>
                            @else
                                <span class="rounded bg-[#352A22] px-2 py-1 text-[10px] font-bold text-[#9D8E87]">HABIS</span>
                            @endif
                        </div>
                        @if ($p->stok > 0 && $p->stok <= 5)
                            <p class="mt-1 text-[10px] font-bold text-[#F87171]">Sisa {{ $p->stok }}!</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="menu-card col-span-full p-8 text-center text-sm text-[#B8A8A1]">
                    Tidak ada menu{{ $cari !== '' ? ' untuk "' . $cari . '"' : '' }}{{ $kategoriAktif ? ' pada kategori ini' : '' }}.
                </p>
            @endforelse
        </div>
    </div>

    {{-- ═══ STICKY CART ═══ --}}
    @if ($jumlahItemKeranjang > 0)
        <a href="{{ route('pelanggan.keranjang.index') }}"
           class="fixed inset-x-4 bottom-[78px] z-30 mx-auto flex max-w-md sm:max-w-xl md:max-w-2xl items-center justify-between rounded-md bg-[#D97706] px-5 py-3.5 text-[#241708] shadow-lg transition hover:bg-[#B56100]">
            <span class="flex items-center gap-2 text-sm font-semibold">
                <i data-lucide="shopping-basket" class="h-4 w-4"></i> {{ $jumlahItemKeranjang }} item
            </span>
            <span class="grotesk text-sm font-bold">Rp {{ number_format($totalKeranjang, 0, ',', '.') }} &rarr;</span>
        </a>
    @endif

    {{-- ═══ BOTTOM NAV ═══ --}}
    @include('pelanggan._nav', ['aktif' => 'menu'])
</div>

{{-- ═══ MODAL DETAIL PRODUK ═══ --}}
<div id="produk-modal" class="produk-modal" aria-hidden="true">
    <div class="produk-modal-panel relative" role="dialog" aria-modal="true" aria-labelledby="produk-modal-title">
        <button type="button" class="produk-modal-x" data-tutup-produk aria-label="Tutup">&times;</button>
        <div class="produk-modal-gambar">
            <img id="produk-modal-gambar" src="" alt="">
        </div>
        <div class="produk-modal-body">
            <h2 id="produk-modal-title" class="produk-modal-title"></h2>
            <p id="produk-modal-deskripsi" class="produk-modal-desc"></p>
            <p id="produk-modal-harga" class="produk-modal-harga"></p>
            <div class="produk-modal-aksi">
                <button type="button" class="produk-modal-tutup" data-tutup-produk>Batal</button>
                <a id="produk-modal-pesan" class="produk-modal-pesan" href="#" aria-disabled="true">
                    <i data-lucide="plus" class="h-4 w-4"></i> Pesan
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('produk-modal');
        if (!modal) return;

        var elGambar = document.getElementById('produk-modal-gambar');
        var elNama = document.getElementById('produk-modal-title');
        var elDeskripsi = document.getElementById('produk-modal-deskripsi');
        var elHarga = document.getElementById('produk-modal-harga');
        var elPesan = document.getElementById('produk-modal-pesan');
        var fokusTerakhir = null;

        function bukaModal(trigger) {
            var gambar = trigger.getAttribute('data-gambar') || '';
            var pesan = trigger.getAttribute('data-pesan') || '';

            elNama.textContent = trigger.getAttribute('data-nama') || '';
            elDeskripsi.textContent = trigger.getAttribute('data-deskripsi') || 'Belum ada deskripsi untuk menu ini.';
            elHarga.textContent = trigger.getAttribute('data-harga') || '';

            elGambar.src = gambar;
            elGambar.alt = trigger.getAttribute('data-nama') || '';
            elGambar.style.display = gambar ? '' : 'none';

            if (pesan) {
                elPesan.href = pesan;
                elPesan.removeAttribute('aria-disabled');
                elPesan.style.display = '';
            } else {
                elPesan.href = '#';
                elPesan.setAttribute('aria-disabled', 'true');
                elPesan.style.display = 'none';
            }

            fokusTerakhir = trigger;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            var tombolTutup = modal.querySelector('.produk-modal-x');
            if (tombolTutup) tombolTutup.focus();
        }

        function tutupModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (fokusTerakhir) {
                fokusTerakhir.focus();
                fokusTerakhir = null;
            }
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-produk]');
            if (trigger) {
                e.preventDefault();
                bukaModal(trigger);
                return;
            }
            if (e.target.closest('[data-tutup-produk]') || e.target === modal) {
                tutupModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                tutupModal();
            }
        });
    })();
</script>
<script>if (window.lucide) lucide.createIcons();</script>
@endpush
@endsection
