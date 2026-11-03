@extends('layouts.admin', ['activeMenu' => 'employees-create'])

@section('title', 'Tambah Pegawai — Tejas Coffee')
@section('header-title', 'Tambah Pegawai')
@section('header-subtitle', 'Buat akun baru dengan role Kasir atau Barista saja')

@section('header-action')
  <a href="{{ route('admin.employees.index') }}" class="btn-new-ticket" style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
    <i data-lucide="arrow-left" size="16"></i>
    Daftar Pegawai
  </a>
@endsection

@section('content')
  <section class="card" style="max-width: 720px;">
    <div class="breadcrumb">
      <a href="{{ route('admin.dashboard') }}">Dashboard</a>
      <span>/</span>
      <a href="{{ route('admin.employees.index') }}">Staff & Roster</a>
      <span>/</span>
      <span>Tambah Pegawai</span>
    </div>

    <div class="card-title-row" style="margin-top:12px;">
      <div style="display:flex; gap:12px; align-items:center;">
        <div class="metric-icon" style="width:40px; height:40px;"><i data-lucide="user-plus" size="18"></i></div>
        <div>
          <h3>Tambah Akun Pegawai</h3>
          <p>Akun langsung aktif dan bisa dipakai login sesuai perannya.</p>
        </div>
      </div>
    </div>

    @if ($errors->any())
      <div class="alert-error" style="margin-bottom:16px;">
        <strong style="display:flex; gap:6px; align-items:center;"><i data-lucide="alert-triangle" size="15"></i> Periksa kembali isian form:</strong>
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.employees.store') }}">
      @csrf

      <div class="form-grid">
        <div class="form-group full">
          <label class="form-label" for="nama_lengkap">Nama Pegawai</label>
          <input class="form-input" id="nama_lengkap" type="text" name="nama_lengkap" value="{{ old('nama_lengkap') }}" required placeholder="cth. Rina Wulandari">
        </div>

        <div class="form-group full">
          <label class="form-label" for="email">Email</label>
          <input class="form-input" id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="pegawai@kopi.dev">
          <span class="form-hint">Dipakai untuk login ke console sesuai role.</span>
        </div>

        <div class="form-group full">
          <label class="form-label">Peran</label>
          <div class="role-picker">
            @foreach ($roles as $role)
              <label class="role-option">
                <input type="radio" name="peran" value="{{ $role }}" @checked(old('peran') === $role) required>
                <div class="role-icon">
                  @if ($role === 'kasir')
                    <i data-lucide="receipt" size="16"></i>
                  @else
                    <i data-lucide="coffee" size="16"></i>
                  @endif
                </div>
                <div>
                  <strong>{{ ucfirst($role) }}</strong>
                  <small>{{ $role === 'kasir' ? 'POS Cashier & Hospitality' : 'Espresso & Cold Bar' }}</small>
                </div>
              </label>
            @endforeach
          </div>
          <span class="form-hint">Hanya Kasir & Barista. Role Admin dan Pelanggan tidak dapat dibuat dari form ini.</span>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input class="form-input" id="password" type="password" name="password" required autocomplete="new-password" placeholder="Minimal 8 karakter">
        </div>

        <div class="form-group">
          <label class="form-label" for="password_confirmation">Konfirmasi Password</label>
          <input class="form-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Ulangi password">
        </div>
      </div>

      <div class="action-btn-row" style="margin-top:8px;">
        <a href="{{ route('admin.employees.index') }}" class="btn-secondary">Batal</a>
        <button type="submit" class="btn-primary" style="display:flex; align-items:center; justify-content:center; gap:6px;">
          <i data-lucide="check" size="14"></i> Simpan Akun
        </button>
      </div>
    </form>
  </section>
@endsection
