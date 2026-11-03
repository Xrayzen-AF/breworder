<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransNotificationController extends Controller
{
    public function __construct(private MidtransService $midtrans) {}

    /**
     * Menangani callback webhook notifikasi dari Midtrans.
     * Route berada di routes/api.php sehingga aman dari proteksi CSRF.
     */
    public function handle(Request $request): JsonResponse
    {
        if (! $this->midtrans->isConfigured()) {
            Log::error('Midtrans webhook diterima tetapi kredensial belum dikonfigurasi.');

            return response()->json([
                'status' => 'error',
                'message' => 'Midtrans belum dikonfigurasi.',
            ], 500);
        }

        $payload = $request->all();
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');

        if ($orderId === '' || $signature === '') {
            return response()->json(['status' => 'error', 'message' => 'Payload tidak lengkap.'], 400);
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$this->midtrans->serverKey());

        if (! hash_equals($expected, $signature)) {
            Log::warning('Midtrans webhook signature mismatch: '.$orderId);

            return response()->json(['status' => 'error', 'message' => 'Invalid signature key.'], 403);
        }

        $pesanan = $this->midtrans->findByOrderId($orderId);

        if (! $pesanan) {
            Log::warning('Midtrans webhook: pesanan tidak ditemukan: '.$orderId);

            return response()->json(['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        try {
            $result = $this->midtrans->applyStatus($pesanan, $payload);

            return response()->json([
                'status' => 'OK',
                'payment_status' => $result['status_pembayaran'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Midtrans callback error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['status' => 'Error', 'message' => 'Gagal memproses notifikasi.'], 500);
        }
    }
}
