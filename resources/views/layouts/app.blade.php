<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Tejas Coffee' }} — Tejas Coffee</title>

    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Satu sumber font untuk semua halaman pelanggan, auth, kasir, barista.
         Inter (400–700) untuk teks UI, Lora (400–700) untuk heading serif.
         Lato sudah tidak dipakai agar weight 500/600 tidak disintesis. --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --color-canvas: #1E1511;
            --color-surface: #221A15;
            --color-card: #271D19;
            --color-elevated: #2F231D;
            --color-line: #3D322E;
            --color-ink: #F1DFD8;
            --color-ink-muted: #B8A8A1;
            --color-primary: #D97706;
            --color-secondary: #E0A96D;
            --color-tertiary: #F59E0B;
            --color-primary-ink: #241708;
            --color-status-lunas: #4ADE80;
            --color-status-antrian: #FBBF24;
            --color-status-void: #F87171;
            color-scheme: dark;
        }
    </style>
</head>
<body class="min-h-full bg-[#1E1511] text-[#F1DFD8]">
    <div class="flex min-h-screen flex-col">
        @yield('content')
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
                        // Tidak bisa blocking secara sync; pakai modal dan kembalikan false
                        // untuk mencegah aksi default. Gunakan tejasConfirm untuk alur async.
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

    {{-- Lucide Icons dimuat sekali di layout agar semua halaman (termasuk
         halaman auth) memakai ikon yang sama dengan stroke tipis. --}}
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>

    @stack('scripts')

    @if (session('success'))
        <div id="toast-success"
             class="fixed bottom-6 right-6 z-50 max-w-sm rounded-lg border border-[#3D322E] bg-[#271D19] p-4 text-[#F1DFD8]"
             style="box-shadow:0 12px 30px rgba(0,0,0,.5);">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded bg-[#D97706] text-[#241708]">
                    <i data-lucide="check" class="h-3.5 w-3.5"></i>
                </span>
                <p class="text-sm">{{ session('success') }}</p>
                <button type="button" onclick="document.getElementById('toast-success').remove()"
                        aria-label="Tutup notifikasi"
                        class="ml-auto text-[#9D8E87] hover:text-[#F1DFD8]">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
        </div>
        <script>
            if (window.lucide) window.lucide.createIcons();
            setTimeout(function () {
                var toast = document.getElementById('toast-success');
                if (toast) toast.remove();
            }, 4000);
        </script>
    @endif
</body>
</html>
