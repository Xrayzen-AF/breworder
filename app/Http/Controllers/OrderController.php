<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SettingController;
use App\Models\MetodePembayaran;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Promo;
use App\Models\Transaksi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderController extends Controller
{
    /**
     * Daftar semua pesanan (untuk kasir/barista).
     */
    public function index(Request $request): JsonResponse
    {
        $pesanan = Pesanan::with('detailPesanan')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status_pesanan', $status))
            ->latest('waktu_pesan')
            ->get();

        return response()->json(['data' => $pesanan]);
    }

    /**
     * Buat pesanan baru: validasi item, cek + kunci stok, hitung total,
     * simpan dalam 1 transaksi DB. Stok dikurangi ATOMIK di dalam
     * transaction (anti race-condition), bukan di controller pemanggil.
     *
     * - `id_pengguna` = pembuat pesanan (kasir untuk tiket POS, pelanggan ybs
     *   untuk order mandiri); jika tidak dikirim, dipakai pengguna
     *   yang sedang login (fallback).
     * - `id_pelanggan` boleh NULL untuk non-member; jika hanya dikirim
     *   `nama_pelanggan`, data pelanggan dibuat/dipakai ulang otomatis.
     * - ANTI-SPOOFING: bila pembuatnya pelanggan, kiriman `id_pengguna` /
     *   `id_pelanggan` / `nama_pelanggan` dari client DIABAIKAN — order selalu
     *   atas nama sendiri (poin korban tak bisa dipakai orang lain).
     * - `id_meja` boleh NULL untuk takeaway/delivery.
     * - Setiap pesanan langsung dibuatkan baris `transaksi` berstatus
     *   `menunggu` (dilunasi kasir via tandaiLunas).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_pengguna' => ['nullable', 'integer', 'exists:pengguna,id_pengguna'],
            'id_pelanggan' => ['nullable', 'integer', 'exists:pelanggan,id_pelanggan'],
            'nama_pelanggan' => ['nullable', 'string', 'max:255'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'id_meja' => ['nullable', 'integer', 'exists:meja,id_meja', 'required_if:tipe_pesanan,dine-in'],
            'id_promo' => ['nullable', 'integer', 'exists:promo,id_promo'],
            'id_metode' => ['nullable', 'integer', 'exists:metode_pembayaran,id_metode'],
            'tipe_pesanan' => ['required', Rule::in(Pesanan::TIPE_PESANAN)],
            'catatan' => ['nullable', 'string', 'max:500'],
            'tukar_poin' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_produk' => ['required', 'integer', 'exists:produk,id_produk'],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.catatan_item' => ['nullable', 'string', 'max:255'],
        ]);

        $pembuat = $request->user();

        // Anti-spoofing: pelanggan hanya boleh order atas dirinya sendiri.
        // Staf (kasir/barista) tetap boleh mengisi id_pelanggan walk-in.
        if ($pembuat?->isPelanggan()) {
            $validated['id_pengguna'] = $pembuat->id_pengguna;
            unset($validated['nama_pelanggan'], $validated['nomor_telepon']);

            if (! $pembuat->nomor_telepon) {
                abort(422, 'Lengkapi nomor HP di profil dulu agar pesanan tercatat atas nama Anda.');
            }

            $validated['id_pelanggan'] = Pelanggan::firstOrCreate(
                ['nomor_telepon' => $pembuat->nomor_telepon],
                ['nama_pelanggan' => $pembuat->nama_lengkap, 'poin_loyalitas' => 0],
            )->id_pelanggan;
        }

        $idPengguna = $validated['id_pengguna'] ?? $request->user()?->id_pengguna;

        if (! $idPengguna) {
            abort(422, 'Pesanan wajib memiliki kasir yang menangani (id_pengguna).');
        }

        $pesanan = DB::transaction(function () use ($validated, $idPengguna) {
            $dibutuhkan = collect($validated['items'])
                ->groupBy('id_produk')
                ->map(fn ($rows) => $rows->sum('jumlah'));

            $produk = Produk::where('status_tersedia', true)
                ->lockForUpdate()
                ->find($dibutuhkan->keys()->all());

            if ($produk->count() !== $dibutuhkan->count()) {
                abort(422, 'Ada produk yang tidak tersedia atau tidak valid.');
            }

            // Cek kecukupan stok SEBELUM membuat pesanan (atomik via lock).
            foreach ($produk as $p) {
                if ($p->stok < $dibutuhkan[$p->id_produk]) {
                    abort(422, 'Stok '.$p->nama_produk.' tidak mencukupi (sisa '.$p->stok.').');
                }
            }

            // Non-member: buatkan data pelanggan dari nama & nomor telepon.
            $idPelanggan = $validated['id_pelanggan'] ?? null;

            if (! $idPelanggan && ! empty($validated['nama_pelanggan'])) {
                $idPelanggan = Pelanggan::firstOrCreate(
                    [
                        'nama_pelanggan' => $validated['nama_pelanggan'],
                        'nomor_telepon' => $validated['nomor_telepon'] ?? null,
                    ],
                    ['poin_loyalitas' => 0],
                )->id_pelanggan;
            }

            $pesanan = Pesanan::create([
                'nomor_pesanan' => Pesanan::generateNomorPesanan(),
                'id_pengguna' => $idPengguna,
                'id_pelanggan' => $idPelanggan,
                'id_meja' => $validated['id_meja'] ?? null,
                'id_promo' => $validated['id_promo'] ?? null,
                'tipe_pesanan' => $validated['tipe_pesanan'],
                'status_pesanan' => 'baru',
                'total_harga' => 0,
                'diskon' => 0,
                'poin_dipakai' => 0,
                'catatan' => $validated['catatan'] ?? null,
            ]);

            $total = 0;

            foreach ($validated['items'] as $item) {
                $produkItem = $produk->firstWhere('id_produk', $item['id_produk']);
                // Harga kustom (mis. add-on kustomisasi) bila dikirim, else harga dasar.
                // Floor: harga kiriman tak boleh di bawah harga dasar (anti order Rp1).
                $hargaSatuan = isset($item['harga_satuan'])
                    ? (string) $item['harga_satuan']
                    : (string) $produkItem->harga;
                if ((float) $hargaSatuan < (float) $produkItem->harga) {
                    abort(422, 'Harga satuan '.$produkItem->nama_produk.' tidak valid.');
                }
                $subtotal = bcmul($hargaSatuan, (string) $item['jumlah'], 2);

                $pesanan->detailPesanan()->create([
                    'id_produk' => $produkItem->id_produk,
                    'jumlah' => $item['jumlah'],
                    'harga_satuan' => $hargaSatuan,
                    'subtotal' => $subtotal,
                    'catatan_item' => $item['catatan_item'] ?? null,
                ]);

                $total = bcadd($total, $subtotal, 2);
            }

            // Kurangi stok ATOMIK di dalam transaction yang sama.
            foreach ($dibutuhkan as $idProduk => $jumlah) {
                Produk::where('id_produk', $idProduk)->decrement('stok', $jumlah);
            }

            $pesanan->update(['total_harga' => $total]);

            // Potongan voucher: persen dari total item, atau nominal (maks. total).
            $diskon = 0;
            if (! empty($validated['id_promo'])) {
                $promo = Promo::find($validated['id_promo']);
                if (! $promo || ! $promo->status_aktif
                    || ($promo->berlaku_mulai && now()->lt($promo->berlaku_mulai))
                    || ($promo->berlaku_sampai && now()->gt($promo->berlaku_sampai))) {
                    abort(422, 'Promo tidak valid atau kedaluwarsa.');
                }
                $diskonPromo = $promo->tipe_diskon === 'persen'
                    ? round((float) $total * (float) $promo->nilai_diskon / 100, 2)
                    : min((float) $promo->nilai_diskon, (float) $total);
                $diskon = bcadd((string) $diskon, (string) $diskonPromo, 2);
                $total = bcsub((string) $total, (string) $diskonPromo, 2);
            }

            // Tukar Bean Points: 1 pts = Rp 10, maks. senilai sisa total.
            $poinPakai = 0;
            if (! empty($validated['tukar_poin']) && $idPelanggan) {
                $member = Pelanggan::lockForUpdate()->find($idPelanggan);
                if ($member && (int) $member->poin_loyalitas > 0) {
                    $pakai = min((int) $member->poin_loyalitas, (int) floor((float) $total / 10));
                    if ($pakai > 0) {
                        $member->decrement('poin_loyalitas', $pakai);
                        $poinPakai = $pakai;
                        $diskonPoin = $pakai * 10;
                        $diskon = bcadd((string) $diskon, (string) $diskonPoin, 2);
                        $total = bcsub((string) $total, (string) $diskonPoin, 2);
                    }
                }
            }

            // Biaya layanan flat per pesanan (diatur di Store Settings).
            $biayaLayanan = max(0, (float) (SettingController::load()['biaya_layanan'] ?? 0));
            $totalBersih = max(0, (float) $total) + $biayaLayanan;
            $pesanan->update([
                'total_harga' => $totalBersih,
                'diskon' => (float) $diskon,
                'biaya_layanan' => $biayaLayanan,
                'poin_dipakai' => $poinPakai,
            ]);

            // Buat tagihan menunggu: dilunasi kasir (tunai/transfer).
            // subtotal = tagihan bersih, total_bayar = 0 sampai lunas.
            $idMetode = $validated['id_metode'] ?? MetodePembayaran::query()
                ->where('kode_metode', 'CASH')
                ->value('id_metode');

            if (! $idMetode) {
                $idMetode = MetodePembayaran::query()->orderBy('id_metode')->value('id_metode');
            }

            if (! $idMetode) {
                $idMetode = MetodePembayaran::create([
                    'kode_metode' => 'CASH',
                    'nama_metode' => 'Tunai',
                    'ada_kembalian' => true,
                ])->id_metode;
            }

            Transaksi::create([
                'id_pesanan' => $pesanan->id_pesanan,
                'id_metode' => $idMetode,
                'subtotal' => $totalBersih,
                'total_bayar' => 0,
                'kembalian' => 0,
                'status_pembayaran' => 'menunggu',
                'waktu_pembayaran' => null,
            ]);

            Pesanan::sinkronMeja($pesanan->id_meja);

            return $pesanan->fresh('detailPesanan');
        });

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $pesanan->load('detailPesanan', 'transaksi'),
        ], 201);
    }

    /**
     * Detail pesanan berdasarkan nomor pesanan.
     * Wajib login; pelanggan hanya boleh melihat miliknya sendiri,
     * staf (kasir/barista/admin) boleh melihat semua.
     */
    public function showByNumber(Request $request, string $nomorPesanan): JsonResponse
    {
        $pesanan = Pesanan::with('detailPesanan')
            ->where('nomor_pesanan', $nomorPesanan)
            ->firstOrFail();

        $user = $request->user();
        $isStaf = $user && in_array($user->peran, ['kasir', 'barista', 'admin'], true);
        $milikSendiri = $user && (int) $pesanan->id_pengguna === (int) $user->id_pengguna;

        if (! $isStaf && ! $milikSendiri) {
            abort(403, 'Bukan pesanan milik Anda.');
        }

        return response()->json(['data' => $pesanan]);
    }

    /**
     * Ubah status pesanan via state machine:
     * baru -> diproses/dibatalkan, diproses -> selesai/dibatalkan.
     * Selesai mensyaratkan transaksi sudah lunas.
     */
    public function updateStatus(Request $request, Pesanan $pesanan): JsonResponse
    {
        $validated = $request->validate([
            'status_pesanan' => ['required', Rule::in(Pesanan::STATUS_PESANAN)],
        ]);

        $tujuan = $validated['status_pesanan'];

        if ($tujuan === 'dibatalkan') {
            return $this->cancel($pesanan);
        }

        if ($pesanan->isTerminal()) {
            return response()->json(['message' => 'Pesanan terminal tidak dapat diubah.'], 422);
        }

        if ($tujuan === 'selesai') {
            $pesanan->loadMissing('transaksi');
            if (! $pesanan->transaksi || $pesanan->transaksi->status_pembayaran !== 'lunas') {
                return response()->json(['message' => 'Pesanan belum dibayar. Lunasi dulu di kasir.'], 422);
            }

            if ($request->user()?->isBarista() && $pesanan->status_pesanan === 'baru') {
                return response()->json([
                    'message' => 'Transisi '.$pesanan->status_pesanan.' → '.$tujuan.' tidak diizinkan.',
                ], 422);
            }

            $pesanan->update(['status_pesanan' => 'selesai']);
            Pesanan::sinkronMeja($pesanan->id_meja);

            return response()->json([
                'message' => 'Status pesanan diperbarui.',
                'data' => $pesanan->load('detailPesanan', 'transaksi'),
            ]);
        }

        if (! Pesanan::bolehTransisi($pesanan->status_pesanan, $tujuan)) {
            return response()->json([
                'message' => 'Transisi '.$pesanan->status_pesanan.' → '.$tujuan.' tidak diizinkan.',
            ], 422);
        }

        return response()->json([
            'message' => 'Status pesanan diperbarui.',
            'data' => $pesanan->load('detailPesanan'),
        ]);
    }

    /**
     * Batalkan pesanan: kembalikan stok + poin, tandai transaksi gagal.
     * Ditolak bila sudah selesai atau sudah lunas.
     */
    public function cancel(Pesanan $pesanan): JsonResponse
    {
        if (! in_array($pesanan->status_pesanan, ['baru', 'diproses'], true)) {
            return response()->json([
                'message' => 'Pesanan hanya dapat dibatalkan sebelum selesai.',
            ], 422);
        }

        try {
            $pesanan->batalkan();
        } catch (\Throwable $e) {
            $kode = $e instanceof HttpException
                ? $e->getStatusCode()
                : 422;

            return response()->json(['message' => $e->getMessage()], $kode);
        }

        return response()->json([
            'message' => 'Pesanan dibatalkan, stok & poin dikembalikan.',
            'data' => $pesanan->load('detailPesanan', 'transaksi'),
        ]);
    }
}
