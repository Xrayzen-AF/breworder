@extends('layouts.admin', ['activeMenu' => 'customers'])

@section('title', 'Customer CRM & Loyalty — Tejas Coffee')
@section('header-title', 'Customer CRM & Loyalty')
@section('header-subtitle', $total . ' member terdaftar • ' . $baruHariIni . ' baru hari ini')

@section('header-action')
  <button class="btn-new-ticket" onclick="document.getElementById('form-member').scrollIntoView({behavior:'smooth'})"><i data-lucide="plus" size="16"></i> Tambah Member</button>
@endsection

@section('content')
  <section class="metrics-grid">
    <div class="metric-card">
      <div class="metric-header"><span>Total Members</span><div class="metric-icon"><i data-lucide="heart" size="16"></i></div></div>
      <div class="metric-value">{{ number_format($total) }}</div>
      <div class="metric-sub"><span class="badge-growth">+{{ $baruHariIni }} baru</span> hari ini</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Gold Tier</span><div class="metric-icon"><i data-lucide="award" size="16"></i></div></div>
      <div class="metric-value">{{ $gold }}</div>
      <div class="metric-sub">≥ 300 poin • pelanggan paling loyal</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Rata-rata Poin</span><div class="metric-icon"><i data-lucide="sparkles" size="16"></i></div></div>
      <div class="metric-value">{{ number_format($avgPoin, 1) }}</div>
      <div class="metric-sub">Per member aktif</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Cari Member</span><div class="metric-icon"><i data-lucide="search" size="16"></i></div></div>
      <form method="GET" action="{{ route('admin.customers.index') }}" style="display:flex;gap:8px;margin-top:4px;">
        <input name="q" value="{{ $q }}" class="form-input" placeholder="Nama / telepon..." style="flex:1;">
        <button class="btn-sm primary">Cari</button>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="card-title-row">
      <div><h3>Daftar Member & Tier</h3><p>Bronze &lt;100 • Silver 100–299 • Gold ≥300 • adjust poin ±1000</p></div>
    </div>
    <div class="table-wrap">
      <table class="ops-table">
        <thead><tr><th>Member</th><th>Tier</th><th>Poin</th><th>Pesanan / Belanja</th><th style="text-align:right;">Aksi</th></tr></thead>
        <tbody>
          @forelse ($members as $m)
            @php $tier = \App\Http\Controllers\Admin\CustomerController::tier((int) $m->poin_loyalitas); @endphp
            <tr>
              <td><div class="emp-cell"><div class="emp-avatar">{{ strtoupper(substr($m->nama_pelanggan, 0, 1)) }}</div><div><div class="emp-name">{{ $m->nama_pelanggan }}</div><div class="emp-email">{{ $m->nomor_telepon ?? '—' }} • sejak {{ $m->terdaftar_pada->translatedFormat('d M Y') }}</div></div></div></td>
              <td>
                @if($tier === 'Gold')<span class="role-badge kasir"><i data-lucide="crown" size="13"></i> Gold</span>
                @elseif($tier === 'Silver')<span class="role-badge barista"><i data-lucide="medal" size="13"></i> Silver</span>
                @else<span class="role-badge" style="background:var(--bg-main);border:1px solid var(--border-color);color:var(--text-muted);">Bronze</span>@endif
              </td>
              <td><strong>{{ $m->poin_loyalitas }}</strong> <span style="font-size:11px;color:var(--text-muted);">pts</span></td>
              <td style="font-size:12px;">{{ $m->pesanan_count }} pesanan<br><strong>Rp {{ number_format($m->total_belanja ?? 0, 0, ',', '.') }}</strong></td>
              <td>
                <div class="row-actions">
                  <form method="POST" action="{{ route('admin.customers.poin', $m) }}" style="display:flex;gap:6px;align-items:center;">@csrf @method('PATCH')
                    <input name="delta" type="number" min="-1000" max="1000" placeholder="+50" class="form-input" style="width:84px;padding:6px 8px;" required title="Delta poin">
                    <button class="btn-sm primary" title="Adjust poin">Poin</button>
                  </form>
                  <form method="POST" action="{{ route('admin.customers.destroy', $m) }}" onsubmit="return confirm('Hapus {{ $m->nama_pelanggan }}?')">@csrf @method('DELETE')
                    <button class="btn-sm danger"><i data-lucide="trash-2" size="13"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5"><div class="empty-state">Belum ada member @if($q) untuk "{{ $q }}" @endif.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($members->hasPages())<div class="pagination-wrap">{{ $members->links() }}</div>@endif
  </section>

  <section class="card" id="form-member" style="max-width:640px;">
    <div class="card-title-row"><div><h3>Tambah Member Manual</h3><p>Untuk pelanggan yang daftar via kasir</p></div></div>
    <form method="POST" action="{{ route('admin.customers.store') }}">
      @csrf
      <div class="form-grid">
        <div class="form-group"><label class="form-label">Nama pelanggan</label><input name="nama_pelanggan" class="form-input" required placeholder="Nama lengkap"></div>
        <div class="form-group"><label class="form-label">Nomor telepon</label><input name="nomor_telepon" class="form-input" placeholder="08xx..."></div>
      </div>
      <div class="form-group" style="max-width:200px;"><label class="form-label">Poin awal</label><input name="poin_loyalitas" type="number" min="0" value="0" class="form-input"></div>
      <button class="btn-primary" style="padding:10px 22px;">Simpan Member</button>
    </form>
  </section>
@endsection
