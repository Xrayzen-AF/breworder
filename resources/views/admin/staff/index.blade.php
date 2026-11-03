@extends('layouts.admin', ['activeMenu' => 'staff'])

@section('title', 'Staff & Roster — Tejas Coffee')
@section('header-title', 'Staff & Roster')
@section('header-subtitle', 'Predictive Labor Allocator — ' . $totalAktif . ' aktif • ' . $tiketHariIni . ' tiket hari ini')

@section('header-action')
  <a href="{{ route('admin.employees.create') }}" class="btn-new-ticket"><i data-lucide="plus" size="16"></i> Tambah Pegawai</a>
@endsection

@section('content')
  <section class="metrics-grid">
    <div class="metric-card">
      <div class="metric-header"><span>Total Pegawai</span><div class="metric-icon"><i data-lucide="users" size="16"></i></div></div>
      <div class="metric-value">{{ $totalKasir + $totalBarista }}</div>
      <div class="metric-sub"><span class="badge-growth">{{ $totalAktif }} aktif</span> terdaftar di sistem</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Kasir</span><div class="metric-icon"><i data-lucide="receipt" size="16"></i></div></div>
      <div class="metric-value">{{ $totalKasir }}</div>
      <div class="metric-sub">POS Cashier & Hospitality Host</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Barista</span><div class="metric-icon"><i data-lucide="coffee" size="16"></i></div></div>
      <div class="metric-value">{{ $totalBarista }}</div>
      <div class="metric-sub">Espresso Bar & Cold Brew Specialist</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Rush Window</span><div class="metric-icon"><i data-lucide="zap" size="16"></i></div></div>
      <div class="metric-value" style="font-size:18px;">{{ $telemetry['peak_window'] ?? '-' }}</div>
      <div class="metric-sub">{{ $telemetry['total_tickets'] ?? 0 }} tiket • avg {{ $telemetry['avg_prep_time_seconds'] ?? 0 }}s</div>
    </div>
  </section>

  @if (!empty($telemetry['recommendation']))
    <div class="alert-success"><i data-lucide="sparkles" size="16"></i> Rekomendasi StaffingAgent: <strong>{{ $telemetry['recommendation'] }}</strong></div>
  @endif

  <section class="middle-grid">
    <div class="card">
      <div class="card-title-row">
        <div><h3>Active Shift Roster</h3><p>Floor staff clocked into Metro Downtown #04</p></div>
        <a href="{{ route('admin.employees.index') }}" class="btn-sm primary"><i data-lucide="settings-2" size="13"></i> Manage Accounts</a>
      </div>
      <div class="roster-list">
        @forelse ($roster as $r)
          <div class="roster-item">
            <div class="roster-user">
              <div class="roster-avatar">{{ strtoupper(substr($r['nama'], 0, 1)) }}</div>
              <div class="roster-info"><h4>{{ $r['nama'] }}</h4><p>{{ ucfirst($r['peran']) }}</p></div>
            </div>
            <div class="roster-time"><span class="badge-active">● Aktif</span></div>
          </div>
        @empty
          <div class="empty-state">Belum ada pegawai aktif.</div>
        @endforelse
      </div>
      <div class="chart-footer" style="margin-top:14px;">
        <span>Next Shift Changeover: 14:00 (Afternoon Crew)</span>
        <a href="{{ route('admin.employees.index') }}">↔ Kelola Akun Pegawai</a>
      </div>
    </div>

    <div class="card">
      <div class="card-title-row"><div><h3>Shift Hari Ini</h3><p>Pembagian station & jam</p></div></div>
      <div class="top-selling-list">
        <div class="item-row"><div class="item-details"><h4>Pagi — 06:30 s/d 14:00</h4><p>Slayer Espresso Bar • Register #1 • Nitro Prep</p></div><div class="item-stats"><div class="cups">3 crew</div></div></div>
        <div class="item-row"><div class="item-details"><h4>Siang — 14:00 s/d 22:00</h4><p>Afternoon Crew • Floor Lead + 2 Barista</p></div><div class="item-stats"><div class="cups">3 crew</div></div></div>
      </div>
      <div class="staff-summary"><span>Beban rata-rata: <strong>{{ $totalAktif > 0 ? round($tiketHariIni / $totalAktif, 1) : 0 }} tiket/staff</strong></span></div>
    </div>
  </section>

  <section class="card">
    <div class="card-title-row">
      <div><h3>Daftar Pegawai</h3><p>Klik toggle untuk aktif/nonaktif • edit & hapus di Manage Accounts</p></div>
    </div>
    <div class="table-wrap">
      <table class="ops-table">
        <thead><tr><th>Pegawai</th><th>Role</th><th>Status</th><th style="text-align:right;">Aksi</th></tr></thead>
        <tbody>
          @forelse ($staff as $s)
            <tr>
              <td><div class="emp-cell"><div class="emp-avatar {{ $s->peran }}">{{ strtoupper(substr($s->nama_lengkap, 0, 1)) }}</div><div><div class="emp-name">{{ $s->nama_lengkap }}</div><div class="emp-email">{{ $s->email }}</div></div></div></td>
              <td><span class="role-badge {{ $s->peran }}">{{ ucfirst($s->peran) }}</span></td>
              <td>@if($s->status_aktif)<span class="badge-active">● Aktif</span>@else<span class="badge-critical">Nonaktif</span>@endif</td>
              <td><div class="row-actions">
                <form method="POST" action="{{ route('admin.staff.toggle', $s) }}">@csrf @method('PATCH')
                  <button class="btn-sm">{{ $s->status_aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                </form>
                <a href="{{ route('admin.employees.edit', $s) }}" class="btn-sm"><i data-lucide="pencil" size="13"></i> Edit</a>
              </div></td>
            </tr>
          @empty
            <tr><td colspan="4"><div class="empty-state">Belum ada pegawai.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($staff->hasPages())<div class="pagination-wrap">{{ $staff->links() }}</div>@endif
  </section>
@endsection
