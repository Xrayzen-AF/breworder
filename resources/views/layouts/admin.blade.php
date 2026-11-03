<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Tejas Coffee - Panel Pengelola')</title>

    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Sama dengan layout utama: Inter untuk UI, Lora untuk heading. --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
    @php
        $activeMenu = $activeMenu ?? '';
        $admin = auth()->user();
        $nav = [
            'Ringkasan' => [
                ['key' => 'dashboard', 'label' => 'Dasbor', 'icon' => 'layout-dashboard', 'route' => 'admin.dashboard'],
                ['key' => 'sales', 'label' => 'Penjualan', 'icon' => 'trending-up', 'route' => 'admin.sales.index'],
                ['key' => 'orders', 'label' => 'Pesanan', 'icon' => 'receipt', 'route' => 'admin.orders.index'],
            ],
            'Operasional' => [
                ['key' => 'inventory', 'label' => 'Stok Bahan', 'icon' => 'package', 'route' => 'admin.inventory.index'],
                ['key' => 'staff', 'label' => 'Pegawai', 'icon' => 'users', 'route' => 'admin.staff.index'],
                ['key' => 'customers', 'label' => 'Pelanggan', 'icon' => 'heart-handshake', 'route' => 'admin.customers.index'],
                ['key' => 'employees', 'label' => 'Akun Pegawai', 'icon' => 'user-cog', 'route' => 'admin.employees.index'],
            ],
            'Data Menu' => [
                ['key' => 'kategori', 'label' => 'Kategori', 'icon' => 'tags', 'route' => 'admin.master.kategori.index'],
                ['key' => 'produk', 'label' => 'Menu', 'icon' => 'coffee', 'route' => 'admin.master.produk.index'],
                ['key' => 'meja', 'label' => 'Meja', 'icon' => 'grid-3x3', 'route' => 'admin.master.meja.index'],
                ['key' => 'promo', 'label' => 'Promo', 'icon' => 'ticket-percent', 'route' => 'admin.master.promo.index'],
            ],
            'Sistem' => [
                ['key' => 'settings', 'label' => 'Pengaturan', 'icon' => 'settings', 'route' => 'admin.settings.index'],
            ],
        ];
    @endphp

    <div class="admin-shell">
        <aside class="admin-sidebar" id="admin-sidebar">
            <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
                <span class="brand-mark">
                    <img src="{{ asset('images/logo-tejas.png') }}" alt="Tejas Coffee" width="22" height="22">
                </span>
                <h1>TEJAS COFFEE</h1>
                <p>Panel Pengelola</p>
            </a>

            <nav class="sidebar-nav" aria-label="Navigasi admin">
                @foreach ($nav as $group => $links)
                    <p class="nav-section">{{ $group }}</p>
                    @foreach ($links as $link)
                        @php
                            $isActive = $activeMenu === $link['key']
                                || ($link['key'] === 'employees' && in_array($activeMenu, ['employees', 'employees-create', 'employees-edit'], true));
                        @endphp
                        <a href="{{ route($link['route']) }}"
                           class="nav-link {{ $isActive ? 'active' : '' }}"
                           @if ($isActive) aria-current="page" @endif>
                            <i data-lucide="{{ $link['icon'] }}" size="16"></i>
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <div class="sidebar-foot">
                <div class="sidebar-user">
                    <span class="avatar">{{ strtoupper(substr($admin->nama_lengkap ?? 'A', 0, 1)) }}</span>
                    <div class="who">
                        <strong>{{ $admin->nama_lengkap ?? 'Administrator' }}</strong>
                        <span>{{ $admin->peran ?? 'admin' }}</span>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sidebar-logout">
                        <i data-lucide="log-out" size="15"></i>
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <button type="button" class="icon-btn sidebar-toggle" id="sidebar-toggle"
                        aria-label="Buka menu" aria-controls="admin-sidebar" aria-expanded="false">
                    <i data-lucide="menu" size="18"></i>
                </button>

                <div class="topbar-text">
                    <h1>@yield('header-title', 'Operations Console')</h1>
                    <p>@yield('header-subtitle', 'Ringkasan operasional Tejas Coffee')</p>
                </div>

                <div class="topbar-actions">
                    @yield('header-action')

                    <a href="{{ route('admin.settings.index') }}" class="icon-btn" title="Pengaturan toko" aria-label="Pengaturan toko">
                        <i data-lucide="sliders-horizontal" size="17"></i>
                    </a>
                    <a href="{{ route('pelanggan.dashboard') }}" class="icon-btn" title="Lihat aplikasi pelanggan" aria-label="Lihat aplikasi pelanggan">
                        <i data-lucide="external-link" size="17"></i>
                    </a>
                </div>
            </header>

            <main class="admin-content">
                @yield('content')
            </main>
        </div>
    </div>

    {{-- Konfirmasi kustom (ganti alert/confirm bawaan browser) --}}
    <div id="tejas-confirm" class="tejas-modal-backdrop" aria-hidden="true">
        <div class="tejas-modal" role="dialog" aria-modal="true" aria-labelledby="tejas-confirm-title">
            <div class="tejas-modal-header">
                <h3 id="tejas-confirm-title" class="tejas-modal-title">Konfirmasi</h3>
            </div>
            <div class="tejas-modal-body">
                <p id="tejas-confirm-message" class="tejas-modal-message"></p>
            </div>
            <div class="tejas-modal-footer">
                <button type="button" id="tejas-confirm-cancel" class="tejas-btn tejas-btn-ghost">Batal</button>
                <button type="button" id="tejas-confirm-ok" class="tejas-btn tejas-btn-primary">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>

    <style>
        .tejas-modal-backdrop{position:fixed;inset:0;background:rgba(12,9,7,.72);display:none;align-items:center;justify-content:center;padding:16px;z-index:9999;backdrop-filter:blur(2px);}
        .tejas-modal-backdrop.is-open{display:flex;}
        .tejas-modal{width:100%;max-width:420px;background:#241B16;border:1px solid #3d322e;border-radius:var(--radius-overlay);box-shadow:0 30px 60px -30px rgba(0,0,0,.65),0 40px 80px -40px rgba(0,0,0,.8);overflow:hidden;animation:tejas-modal-in .18s ease-out;}
        .tejas-modal-header{padding:18px 22px 0 22px;}
        .tejas-modal-title{font-family:Lora,ui-serif,Georgia,serif;font-weight:700;font-size:16px;letter-spacing:.01em;color:#f1dfd8;}
        .tejas-modal-body{padding:10px 22px 18px 22px;}
        .tejas-modal-message{font-size:14px;line-height:1.7;color:#d7c7be;white-space:normal;}
        .tejas-modal-footer{display:flex;gap:10px;justify-content:flex-end;padding:0 22px 20px 22px;}
        .tejas-btn{appearance:none;border:1px solid transparent;background:transparent;color:#f1dfd8;font-family:inherit;font-weight:600;font-size:13px;letter-spacing:.01em;padding:9px 16px;border-radius:6px;display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:all .16s ease;}
        .tejas-btn-ghost{border-color:#4a3b35;color:#d7c7be;}
        .tejas-btn-ghost:hover{border-color:#5f4b44;color:#f1dfd8;}
        .tejas-btn-primary{background:#d97706;color:#241708;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .tejas-btn-primary:hover{filter:brightness(1.05);}
        @keyframes tejas-modal-in{from{opacity:0;transform:translateY(8px) scale(.98);}to{opacity:1;transform:translateY(0) scale(1);}}
    </style>

    <script>
        (function(){
            var backdrop=document.getElementById('tejas-confirm');
            if(!backdrop)return;
            var msg=document.getElementById('tejas-confirm-message');
            var ok=document.getElementById('tejas-confirm-ok');
            var cancel=document.getElementById('tejas-confirm-cancel');
            var resolver=null;
            function close(val){
                backdrop.classList.remove('is-open');
                backdrop.setAttribute('aria-hidden','true');
                if(resolver){var r=resolver;resolver=null;r(val);}
            }
            window.tejasConfirm=function(message){
                return new Promise(function(resolve){
                    resolver=resolve;
                    msg.textContent=message||'Apakah Anda yakin?';
                    backdrop.classList.add('is-open');
                    backdrop.setAttribute('aria-hidden','false');
                    try{ok.focus();}catch(e){}
                });
            };
            window.confirm=function(message){
                try{
                    if(backdrop){
                        window.tejasConfirm(message);
                    }
                }catch(e){}
                return false;
            };
            ok.addEventListener('click',function(){close(true);});
            cancel.addEventListener('click',function(){close(false);});
            backdrop.addEventListener('click',function(e){if(e.target===backdrop) close(false);});
            document.addEventListener('keydown',function(e){if(e.key==='Escape' && backdrop.classList.contains('is-open')) close(false);});
            function interceptForm(form){
                if(!(form instanceof HTMLFormElement)) return;
                if(form.dataset.tejasConfirmBound) return;
                var onsubmit=form.getAttribute('onsubmit')||'';
                var m=onsubmit.match(/confirm\s*\(\s*(['"])(.*?)\1\s*\)/i);
                if(m){
                    form.dataset.tejasConfirmBound='1';
                    form.removeAttribute('onsubmit');
                    form.dataset.tejasConfirmMsg=m[2];
                    return;
                }
                if(form.hasAttribute('data-confirm')){
                    form.dataset.tejasConfirmBound='1';
                    form.dataset.tejasConfirmMsg=form.getAttribute('data-confirm');
                    form.removeAttribute('data-confirm');
                }
            }
            document.querySelectorAll('form').forEach(interceptForm);
            var mo=new MutationObserver(function(list){
                list.forEach(function(mutation){
                    mutation.addedNodes.forEach(function(node){
                        if(node.nodeType===1){
                            if(node.tagName==='FORM') interceptForm(node);
                            node.querySelectorAll&&node.querySelectorAll('form').forEach(interceptForm);
                        }
                    });
                });
            });
            mo.observe(document.documentElement,{childList:true,subtree:true});
            document.addEventListener('click',function(e){
                var btn=e.target.closest('button[type=submit], input[type=submit]');
                if(!btn) return;
                var form=btn.form;
                if(!form) return;
                if(form.dataset.tejasConfirmMsg && !form.dataset.tejasConfirmSubmitted){
                    e.preventDefault();
                    e.stopPropagation();
                    var msgText=form.dataset.tejasConfirmMsg;
                    window.tejasConfirm(msgText).then(function(ok2){
                        if(ok2){
                            form.dataset.tejasConfirmSubmitted='1';
                            if(typeof form.requestSubmit==='function'){
                                try{form.requestSubmit(btn);}catch(err){form.submit();}
                            }else{
                                form.submit();
                            }
                        }
                    });
                }
            },true);
            document.addEventListener('submit',function(e){
                var form=e.target;
                if(!(form instanceof HTMLFormElement)) return;
                if(form.dataset.tejasConfirmSubmitted) return;
                if(form.dataset.tejasConfirmMsg){
                    e.preventDefault();
                    e.stopPropagation();
                    var msgText=form.dataset.tejasConfirmMsg;
                    window.tejasConfirm(msgText).then(function(ok2){
                        if(ok2){
                            form.dataset.tejasConfirmSubmitted='1';
                            if(typeof form.requestSubmit==='function'){form.requestSubmit();}else{form.submit();}
                        }
                    });
                }
            },true);
        })();
    </script>

    @if (session('success'))
        <div id="toast-success"
             class="alert-success"
             role="status"
             style="position:fixed;right:24px;bottom:24px;z-index:60;max-width:380px;margin:0;box-shadow:0 12px 30px rgba(0,0,0,.55);background:#271D19;border-color:#3D322E;color:#F1DFD8;">
            <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;flex:0 0 20px;border-radius:6px;background:#4ADE80;color:#12210F;">
                <i data-lucide="check" style="width:13px;height:13px;"></i>
            </span>
            <span style="flex:1;">{{ session('success') }}</span>
            <button type="button" onclick="document.getElementById('toast-success').remove()"
                    aria-label="Tutup notifikasi"
                    style="border:0;background:transparent;color:#9D8E87;display:inline-flex;align-items:center;cursor:pointer;">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>
        <script>
            setTimeout(function () {
                var toast = document.getElementById('toast-success');
                if (toast) toast.remove();
            }, 5000);
        </script>
    @endif

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }

        (function () {
            var sidebar = document.getElementById('admin-sidebar');
            var toggle = document.getElementById('sidebar-toggle');
            if (!sidebar || !toggle) return;

            function setOpen(open) {
                sidebar.classList.toggle('open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            toggle.addEventListener('click', function () {
                setOpen(!sidebar.classList.contains('open'));
            });

            document.addEventListener('click', function (event) {
                if (window.innerWidth > 900) return;
                if (sidebar.classList.contains('open')
                    && !sidebar.contains(event.target)
                    && !toggle.contains(event.target)) {
                    setOpen(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') setOpen(false);
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
