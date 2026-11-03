@extends('layouts.admin', ['activeMenu' => 'promo'])

@section('title', 'Master Promo — Tejas Coffee')
@section('header-title', 'Master Promo')
@section('header-subtitle', 'Voucher diskon — dipakai di checkout pelanggan')

@section('content')
  @if (session('error'))
    <div class="alert-error">{{ session('error') }}</div>
  @endif

  <section class="metrics-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="metric-card">
      <div class="metric-header"><span>Total Promo</span><div class="metric-icon"><i data-lucide="ticket" size="16"></i></div></div>
      <div class="metric-value">{{ $promo->count() }}</div>
      <div class="metric-sub">Seluruh voucher terdaftar</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Aktif</span><div class="metric-icon"><i data-lucide="check-circle-2" size="16"></i></div></div>
      <div class="metric-value">{{ $promo->where('status_aktif', true)->count() }}</div>
      <div class="metric-sub">Dipakai di checkout</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Sudah Dipakai</span><div class="metric-icon"><i data-lucide="repeat" size="16"></i></div></div>
      <div class="metric-value">{{ $promo->sum('pesanan_count') }}</div>
      <div class="metric-sub">Total kali promo dipakai</div>
    </div>
  </section>

  <section class="bottom-grid" style="margin-top:20px;">
    <div class="card">
      <div class="card-title-row"><div><h3>Daftar Promo</h3><p>Klik Update untuk ubah data</p></div></div>
      <div class="table-wrap">
        <table class="ops-table">
          <thead><tr><th>Kode</th><th>Diskon</th><th>Periode</th><th>Pakai</th><th style="text-align:right;">Aksi</th></tr></thead>
          <tbody>
          @forelse ($promo as $p)
            <tr>
              <td><div class="emp-name">{{ $p->kode_promo }}</div><div class="emp-email">{{ ucfirst($p->tipe_diskon) }}</div></td>
              <td style="white-space:nowrap;">@if($p->tipe_diskon === 'persen'){{ $p->nilai_diskon }}%@else Rp {{ number_format($p->nilai_diskon, 0, ',', '.') }}@endif</td>
              <td style="white-space:nowrap; font-size:12px; color:var(--text-muted);">
                {{ $p->berlaku_mulai?->format('d M Y') ?? '—' }} – {{ $p->berlaku_sampai?->format('d M Y') ?? '—' }}
              </td>
              <td>{{ $p->pesanan_count }}×</td>
              <td>
                @if($p->status_aktif)
                  <span class="badge-active">Aktif</span>
                @else
                  <span class="badge-critical">Nonaktif</span>
                @endif
                <details style="margin-top:8px;">
                  <summary style="font-size:12px;font-weight:600;cursor:pointer;color:var(--accent-caramel);">Update</summary>
                  <form method="POST" action="{{ route('admin.master.promo.update', $p) }}" style="display:grid;gap:8px;margin-top:8px;">
                    @csrf @method('PUT')
                    <input name="kode_promo" value="{{ old('kode_promo', $p->kode_promo) }}" class="form-input" required>
                    <select name="tipe_diskon" class="form-select">
                      @foreach ($tipeDiskon as $t)
                        <option value="{{ $t }}" @selected($p->tipe_diskon === $t)>{{ ucfirst($t) }}</option>
                      @endforeach
                    </select>
                    <input name="nilai_diskon" type="number" min="0" step="0.01" value="{{ old('nilai_diskon', $p->nilai_diskon) }}" class="form-input" required>
                    <input name="berlaku_mulai" type="datetime-local" value="{{ $p->berlaku_mulai?->format('Y-m-d\TH:i') }}" class="form-input">
                    <input name="berlaku_sampai" type="datetime-local" value="{{ $p->berlaku_sampai?->format('Y-m-d\TH:i') }}" class="form-input">
                    <label class="form-hint" style="display:flex;gap:8px;align-items:center;">
                      <input type="checkbox" name="status_aktif" value="1" @checked($p->status_aktif)> Status aktif
                    </label>
                    <button class="btn-sm primary">Simpan</button>
                  </form>
                </details>
              </td>
            </tr>
          @empty
            <tr><td colspan="5"><div class="empty-state">Belum ada promo. Tambah via form di kanan.</div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-title-row"><div><h3>Tambah Promo Baru</h3><p>Voucher langsung bisa ditukar pelanggan</p></div></div>
      <form method="POST" action="{{ route('admin.master.promo.store') }}">
        @csrf
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Kode promo</label><input name="kode_promo" class="form-input" placeholder="COFFEE20" required></div>
          <div class="form-group">
            <label class="form-label">Tipe diskon</label>
            <select name="tipe_diskon" class="form-select">
              @foreach ($tipeDiskon as $t)
                <option value="{{ $t }}">{{ ucfirst($t) }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Nilai diskon</label>
          <input name="nilai_diskon" type="number" min="0" step="0.01" class="form-input" required>
          <span class="form-hint">Pakai angka sesuai tipe: persen (%) atau nominal (Rp)</span>
        </div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Berlaku mulai</label><input name="berlaku_mulai" type="datetime-local" class="form-input"></div>
          <div class="form-group"><label class="form-label">Berlaku sampai</label><input name="berlaku_sampai" type="datetime-local" class="form-input"></div>
        </div>
        <div class="form-group">
          <label class="form-hint" style="display:flex;gap:8px;align-items:center;">
            <input type="checkbox" name="status_aktif" value="1" checked> Status aktif
          </label>
        </div>
        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Promo</button>
      </form>
    </div>
  </section>
@endsection
