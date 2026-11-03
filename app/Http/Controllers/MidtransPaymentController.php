<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pembayaran non-tunai via Midtrans Core API untuk POS kasir:
 * QRIS dinamis dan Virtual Account (transfer bank).
 */
class MidtransPaymentController extends Controller
{
    public function __construct(private MidtransService $midtrans) {}

    public function qris(Request $request, Pesanan $pesanan): JsonResponse
    {
        $this->authorizeKasir($request);
        $this->ensurePayable($pesanan);

        if (! $this->midtrans->isConfigured()) {
            return $this->notConfigured();
        }

        if ($pending = $this->midtrans->pendingPayment($pesanan)) {
            return response()->json([
                'status' => 'success',
                'reused' => true,
                'payment' => $pending,
                'message' => 'Pembayaran sebelumnya masih aktif.',
            ]);
        }

        try {
            $payment = $this->midtrans->chargeQris($pesanan);
        } catch (\Throwable $e) {
            return $this->gatewayError($e);
        }

        return response()->json([
            'status' => 'success',
            'reused' => false,
            'payment' => $payment,
        ]);
    }

    public function transfer(Request $request, Pesanan $pesanan): JsonResponse
    {
        $this->authorizeKasir($request);
        $this->ensurePayable($pesanan);

        $validated = $request->validate([
            'bank' => ['required', 'string', Rule::in(array_keys(MidtransService::BANKS))],
        ]);

        if (! $this->midtrans->isConfigured()) {
            return $this->notConfigured();
        }

        if ($pending = $this->midtrans->pendingPayment($pesanan)) {
            return response()->json([
                'status' => 'success',
                'reused' => true,
                'payment' => $pending,
                'message' => 'Pembayaran sebelumnya masih aktif.',
            ]);
        }

        try {
            $payment = $this->midtrans->chargeTransfer($pesanan, $validated['bank']);
        } catch (\Throwable $e) {
            return $this->gatewayError($e);
        }

        return response()->json([
            'status' => 'success',
            'reused' => false,
            'payment' => $payment,
        ]);
    }

    /**
     * Polling status: dipakai modal kasir tiap beberapa detik agar
     * pembayaran terdeteksi walau webhook belum bisa menjangkau localhost.
     */
    public function status(Request $request, Pesanan $pesanan): JsonResponse
    {
        $this->authorizeKasir($request);
        $pesanan->loadMissing('transaksi');
        $trx = $pesanan->transaksi;

        if (! $trx || ! $trx->midtrans_order_id) {
            return response()->json([
                'status' => 'success',
                'payment_status' => $trx?->status_pembayaran ?? 'menunggu',
                'payment' => $trx ? $this->midtrans->publicPayment($trx) : null,
            ]);
        }

        if (in_array($trx->status_pembayaran, ['lunas', 'gagal'], true)) {
            return response()->json([
                'status' => 'success',
                'payment_status' => $trx->status_pembayaran,
                'payment' => $this->midtrans->publicPayment($trx),
            ]);
        }

        try {
            $raw = $this->midtrans->remoteStatus($trx->midtrans_order_id);
            $applied = $this->midtrans->applyStatus($pesanan, $raw);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'success',
                'payment_status' => $trx->status_pembayaran,
                'payment' => $this->midtrans->publicPayment($trx),
                'remote_error' => $e->getMessage(),
            ]);
        }

        $trx->refresh();

        return response()->json([
            'status' => 'success',
            'payment_status' => $applied['status_pembayaran'],
            'remote_status' => $applied['remote_status'],
            'already_paid' => $applied['already_paid'],
            'payment' => $this->midtrans->publicPayment($trx),
        ]);
    }

    private function authorizeKasir(Request $request): void
    {
        abort_unless($request->user()?->isKasir() || $request->user()?->isAdmin(), 403);
    }

    private function ensurePayable(Pesanan $pesanan): void
    {
        if ($pesanan->isTerminal()) {
            abort(422, 'Pesanan sudah selesai atau dibatalkan.');
        }

        if ($pesanan->sudahLunas()) {
            abort(422, 'Pesanan ini sudah lunas.');
        }
    }

    private function notConfigured(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'code' => 'midtrans_not_configured',
            'message' => 'Midtrans belum dikonfigurasi. Minta admin mengisi Server Key & Client Key di Store Settings.',
        ], 422);
    }

    private function gatewayError(\Throwable $e): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 422);
    }
}
