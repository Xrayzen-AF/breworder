@extends('layouts.admin', ['activeMenu' => 'sales'])

@section('title', 'Sales & Revenue — Tejas Coffee')
@section('header-title', 'Sales & Revenue')
@section('header-subtitle', 'Financial Performance Monitor — omzet real dari transaksi lunas')

@section('header-action')
  <a href="{{ route('admin.dashboard') }}" class="btn-new-ticket" style="background-color: var(--bg-subtle); color: var(--text-main);">
    <i data-lucide="arrow-left" size="16"></i> Overview
  </a>
@endsection

@section('content')
  {{-- METRICS --}}
  <section class="metrics-grid">
    <div class="metric-card">
      <div class="metric-header"><span>Pendapatan Hari Ini</span><div class="metric-icon"><i data-lucide="dollar-sign" size="16"></i></div></div>
      <div class="metric-value">Rp {{ number_format($summary['today_revenue'] ?? 0, 0, ',', '.') }}</div>
      <div class="metric-sub">
        @if (!is_null($summary['revenue_vs_yesterday_pct'] ?? null))
          <span class="badge-growth">{{ ($summary['revenue_vs_yesterday_pct'] >= 0 ? '+' : '') . $summary['revenue_vs_yesterday_pct'] }}%</span> vs kemarin
        @else
          <span style="color:var(--text-light);">Belum ada pembanding kemarin</span>
        @endif
      </div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Margin Kotor</span><div class="metric-icon"><i data-lucide="percent" size="16"></i></div></div>
      <div class="metric-value">{{ $summary['aggregated_gross_margin_pct'] ?? 0 }}%</div>
      <div class="metric-sub">Berbobot volume • HPP 25% harga jual</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Pesanan Hari Ini</span><div class="metric-icon"><i data-lucide="receipt" size="16"></i></div></div>
      <div class="metric-value">{{ $telemetry['total_tickets'] ?? 0 }} <span style="font-size:14px;font-weight:normal;color:var(--text-muted);">tiket</span></div>
      <div class="metric-sub"><i data-lucide="clock" size="12"></i> Peak: {{ $telemetry['peak_window'] ?? '-' }}</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Menu Kurang Laku</span><div class="metric-icon"><i data-lucide="alert-triangle" size="16" style="color:var(--accent-alert);"></i></div></div>
      <div class="metric-value">{{ collect($marginProduk)->where('at_risk', true)->count() }}</div>
      <div class="metric-sub">High-margin &lt; {{ \App\Services\Agents\SalesAgent::VOLUME_AT_RISK_THRESHOLD }} cups</div>
    </div>
  </section>

  {{-- GRAFIK 7 HARI --}}
  <section class="card">
    <div class="card-title-row">
      <div><h3>Omzet 7 Hari Terakhir</h3><p>Transaksi berstatus lunas • sumber: tabel transaksi</p></div>
      <span style="font-size:12px;color:var(--text-muted);">Total 7 hari: Rp {{ number_format(collect($grafik)->sum('omzet'), 0, ',', '.') }}</span>
    </div>
    <div class="chart-container" style="height:180px;">
      @foreach ($grafik as $g)
        @php $pct = $maxOmzet > 0 ? max(6, round($g['omzet'] / $maxOmzet * 100)) : 6; @endphp
        <div class="bar-group">
          @if ($g['omzet'] === collect($grafik)->max('omzet') && $g['omzet'] > 0)<span class="peak-tag">Peak</span>@endif
          <div class="bar {{ $g['omzet'] === collect($grafik)->max('omzet') && $g['omzet'] > 0 ? 'peak' : '' }}" style="height:{{ $pct }}%;" title="Rp {{ number_format($g['omzet'], 0, ',', '.') }}"></div>
          <span class="bar-label">{{ $g['label'] }}</span>
        </div>
      @endforeach
    </div>
    <div class="chart-footer">
      <span>Rata-rata harian: Rp {{ number_format(collect($grafik)->avg('omzet'), 0, ',', '.') }} • {{ collect($grafik)->sum('transaksi') }} transaksi</span>
      <span style="font-size:11px;">{{ $telemetry['recommendation'] ?? '' }}</span>
    </div>
  </section>

  <section class="middle-grid">
    {{-- TOP SELLING --}}
    <div class="card">
      <div class="card-title-row"><div><h3>Menu Terlaris</h3><p>Jumlah terjual dan margin per produk</p></div><span style="font-size:12px;color:var(--text-muted);">Semua pesanan selesai</span></div>
      <div class="top-selling-list">
        @forelse ($marginProduk as $i => $m)
          <div class="item-row">
            <div class="item-rank">#{{ $i + 1 }}</div>
            <div class="item-details">
              <h4>{{ $m['nama'] }} @if($m['at_risk'])<span class="badge-critical" style="margin-left:6px;">At-risk</span>@endif</h4>
              <p>Rp {{ number_format($m['pendapatan'], 0, ',', '.') }} pendapatan</p>
            </div>
            <div class="item-stats"><div class="cups">{{ $m['cups'] }} cups</div><div class="margin">• {{ $m['margin_pct'] }}% Margin</div></div>
          </div>
        @empty
          <div class="empty-state">Belum ada penjualan selesai.</div>
        @endforelse
      </div>
    </div>

    {{-- METODE + STATUS --}}
    <div class="card">
      <div class="card-title-row"><div><h3>Metode Pembayaran Hari Ini</h3><p>Breakdown transaksi lunas</p></div></div>
      <div class="top-selling-list">
        @forelse ($perMetode as $m)
          <div class="item-row">
            <div class="item-details"><h4>{{ $m->nama_metode }}</h4><p>{{ $m->transaksi }} transaksi</p></div>
            <div class="item-stats"><div class="cups">Rp {{ number_format($m->omzet, 0, ',', '.') }}</div></div>
          </div>
        @empty
          <div class="empty-state">Belum ada transaksi lunas hari ini.</div>
        @endforelse
      </div>
      <div class="staff-summary">
        @foreach (['baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'dibatalkan' => 'Batal'] as $key => $label)
          <span>{{ $label }}: <strong>{{ $perStatus[$key] ?? 0 }}</strong></span>
        @endforeach
      </div>
    </div>
  </section>
@endsection
