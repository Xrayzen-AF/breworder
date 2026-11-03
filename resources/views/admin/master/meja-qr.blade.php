@extends('layouts.admin', ['activeMenu' => 'meja'])

@section('title', 'QR Meja — Tejas Coffee')
@section('header-title', 'QR Meja')
@section('header-subtitle', 'Cetak dan tempel QR ini di masing-masing meja untuk check-in pelanggan')

@section('header-action')
  <div style="display:flex;gap:8px;">
    <a href="{{ route('admin.master.meja.index') }}" class="btn-sm">Kembali</a>
    <button type="button" class="btn-sm primary" onclick="window.print()">
      <i data-lucide="printer" size="13"></i> Cetak QR
    </button>
  </div>
@endsection

@section('content')
  <style>
    .qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
    .qr-card { background: #fff; color: #1E1511; border-radius: 12px; padding: 18px 16px; text-align: center; break-inside: avoid; }
    .qr-card h3 { font-family: 'Lora', ui-serif, Georgia, serif; font-size: 22px; margin: 0; }
    .qr-card p { margin: 2px 0 0; font-size: 11px; color: #6B5548; }
    .qr-card .qr-img { width: 100%; max-width: 180px; margin: 12px auto 8px; }
    .qr-card .qr-img svg { display: block; width: 100%; height: auto; }
    .qr-card .qr-url { font-size: 8.5px; color: #82746E; word-break: break-all; line-height: 1.4; }
    .qr-note { margin-bottom: 16px; font-size: 12.5px; line-height: 1.6; color: #B8A8A1; }
    @media print {
      body, .admin-body, .admin-main, .admin-content { background: #fff !important; }
      .admin-sidebar, .admin-topbar, .qr-note, .no-print { display: none !important; }
      .admin-main { margin: 0 !important; padding: 0 !important; }
      .qr-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
      .qr-card { border: 1px solid #ddd; border-radius: 8px; }
    }
  </style>

  <p class="qr-note">
    Setiap QR mengarah ke halaman scan meja tersebut. Token pada URL bersifat acak,
    jadi pelanggan tidak bisa menebak atau memakai QR meja lain. Tempel QR dalam
    keadaan tercetak (hitam-putih) agar mudah dipindai.
  </p>

  <div class="qr-grid">
    @foreach ($meja as $m)
      <div class="qr-card">
        <h3>Meja {{ $m->nomor_meja }}</h3>
        <p>Kapasitas {{ $m->kapasitas }} orang</p>
        <div class="qr-img">{!! $qr[$m->id_meja] !!}</div>
        <div class="qr-url">{{ route('pelanggan.meja.scan', ['meja' => $m->qr_token]) }}</div>
      </div>
    @endforeach
  </div>
@endsection
