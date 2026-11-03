@extends('layouts.admin', ['activeMenu' => 'inventory'])

@section('title', 'Stok Bahan — Tejas Coffee')
@section('header-title', 'Stok Bahan')
@section('header-subtitle', 'Pantau semua bahan dan pesan ulang saat stok menipis')

@section('header-action')
  <button class="btn-new-ticket" onclick="document.getElementById('form-tambah').scrollIntoView({behavior:'smooth'})">
    <i data-lucide="plus" size="16"></i> Tambah Bahan
  </button>
@endsection

@section('content')
  @if (session('error'))
    <div class="alert-error">{{ session('error') }}</div>
  @endif

  <section class="metrics-grid">
    <div class="metric-card">
      <div class="metric-header"><span>Stok Aman</span><div class="metric-icon"><i data-lucide="leaf" size="16"></i></div></div>
      <div class="metric-value">{{ $kesehatan }}%</div>
      <div class="metric-sub">{{ $semua->count() - $alerts->count() }} dari {{ $semua->count() }} bahan aman</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Jenis Bahan</span><div class="metric-icon"><i data-lucide="boxes" size="16"></i></div></div>
      <div class="metric-value">{{ $semua->count() }}</div>
      <div class="metric-sub">{{ $semua->pluck('kategori')->unique()->count() }} kategori terdaftar</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Perlu Diisi</span><div class="metric-icon"><i data-lucide="alert-triangle" size="16" style="color:var(--accent-alert);"></i></div></div>
      <div class="metric-value" style="color:var(--accent-alert);">{{ $alerts->count() }}</div>
      <div class="metric-sub">Stok di bawah batas minimum</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Pesanan Ulang</span><div class="metric-icon"><i data-lucide="truck" size="16"></i></div></div>
      <div class="metric-value">{{ $riwayat->count() }}</div>
      <div class="metric-sub">10 pesanan terakhir tercatat</div>
    </div>
  </section>

  {{-- STOK MENIPIS --}}
  @if ($alerts->count() > 0)
    <section class="card alert-card">
      <div class="card-title-row" style="margin-bottom:8px;">
        <div style="display:flex;align-items:center;gap:8px;"><i data-lucide="package-search" size="18" style="color:var(--accent-alert);"></i><h3>Stok Menipis — {{ $alerts->count() }} bahan</h3></div>
        <span class="badge-critical">Segera isi</span>
      </div>
      @foreach ($alerts as $a)
        @php $satuan = $a['biji']->satuan ?: 'kg'; @endphp
        <div class="bean-box">
          <div>
            <h4>{{ $a['biji']->nama_biji }}</h4>
            <p>{{ $a['biji']->kategori }} • {{ $a['biji']->deskripsi ?: 'Tanpa keterangan' }} • Batas minimum {{ $a['biji']->threshold_kg }} {{ $satuan }} • Target {{ $a['biji']->safety_cap_kg }} {{ $satuan }}</p>
          </div>
          <div class="bean-weight">{{ number_format($a['biji']->sisa_kg, 1) }} {{ $satuan }}<span>tersisa</span></div>
        </div>
        <div class="inventory-meta">
          <span>Perkiraan habis: {{ $a['deplesi'] ? $a['deplesi']->translatedFormat('d M H:i') : 'belum bisa dihitung' }}</span>
          <span>Saran pesan ulang: <strong>{{ number_format($a['reorder_qty'], 1) }} {{ $satuan }}</strong></span>
        </div>
        <form method="POST" action="{{ route('admin.inventory.reorder', $a['biji']) }}" style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
          @csrf
          <input type="hidden" name="destination" value="Supplier">
          <input type="number" step="0.1" min="0.1" name="order_quantity_kg" value="{{ number_format($a['reorder_qty'], 1, '.', '') }}" class="form-input" style="max-width:140px;" title="Jumlah pesanan ({{ $satuan }})">
          <button type="submit" class="btn-primary" style="flex:0 0 auto;padding:8px 18px;">Pesan Ulang {{ number_format($a['reorder_qty'], 1) }} {{ $satuan }}</button>
        </form>
      @endforeach
    </section>
  @endif

  {{-- TABEL STOK --}}
  <section class="card">
    <div class="card-title-row">
      <div><h3>Semua Bahan</h3><p>Klik "Ubah" untuk memperbarui jumlah, batas minimum, atau pemakaian</p></div>
    </div>
    <div class="table-wrap">
      <table class="ops-table">
        <thead><tr><th>Nama Bahan</th><th>Kategori</th><th>Sisa / Minimum</th><th>Pemakaian / jam</th><th>Status</th><th style="text-align:right;">Aksi</th></tr></thead>
        <tbody>
          @forelse ($semua as $b)
            @php $satuan = $b->satuan ?: 'kg'; @endphp
            <tr>
              <td><div class="emp-name">{{ $b->nama_biji }}</div><div class="emp-email">{{ $b->deskripsi ?: 'Tanpa keterangan' }} • {{ $b->log_reorder_count }}× pesan ulang</div></td>
              <td><span class="badge-active">{{ $b->kategori }}</span></td>
              <td style="white-space:nowrap;"><strong>{{ $b->sisa_kg }} {{ $satuan }}</strong> <span style="color:var(--text-muted);font-size:12px;">/ min {{ $b->threshold_kg }} • target {{ $b->safety_cap_kg }}</span></td>
              <td>{{ $b->laju_konsumsi_kg_per_jam }} {{ $satuan }}</td>
              <td>@if($b->isKritis())<span class="badge-critical">Menipis</span>@else<span class="badge-active">● Aman</span>@endif</td>
              <td>
                <div class="row-actions">
                  <form method="POST" action="{{ route('admin.inventory.reorder', $b) }}" style="display:inline;">@csrf
                    <input type="hidden" name="destination" value="Supplier">
                    <button class="btn-sm primary" title="Pesan ulang sampai target"><i data-lucide="truck" size="13"></i> Pesan</button>
                  </form>
                  <form method="POST" action="{{ route('admin.inventory.destroy', $b) }}" style="display:inline;" onsubmit="return confirm('Hapus {{ $b->nama_biji }}?')">@csrf @method('DELETE')
                    <button class="btn-sm danger" title="Hapus bahan"><i data-lucide="trash-2" size="13"></i></button>
                  </form>
                </div>
                <details style="margin-top:8px;">
                  <summary style="font-size:12px;font-weight:600;cursor:pointer;color:var(--accent-caramel);">Ubah stok</summary>
                  <form method="POST" action="{{ route('admin.inventory.update', $b) }}" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
                    @csrf @method('PUT')
                    <select name="kategori" class="form-select" title="Kategori" required>
                      @foreach ($kategoriList as $k)
                        <option value="{{ $k }}" {{ $b->kategori === $k ? 'selected' : '' }}>{{ $k }}</option>
                      @endforeach
                    </select>
                    <select name="satuan" class="form-select" title="Satuan" required>
                      @foreach ($satuanList as $s)
                        <option value="{{ $s }}" {{ $b->satuan === $s ? 'selected' : '' }}>{{ $s }}</option>
                      @endforeach
                    </select>
                    <input name="sisa_kg" type="number" step="0.01" value="{{ $b->sisa_kg }}" class="form-input" title="Jumlah stok sekarang" required>
                    <input name="threshold_kg" type="number" step="0.01" value="{{ $b->threshold_kg }}" class="form-input" title="Batas minimum" required>
                    <input name="safety_cap_kg" type="number" step="0.01" value="{{ $b->safety_cap_kg }}" class="form-input" title="Target stok" required>
                    <input name="laju_konsumsi_kg_per_jam" type="number" step="0.0001" value="{{ $b->laju_konsumsi_kg_per_jam }}" class="form-input" title="Pemakaian per jam" required>
                    <input name="deskripsi" value="{{ $b->deskripsi }}" class="form-input" placeholder="Keterangan" style="grid-column:1/-1;">
                    <button class="btn-sm primary" style="grid-column:1/-1;">Simpan</button>
                  </form>
                </details>
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="empty-state">Belum ada bahan. Tambahkan bahan pertama di form bawah.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="bottom-grid">
    <div class="card" id="form-tambah">
      <div class="card-title-row"><div><h3>Tambah Bahan Baru</h3><p>Biji kopi, susu, sirup, kemasan, dan bahan lain</p></div></div>
      <form method="POST" action="{{ route('admin.inventory.store') }}">
        @csrf
        <div class="form-group"><label class="form-label">Nama bahan</label><input name="nama_biji" class="form-input" placeholder="cth: Susu UHT Full Cream" required></div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Kategori</label>
            <select name="kategori" class="form-select" required>
              @foreach ($kategoriList as $k)
                <option value="{{ $k }}" {{ $k === 'Biji Kopi' ? 'selected' : '' }}>{{ $k }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Satuan</label>
            <select name="satuan" class="form-select" required>
              @foreach ($satuanList as $s)
                <option value="{{ $s }}" {{ $s === 'kg' ? 'selected' : '' }}>{{ $s }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Keterangan</label><input name="deskripsi" class="form-input" placeholder="cth: 1 liter per karton"></div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Jumlah stok</label><input name="sisa_kg" type="number" step="0.01" value="10" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Batas minimum</label><input name="threshold_kg" type="number" step="0.01" value="5" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Target stok</label><input name="safety_cap_kg" type="number" step="0.01" value="25" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Pemakaian per jam</label><input name="laju_konsumsi_kg_per_jam" type="number" step="0.0001" value="0.5" class="form-input" required></div>
        </div>
        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Bahan</button>
      </form>
    </div>
    <div class="card">
      <div class="card-title-row"><div><h3>Riwayat Pesan Ulang</h3><p>Bahan yang sudah dipesan ke supplier</p></div></div>
      <div class="top-selling-list">
        @forelse ($riwayat as $r)
          <div class="item-row">
            <div class="item-details"><h4>{{ $r->stokBiji->nama_biji ?? '#' . $r->id_stok_biji }}</h4><p>{{ $r->destination }} • {{ $r->waktu_dispatch->translatedFormat('d M H:i') }} • {{ $r->status }}</p></div>
            <div class="item-stats"><div class="cups">{{ $r->order_quantity_kg }} {{ $r->stokBiji->satuan ?? 'kg' }}</div></div>
          </div>
        @empty
          <div class="empty-state">Belum ada pesanan ulang.</div>
        @endforelse
      </div>
    </div>
  </section>
@endsection
