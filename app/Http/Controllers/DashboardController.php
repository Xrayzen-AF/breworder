<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SettingController;
use App\Models\Kategori;
use App\Models\Meja;
use App\Models\MetodePembayaran;
use App\Models\Pelanggan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ReservasiMeja;
use App\Models\Transaksi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Midtrans\Config;
use Midtrans\Snap;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Dashboard fungsional per role sesuai wireframe:
 * - Barista: KDS 3 kolom (Baru / Diproses / Selesai) — S1-02 §C4
 * - Kasir:   POS (pesanan menunggu bayar / siap diambil) — S1-02 §C5
 * - Pelanggan: menu unggulan + riwayat pesanan — S1-02 §C1/C3
 */
class DashboardController extends Controller
{
    /**
     * KDS Barista — papan antrian + dispatch pickup ala Figma.
     */
    public function barista(Request $request)
    {
        $antrian = Pesanan::with(['detailPesanan.produk', 'meja', 'pelanggan', 'transaksi'])
            ->whereIn('status_pesanan', ['baru', 'diproses'])
            ->oldest('waktu_pesan')
            ->get();

        $selesaiList = Pesanan::with(['detailPesanan.produk', 'meja', 'pelanggan', 'transaksi'])
            ->where('status_pesanan', 'selesai')
            ->whereDate('waktu_pesan', today())
            ->latest('waktu_pesan')
            ->limit(6)
            ->get();

        // Sesi meja yang lewat waktu dilepas dulu agar nama pemesan lama
        // tidak tertinggal di papan.
        ReservasiMeja::bersihkanKedaluwarsa();

        $meja = Meja::withCount(['pesanan' => fn ($q) => $q->whereIn('status_pesanan', ['baru', 'diproses'])])
            ->with('reservasiAktif.pelanggan')
            ->orderBy('nomor_meja')
            ->get();

        return view('barista.dashboard', [
            'pesananBaru' => $antrian->where('status_pesanan', 'baru')->values(),
            'pesananDiproses' => $antrian->where('status_pesanan', 'diproses')->values(),
            'meja' => $meja,
            'statusMeja' => Meja::STATUS,
            'jumlahSelesaiHariIni' => Pesanan::where('status_pesanan', 'selesai')
                ->whereDate('waktu_pesan', today())->count(),
            'selesaiList' => $selesaiList,
            'pinned' => session('kds_pinned', []),
            'dismissed' => session('kds_dismissed', []),
            'filter' => $request->query('filter', 'all'),
        ]);
    }

    /**
     * Ubah status meja dari papan KDS barista.
     * Meja yang masih punya pesanan aktif (baru/diproses) tidak boleh
     * ditandai kosong/dipesan — statusnya dikunci "terisi" sampai pesanan
     * selesai atau dibatalkan agar papan kasir tetap konsisten.
     */
    public function updateMejaStatus(Request $request, Meja $meja): RedirectResponse
    {
        abort_unless($request->user()?->isBarista(), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', Meja::STATUS)],
        ], [
            'status.required' => 'Status meja wajib dipilih.',
            'status.in' => 'Status meja tidak dikenali.',
        ]);

        $aktif = $meja->pesanan()
            ->whereIn('status_pesanan', ['baru', 'diproses'])
            ->count();

        if ($aktif > 0 && $validated['status'] !== 'terisi') {
            return back()->with('error', "Meja {$meja->nomor_meja} masih punya {$aktif} pesanan aktif. Selesaikan atau batalkan dulu pesanannya.");
        }

        $meja->update(['status' => $validated['status']]);

        return back()->with('success', "Status meja {$meja->nomor_meja} diubah ke ".ucfirst($validated['status']).'.');
    }

    /**
     * Bump: sematkan tiket ke atas papan (toggle, tersimpan di session).
     */
    public function kdsPin(Request $request, Pesanan $pesanan)
    {
        abort_unless($request->user()?->isBarista(), 403);

        $pinned = session('kds_pinned', []);
        if (in_array($pesanan->id_pesanan, $pinned, true)) {
            $pinned = array_values(array_diff($pinned, [$pesanan->id_pesanan]));
        } else {
            $pinned[] = $pesanan->id_pesanan;
        }
        session(['kds_pinned' => $pinned]);

        return back();
    }

    /**
     * Dismiss: sembunyikan kartu pickup dari strip dispatch (session).
     */
    public function kdsDismiss(Request $request, Pesanan $pesanan)
    {
        abort_unless($request->user()?->isBarista(), 403);

        $dismissed = session('kds_dismissed', []);
        if (! in_array($pesanan->id_pesanan, $dismissed, true)) {
            $dismissed[] = $pesanan->id_pesanan;
        }
        session(['kds_dismissed' => $dismissed]);

        return back();
    }

    /**
     * POS Kasir — katalog menu + tiket builder + antrian pesanan.
     */
    public function kasir(Request $request)
    {
        // Tombol "New Ticket" di topbar: buang draf tiket lalu muat ulang bersih.
        if ($request->query('new')) {
            session()->forget('kasir_ticket');

            return redirect()->route('kasir.dashboard');
        }

        $pesananAktif = Pesanan::with(['detailPesanan.produk', 'meja', 'pelanggan', 'transaksi'])
            ->whereIn('status_pesanan', ['baru', 'diproses'])
            ->latest('waktu_pesan')
            ->get();

        $selesaiHariIni = Pesanan::where('status_pesanan', 'selesai')
            ->whereDate('waktu_pesan', today());

        // Pesanan selesai hari ini — dipakai kasir untuk cetak ulang struk.
        // `selesai_cari` memfilter daftar ini saja, tanpa mengganggu tiket aktif.
        $cariSelesai = trim((string) $request->query('selesai_cari', ''));

        $selesaiList = (clone $selesaiHariIni)
            ->with(['meja', 'pelanggan', 'transaksi.metodePembayaran'])
            ->when($cariSelesai !== '', fn ($q) => $q->where('nomor_pesanan', 'like', "%{$cariSelesai}%"))
            ->latest('waktu_pesan')
            ->limit(25)
            ->get();

        $omzetHariIni = (float) Transaksi::where('status_pembayaran', 'lunas')
            ->whereDate('waktu_pembayaran', today())
            ->sum('total_bayar');

        // Katalog untuk grid POS (filter kategori + pencarian nama).
        $kategoriAktif = $request->query('kategori');
        $cari = trim((string) $request->query('cari', ''));

        $kategori = Kategori::orderBy('nama_kategori')->get();

        $produk = Produk::with('kategori:id_kategori,nama_kategori')
            ->where('status_tersedia', true)
            ->when($kategoriAktif, fn ($q) => $q->whereHas('kategori',
                fn ($k) => $k->where('nama_kategori', $kategoriAktif)))
            ->when($cari !== '', fn ($q) => $q->where('nama_produk', 'like', "%{$cari}%"))
            ->orderBy('nama_produk')
            ->get();

        $mejaBoard = $this->mejaSnapshot();
        $mejaTersedia = collect($mejaBoard['tables']);

        // Draf tiket kasir (session) + totalnya.
        $tiket = array_values(session('kasir_ticket', []));
        $totalTiket = array_sum(array_map(
            fn ($i) => $i['harga'] * $i['jumlah'], $tiket));

        // Tiket antrian yang dipilih di panel kanan (query ?ticket=id atau ?lookup=nomor).
        $tiketTerpilih = null;
        $lookup = trim((string) $request->query('lookup', ''));
        if ($lookup !== '') {
            $tiketTerpilih = $pesananAktif->first(
                fn ($p) => stripos($p->nomor_pesanan, $lookup) !== false);
        } elseif ($request->query('ticket')) {
            $tiketTerpilih = $pesananAktif->firstWhere(
                'id_pesanan', (int) $request->query('ticket'));
        }
        $tiketTerpilih ??= $pesananAktif->first();

        return view('kasir.dashboard', [
            'pesananAktif' => $pesananAktif,
            'selesaiList' => $selesaiList,
            'cariSelesai' => $cariSelesai,
            'jumlahSelesaiHariIni' => (clone $selesaiHariIni)->count(),
            'omzetHariIni' => $omzetHariIni,
            'biayaLayanan' => (float) (SettingController::load()['biaya_layanan'] ?? 0),
            'produk' => $produk,
            'kategori' => $kategori,
            'kategoriAktif' => $kategoriAktif,
            'cari' => $cari,
            'lookup' => $lookup,
            'mejaTersedia' => $mejaTersedia,
            'mejaBoard' => $mejaBoard,
            'tiket' => $tiket,
            'totalTiket' => $totalTiket,
            'tiketTerpilih' => $tiketTerpilih,
            'metodeList' => MetodePembayaran::orderBy('id_metode')->get(),
        ]);
    }

    /**
     * Status meja realtime untuk papan pemilihan meja di POS.
     * Kolom `meja.status` adalah sumber kebenaran (termasuk status yang
     * diatur manual oleh barista), kecuali meja yang masih punya pesanan
     * aktif — selalu dihitung `terisi` agar tidak pernah tampil kosong.
     */
    public function mejaStatus(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isKasir(), 403);

        return response()
            ->json($this->mejaSnapshot())
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Status meja realtime untuk halaman pelanggan (dipolling dari panel
     * "Status Meja" di beranda). Tanpa detail pesanan — pelanggan hanya
     * perlu tahu meja mana yang kosong/terisi/dipesan.
     */
    public function mejaStatusPelanggan(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isPelanggan(), 403);

        return response()
            ->json($this->mejaSnapshot(false))
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * @param  bool  $denganDetail  sertakan nomor/total pesanan aktif (khusus staf)
     * @return array{tables: array<int, array<string, mixed>>, counts: array<string, int>, updated_at: string}
     */
    private function mejaSnapshot(bool $denganDetail = true): array
    {
        // Sesi meja yang lewat waktu dilepas di sini agar papan status
        // (dipolling tiap 8 detik) tidak menampilkan meja "terisi" selamanya.
        ReservasiMeja::bersihkanKedaluwarsa();

        $tables = Meja::query()->orderBy('nomor_meja')->get();

        $activeOrders = Pesanan::with('transaksi')
            ->whereIn('status_pesanan', ['baru', 'diproses'])
            ->whereNotNull('id_meja')
            ->oldest('waktu_pesan')
            ->get()
            ->groupBy('id_meja');

        $rows = $tables->map(function (Meja $meja) use ($activeOrders, $denganDetail) {
            $orders = $activeOrders->get($meja->id_meja, collect());

            // Pesanan aktif selalu menang (meja terpakai tidak boleh tampil
            // kosong). Selain itu ikuti kolom `meja.status` apa adanya supaya
            // status kosong/terisi/dipesan yang diatur barista terlihat sama
            // persis oleh pelanggan.
            $status = $orders->isNotEmpty()
                ? 'terisi'
                : (in_array($meja->status, Meja::STATUS, true) ? $meja->status : 'kosong');

            return [
                'id' => $meja->id_meja,
                'nomor' => $meja->nomor_meja,
                'kapasitas' => (int) $meja->kapasitas,
                'status' => $status,
                'label' => ucfirst($status),
                'aktif' => $orders->count(),
                'orders' => $denganDetail
                    ? $orders->map(fn (Pesanan $pesanan) => [
                        'nomor' => $pesanan->nomor_pesanan,
                        'status' => $pesanan->status_pesanan,
                        'total' => (float) $pesanan->total_harga,
                        'paid' => $pesanan->transaksi?->status_pembayaran === 'lunas',
                        'waktu' => $pesanan->waktu_pesan?->format('H:i'),
                        'since_minutes' => $pesanan->waktu_pesan
                            ? max(0, (int) $pesanan->waktu_pesan->diffInMinutes(now()))
                            : 0,
                    ])->values()->all()
                    : [],
            ];
        })->values();

        $counts = ['kosong' => 0, 'terisi' => 0, 'dipesan' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']]++;
        }

        return [
            'tables' => $rows->all(),
            'counts' => $counts + ['total' => $rows->count()],
            'updated_at' => now()->format('H:i:s'),
        ];
    }

    /**
     * Tambah 1 item ke draf tiket kasir (session `kasir_ticket`).
     */
    public function ticketTambah(Request $request, Produk $produk)
    {
        abort_unless($request->user()?->isKasir(), 403);

        if (! $produk->status_tersedia || $produk->stok <= 0) {
            return back()->with('error', 'Menu '.$produk->nama_produk.' sedang habis.');
        }

        $tiket = session('kasir_ticket', []);
        $id = $produk->id_produk;
        $sudahAda = $tiket[$id]['jumlah'] ?? 0;

        if ($sudahAda + 1 > $produk->stok) {
            return back()->with('error', 'Stok '.$produk->nama_produk.' tidak mencukupi.');
        }

        $tiket[$id] = [
            'id_produk' => $id,
            'nama' => $produk->nama_produk,
            'harga' => (float) $produk->harga,
            'jumlah' => $sudahAda + 1,
        ];
        session(['kasir_ticket' => $tiket]);

        return back()->with('success', $produk->nama_produk.' masuk ke tiket.');
    }

    /**
     * Ubah jumlah item di draf tiket kasir (0 = hapus baris).
     */
    public function ticketUpdate(Request $request, int $idProduk)
    {
        abort_unless($request->user()?->isKasir(), 403);

        $validated = $request->validate([
            'jumlah' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $tiket = session('kasir_ticket', []);

        if (! isset($tiket[$idProduk])) {
            return back()->with('error', 'Item tidak ada di tiket.');
        }

        if ((int) $validated['jumlah'] === 0) {
            unset($tiket[$idProduk]);
        } else {
            $produk = Produk::find($idProduk);
            if ($produk && $validated['jumlah'] > $produk->stok) {
                return back()->with('error', 'Stok '.$produk->nama_produk.' tidak mencukupi.');
            }
            $tiket[$idProduk]['jumlah'] = (int) $validated['jumlah'];
        }

        session(['kasir_ticket' => $tiket]);

        return back()->with('success', 'Tiket diperbarui.');
    }

    /**
     * Buat pesanan dari draf tiket kasir (Charge) via OrderController@store.
     * Stok dikurangi atomik di dalam OrderController (jangan decrement lagi
     * di sini). Status awal `baru` + transaksi `menunggu` — pelunasan
     * terpisah via tandaiLunas agar barista sempat memasak.
     */
    public function ticketBuat(Request $request)
    {
        abort_unless($request->user()?->isKasir(), 403);

        $items = array_values(session('kasir_ticket', []));
        if (empty($items)) {
            return back()->with('error', 'Tiket masih kosong.');
        }

        $validated = $request->validate([
            'tipe_pesanan' => ['required', 'in:dine-in,takeaway'],
            'id_meja' => ['nullable', 'integer', 'exists:meja,id_meja', 'required_if:tipe_pesanan,dine-in'],
            'metode' => ['nullable', 'in:cash,qris,transfer'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'nama_pelanggan' => ['nullable', 'string', 'max:255'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
        ], [
            'id_meja.required_if' => 'Pilih meja untuk pesanan yang dimakan di tempat.',
        ]);

        // Cek stok ulang sebelum membuat pesanan.
        foreach ($items as $i) {
            $produk = Produk::where('status_tersedia', true)->find($i['id_produk']);
            if (! $produk) {
                return back()->with('error', $i['nama'].' sudah tidak tersedia.');
            }
            if ($i['jumlah'] > $produk->stok) {
                return back()->with('error', 'Stok '.$produk->nama_produk.' tidak mencukupi.');
            }
        }

        $pengguna = $request->user();
        $metode = $validated['metode'] ?? 'cash';
        unset($validated['metode']);

        $idMetode = MetodePembayaran::query()
            ->where('kode_metode', strtoupper($metode))
            ->value('id_metode');

        $innerRequest = new Request(array_merge($validated, [
            'id_pengguna' => $pengguna->id_pengguna,
            'id_metode' => $idMetode,
            'items' => array_map(fn ($i) => [
                'id_produk' => $i['id_produk'],
                'jumlah' => $i['jumlah'],
            ], $items),
        ]));
        $innerRequest->setUserResolver(fn () => $pengguna);

        $response = app(OrderController::class)->store($innerRequest);

        if ($response->status() !== 201) {
            $pesan = $response->getData(true)['message'] ?? 'Gagal membuat tiket.';

            return back()->with('error', $pesan);
        }

        $pesanan = $response->getData(true)['data'];

        session()->forget('kasir_ticket');

        $tujuan = ['ticket' => $pesanan['id_pesanan']];
        if (in_array($metode, ['qris', 'transfer'], true)) {
            $tujuan['bayar'] = $metode;
        }

        return redirect()
            ->route('kasir.dashboard', $tujuan)
            ->with('success', 'Pesanan '.$pesanan['nomor_pesanan'].' dibuat dan masuk ke dapur.');
    }

    /**
     * Portal Pelanggan — beranda: menu unggulan + status pesanan berjalan.
     * Riwayat pesanan punya halaman sendiri (lihat method riwayat()).
     */
    public function pelanggan(Request $request)
    {
        $pengguna = $request->user();

        $dataPelanggan = $this->dataPelanggan($pengguna);

        // Pesanan HANYA milik sendiri: via member + via order mandiri.
        // Tanpa keduanya = kosong (jangan tampilkan order orang lain).
        $terakhir = $this->pesananMilikSendiri($pengguna, $dataPelanggan)
            ->latest('waktu_pesan')
            ->limit(10)
            ->get();

        $produkUnggulan = Produk::with('kategori:id_kategori,nama_kategori')
            ->where('status_tersedia', true)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        $kategori = Kategori::orderBy('nama_kategori')->get();

        // Data member untuk kartu Velvet Club (poin, stamp dari pesanan selesai).
        $selesaiCount = 0;
        if ($dataPelanggan) {
            $selesaiCount = Pesanan::where('id_pelanggan', $dataPelanggan->id_pelanggan)
                ->where('status_pesanan', 'selesai')
                ->count();
        }
        $poin = (int) ($dataPelanggan->poin_loyalitas ?? 0);
        $tier = $poin >= 1000 ? 'Gold' : ($poin >= 500 ? 'Silver' : 'Bronze');

        $pesananAktif = $terakhir->first(
            fn ($p) => in_array($p->status_pesanan, ['baru', 'diproses'], true));

        return view('pelanggan.dashboard', [
            'produkUnggulan' => $produkUnggulan,
            'kategori' => $kategori,
            'member' => $dataPelanggan,
            'poin' => $poin,
            'tier' => $tier,
            'selesaiCount' => $selesaiCount,
            'pesananAktif' => $pesananAktif,
            'mejaBoard' => $this->mejaSnapshot(false),
        ]);
    }

    /**
     * Riwayat pesanan pelanggan di halaman terpisah (bukan di beranda).
     */
    public function riwayat(Request $request)
    {
        $pengguna = $request->user();
        $dataPelanggan = $this->dataPelanggan($pengguna);

        return view('pelanggan.riwayat', [
            'riwayatPesanan' => $this->pesananMilikSendiri($pengguna, $dataPelanggan)
                ->latest('waktu_pesan')
                ->paginate(10)
                ->withQueryString(),
            'member' => $dataPelanggan,
        ]);
    }

    /** Data member pelanggan berdasarkan nomor telepon akun (boleh null). */
    private function dataPelanggan(Pengguna $pengguna): ?Pelanggan
    {
        return $pengguna->nomor_telepon
            ? Pelanggan::where('nomor_telepon', $pengguna->nomor_telepon)->first()
            : null;
    }

    /** Query dasar pesanan milik pengguna + member yang tertaut nomor teleponnya. */
    private function pesananMilikSendiri(Pengguna $pengguna, ?Pelanggan $dataPelanggan)
    {
        return Pesanan::with('detailPesanan.produk', 'transaksi')
            ->where(function ($q) use ($pengguna, $dataPelanggan) {
                $q->where('id_pengguna', $pengguna->id_pengguna);
                if ($dataPelanggan) {
                    $q->orWhere('id_pelanggan', $dataPelanggan->id_pelanggan);
                }
            });
    }

    /**
     * Ubah status pesanan (barista/kasir) via state machine — web form.
     * - baru -> diproses (barista/kasir)
     * - diproses -> selesai (mensyaratkan lunas)
     * - baru/diproses -> dibatalkan (kembalikan stok + poin)
     */
    public function updateStatus(Request $request, Pesanan $pesanan)
    {
        abort_unless($request->user()?->isKasir() || $request->user()?->isBarista(), 403);
        $validated = $request->validate([
            'status_pesanan' => ['required', 'in:baru,diproses,selesai,dibatalkan'],
        ]);

        $tujuan = $validated['status_pesanan'];

        if ($tujuan === 'dibatalkan') {
            try {
                $pesanan->batalkan();
            } catch (\Throwable $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', 'Pesanan '.$pesanan->nomor_pesanan.' dibatalkan, stok dikembalikan.');
        }

        if ($pesanan->isTerminal()) {
            return back()->with('error', 'Pesanan terminal tidak dapat diubah.');
        }

        if ($tujuan === 'selesai') {
            $pesanan->loadMissing('transaksi');
            if (! $pesanan->transaksi || $pesanan->transaksi->status_pembayaran !== 'lunas') {
                return back()->with('error', 'Pesanan belum dibayar. Lunasi dulu di kasir sebelum serah terima.');
            }

            // Barista tetap harus memproses pesanan terlebih dahulu sebelum menandai selesai
            if ($request->user()?->isBarista() && $pesanan->status_pesanan === 'baru') {
                return back()->with('error', 'Pesanan baru harus diproses terlebih dahulu sebelum ditandai selesai.');
            }

            $pesanan->update(['status_pesanan' => 'selesai']);
            Pesanan::sinkronMeja($pesanan->id_meja);

            return back()->with('success', 'Pesanan '.$pesanan->nomor_pesanan.' selesai diserahterimakan.');
        }

        if (! Pesanan::bolehTransisi($pesanan->status_pesanan, $tujuan)) {
            return back()->with('error', 'Transisi '.$pesanan->status_pesanan.' → '.$tujuan.' tidak diizinkan.');
        }

        $pesanan->update(['status_pesanan' => $tujuan]);

        return back()->with('success', 'Pesanan '.$pesanan->nomor_pesanan.' → '.$validated['status_pesanan'].'.');
    }

    /**
     * Lunasi tagihan pesanan (kasir only) — tunai / transfer.
     * - Tunai: input uang diterima, kembalian dihitung otomatis.
     * - Transfer: nominal pas, wajib nomor referensi.
     * - Member dapat 1 poin tiap Rp 10.000 bersih.
     * - TIDAK mengubah status pesanan (serah terima terpisah),
     *   agar pesanan bayar-di-depan tetap dimasak barista.
     */
    public function tandaiLunas(Request $request, Pesanan $pesanan)
    {
        abort_unless($request->user()?->isKasir(), 403);

        $validated = $request->validate([
            'id_metode' => ['nullable', 'integer', 'exists:metode_pembayaran,id_metode'],
            'jumlah_bayar' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'nomor_referensi' => ['nullable', 'string', 'max:100'],
            'bank_atau_ewallet' => ['nullable', 'string', 'max:100'],
        ]);

        $tagihan = (float) $pesanan->total_harga;

        try {
            $hasil = DB::transaction(function () use ($pesanan, $validated, $tagihan) {
                $trx = $pesanan->transaksi()->lockForUpdate()->first();

                if ($trx && $trx->status_pembayaran === 'lunas') {
                    abort(422, 'Pesanan ini sudah lunas.');
                }

                $metode = isset($validated['id_metode'])
                    ? MetodePembayaran::find($validated['id_metode'])
                    : MetodePembayaran::where('kode_metode', 'CASH')->first()
                        ?? MetodePembayaran::orderBy('id_metode')->first();

                if (! $metode) {
                    $metode = MetodePembayaran::create([
                        'kode_metode' => 'CASH',
                        'nama_metode' => 'Tunai',
                        'ada_kembalian' => true,
                    ]);
                }

                if ($metode->ada_kembalian) {
                    // TUNAI: wajib uang >= tagihan.
                    $bayar = (float) ($validated['jumlah_bayar'] ?? $tagihan);
                    if ($bayar < $tagihan) {
                        abort(422, 'Uang diterima kurang Rp '.number_format($tagihan - $bayar, 0, ',', '.').'.');
                    }
                    $kembalian = $bayar - $tagihan;
                    $referensi = $validated['nomor_referensi'] ?? null;
                    $bank = $validated['bank_atau_ewallet'] ?? null;
                } else {
                    // TRANSFER/QRIS: nominal pas, wajib bukti referensi.
                    $bayar = (float) ($validated['jumlah_bayar'] ?? $tagihan);
                    if (abs($bayar - $tagihan) > 0.01) {
                        abort(422, 'Nominal transfer harus pas Rp '.number_format($tagihan, 0, ',', '.').'.');
                    }
                    if (empty($validated['nomor_referensi'])) {
                        abort(422, 'Nomor referensi transfer wajib diisi.');
                    }
                    $kembalian = 0;
                    $referensi = $validated['nomor_referensi'];
                    $bank = $validated['bank_atau_ewallet'] ?? $metode->nama_metode;
                }

                $payload = [
                    'id_metode' => $metode->id_metode,
                    'subtotal' => $tagihan,
                    'total_bayar' => $bayar,
                    'kembalian' => $kembalian,
                    'bank_atau_ewallet' => $bank,
                    'nomor_referensi' => $referensi,
                    'status_pembayaran' => 'lunas',
                    'waktu_pembayaran' => now(),
                ];

                if ($trx) {
                    $trx->update($payload);
                } else {
                    $trx = Transaksi::create(['id_pesanan' => $pesanan->id_pesanan] + $payload);
                }

                // Earn loyalty: 1 poin / Rp 10.000.
                $poinBaru = 0;
                if ($pesanan->id_pelanggan && $tagihan > 0) {
                    $poinBaru = (int) floor($tagihan / Pesanan::RUPIAH_PER_POIN);
                    if ($poinBaru > 0) {
                        Pelanggan::where('id_pelanggan', $pesanan->id_pelanggan)
                            ->increment('poin_loyalitas', $poinBaru);
                    }
                }

                return ['kembalian' => $kembalian, 'poin' => $poinBaru, 'bayar' => $bayar];
            });
        } catch (\Throwable $e) {
            $kode = $e instanceof HttpException
                ? $e->getStatusCode()
                : 422;

            if ($kode === 422 || $e instanceof ValidationException) {
                return back()->with('error', $e->getMessage());
            }

            throw $e;
        }

        $pesan = 'Pembayaran '.$pesanan->nomor_pesanan.' lunas (Rp '.number_format($hasil['bayar'], 0, ',', '.').')';
        if ($hasil['kembalian'] > 0) {
            $pesan .= ', kembalian Rp '.number_format($hasil['kembalian'], 0, ',', '.');
        }
        if ($hasil['poin'] > 0) {
            $pesan .= ', +'.$hasil['poin'].' poin.';
        } else {
            $pesan .= '.';
        }

        return back()->with('success', $pesan);
    }

    /**
     * Minta Snap Token Midtrans untuk pesanan aktif (kasir / admin only).
     */
    public function snapToken(Request $request, Pesanan $pesanan): JsonResponse
    {
        abort_unless($request->user()?->isKasir() || $request->user()?->isAdmin(), 403);

        $pesanan->loadMissing(['pelanggan', 'transaksi', 'detailPesanan.produk']);

        if ($pesanan->transaksi && $pesanan->transaksi->status_pembayaran === 'lunas') {
            return response()->json([
                'status' => 'error',
                'message' => 'Pesanan ini sudah lunas.',
            ], 422);
        }

        $serverKey = config('services.midtrans.server_key');
        if (empty($serverKey) || $serverKey === 'SB-Mid-server-xxxxxxxxxxxx') {
            return response()->json([
                'status' => 'error',
                'message' => 'Kredensial Midtrans Server Key belum diatur di file .env!',
            ], 500);
        }

        Config::$serverKey = $serverKey;
        Config::$isProduction = (bool) config('services.midtrans.is_production', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $grossAmount = (int) round((float) $pesanan->total_harga);

        if ($grossAmount <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Total tagihan pesanan harus lebih dari 0.',
            ], 422);
        }

        $params = [
            'transaction_details' => [
                'order_id' => $pesanan->nomor_pesanan.'-'.time(),
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $pesanan->pelanggan?->nama_pelanggan ?? 'Pelanggan Kasir',
                'phone' => $pesanan->pelanggan?->nomor_telepon ?? null,
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            return response()->json([
                'status' => 'success',
                'snap_token' => $snapToken,
                'order_id' => $pesanan->nomor_pesanan,
                'total_amount' => $grossAmount,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal meminta Snap Token: '.$e->getMessage(),
            ], 500);
        }
    }
}
