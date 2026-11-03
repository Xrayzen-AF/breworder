@php
    // Satu navbar untuk semua halaman pelanggan. Set $aktif = beranda|menu|keranjang|riwayat|profil
    // sebelum @include agar item yang sedang dibuka tersorot.
    $aktif = $aktif ?? null;
    $jumlahItemNav = collect(session('keranjang', []))->sum(fn ($i) => (int) ($i['jumlah'] ?? 0));
@endphp

{{-- ═══ BOTTOM NAV PELANGGAN — dipakai identik di semua halaman ═══ --}}
<nav class="bnav" aria-label="Navigasi pelanggan">
    <div class="mx-auto flex w-full max-w-md items-end px-6 sm:max-w-xl md:max-w-2xl">
        <a href="{{ route('pelanggan.dashboard') }}" class="{{ $aktif === 'beranda' ? 'active' : '' }}">
            <i data-lucide="home" class="h-5 w-5"></i>Beranda
        </a>
        <a href="{{ route('pelanggan.menu') }}" class="{{ $aktif === 'menu' ? 'active' : '' }}">
            <i data-lucide="book-open" class="h-5 w-5"></i>Menu
        </a>
        <a href="{{ route('pelanggan.keranjang.index') }}" class="scan-btn {{ $aktif === 'keranjang' ? 'active' : '' }}"
           title="Keranjang" aria-label="Keranjang">
            <i data-lucide="shopping-basket" class="h-6 w-6"></i>
            @if ($jumlahItemNav > 0)
                <span class="scan-badge">{{ $jumlahItemNav }}</span>
            @endif
        </a>
        <a href="{{ route('pelanggan.riwayat') }}" class="{{ $aktif === 'riwayat' ? 'active' : '' }}">
            <i data-lucide="history" class="h-5 w-5"></i>Riwayat
        </a>
        <a href="{{ route('pelanggan.profil') }}" class="{{ $aktif === 'profil' ? 'active' : '' }}">
            <i data-lucide="user" class="h-5 w-5"></i>Profil
        </a>
    </div>
</nav>

<style>
  .bnav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 30; background: rgba(30, 21, 17, .95); backdrop-filter: blur(8px); border-top: 1px solid #3D322E; }
  .bnav a, .bnav button { display: flex; flex-direction: column; align-items: center; gap: 2px; font-size: 11px; font-weight: 500; color: #9D8E87; padding: 8px 0 10px; flex: 1; background: none; border: 0; font-family: inherit; cursor: pointer; }
  .bnav a.active, .bnav a:hover, .bnav button:hover { color: #F59E0B; }
  .scan-btn { position: relative; width: 52px; height: 52px; margin-top: -22px; border-radius: var(--radius-control); background: #D97706; color: #241708; display: flex; align-items: center; justify-content: center; border: 2px solid #1E1511; box-shadow: 0 6px 16px rgba(0, 0, 0, .45); flex: none !important; }
  .scan-btn:hover { color: #241708; }
  .scan-badge { position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 999px; background: #F1DFD8; border: 2px solid #1E1511; color: #241708; font-size: 10px; font-weight: 700; line-height: 1; display: flex; align-items: center; justify-content: center; }
</style>
