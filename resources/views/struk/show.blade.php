<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $pesanan->nomor_pesanan }} — {{ $toko['store_name'] ?? 'Tejas Coffee' }}</title>
    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #17110E;
            color: #111;
        }

        .screen {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 20px 12px 40px;
        }

        /* ── Toolbar (tidak ikut tercetak) ── */
        .toolbar {
            width: 100%;
            max-width: 80mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        }
        .toolbar a, .toolbar button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #3D322E;
            background: #271D19;
            color: #F1DFD8;
            border-radius: 6px;
            padding: 9px 13px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background .15s, border-color .15s;
        }
        .toolbar a:hover, .toolbar button:hover { background: #352A22; border-color: #554336; }
        .toolbar .primary { background: #D97706; border-color: #D97706; color: #241708; }
        .toolbar .primary:hover { background: #B56100; border-color: #B56100; }
        .toolbar .hint {
            font-size: 10.5px;
            color: #9D8E87;
            text-align: center;
        }

        /* ── Kertas struk 80 mm ── */
        .paper {
            width: 80mm;
            background: #fff;
            color: #111;
            padding: 4mm 3.5mm 5mm;
            font-family: 'Courier New', Courier, ui-monospace, monospace;
            font-size: 10.5px;
            line-height: 1.38;
            font-variant-numeric: tabular-nums;
            overflow-wrap: break-word;
            box-shadow: 0 12px 40px rgba(0, 0, 0, .55);
        }

        .center { text-align: center; }
        .muted { color: #444; }
        .strong { font-weight: 700; }
        .logo {
            display: block;
            width: 16mm;
            height: auto;
            margin: 0 auto 2mm;
            /* Logo asli putih transparan — jadikan hitam agar tercetak di kertas putih. */
            filter: brightness(0);
        }
        .store-name {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .store-meta { font-size: 9.5px; line-height: 1.35; color: #333; }
        .store-meta span { display: block; overflow-wrap: anywhere; }

        .dashed {
            border: 0;
            border-top: 1px dashed #444;
            margin: 2.4mm 0;
        }

        .antrian-label {
            text-align: center;
            font-size: 10px;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #333;
        }
        .antrian {
            text-align: center;
            font-size: 21px;
            font-weight: 700;
            line-height: 1.15;
            margin-top: .6mm;
            overflow-wrap: anywhere;
        }

        .meta { display: grid; grid-template-columns: 20mm 1fr; column-gap: 1.5mm; row-gap: .5mm; }
        .meta dt { color: #333; }
        .meta dd { margin: 0; overflow-wrap: anywhere; word-break: break-word; }

        .items { margin: 0; padding: 0; list-style: none; }
        .item {
            display: grid;
            grid-template-columns: 4.5mm 1fr auto;
            column-gap: 1.5mm;
            align-items: start;
            margin-top: 1.6mm;
        }
        .item:first-child { margin-top: 0; }
        .item .qty { color: #333; }
        .item .name { min-width: 0; overflow-wrap: anywhere; word-break: break-word; }
        .item .price { text-align: right; white-space: nowrap; }
        .item-note {
            margin: .2mm 0 0 6mm;
            color: #333;
            overflow-wrap: anywhere;
        }

        .totals { display: grid; gap: .7mm; }
        .row { display: flex; align-items: flex-start; justify-content: space-between; gap: 2.5mm; }
        .row > span:first-child { min-width: 0; overflow-wrap: anywhere; word-break: break-word; }
        .row .val { flex: 0 0 auto; white-space: nowrap; text-align: right; }

        /* Referensi pembayaran (UUID/ID Midtrans) sering panjang:
           tampilkan sebagai blok penuh dan boleh terpotong ke baris baru. */
        .reference {
            margin-top: 1mm;
            padding-top: .9mm;
            border-top: 1px dotted #999;
            font-size: 9.5px;
            line-height: 1.4;
        }
        .reference-label { display: block; color: #444; }
        .reference-value {
            display: block;
            overflow-wrap: anywhere;
            word-break: break-all;
        }
        .row.total {
            font-size: 12px;
            font-weight: 700;
            margin-top: 1mm;
            padding-top: 1.6mm;
            border-top: 1px dashed #444;
        }
        .row.pay { font-weight: 700; }
        .row.sub { color: #333; }

        .footer { text-align: center; font-size: 10px; line-height: 1.5; }
        .footer .thanks { font-size: 11.5px; font-weight: 700; }
        .footer span { display: block; overflow-wrap: anywhere; word-break: break-word; }
        .printed { text-align: center; font-size: 8.5px; color: #555; margin-top: 2mm; }

        /* ── Cetak: hanya kertas 80 mm ── */
        @page {
            size: 80mm auto;
            margin: 0;
        }
        @media print {
            html, body { background: #fff; }
            .no-print { display: none !important; }
            .screen { display: block; padding: 0; min-height: 0; }
            .paper {
                width: 80mm;
                margin: 0;
                padding: 3.5mm 3mm 4mm;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
@php
    $idr = fn ($n) => number_format((float) $n, 0, ',', '.');
    $trx = $pesanan->transaksi;
    $lunas = $trx && $trx->status_pembayaran === 'lunas';
    $kodeMetode = strtoupper((string) ($trx?->metodePembayaran?->kode_metode ?? ''));
    $namaMetode = $trx?->metodePembayaran?->nama_metode ?? '-';
    $metodeLabel = match ($kodeMetode) {
        'CASH' => 'TUNAI',
        'QRIS' => 'QRIS',
        'TRANSFER' => 'TRANSFER',
        default => strtoupper($namaMetode),
    };
    $jumlahItem = $pesanan->detailPesanan->sum('jumlah');
    $subtotalItem = $pesanan->detailPesanan->sum('subtotal');
    $diskon = (float) ($pesanan->diskon ?? 0);
    $tipeLabel = match ($pesanan->tipe_pesanan) {
        'dine-in' => 'MAKAN DITEMPAT',
        'takeaway' => 'BAWA PULANG',
        default => 'DIANTAR',
    };
    $pelayan = ($pesanan->pengguna?->peran ?? null) === 'pelanggan'
        ? 'Self Order'
        : ($pesanan->pengguna?->nama_lengkap ?? '-');
    $kembali = $trx ? (float) $trx->kembalian : 0;
@endphp

<div class="screen">
    <div class="toolbar no-print">
        @php
            $kembali = auth()->user()?->isAdmin()
                ? route('admin.orders.index')
                : (auth()->user()?->isKasir()
                    ? route('kasir.dashboard')
                    : route('pelanggan.status', $pesanan->nomor_pesanan));
        @endphp
        <a href="{{ $kembali }}">&larr; Kembali</a>
        @if ($bolehCetak)
            <button type="button" class="primary" onclick="window.print()">Cetak Struk</button>
        @else
            <span class="hint">Tunjukkan struk ini ke kasir bila perlu.</span>
        @endif
    </div>

    <div class="paper" id="struk">
        <img src="{{ asset('images/logo-tejas.png') }}" alt="{{ $toko['store_name'] ?? 'Tejas Coffee' }}" class="logo">

        <div class="center store-name">{{ $toko['store_name'] ?? 'Tejas Coffee' }}</div>
        <div class="center store-meta">
            <span>{{ $toko['branch'] ?? '' }}</span>
            <span>{{ $toko['address'] ?? '' }}</span>
        </div>

        <div class="center" style="margin-top:2.4mm">
            {{ $tipeLabel }}@if ($pesanan->meja) - MEJA {{ strtoupper($pesanan->meja->nomor_meja) }}@endif
        </div>
        <div class="center">{{ $pesanan->waktu_pesan?->format('d-m-Y H:i') }}</div>

        <hr class="dashed">

        <div class="antrian-label">Antrian</div>
        <div class="antrian">#{{ $nomorAntrian }}</div>

        <hr class="dashed">

        <dl class="meta">
            <dt>Pesanan</dt>
            <dd>: {{ $pesanan->nomor_pesanan }}</dd>
            <dt>Pelayan</dt>
            <dd>: {{ $pelayan }}</dd>
            <dt>Pelanggan</dt>
            <dd>: {{ $pesanan->pelanggan?->nama_pelanggan ?? '-' }}</dd>
            <dt>Telepon</dt>
            <dd>: {{ $pesanan->pelanggan?->nomor_telepon ?: '-' }}</dd>
        </dl>

        <hr class="dashed">

        <ul class="items">
            @foreach ($pesanan->detailPesanan as $d)
                <li>
                    <div class="item">
                        <span class="qty">{{ $d->jumlah }}</span>
                        <span class="name">{{ $d->produk?->nama_produk ?? 'Produk #' . $d->id_produk }}</span>
                        <span class="price">{{ $idr($d->subtotal) }}</span>
                    </div>
                    @if ($d->catatan_item)
                        <div class="item-note">{{ $d->catatan_item }}</div>
                    @endif
                </li>
            @endforeach
        </ul>

        <hr class="dashed">

        <div class="totals">
            <div class="row sub">
                <span>Subtotal</span>
                <span class="val">{{ $idr($subtotalItem) }}</span>
            </div>
            @if ($diskon > 0)
                <div class="row sub">
                    <span>Diskon{{ $pesanan->promo?->nama_promo ? ' (' . $pesanan->promo->nama_promo . ')' : '' }}</span>
                    <span class="val">-{{ $idr($diskon) }}</span>
                </div>
            @endif
            @if ((int) ($pesanan->poin_dipakai ?? 0) > 0)
                <div class="row sub">
                    <span>Poin ditukar</span>
                    <span class="val">{{ $pesanan->poin_dipakai }} pts</span>
                </div>
            @endif
            <div class="row sub">
                <span>Biaya layanan</span>
                <span class="val">{{ $idr($pesanan->biaya_layanan ?? 0) }}</span>
            </div>
            <div class="row total">
                <span>TOTAL ({{ $jumlahItem }} ITEM)</span>
                <span class="val">{{ $idr($pesanan->total_harga) }}</span>
            </div>
        </div>

        <hr class="dashed">

        <div class="totals">
            @if ($lunas)
                <div class="row pay">
                    <span>{{ $metodeLabel }} LUNAS</span>
                    <span class="val">{{ $idr($trx->total_bayar) }}</span>
                </div>
                @if ($trx->metodePembayaran?->ada_kembalian)
                    @if ($kembali > 0)
                        <div class="row sub">
                            <span>Kembalian</span>
                            <span class="val">{{ $idr($kembali) }}</span>
                        </div>
                    @endif
                @elseif ($trx->bank_atau_ewallet || $trx->nomor_referensi)
                    <div class="reference">
                        <span class="reference-label">Referensi pembayaran</span>
                        <span class="reference-value">{{ $trx->nomor_referensi ?: $trx->bank_atau_ewallet }}</span>
                    </div>
                @endif
            @else
                <div class="row pay">
                    <span>BELUM DIBAYAR</span>
                    <span class="val">{{ $idr($pesanan->total_harga) }}</span>
                </div>
                <div class="center muted" style="margin-top:1mm">Silakan bayar di kasir.</div>
            @endif
        </div>

        @if ($pesanan->catatan)
            <hr class="dashed">
            <div class="sub">Catatan: {{ $pesanan->catatan }}</div>
        @endif

        <hr class="dashed">

        <div class="footer">
            <span class="thanks">Have a nice day</span>
            <span>Terima kasih atas kunjungan Anda</span>
            @if (! empty($toko['phone']))
                <span>WA : {{ $toko['phone'] }}</span>
            @endif
        </div>

        <div class="printed">Dicetak {{ now()->format('d-m-Y H:i') }}</div>
    </div>
</div>

@if ($autoPrint && $bolehCetak)
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
@endif
</body>
</html>
