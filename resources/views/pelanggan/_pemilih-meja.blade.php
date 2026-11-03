@php
    $mpId = $mpId ?? 'id_meja';
    $mpList = collect($mejaTersedia ?? [])->sortBy('nomor_meja')->values();
    $mpStatus = function ($status) {
        return in_array($status, ['terisi', 'dipesan'], true) ? $status : 'kosong';
    };
    $mpSelected = $terpilih ?? null;
    $mpSelectedRow = $mpList->first(fn ($m) => (string) $m->id_meja === (string) $mpSelected);
    $mpSelectedStatus = $mpSelectedRow ? $mpStatus($mpSelectedRow->status ?? null) : 'kosong';
    $mpLabel = fn ($status) => $status === 'terisi' ? 'Terisi' : ($status === 'dipesan' ? 'Dipesan' : 'Kosong');
@endphp

<div class="mp" data-mp data-endpoint="{{ route('pelanggan.meja.status') }}">
    <input type="hidden" name="id_meja" id="{{ $mpId }}" value="{{ $mpSelectedRow?->id_meja }}">

    <button type="button" id="{{ $mpId }}-trigger" class="mp-trigger" data-mp-trigger
            aria-haspopup="listbox" aria-expanded="false" aria-controls="{{ $mpId }}-panel">
        <i data-lucide="armchair"></i>
        <span class="mp-trigger-label" data-mp-label @if (! $mpSelectedRow) data-empty="1" @endif>
            @if ($mpSelectedRow)
                Meja {{ $mpSelectedRow->nomor_meja }} · {{ $mpSelectedRow->kapasitas }} kursi
            @else
                — Pilih meja —
            @endif
        </span>
        <span class="mp-status" data-mp-current data-status="{{ $mpSelectedRow ? $mpSelectedStatus : 'none' }}">
            <span class="mp-dot"></span>
            <span data-mp-current-label>{{ $mpSelectedRow ? $mpLabel($mpSelectedStatus) : '' }}</span>
        </span>
        <i data-lucide="chevron-down" class="mp-chevron"></i>
    </button>

    <div class="mp-panel" id="{{ $mpId }}-panel" data-mp-panel role="listbox" aria-label="Pilih meja" hidden>
        <div class="mp-head">
            <span class="mp-head-title">Status Meja</span>
            <span class="mp-live">
                <span class="mp-live-dot"></span>
                <span data-mp-updated aria-live="polite">memuat…</span>
            </span>
        </div>

        <div class="mp-list" data-mp-list>
            @forelse ($mpList as $m)
                @php $st = $mpStatus($m->status ?? null); @endphp
                <button type="button" class="mp-option" role="option"
                        data-mp-option data-id="{{ $m->id_meja }}" data-nomor="{{ $m->nomor_meja }}"
                        data-kapasitas="{{ $m->kapasitas }}" data-status="{{ $st }}"
                        aria-selected="{{ (string) $mpSelected === (string) $m->id_meja ? 'true' : 'false' }}">
                    <span class="mp-option-main">
                        <span class="mp-option-name">Meja {{ $m->nomor_meja }}</span>
                        <span class="mp-option-cap">{{ $m->kapasitas }} kursi</span>
                    </span>
                    <span class="mp-chip" data-mp-chip data-status="{{ $st }}">
                        <span class="mp-dot"></span>
                        <span data-mp-chip-label>{{ $mpLabel($st) }}</span>
                    </span>
                </button>
            @empty
                <p class="mp-empty">Belum ada meja terdaftar.</p>
            @endforelse
        </div>

        <p class="mp-foot">Status diperbarui otomatis · meja terisi tetap bisa dipilih</p>
    </div>
</div>

<style>
  .mp { position: relative; }
  .mp-trigger {
    display: flex; align-items: center; gap: 8px; width: 100%;
    background: #221A15; border: 1px solid #3D322E; border-radius: var(--radius-control);
    padding: 10px 12px; font-family: inherit; font-size: 13px; font-weight: 600;
    color: #F1DFD8; text-align: left; cursor: pointer; outline: none;
    transition: background-color .15s ease, border-color .15s ease;
  }
  .mp-trigger:hover { background: #2A1F19; border-color: #554336; }
  .mp-trigger:focus-visible { border-color: #D97706; box-shadow: 0 0 0 3px rgba(217,119,6,.18); }
  .mp-trigger svg { width: 15px; height: 15px; flex: none; color: #B8A8A1; }
  .mp-trigger-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .mp-trigger-label[data-empty="1"] { font-weight: 500; color: #82746E; }
  .mp-chevron { margin-left: 2px; transition: transform .18s ease; }
  .mp[data-open="1"] .mp-chevron { transform: rotate(180deg); }

  .mp-dot { width: 6px; height: 6px; border-radius: 999px; flex: none; background: #82746E; }
  [data-status="kosong"] .mp-dot { background: #4ADE80; }
  [data-status="terisi"] .mp-dot { background: #F87171; }
  [data-status="dipesan"] .mp-dot { background: #FBBF24; }

  .mp-status {
    display: inline-flex; align-items: center; gap: 5px; flex: none;
    font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    color: #9D8E87;
  }
  .mp-status[data-status="kosong"] { color: #86E0A4; }
  .mp-status[data-status="terisi"] { color: #EFA2A2; }
  .mp-status[data-status="dipesan"] { color: #EFBE74; }
  .mp-status[data-status="none"] { display: none; }

  .mp-panel {
    position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 50;
    background: #241B16; border: 1px solid #3D322E; border-radius: var(--radius-surface);
    box-shadow: 0 20px 44px -20px rgba(0,0,0,.9); overflow: hidden;
  }
  .mp-head {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 8px 12px; background: #2A1F19; border-bottom: 1px solid rgba(61,50,46,.85);
  }
  .mp-head-title { font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #9D8E87; }
  .mp-live { display: inline-flex; align-items: center; gap: 6px; font-size: 10px; color: #9D8E87; font-variant-numeric: tabular-nums; }
  .mp-live-dot { width: 5px; height: 5px; border-radius: 999px; background: #4ADE80; animation: mpPulse 2s ease-in-out infinite; }
  .mp[data-sync="error"] .mp-live-dot { background: #F87171; animation: none; }
  @keyframes mpPulse { 0%, 100% { opacity: 1; } 50% { opacity: .25; } }

  .mp-list { max-height: min(288px, 52vh); overflow-y: auto; padding: 4px; }
  .mp-option {
    display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%;
    padding: 9px 8px; border: 0; border-radius: var(--radius-control);
    background: transparent; color: #F1DFD8; text-align: left; cursor: pointer;
  }
  .mp-option:hover, .mp-option:focus-visible { background: #2F231D; outline: none; }
  .mp-option[aria-selected="true"] { background: rgba(217,119,6,.12); }
  .mp-option[aria-selected="true"] .mp-option-name { color: #F6C89A; }
  .mp-option-main { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
  .mp-option-name { font-size: 12.5px; font-weight: 600; white-space: nowrap; }
  .mp-option-cap { font-size: 10.5px; color: #9D8E87; }

  .mp-chip {
    display: inline-flex; align-items: center; gap: 5px; flex: none;
    padding: 3px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 600;
    background: rgba(130,116,110,.12); color: #9D8E87; white-space: nowrap;
  }
  .mp-chip[data-status="kosong"] { background: rgba(74,222,128,.08); color: #86E0A4; }
  .mp-chip[data-status="terisi"] { background: rgba(248,113,113,.08); color: #EFA2A2; }
  .mp-chip[data-status="dipesan"] { background: rgba(245,158,11,.10); color: #EFBE74; }

  .mp-empty { padding: 14px 12px; text-align: center; font-size: 12px; color: #9D8E87; }
  .mp-foot { padding: 8px 12px; border-top: 1px solid rgba(61,50,46,.85); font-size: 10px; line-height: 1.5; color: #82746E; }
</style>

<script>
(function () {
  var roots = document.querySelectorAll('[data-mp]');
  if (!roots.length) return;

  function statusOf(s) { return (s === 'terisi' || s === 'dipesan') ? s : 'kosong'; }
  function labelOf(s) { return s === 'terisi' ? 'Terisi' : (s === 'dipesan' ? 'Dipesan' : 'Kosong'); }

  Array.prototype.forEach.call(roots, function (root) {
    if (root.dataset.mpReady) return;
    root.dataset.mpReady = '1';

    var trigger = root.querySelector('[data-mp-trigger]');
    var panel = root.querySelector('[data-mp-panel]');
    var list = root.querySelector('[data-mp-list]');
    var input = root.querySelector('input[name="id_meja"]');
    var label = root.querySelector('[data-mp-label]');
    var current = root.querySelector('[data-mp-current]');
    var currentLabel = root.querySelector('[data-mp-current-label]');
    var updated = root.querySelector('[data-mp-updated]');
    var endpoint = root.dataset.endpoint;
    var timer = null;

    function open() {
      panel.hidden = false;
      root.dataset.open = '1';
      trigger.setAttribute('aria-expanded', 'true');
      refresh();
      var active = list.querySelector('[data-mp-option][aria-selected="true"]') || list.querySelector('[data-mp-option]');
      if (active) active.focus({ preventScroll: true });
    }

    function close() {
      panel.hidden = true;
      root.dataset.open = '0';
      trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', function () {
      if (panel.hidden) { open(); } else { close(); }
    });

    document.addEventListener('click', function (e) {
      if (!root.contains(e.target)) close();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !panel.hidden) {
        close();
        trigger.focus();
      }
    });

    list.addEventListener('click', function (e) {
      var option = e.target.closest('[data-mp-option]');
      if (!option) return;

      var state = statusOf(option.getAttribute('data-status'));
      input.value = option.getAttribute('data-id');
      label.textContent = 'Meja ' + option.getAttribute('data-nomor') + ' · ' + option.getAttribute('data-kapasitas') + ' kursi';
      label.removeAttribute('data-empty');
      current.setAttribute('data-status', state);
      currentLabel.textContent = labelOf(state);

      Array.prototype.forEach.call(list.querySelectorAll('[data-mp-option]'), function (o) {
        o.setAttribute('aria-selected', o === option ? 'true' : 'false');
      });

      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      close();
      trigger.focus();
    });

    list.addEventListener('keydown', function (e) {
      var options = Array.prototype.slice.call(list.querySelectorAll('[data-mp-option]'));
      if (!options.length) return;
      var i = options.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); (options[i + 1] || options[0]).focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); (options[i - 1] || options[options.length - 1]).focus(); }
      else if (e.key === 'Home') { e.preventDefault(); options[0].focus(); }
      else if (e.key === 'End') { e.preventDefault(); options[options.length - 1].focus(); }
    });

    function paint(option, status) {
      var state = statusOf(status);
      option.setAttribute('data-status', state);
      var chip = option.querySelector('[data-mp-chip]');
      if (chip) {
        chip.setAttribute('data-status', state);
        var chipLabel = chip.querySelector('[data-mp-chip-label]');
        if (chipLabel) chipLabel.textContent = labelOf(state);
      }
      if (String(input.value) === String(option.getAttribute('data-id'))) {
        current.setAttribute('data-status', state);
        currentLabel.textContent = labelOf(state);
      }
    }

    function refresh() {
      if (!endpoint) return;
      var opts = { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' };
      fetch(endpoint, opts)
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function (data) {
          (data.tables || []).forEach(function (t) {
            var option = list.querySelector('[data-mp-option][data-id="' + t.id + '"]');
            if (option) paint(option, t.status);
          });
          if (updated) updated.textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
          root.dataset.sync = 'ok';
        })
        .catch(function () {
          if (updated) updated.textContent = 'Gagal memperbarui';
          root.dataset.sync = 'error';
        });
    }

    function tick() {
      if (document.visibilityState === 'visible') refresh();
    }

    refresh();
    timer = setInterval(tick, 8000);
    document.addEventListener('visibilitychange', tick);
    window.addEventListener('pagehide', function () { clearInterval(timer); });
  });
})();
</script>
