@extends('layouts.admin', ['activeMenu' => 'employees-edit'])

@section('title', 'Edit Pegawai — Tejas Coffee')
@section('header-title', 'Edit Pegawai')
@section('header-subtitle', 'Ubah data akun ' . ucfirst($employee->peran) . ' — ' . $employee->nama_lengkap)

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
      <span>Edit — {{ $employee->nama_lengkap }}</span>
    </div>

    <div class="card-title-row" style="margin-top:12px;">
      <div style="display:flex; gap:12px; align-items:center;">
        <div class="emp-avatar {{ $employee->peran }}" style="width:40px; height:40px; font-size:15px;">
          {{ strtoupper(substr($employee->nama_lengkap, 0, 1)) }}
        </div>
        <div>
          <h3>{{ $employee->nama_lengkap }}</h3>
          <p>{{ $employee->email }} •
            @if ($employee->peran === 'kasir')
              <span class="role-badge kasir">Kasir</span>
            @else
              <span class="role-badge barista">Barista</span>
            @endif
          </p>
        </div>
      </div>
      <span class="badge-active">● On Roster</span>
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

    <form method="POST" action="{{ route('admin.employees.update', $employee) }}">
      @csrf
      @method('PUT')

      <div class="form-grid">
        <div class="form-group full">
          <label class="form-label" for="nama_lengkap">Nama Pegawai</label>
          <input class="form-input" id="nama_lengkap" type="text" name="nama_lengkap" value="{{ old('nama_lengkap', $employee->nama_lengkap) }}" required>
        </div>

        <div class="form-group full">
          <label class="form-label" for="email">Email</label>
          <input class="form-input" id="email" type="email" name="email" value="{{ old('email', $employee->email) }}" required>
        </div>

        <div class="form-group full">
          <label class="form-label">Peran</label>
          <div class="role-picker">
            @foreach ($roles as $role)
              <label class="role-option">
                <input type="radio" name="peran" value="{{ $role }}" @checked(old('peran', $employee->peran) === $role) required>
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
          <span class="form-hint">Hanya Kasir & Barista. Role Admin dan Pelanggan tidak dapat dipilih.</span>
        </div>

        <div class="form-group full">
          <div class="password-box">
            <p style="font-size:13px; font-weight:600; display:flex; gap:6px; align-items:center;">
              <i data-lucide="key-round" size="14"></i> Ganti Password <span style="font-weight:400; color:var(--text-muted);">(opsional)</span>
            </p>
            <p class="form-hint" style="margin:4px 0 12px;">Biarkan kosong jika tidak ingin mengubah password.</p>
            <div class="form-grid" style="margin-bottom:0;">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="password">Password Baru</label>
                <input class="form-input" style="background:#fff;" id="password" type="password" name="password" autocomplete="new-password" placeholder="Minimal 8 karakter">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="password_confirmation">Konfirmasi Password Baru</label>
                <input class="form-input" style="background:#fff;" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Ulangi password baru">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="action-btn-row" style="margin-top:8px;">
        <a href="{{ route('admin.employees.index') }}" class="btn-secondary">Batal</a>
        <button type="submit" class="btn-primary" style="display:flex; align-items:center; justify-content:center; gap:6px;">
          <i data-lucide="check" size="14"></i> Perbarui Akun
        </button>
      </div>
    </form>
  </section>
@endsection
