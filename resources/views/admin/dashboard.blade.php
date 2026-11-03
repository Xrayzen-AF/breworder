@extends('layouts.admin', ['activeMenu' => 'dashboard'])

@section('title', 'Tejas Coffee - Ringkasan Toko')
@section('header-title', 'Ringkasan Toko')
@section('header-subtitle', 'Penjualan dan stok hari ini')

@section('header-action')
  <a href="{{ route('admin.master.produk.index') }}" class="btn-new-ticket">
    <i data-lucide="coffee" size="14"></i>
    Kelola Menu
  </a>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $rpShort = function ($n) {
      $n = (float) $n;
      if ($n >= 1000000) {
          return 'Rp ' . rtrim(rtrim(number_format($n / 1000000, 1, ',', '.'), '0'), ',') . ' jt';
      }
      if ($n >= 1000) {
          return 'Rp ' . number_format($n / 1000, 0, ',', '.') . ' rb';
      }
      return 'Rp ' . number_format($n, 0, ',', '.');
  };
@endphp

@section('content')
  <section class="metrics-grid">
    <div class="metric-card">
      <div class="metric-header">
        <span>Pendapatan Hari Ini</span>
        <div class="metric-icon"><i data-lucide="wallet" size="16"></i></div>
      </div>
      <div class="metric-value">{{ $rp($omzetHariIni) }}</div>
      <div class="metric-sub">Dari pesanan yang sudah dibayar</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span>Pesanan Selesai</span>
        <div class="metric-icon"><i data-lucide="receipt" size="16"></i></div>
      </div>
      <div class="metric-value">{{ $pesananSelesaiHariIni }} <span style="font-family:'Lora',ui-serif,Georgia,serif; font-size: 14px; font-weight: 400; color: var(--text-muted);">pesanan</span></div>
      <div class="metric-sub">Diselesaikan hari ini</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span>Pelanggan Terdaftar</span>
        <div class="metric-icon"><i data-lucide="users" size="16"></i></div>
      </div>
      <div class="metric-value">{{ number_format($totalPelanggan) }}</div>
      <div class="metric-sub">Total akun pelanggan</div>
    </div>

    <div class="metric-card">
      <div class="metric-header">
        <span>Stok Aman</span>
        <div class="metric-icon"><i data-lucide="package" size="16"></i></div>
      </div>
      <div class="metric-value" style="{{ $stokKritis->count() > 0 ? 'color:var(--accent-alert);' : '' }}">{{ $kesehatanStok }}%</div>
      <div class="metric-sub">
        @if ($stokKritis->count() > 0)
          {{ $stokKritis->count() }} dari {{ $totalBahan }} bahan perlu diisi
        @else
          Semua bahan masih aman
        @endif
      </div>
    </div>
  </section>

  <section class="middle-grid">
    <div class="card">
      <div class="card-title-row">
        <div>
          <h3>Penjualan per Jam</h3>
          <p>Hari ini, dari pesanan yang sudah dibayar</p>
        </div>
        <div class="select-dropdown"><span style="width:8px;height:8px;border:1px solid var(--text-muted);border-radius:9999px;display:inline-block;"></span> Omzet</div>
      </div>
      <div class="chart-container">
        @foreach ($grafik as $bar)
          @php $tinggi = $bar['omzet'] > 0 ? max(6, round($bar['omzet'] / $maxJam * 100)) : 2; @endphp
          <div class="bar-group">
            <span class="bar-value {{ $bar['omzet'] > 0 ? 'dark' : '' }}">{{ $bar['omzet'] > 0 ? $rpShort($bar['omzet']) : '—' }}</span>
            @if ($bar['omzet'] > 0 && $bar['omzet'] == $maxJam)
              <span class="peak-tag">Ramai</span>
            @endif
            <div class="bar {{ $bar['omzet'] > 0 && $bar['omzet'] == $maxJam ? 'peak' : '' }}" style="height: {{ $tinggi }}%;"></div>
            <span class="bar-label">{{ $bar['label'] }}</span>
          </div>
        @endforeach
      </div>
      <div class="chart-footer">
        <span>
          @if ($omzetHariIni > 0)
            Jam paling ramai: <strong>{{ $jamTersibuk['label'] ?? '-' }}</strong>
          @else
            Belum ada penjualan hari ini.
          @endif
        </span>
        <a href="{{ route('admin.sales.index') }}">Lihat laporan →</a>
      </div>
    </div>

    <div class="card">
      <div class="card-title-row">
        <div><h3>Menu Terlaris Hari Ini</h3><p>Berdasarkan pesanan yang sudah selesai</p></div>
      </div>
      <div class="top-selling-list">
        @forelse ($topProduk as $i => $p)
          <div class="item-row">
            <div class="item-rank">#{{ $i + 1 }}</div>
            <div class="item-details"><h4>{{ $p['nama'] }}</h4><p>{{ $p['terjual'] }} terjual</p></div>
            <div class="item-stats"><div class="cups">{{ $rpShort($p['pendapatan']) }}</div></div>
          </div>
        @empty
          <div class="empty-state">Belum ada penjualan hari ini.</div>
        @endforelse
      </div>
      <div class="chart-footer">
        <span>Total menu tersedia: <strong style="color:var(--text-main);">{{ \App\Models\Produk::count() }}</strong></span>
        <a href="{{ route('admin.master.produk.index') }}">Kelola Menu</a>
      </div>
    </div>
  </section>

  <section class="bottom-grid">
    <div class="card">
      <div class="card-title-row" style="margin-bottom: 4px;">
        <div style="display: flex; align-items: center; gap: 8px;">
          <i data-lucide="package-search" size="18" style="color: {{ $stokKritis->count() > 0 ? 'var(--accent-alert)' : 'var(--accent-green)' }};"></i>
          <h3>Stok Bahan</h3>
        </div>
        <span class="{{ $stokKritis->count() > 0 ? 'badge-critical' : 'badge-active' }}">
          {{ $stokKritis->count() > 0 ? 'Perlu diisi' : 'Aman' }}
        </span>
      </div>

      @php $kritis = $stokKritis->first(); @endphp
      @if ($kritis)
        <p style="font-size: 12px; color: var(--text-muted);">Bahan pertama yang perlu dipesan ulang</p>
        <div class="bean-box">
          <div><h4>{{ $kritis->nama_biji }}</h4><p>{{ $kritis->kategori }} • {{ $kritis->deskripsi ?: 'Tanpa keterangan' }}</p></div>
          <div class="bean-weight">{{ number_format((float) $kritis->sisa_kg, 1) }} {{ $kritis->satuan }}<span>tersisa</span></div>
        </div>
        <div class="inventory-meta">
          <span>Batas minimum: {{ $kritis->threshold_kg }} {{ $kritis->satuan }}</span>
          <span>Target stok: {{ $kritis->safety_cap_kg }} {{ $kritis->satuan }}</span>
        </div>
        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px;">
          {{ $stokKritis->count() > 1 ? 'Masih ada ' . ($stokKritis->count() - 1) . ' bahan lain yang perlu diisi.' : 'Segera pesan ulang agar stok tidak habis.' }}
        </p>
      @else
        <p style="font-size: 13px; color: var(--text-muted); margin: 12px 0 18px;">
          Tidak ada bahan di bawah batas minimum. Semua stok aman.
        </p>
      @endif

      <div class="action-btn-row">
        <a href="{{ route('admin.inventory.index') }}" class="btn-primary">Buka Stok Bahan</a>
      </div>
    </div>

    <div class="card">
      <div class="card-title-row" style="margin-bottom: 4px;">
        <h3>Pegawai Aktif</h3>
        <span class="badge-active" style="font-size:12px;">{{ $staff->count() }} orang</span>
      </div>
      <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Akun kasir dan barista yang aktif</p>

      <div class="roster-list">
        @forelse ($staff as $orang)
          <div class="roster-item">
            <div class="roster-user">
              <div class="roster-avatar"><i data-lucide="user" size="16"></i></div>
              <div class="roster-info">
                <h4>{{ $orang->nama_lengkap }}</h4>
                <p>{{ ucfirst($orang->peran) }}</p>
              </div>
            </div>
            <div class="roster-time">
              <span class="badge-active" style="font-size:11px; padding:2px 8px;">Aktif</span>
            </div>
          </div>
        @empty
          <div class="empty-state">Belum ada pegawai aktif.</div>
        @endforelse
      </div>

      <div class="staff-summary">
        <span>Total pengguna: <strong>{{ $totalUsers }}</strong></span>
        <span>• Kasir: <strong>{{ $totalKasir }}</strong></span>
        <span>• Barista: <strong>{{ $totalBarista }}</strong></span>
        <span>• Pelanggan: <strong>{{ $totalPelanggan }}</strong></span>
      </div>
      <div class="chart-footer">
        <span>Kelola akun dan status pegawai</span>
        <a href="{{ route('admin.employees.index') }}" style="display:inline-flex;align-items:center;gap:6px;"><i data-lucide="user-cog" size="14"></i> Kelola Pegawai</a>
      </div>
    </div>
  </section>
@endsection
