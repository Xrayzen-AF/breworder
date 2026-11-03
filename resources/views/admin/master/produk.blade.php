@extends('layouts.admin', ['activeMenu' => 'produk'])

@section('title', 'Master Produk — Tejas Coffee')
@section('header-title', 'Master Produk')
@section('header-subtitle', 'Data dasar menu & harga — direferensikan detail pesanan')

@section('content')
  @if (session('error'))
    <div class="alert-error">{{ session('error') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert-error" style="margin-bottom:16px;">
      <ul style="margin:0;padding-left:18px;">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <section class="metrics-grid">
    <div class="metric-card">
      <div class="metric-header"><span>Total Produk</span><div class="metric-icon"><i data-lucide="package" size="16"></i></div></div>
      <div class="metric-value">{{ $produk->total() }}</div>
      <div class="metric-sub">{{ $produk->lastPage() }} halaman</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Tersedia</span><div class="metric-icon"><i data-lucide="check-circle-2" size="16"></i></div></div>
      <div class="metric-value">{{ $produk->getCollection()->where('status_tersedia', true)->count() }}</div>
      <div class="metric-sub">Tampak di menu pelanggan</div>
    </div>
    <div class="metric-card">
      <div class="metric-header"><span>Habis</span><div class="metric-icon"><i data-lucide="alert-triangle" size="16" style="color:var(--accent-alert);"></i></div></div>
      <div class="metric-value" style="color:var(--accent-alert);">{{ $produk->getCollection()->where('stok', 0)->count() }}</div>
      <div class="metric-sub">Stok kosong (di halaman ini)</div>
    </div>
  </section>

  <section class="card" style="margin-top:20px;">
    <div class="card-title-row"><div><h3>Daftar Produk</h3><p>Klik Update untuk ubah data & foto produk</p></div></div>
    <div class="table-wrap">
      <table class="ops-table">
        <thead>
          <tr>
            <th style="min-width:180px;">Produk</th>
            <th>Kategori</th>
            <th>Harga</th>
            <th>Stok</th>
            <th>Status</th>
            <th style="text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @forelse ($produk as $p)
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:44px;height:44px;flex-shrink:0;border-radius:8px;background:#241B17;border:1px solid #3D322E;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:2px;">
                  @if ($p->url_gambar)
                    <img src="{{ $p->url_gambar }}" alt="{{ $p->nama_produk }}" style="width:100%;height:100%;object-fit:contain;">
                  @else
                    <i data-lucide="coffee" size="18" style="color:#9D8E87;"></i>
                  @endif
                </div>
                <div>
                  <div class="emp-name">{{ $p->nama_produk }}</div>
                  <div class="emp-email">{{ $p->kode_produk }}</div>
                </div>
              </div>
            </td>
            <td>{{ $p->kategori->nama_kategori ?? '—' }}</td>
            <td style="white-space:nowrap;">Rp {{ number_format($p->harga, 0, ',', '.') }}</td>
            <td>{{ $p->stok }}</td>
            <td>@if($p->status_tersedia)<span class="badge-active">Tersedia</span>@else<span class="badge-critical">Habis/Nonaktif</span>@endif</td>
            <td>
              <div class="row-actions">
                <form method="POST" action="{{ route('admin.master.produk.destroy', $p) }}" style="display:inline;" onsubmit="return confirm('Hapus produk {{ $p->nama_produk }}?')">@csrf @method('DELETE')
                  <button class="btn-sm danger" title="Hapus"><i data-lucide="trash-2" size="13"></i></button>
                </form>
              </div>
              <details style="margin-top:8px;">
                <summary style="font-size:12px;font-weight:600;cursor:pointer;color:var(--accent-caramel);">Update</summary>
                <form method="POST" action="{{ route('admin.master.produk.update', $p) }}" enctype="multipart/form-data" class="edit-product-form" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:10px;margin-top:10px;background:#1E1511;padding:12px;border-radius:10px;border:1px solid #3D322E;">
                  @csrf @method('PUT')
                  <div style="grid-column:1/-1;">
                    <label class="form-label" style="font-size:11px;">Kategori</label>
                    <select name="id_kategori" class="form-select" required>
                      @foreach ($kategori as $k)
                        <option value="{{ $k->id_kategori }}" @selected($p->id_kategori === $k->id_kategori)>{{ $k->nama_kategori }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div>
                    <label class="form-label" style="font-size:11px;">Kode Produk</label>
                    <input name="kode_produk" value="{{ old('kode_produk', $p->kode_produk) }}" class="form-input" required placeholder="Kode">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:11px;">Nama Produk</label>
                    <input name="nama_produk" value="{{ old('nama_produk', $p->nama_produk) }}" class="form-input" required placeholder="Nama">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:11px;">Harga (Rp)</label>
                    <input name="harga" type="number" min="0" step="0.01" value="{{ old('harga', $p->harga) }}" class="form-input" required placeholder="Harga">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:11px;">Stok</label>
                    <input name="stok" type="number" min="0" value="{{ old('stok', $p->stok) }}" class="form-input" required placeholder="Stok">
                  </div>
                  <div style="grid-column:1/-1;">
                    <label class="form-label" style="font-size:11px;">Deskripsi</label>
                    <input name="deskripsi" value="{{ old('deskripsi', $p->deskripsi) }}" class="form-input" placeholder="Deskripsi">
                  </div>

                  {{-- File upload untuk gambar produk --}}
                  <div style="grid-column:1/-1;background:#241B17;padding:12px;border-radius:8px;border:1px dashed #4D3D36;">
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                      @if ($p->url_gambar)
                        <div style="width:52px;height:52px;border-radius:8px;background:#1E1511;border:1px solid #3D322E;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:3px;flex-shrink:0;">
                          <img src="{{ $p->url_gambar }}" alt="Foto saat ini" style="width:100%;height:100%;object-fit:contain;">
                        </div>
                      @endif
                      <div style="flex:1;min-width:180px;">
                        <label class="form-label" style="font-size:11.5px;margin-bottom:4px;display:block;">
                          {{ $p->url_gambar ? 'Ganti Foto Produk (Upload File):' : 'Upload Foto Produk:' }}
                        </label>
                        <input type="file" name="gambar" accept="image/*" class="form-input" style="padding:6px;font-size:12px;">
                        <span style="font-size:10.5px;color:var(--text-muted);display:block;margin-top:3px;">Format: JPG, PNG, WEBP, AVIF (Maks 5MB). Biarkan kosong jika tidak ingin mengubah.</span>
                      </div>
                    </div>
                  </div>

                  <div style="grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:4px;">
                    <label class="form-hint" style="display:flex;gap:8px;align-items:center;">
                      <input type="checkbox" name="status_tersedia" value="1" @checked($p->status_tersedia)> Status tersedia
                    </label>
                    <button class="btn-sm primary" type="submit">Simpan Perubahan</button>
                  </div>
                </form>
              </details>
            </td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="empty-state">Belum ada produk.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    @if ($produk->hasPages())
      <div class="pagination-wrap">{{ $produk->links() }}</div>
    @endif
  </section>

  <section class="bottom-grid" style="margin-top:20px;">
    <div class="card">
      <div class="card-title-row"><div><h3>Tambah Produk Baru</h3><p>Data master menu</p></div></div>
      <form method="POST" action="{{ route('admin.master.produk.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Kategori</label>
            <select name="id_kategori" class="form-select" required>
              @foreach ($kategori as $k)
                <option value="{{ $k->id_kategori }}">{{ $k->nama_kategori }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group"><label class="form-label">Kode produk</label><input name="kode_produk" class="form-input" placeholder="ART-003" required></div>
        </div>
        <div class="form-group"><label class="form-label">Nama produk</label><input name="nama_produk" class="form-input" placeholder="Matcha Latte" required></div>
        <div class="form-group"><label class="form-label">Deskripsi</label><input name="deskripsi" class="form-input" placeholder="Racikan matcha spesial dengan susu segar"></div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Harga (Rp)</label><input name="harga" type="number" min="0" step="0.01" class="form-input" value="25000" required></div>
          <div class="form-group"><label class="form-label">Stok</label><input name="stok" type="number" min="0" class="form-input" value="50" required></div>
        </div>
        <div class="form-group">
          <label class="form-label">Foto Produk (Upload File)</label>
          <input type="file" name="gambar" accept="image/*" class="form-input" style="padding:8px;">
          <span style="font-size:11px;color:var(--text-muted);display:block;margin-top:4px;">Pilih file gambar (JPG, PNG, WEBP, AVIF, maks 5MB)</span>
        </div>
        <div class="form-group">
          <label class="form-hint" style="display:flex;gap:8px;align-items:center;">
            <input type="checkbox" name="status_tersedia" value="1" checked> Status tersedia
          </label>
        </div>
        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Produk</button>
      </form>
    </div>
    <div class="card">
      <div class="card-title-row"><div><h3>Catatan Master</h3><p>Peran tabel produk</p></div></div>
      <div class="top-selling-list">
        <div class="item-row"><div class="item-details"><h4>Upload file foto</h4><p>File disimpan otomatis ke server tanpa perlu menyalin URL eksternal</p></div></div>
        <div class="item-row"><div class="item-details"><h4>Kategori wajib ada</h4><p>Produk merujuk ke tabel kategori (FK cascade)</p></div></div>
        <div class="item-row"><div class="item-details"><h4>Harga & stok</h4><p>Sumber kebenaran untuk POS & pengurangan stok</p></div></div>
        <div class="item-row"><div class="item-details"><h4>Status tersedia</h4><p>Gunakan ini untuk produk yang pernah terjual (tidak boleh dihapus)</p></div></div>
      </div>
    </div>
  </section>
@endsection
