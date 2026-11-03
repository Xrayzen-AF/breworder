<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SettingController;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Promo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * MVP alur pemesanan pelanggan (sesuai wireframe mobile):
 * /menu → keranjang (session) → checkout → status pesanan.
 */
class CartController extends Controller
{
    /** Katalog menu + filter kategori + pencarian. */
    public function menu(Request $request)
    {
        $kategoriAktif = $request->query('kategori');
        $cari = trim((string) $request->query('cari', ''));

        $kategori = Kategori::orderBy('nama_kategori')->get();

        $produk = Produk::with('kategori:id_kategori,nama_kategori')
            ->where('status_tersedia', true)
            ->when($kategoriAktif, function ($q) use ($kategoriAktif) {
                $q->whereHas('kategori', fn ($k) => $k->where('nama_kategori', $kategoriAktif));
            })
            ->when($cari !== '', function ($q) use ($cari) {
                $q->where(fn ($w) => $w->where('nama_produk', 'like', "%{$cari}%")
                    ->orWhere('deskripsi', 'like', "%{$cari}%"));
            })
            ->orderBy('nama_produk')
            ->get();

        return view('pelanggan.menu', [
            'produk' => $produk,
            'kategori' => $kategori,
            'kategoriAktif' => $kategoriAktif,
            'cari' => $cari,
            'keranjang' => $this->items(),
            'totalKeranjang' => $this->total(),
            'jumlahItemKeranjang' => $this->count(),
        ]);
    }

    /**
     * Opsi kustomisasi menu (harga delta dalam rupiah) sesuai Figma
     * ORDER_CUSTOMIZATION: ukuran, susu, es, manis (pilih 1) + add-on (bisa >1).
     */
    public const OPSI_UKURAN = [
        'regular' => ['label' => 'Regular 350 ml', 'delta' => 0],
        'large' => ['label' => 'Large 500 ml', 'delta' => 6000],
    ];

    public const OPSI_SUSU = [
        'fresh' => ['label' => 'Fresh Milk', 'delta' => 0],
        'oat' => ['label' => 'Oat Milk', 'delta' => 8000],
        'almond' => ['label' => 'Almond Milk', 'delta' => 8000],
        'skim' => ['label' => 'Skim Milk', 'delta' => 0],
    ];

    public const OPSI_ES = [
        'normal' => ['label' => 'Normal Ice', 'delta' => 0],
        'less' => ['label' => 'Less Ice (50%)', 'delta' => 0],
        'no' => ['label' => 'No Ice', 'delta' => 0],
    ];

    public const OPSI_MANIS = [
        'normal' => ['label' => 'Normal (100%)', 'delta' => 0],
        'less' => ['label' => 'Less (50%)', 'delta' => 0],
        'no' => ['label' => 'No Sugar (0%)', 'delta' => 0],
    ];

    public const OPSI_ADDON = [
        'shot' => ['label' => 'Extra Espresso Shot', 'delta' => 7000],
        'foam' => ['label' => 'Vanilla Sweet Cream Foam', 'delta' => 6000],
    ];

    /** Halaman kustomisasi satu produk (Langkah 1/3). Mode ubah via ?key=. */
    public function kustom(Request $request, Produk $produk)
    {
        if (! $produk->status_tersedia || $produk->stok <= 0) {
            return redirect()->route('pelanggan.menu')
                ->with('error', 'Menu '.$produk->nama_produk.' sedang habis.');
        }

        // Mode "Ubah": ambil baris lama untuk prefill form.
        $editKey = $request->query('key');
        $editBaris = null;
        if (is_string($editKey) && $editKey !== '') {
            $baris = session('keranjang', [])[$editKey] ?? null;
            if ($baris && (int) ($baris['id_produk'] ?? 0) === (int) $produk->id_produk) {
                $editBaris = $baris;
            } else {
                $editKey = null;
            }
        }

        return view('pelanggan.kustom', [
            'produk' => $produk->loadMissing('kategori:id_kategori,nama_kategori'),
            'jumlahItemKeranjang' => $this->count(),
            'editKey' => $editKey,
            'editBaris' => $editBaris,
        ]);
    }

    /** Harga satuan = harga dasar + delta opsi (dihitung ulang di server). */
    public static function hargaSatuan(float $dasar, array $opsi): float
    {
        $delta = (self::OPSI_UKURAN[$opsi['ukuran']]['delta'] ?? 0)
            + (self::OPSI_SUSU[$opsi['susu']]['delta'] ?? 0);
        foreach ($opsi['addon'] ?? [] as $a) {
            $delta += self::OPSI_ADDON[$a]['delta'] ?? 0;
        }

        return $dasar + $delta;
    }

    /** Ringkasan opsi untuk struk/barista, mis. "Large • Oat Milk • Less Ice". */
    public static function ringkasOpsi(array $opsi, ?string $catatan = null): string
    {
        $bagian = [];
        if (($opsi['ukuran'] ?? 'regular') !== 'regular') {
            $bagian[] = self::OPSI_UKURAN[$opsi['ukuran']]['label'];
        }
        if (($opsi['susu'] ?? 'fresh') !== 'fresh') {
            $bagian[] = self::OPSI_SUSU[$opsi['susu']]['label'];
        }
        if (($opsi['es'] ?? 'normal') !== 'normal') {
            $bagian[] = self::OPSI_ES[$opsi['es']]['label'];
        }
        if (($opsi['manis'] ?? 'normal') !== 'normal') {
            $bagian[] = self::OPSI_MANIS[$opsi['manis']]['label'];
        }
        foreach ($opsi['addon'] ?? [] as $a) {
            $bagian[] = '+'.self::OPSI_ADDON[$a]['label'];
        }
        if ($catatan) {
            $bagian[] = '"'.$catatan.'"';
        }

        return implode(' • ', $bagian);
    }

    /** Tambah item ke keranjang (session), dukung varian kustomisasi. */
    public function tambah(Request $request, Produk $produk)
    {
        $validated = $request->validate([
            'jumlah' => ['nullable', 'integer', 'min:1', 'max:20'],
            'ukuran' => ['nullable', 'string', 'in:regular,large'],
            'susu' => ['nullable', 'string', 'in:fresh,oat,almond,skim'],
            'es' => ['nullable', 'string', 'in:normal,less,no'],
            'manis' => ['nullable', 'string', 'in:normal,less,no'],
            'addon' => ['nullable', 'array', 'max:2'],
            'addon.*' => ['string', 'in:shot,foam'],
            'catatan' => ['nullable', 'string', 'max:100'],
            'ganti_key' => ['nullable', 'string', 'max:64'],
        ]);

        $jumlah = $validated['jumlah'] ?? 1;

        if (! $produk->status_tersedia || $produk->stok <= 0) {
            return back()->with('error', 'Menu '.$produk->nama_produk.' sedang habis.');
        }

        $opsi = [
            'ukuran' => $validated['ukuran'] ?? 'regular',
            'susu' => $validated['susu'] ?? 'fresh',
            'es' => $validated['es'] ?? 'normal',
            'manis' => $validated['manis'] ?? 'normal',
            'addon' => array_values(array_unique($validated['addon'] ?? [])),
        ];
        $catatan = trim($validated['catatan'] ?? '');
        $kustom = $opsi['ukuran'] !== 'regular' || $opsi['susu'] !== 'fresh'
            || $opsi['es'] !== 'normal' || $opsi['manis'] !== 'normal'
            || ! empty($opsi['addon']) || $catatan !== '';

        // Varian berbeda = baris keranjang berbeda; tanpa kustom = gabung per produk.
        $key = (string) $produk->id_produk;
        if ($kustom) {
            $key .= '_'.substr(md5(json_encode([$opsi, $catatan])), 0, 6);
        }

        $keranjang = session('keranjang', []);

        // Mode "Ubah Kustomisasi": buang baris lama dulu agar DIGANTI,
        // bukan diduplikat. Kunci asing (produk lain) diabaikan.
        $gantiKey = $validated['ganti_key'] ?? null;
        $mengganti = $gantiKey && isset($keranjang[$gantiKey])
            && (int) ($keranjang[$gantiKey]['id_produk'] ?? 0) === (int) $produk->id_produk;
        if ($mengganti) {
            unset($keranjang[$gantiKey]);
        }

        $totalProdukIni = collect($keranjang)
            ->where('id_produk', $produk->id_produk)
            ->sum('jumlah');
        if ($totalProdukIni + $jumlah > $produk->stok) {
            $sisa = max(0, $produk->stok - $totalProdukIni);

            return back()->with('error', 'Stok '.$produk->nama_produk.' tidak mencukupi (sisa '.$sisa.' untuk semua varian).');
        }

        $keranjang[$key] = [
            'key' => $key,
            'id_produk' => $produk->id_produk,
            'nama' => $produk->nama_produk,
            'sku' => $produk->kode_produk,
            'kategori' => $produk->kategori->nama_kategori ?? null,
            'gambar' => $produk->url_gambar,
            'harga' => self::hargaSatuan((float) $produk->harga, $opsi),
            'jumlah' => ($keranjang[$key]['jumlah'] ?? 0) + $jumlah,
            'opsi' => $kustom ? $opsi : null,
            'opsi_text' => $kustom ? self::ringkasOpsi($opsi, $catatan !== '' ? $catatan : null) : null,
            'catatan' => $catatan !== '' ? $catatan : null,
        ];

        session(['keranjang' => $keranjang]);

        $pesan = $mengganti
            ? $produk->nama_produk.' diperbarui sesuai kustomisasi baru.'
            : $produk->nama_produk.' ditambahkan ke keranjang.';

        return redirect()->route('pelanggan.keranjang.index')->with('success', $pesan);
    }

    /** Ubah jumlah / hapus baris keranjang berdasarkan key varian. */
    public function update(Request $request, string $key)
    {
        $validated = $request->validate([
            'jumlah' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $keranjang = session('keranjang', []);

        if ($validated['jumlah'] === 0) {
            unset($keranjang[$key]);
        } else {
            if (! isset($keranjang[$key])) {
                return back()->with('error', 'Item tidak ada di keranjang.');
            }

            $produk = Produk::find($keranjang[$key]['id_produk']);
            if ($produk) {
                // Agregat semua varian produk yang sama (kecuali baris ini).
                $totalLain = 0;
                foreach ($keranjang as $k => $baris) {
                    if ($k !== $key && (int) ($baris['id_produk'] ?? 0) === (int) $produk->id_produk) {
                        $totalLain += (int) $baris['jumlah'];
                    }
                }
                if ($totalLain + $validated['jumlah'] > $produk->stok) {
                    return back()->with('error', 'Stok '.$produk->nama_produk.' tidak mencukupi (sisa '.max(0, $produk->stok - $totalLain).').');
                }
            }

            $keranjang[$key]['jumlah'] = $validated['jumlah'];
        }

        session(['keranjang' => $keranjang]);

        return back()->with('success', 'Keranjang diperbarui.');
    }

    /** Hapus satu baris keranjang secara eksplisit (tombol Hapus). */
    public function hapus(string $key)
    {
        $keranjang = session('keranjang', []);

        if (! isset($keranjang[$key])) {
            return back()->with('error', 'Item tidak ada di keranjang.');
        }

        $nama = $keranjang[$key]['nama'] ?? 'Item';
        unset($keranjang[$key]);
        session(['keranjang' => $keranjang]);

        return back()->with('success', $nama.' dihapus dari keranjang.');
    }

    /** Halaman keranjang (Step 02): review + tipe + voucher + poin + rekomendasi. */
    public function index()
    {
        $items = $this->items();
        $pengguna = auth()->user();
        $member = $pengguna
            ? Pelanggan::where('nomor_telepon', $pengguna->nomor_telepon)->first()
            : null;

        $ring = self::ringkasan($items, session('voucher_kode'), (bool) session('tukar_poin'), $member);

        $inCart = collect($items)->pluck('id_produk')->unique()->all();
        $rekomendasi = Produk::with('kategori:id_kategori,nama_kategori')
            ->where('status_tersedia', true)
            ->where('stok', '>', 0)
            ->when($inCart, fn ($q) => $q->whereNotIn('id_produk', $inCart))
            ->inRandomOrder()
            ->limit(3)
            ->get();

        $mejaTersedia = DB::table('meja')->orderBy('nomor_meja')->get();
        $mejaDipilih = session('checkout_meja');
        $mejaDipilihNomor = $mejaDipilih
            ? ($mejaTersedia->firstWhere('id_meja', (int) $mejaDipilih)->nomor_meja ?? null)
            : null;

        return view('pelanggan.keranjang', [
            'keranjang' => $items,
            'totalKeranjang' => $ring['total'],
            'ringkasan' => $ring,
            'rekomendasi' => $rekomendasi,
            'mejaTersedia' => $mejaTersedia,
            'tipeDipilih' => session('checkout_tipe', 'dine-in'),
            'mejaDipilih' => $mejaDipilih,
            'mejaDipilihNomor' => $mejaDipilihNomor,
            'member' => $member,
        ]);
    }

    /** Cek masa berlaku promo. */
    public static function promoBerlaku(Promo $promo): bool
    {
        return $promo->status_aktif
            && (! $promo->berlaku_mulai || now()->gte($promo->berlaku_mulai))
            && (! $promo->berlaku_sampai || now()->lte($promo->berlaku_sampai));
    }

    /**
     * Ringkasan biaya: subtotal − voucher − poin + biaya layanan = total.
     * 100 Bean Points = Rp 1.000.
     */
    public static function ringkasan(array $items, ?string $kodePromo, bool $tukarPoin, $member): array
    {
        $subtotal = array_sum(array_map(fn ($i) => $i['harga'] * $i['jumlah'], $items));

        $promo = $kodePromo ? Promo::where('kode_promo', $kodePromo)->first() : null;
        if ($promo && ! self::promoBerlaku($promo)) {
            $promo = null;
        }
        $diskonPromo = 0;
        if ($promo) {
            $diskonPromo = $promo->tipe_diskon === 'persen'
                ? round($subtotal * (float) $promo->nilai_diskon / 100, 2)
                : min((float) $promo->nilai_diskon, $subtotal);
        }

        $sisa = $subtotal - $diskonPromo;
        $poinTersedia = (int) ($member->poin_loyalitas ?? 0);
        // 100 pts = Rp 1.000 → 1 pts = Rp 10.
        $poinPakai = ($tukarPoin && $poinTersedia > 0)
            ? min($poinTersedia, (int) floor($sisa / 10))
            : 0;
        $diskonPoin = $poinPakai * 10;

        $biayaLayanan = max(0, (float) (SettingController::load()['biaya_layanan'] ?? 0));

        return [
            'subtotal' => $subtotal,
            'biaya_layanan' => $biayaLayanan,
            'promo' => $promo,
            'diskon_promo' => $diskonPromo,
            'poin_tersedia' => $poinTersedia,
            'poin_pakai' => $poinPakai,
            'diskon_poin' => $diskonPoin,
            'tukar_poin' => $tukarPoin && $poinPakai > 0,
            'total' => max(0, $sisa - $diskonPoin) + $biayaLayanan,
        ];
    }

    /** Terapkan / hapus voucher promo (session). */
    public function voucher(Request $request)
    {
        $validated = $request->validate([
            'aksi' => ['required', 'in:terapkan,hapus'],
            'kode' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validated['aksi'] === 'hapus') {
            session()->forget('voucher_kode');

            return back()->with('success', 'Voucher dihapus.');
        }

        $kode = strtoupper(trim($validated['kode'] ?? ''));
        $promo = $kode !== '' ? Promo::where('kode_promo', $kode)->first() : null;

        if (! $promo || ! self::promoBerlaku($promo)) {
            return back()->with('error', 'Kode voucher tidak valid atau kedaluwarsa.');
        }

        session(['voucher_kode' => $promo->kode_promo]);

        return back()->with('success', 'Voucher '.$promo->kode_promo.' diterapkan.');
    }

    /** Aktif/nonaktif penukaran Bean Points (session). */
    public function poinToggle()
    {
        if (session('tukar_poin')) {
            session()->forget('tukar_poin');
        } else {
            session(['tukar_poin' => true]);
        }

        return back();
    }

    /** Simpan tipe pemesanan + meja ke session, lanjut ke checkout. */
    public function tipeSimpan(Request $request)
    {
        $validated = $request->validate([
            'tipe_pesanan' => ['required', 'in:dine-in,takeaway'],
            'id_meja' => ['nullable', 'integer', 'exists:meja,id_meja', 'required_if:tipe_pesanan,dine-in'],
        ], [
            'id_meja.required_if' => 'Nomor meja wajib dipilih untuk pesanan dine-in.',
        ]);

        session([
            'checkout_tipe' => $validated['tipe_pesanan'],
            'checkout_meja' => $validated['id_meja'] ?? null,
        ]);

        return redirect()->route('pelanggan.checkout');
    }

    /** Halaman checkout: konfirmasi tipe/meja + ringkasan akhir. */
    public function checkout()
    {
        $items = $this->items();

        if (empty($items)) {
            return redirect()->route('pelanggan.menu')->with('error', 'Keranjang masih kosong.');
        }

        $pengguna = auth()->user();
        $member = $pengguna
            ? Pelanggan::where('nomor_telepon', $pengguna->nomor_telepon)->first()
            : null;

        return view('pelanggan.checkout', [
            'keranjang' => $items,
            'totalKeranjang' => self::ringkasan($items, session('voucher_kode'), (bool) session('tukar_poin'), $member)['total'],
            'ringkasan' => self::ringkasan($items, session('voucher_kode'), (bool) session('tukar_poin'), $member),
            'mejaTersedia' => DB::table('meja')->orderBy('nomor_meja')->get(),
        ]);
    }

    /** Simpan pesanan via OrderController (logika bisnis existing) + kurangi stok. */
    public function simpanPesanan(Request $request)
    {
        $items = $this->items();

        if (empty($items)) {
            return redirect()->route('pelanggan.menu')->with('error', 'Keranjang masih kosong.');
        }

        $validated = $request->validate([
            'tipe_pesanan' => ['required', 'in:dine-in,takeaway'],
            'id_meja' => ['nullable', 'integer', 'exists:meja,id_meja', 'required_if:tipe_pesanan,dine-in'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'id_meja.required_if' => 'Nomor meja wajib dipilih untuk pesanan dine-in.',
        ]);

        $pengguna = $request->user();

        // Akun lama tanpa no. HP tak punya member — arahkan lengkapi dulu
        // agar order tidak menumpang baris member NULL milik orang lain.
        if (! $pengguna->nomor_telepon) {
            return redirect()->route('pelanggan.profil')
                ->with('error', 'Lengkapi nomor HP di profil dulu agar pesanan & poin tercatat atas nama Anda.');
        }

        // Pesanan atas nama akun pelanggan yang login (non-member tak dibutuhkan
        // karena semua pelanggan wajib punya akun).
        $pelanggan = Pelanggan::firstOrCreate(
            ['nomor_telepon' => $pengguna->nomor_telepon],
            ['nama_pelanggan' => $pengguna->nama_lengkap, 'poin_loyalitas' => 0],
        );

        // Voucher + poin dari Step 02 (divalidasi ulang di OrderController).
        $kodePromo = session('voucher_kode');
        if ($kodePromo) {
            $cek = Promo::where('kode_promo', $kodePromo)->first();
            if (! $cek || ! self::promoBerlaku($cek)) {
                return redirect()->route('pelanggan.keranjang.index')
                    ->with('error', 'Voucher kedaluwarsa, silakan pilih ulang.');
            }
        }

        // Delegasikan ke OrderController@store (validasi + hitung total + DB transaction).
        // Request baru harus membawa user resolver — jika tidak, OrderController
        // tidak bisa mengisi id_pengguna dan akan menolak pesanan.
        $innerRequest = new Request(array_merge($request->all(), [
            'id_pelanggan' => $pelanggan->id_pelanggan,
            'id_pengguna' => $pengguna->id_pengguna,
            'id_promo' => isset($cek) && $cek ? $cek->id_promo : null,
            'tukar_poin' => (bool) session('tukar_poin'),
            'items' => array_map(fn ($i) => [
                'id_produk' => $i['id_produk'],
                'jumlah' => $i['jumlah'],
                'harga_satuan' => $i['harga'],
                'catatan_item' => $i['opsi_text'] ?? null,
            ], $items),
        ]));
        $innerRequest->setUserResolver(fn () => $pengguna);

        $orderController = app(OrderController::class);

        // abort(422) dari store() melempar HttpException — tangkap agar user
        // kembali ke checkout dengan pesan ramah, bukan halaman error mentah.
        try {
            $response = $orderController->store($innerRequest);
        } catch (HttpException $e) {
            return redirect()->route('pelanggan.checkout')->with('error', $e->getMessage());
        }

        if ($response->status() !== 201) {
            $pesan = $response->getData(true)['message'] ?? 'Gagal membuat pesanan.';

            return redirect()->route('pelanggan.checkout')->with('error', $pesan);
        }

        $pesanan = $response->getData(true)['data'];

        session()->forget(['keranjang', 'voucher_kode', 'tukar_poin']);

        return redirect()
            ->route('pelanggan.status', $pesanan['nomor_pesanan'])
            ->with('success', 'Pesanan '.$pesanan['nomor_pesanan'].' berhasil dibuat!');
    }

    /**
     * Batalkan proses pemesanan sebelum pesanan dibuat:
     * kosongkan keranjang beserta voucher, poin, dan pilihan meja.
     */
    public function batalKeranjang(Request $request)
    {
        abort_unless($request->user()?->isPelanggan(), 403);

        session()->forget([
            'keranjang',
            'voucher_kode',
            'tukar_poin',
            'checkout_tipe',
            'checkout_meja',
        ]);

        return redirect()->route('pelanggan.menu')
            ->with('success', 'Pesanan dibatalkan. Keranjang sudah dikosongkan.');
    }

    /** Halaman status pesanan (timeline + status bayar) — hanya milik sendiri. */
    public function status(Request $request, string $nomorPesanan)
    {
        $pesanan = Pesanan::with('detailPesanan.produk', 'meja', 'transaksi.metodePembayaran')
            ->where('nomor_pesanan', $nomorPesanan)
            ->firstOrFail();

        abort_unless($this->milikSendiri($request->user(), $pesanan), 403, 'Bukan pesanan milik Anda.');

        return view('pelanggan.status', ['pesanan' => $pesanan]);
    }

    /**
     * Status ringan untuk polling real-time halaman pelanggan.
     * Tanpa detail item — hanya status pesanan & pembayaran.
     */
    public function statusLive(Request $request, Pesanan $pesanan): JsonResponse
    {
        abort_unless($this->milikSendiri($request->user(), $pesanan), 403, 'Bukan pesanan milik Anda.');

        $pesanan->loadMissing('transaksi');

        return response()
            ->json([
                'status_pesanan' => $pesanan->status_pesanan,
                'status_pembayaran' => $pesanan->transaksi?->status_pembayaran ?? 'menunggu',
                'siap_diambil' => $pesanan->status_pesanan === 'selesai',
                'diperbarui' => now()->toIso8601String(),
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Batalkan pesanan milik sendiri (hanya bila masih baru/diproses
     * dan belum lunas). Stok + poin dikembalikan otomatis.
     */
    public function batalkan(Request $request, Pesanan $pesanan)
    {
        $pengguna = $request->user();
        abort_unless($pengguna?->isPelanggan(), 403);

        if (! $this->milikSendiri($pengguna, $pesanan)) {
            abort(403, 'Bukan pesanan milik Anda.');
        }

        try {
            $pesanan->batalkan();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pelanggan.dashboard')
            ->with('success', 'Pesanan '.$pesanan->nomor_pesanan.' dibatalkan.');
    }

    /** Item keranjang dari session. */
    private function items(): array
    {
        return array_values(session('keranjang', []));
    }

    /**
     * Kepemilikan order: cocok via pembuat (id_pengguna) ATAU via member.
     * Dua jalur agar order lama (sebelum no. HP wajib) tetap dikenali.
     */
    private function milikSendiri($pengguna, Pesanan $pesanan): bool
    {
        if (! $pengguna) {
            return false;
        }

        if ((int) $pesanan->id_pengguna === (int) $pengguna->id_pengguna) {
            return true;
        }

        if ($pengguna->nomor_telepon) {
            $member = Pelanggan::where('nomor_telepon', $pengguna->nomor_telepon)->first();

            return $member && (int) $pesanan->id_pelanggan === (int) $member->id_pelanggan;
        }

        return false;
    }

    /** Total harga keranjang. */
    private function total(): float
    {
        return array_sum(array_map(
            fn ($i) => $i['harga'] * $i['jumlah'],
            $this->items(),
        ));
    }

    /** Jumlah total item di keranjang. */
    private function count(): int
    {
        return array_sum(array_column($this->items(), 'jumlah'));
    }
}
