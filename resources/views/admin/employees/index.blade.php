@extends('layouts.admin', ['activeMenu' => 'employees'])

@section('title', 'Staff & Roster — Tejas Coffee')
@section('header-title', 'Staff & Roster')
@section('header-subtitle', 'Kelola akun Kasir & Barista — hanya Admin • ' . $employees->total() . ' pegawai terdaftar')

@section('header-action')
  <a href="{{ route('admin.employees.create') }}" class="btn-new-ticket">
    <i data-lucide="plus" size="16"></i>
    Tambah Pegawai
  </a>
@endsection

@section('content')
  {{-- METRICS --}}
  <section class="metrics-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="metric-card">
      <div class="metric-header">
        <span>Total Pegawai</span>
        <div class="metric-icon"><i data-lucide="users" size="16"></i></div>
      </div>
      <div class="metric-value">{{ $employees->total() }}</div>
      <div class="metric-sub"><span class="badge-growth">Aktif</span> Kasir + Barista terdaftar</div>
    </div>
    <div class="metric-card">
      <div class="metric-header">
        <span>Kasir</span>
        <div class="metric-icon"><i data-lucide="receipt" size="16"></i></div>
      </div>
      <div class="metric-value">{{ $totalKasir ?? $employees->where('peran', 'kasir')->count() }}</div>
      <div class="metric-sub"><i data-lucide="store" size="12"></i> POS Cashier & Hospitality Host</div>
    </div>
    <div class="metric-card">
      <div class="metric-header">
        <span>Barista</span>
        <div class="metric-icon"><i data-lucide="coffee" size="16"></i></div>
      </div>
      <div class="metric-value">{{ $totalBarista ?? $employees->where('peran', 'barista')->count() }}</div>
      <div class="metric-sub"><i data-lucide="flame" size="12"></i> Espresso Bar & Cold Brew Specialist</div>
    </div>
  </section>

  {{-- TABLE CARD --}}
  <section class="card">
    <div class="card-title-row">
      <div>
        <h3>Daftar Akun Pegawai</h3>
        <p>Buat, ubah, dan hapus akun Kasir & Barista. Kasir & Barista tidak bisa mendaftar mandiri.</p>
      </div>
      <div style="display:flex; gap:8px;">
        <a href="{{ route('admin.dashboard') }}" class="btn-sm"><i data-lucide="arrow-left" size="14"></i> Dashboard</a>
        <a href="{{ route('admin.employees.create') }}" class="btn-sm primary"><i data-lucide="plus" size="14"></i> Tambah</a>
      </div>
    </div>

    <div class="breadcrumb">
      <a href="{{ route('admin.dashboard') }}">Dashboard</a>
      <span>/</span>
      <span>Staff & Roster</span>
    </div>

    <div class="table-wrap" style="margin-top:12px;">
      <table class="ops-table">
        <thead>
          <tr>
            <th>Pegawai</th>
            <th>Role</th>
            <th>Dibuat</th>
            <th style="text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($employees as $employee)
            <tr>
              <td>
                <div class="emp-cell">
                  <div class="emp-avatar {{ $employee->peran }}">{{ strtoupper(substr($employee->nama_lengkap, 0, 1)) }}</div>
                  <div>
                    <div class="emp-name">{{ $employee->nama_lengkap }}</div>
                    <div class="emp-email">{{ $employee->email }}</div>
                  </div>
                </div>
              </td>
              <td>
                @if ($employee->peran === 'kasir')
                  <span class="role-badge kasir"><i data-lucide="receipt" size="13"></i> Kasir</span>
                @else
                  <span class="role-badge barista"><i data-lucide="coffee" size="13"></i> Barista</span>
                @endif
              </td>
              <td style="white-space:nowrap; font-size:12px; color:var(--text-muted);">
                {{ $employee->dibuat_pada->translatedFormat('d M Y') }}
              </td>
              <td>
                <div class="row-actions">
                  <a href="{{ route('admin.employees.edit', $employee) }}" class="btn-sm">
                    <i data-lucide="pencil" size="13"></i> Edit
                  </a>
                  <form action="{{ route('admin.employees.destroy', $employee) }}" method="POST"
                        onsubmit="return confirm('Yakin ingin menghapus akun {{ $employee->nama_lengkap }}?')"
                        style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-sm danger">
                      <i data-lucide="trash-2" size="13"></i> Hapus
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4">
                <div class="empty-state">
                  <div style="display:flex; justify-content:center; margin-bottom:10px; color:var(--text-light);">
                    <i data-lucide="users" size="32"></i>
                  </div>
                  Belum ada akun pegawai.<br>Klik <strong>Tambah Pegawai</strong> untuk membuat akun kasir atau barista.
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($employees->hasPages())
      <div class="pagination-wrap">
        {{ $employees->links() }}
      </div>
    @endif

    <div class="chart-footer">
      <span>Floor staff currently clocked into Metro Downtown #04</span>
      <a href="{{ route('admin.dashboard') }}">← Kembali ke Operations Console</a>
    </div>
  </section>
@endsection
