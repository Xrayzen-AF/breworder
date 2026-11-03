@extends('layouts.admin', ['activeMenu' => 'settings'])

@section('title', 'Store Settings — Tejas Coffee')
@section('header-title', 'Store Settings')
@section('header-subtitle', 'Profil gerai, default operasional & info sistem')

@section('content')
  <section class="middle-grid">
    <div class="card">
      <div class="card-title-row"><div><h3>Profil Toko</h3><p>Tampil di struk, header POS & Ops Console</p></div><i data-lucide="store" size="18" style="color:var(--accent-caramel);"></i></div>
      <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')
        {{-- kirim ulang default operasional agar validasi tetap lolos --}}
        @foreach (['threshold_default_kg','safety_cap_default_kg','poin_per_10k','biaya_layanan'] as $f)
          <input type="hidden" name="{{ $f }}" value="{{ $settings[$f] }}">
        @endforeach
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Nama toko</label><input name="store_name" value="{{ old('store_name', $settings['store_name']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Cabang</label><input name="branch" value="{{ old('branch', $settings['branch']) }}" class="form-input" required></div>
        </div>
        <div class="form-group"><label class="form-label">Alamat</label><input name="address" value="{{ old('address', $settings['address']) }}" class="form-input" required></div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Telepon</label><input name="phone" value="{{ old('phone', $settings['phone']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Destinasi roastery default</label><input name="roastery_destination" value="{{ old('roastery_destination', $settings['roastery_destination']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Jam buka</label><input name="open_hour" type="time" value="{{ old('open_hour', $settings['open_hour']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Jam tutup</label><input name="close_hour" type="time" value="{{ old('close_hour', $settings['close_hour']) }}" class="form-input" required></div>
        </div>
        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Profil</button>
      </form>
    </div>

    <div class="card">
      <div class="card-title-row"><div><h3>Default Operasional</h3><p> dipakai saat tambah biji & hitung loyalitas</p></div><i data-lucide="sliders-horizontal" size="18" style="color:var(--accent-caramel);"></i></div>
      <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')
        {{-- kirim ulang profil agar validasi profil tetap lolos --}}
        @foreach (['store_name','branch','address','phone','open_hour','close_hour','roastery_destination'] as $f)
          <input type="hidden" name="{{ $f }}" value="{{ $settings[$f] }}">
        @endforeach
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Threshold biji default (kg)</label><input name="threshold_default_kg" type="number" step="0.1" value="{{ old('threshold_default_kg', $settings['threshold_default_kg']) }}" class="form-input" required><span class="form-hint">Alert kritis jika sisa di bawah ini</span></div>
          <div class="form-group"><label class="form-label">Safety cap default (kg)</label><input name="safety_cap_default_kg" type="number" step="0.1" value="{{ old('safety_cap_default_kg', $settings['safety_cap_default_kg']) }}" class="form-input" required><span class="form-hint">Target qty reorder otomatis</span></div>
        </div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Poin per Rp 10.000</label><input name="poin_per_10k" type="number" min="0" value="{{ old('poin_per_10k', $settings['poin_per_10k']) }}" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Biaya layanan per pesanan (Rp)</label><input name="biaya_layanan" type="number" min="0" step="100" value="{{ old('biaya_layanan', $settings['biaya_layanan']) }}" class="form-input" required><span class="form-hint">Ditambahkan ke total & tampil di struk</span></div>
        </div>
        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Default</button>
      </form>
      <div class="staff-summary"><span>Jam operasional: <strong>{{ $settings['open_hour'] }} – {{ $settings['close_hour'] }}</strong></span></div>
    </div>
  </section>

  <section class="bottom-grid">
    <div class="card">
      <div class="card-title-row"><div><h3>Metode Pembayaran</h3><p> dipakai kasir saat tandai lunas</p></div></div>
      <div class="top-selling-list" style="margin-bottom:12px;">
        @forelse ($metode as $m)
          <div class="item-row"><div class="item-details"><h4>{{ $m->nama_metode }}</h4><p>{{ $m->kode_metode }} • {{ $m->ada_kembalian ? 'ada kembalian' : 'tanpa kembalian' }}</p></div></div>
        @empty
          <div class="empty-state">Belum ada metode.</div>
        @endforelse
      </div>
      <form method="POST" action="{{ route('admin.settings.metode') }}" style="display:flex;gap:8px;">
        @csrf
        <input name="kode_metode" class="form-input" placeholder="kode: qris" required style="max-width:140px;">
        <input name="nama_metode" class="form-input" placeholder="Nama: QRIS" required>
        <button class="btn-sm primary">Tambah</button>
      </form>
    </div>

    <div class="card">
      <div class="card-title-row"><div><h3>Info Sistem</h3><p>Environment & data master</p></div><i data-lucide="info" size="18" style="color:var(--accent-caramel);"></i></div>
      <div class="top-selling-list">
        @foreach ($info as $k => $v)
          <div class="item-row"><div class="item-details"><h4>{{ $k }}</h4></div><div class="item-stats"><div class="cups" style="font-weight:500;font-size:12px;">{{ $v }}</div></div></div>
        @endforeach
      </div>
      <div class="staff-summary"><span>Meja terdaftar: <strong>{{ $meja->count() }}</strong> ditampilkan (maks 20)</span></div>
    </div>
  </section>

  <section class="bottom-grid">
    <div class="card">
      <div class="card-title-row">
        <div><h3>Midtrans Payment Gateway</h3><p>QRIS dinamis &amp; virtual account untuk pembayaran kasir</p></div>
        <span class="{{ $midtransInfo['configured'] ? 'badge-active' : 'badge-critical' }}">{{ $midtransInfo['configured'] ? 'Terhubung' : 'Belum diatur' }}</span>
      </div>

      @if ($errors->any())
        <div class="alert-error"><strong>Periksa input:</strong><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
      @endif

      <form method="POST" action="{{ route('admin.settings.midtrans') }}">
        @csrf @method('PUT')
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Server Key</label>
            <input name="server_key" type="password" class="form-input" autocomplete="new-password"
                   placeholder="{{ $midtransInfo['server_masked'] ?? 'SB-Mid-server-...' }}">
            <span class="form-hint">Kosongkan jika tidak ingin mengubah key tersimpan.</span>
          </div>
          <div class="form-group">
            <label class="form-label">Client Key</label>
            <input name="client_key" type="password" class="form-input" autocomplete="new-password"
                   placeholder="{{ $midtransInfo['client_masked'] ?? 'SB-Mid-client-...' }}">
            <span class="form-hint">Diperlukan untuk transaksi Snap; QRIS/VA memakai Server Key.</span>
          </div>
        </div>
        <label class="form-hint" style="display:flex;align-items:center;gap:8px;margin:0 0 14px;cursor:pointer;">
          <input type="checkbox" name="is_production" value="1" @checked($midtrans?->is_production)>
          Mode production (nonaktifkan untuk sandbox)
        </label>
        <button class="btn-primary" style="width:100%;padding:10px;">Simpan Kredensial</button>
      </form>

      <div class="staff-summary"><span>Mode: <strong>{{ $midtransInfo['mode'] }}</strong> • Server Key: <strong>{{ $midtransInfo['server_masked'] ?? 'belum diisi' }}</strong></span></div>
    </div>

    <div class="card">
      <div class="card-title-row"><div><h3>Webhook &amp; Aktivasi</h3><p>Supaya status lunas masuk otomatis</p></div><i data-lucide="webhook" size="18" style="color:var(--accent-caramel);"></i></div>
      <p class="form-hint" style="margin-top:0;">Pasang URL berikut di dashboard Midtrans → Settings → Payment Notification URL:</p>
      <div class="password-box" style="margin-bottom:12px;word-break:break-all;"><code style="font-size:11.5px;color:var(--text-main);">{{ $midtransInfo['webhook_url'] }}</code></div>
      <div class="top-selling-list">
        <div class="item-row"><div class="item-details"><h4>QRIS</h4><p>Scan QR dinamis yang dibuat per tagihan</p></div><span class="badge-active">Aktif</span></div>
        <div class="item-row"><div class="item-details"><h4>Virtual Account</h4><p>BCA, BNI, BRI, Mandiri, Permata</p></div><span class="badge-active">Aktif</span></div>
      </div>
      <p class="form-hint">Pastikan channel QRIS &amp; VA sudah diaktifkan di akun Midtrans. Di localhost, kasir tetap bisa mengecek status lewat polling tanpa webhook.</p>
    </div>
  </section>
@endsection
