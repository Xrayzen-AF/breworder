<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogReorder;
use App\Models\StokBiji;
use App\Services\Agents\InventoryAgent;
use App\Services\Agents\SalesAgent;
use App\Services\Agents\StaffingAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Endpoint API untuk AI workers (multi-agent orchestration):
 * 3.1 GET  /api/v1/ops/financial-summary
 * 3.2 GET  /api/v1/ops/traffic-telemetry
 * 3.3 GET  /api/v1/inventory/alerts
 * 3.4 POST /api/v1/inventory/reorder
 */
class OpsAgentController extends Controller
{
    public function __construct(
        private readonly SalesAgent $salesAgent,
        private readonly StaffingAgent $staffingAgent,
        private readonly InventoryAgent $inventoryAgent,
    ) {}

    /**
     * 3.1 — Financial Stream.
     */
    public function financialSummary(): JsonResponse
    {
        return response()->json($this->salesAgent->financialSummary());
    }

    /**
     * 3.2 — Order & Staffing Analytics.
     */
    public function trafficTelemetry(): JsonResponse
    {
        return response()->json($this->staffingAgent->trafficTelemetry());
    }

    /**
     * 3.3 — Inventory Telemetry (alert kritis; array kosong jika aman).
     */
    public function inventoryAlerts(): JsonResponse
    {
        return response()->json($this->inventoryAgent->criticalAlerts());
    }

    /**
     * 3.4 — Supply Chain Automation Action.
     * Dispatch webhook ke roastery + catat ke log_reorder + update stok.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination' => ['required', 'string', 'max:255'],
            'item' => ['required', 'string', 'max:255'],
            'order_quantity_kg' => ['required', 'numeric', 'min:0.1', 'max:1000'],
        ]);

        $biji = StokBiji::where('nama_biji', $validated['item'])->first();

        if (! $biji) {
            return response()->json([
                'message' => "Item '{$validated['item']}' tidak ditemukan di inventori.",
            ], 404);
        }

        $result = DB::transaction(function () use ($validated, $biji) {
            // Update stok: tambah sesuai quantity yang dikirim roastery.
            $biji->increment('sisa_kg', $validated['order_quantity_kg']);

            // Dispatch webhook ke roastery (destination) — best-effort;
            // kegagalan HTTP tidak menggagalkan pencatatan reorder lokal.
            $webhookOk = null;
            $reference = null;

            if (config('services.roastery.webhook_url')) {
                try {
                    $response = Http::timeout(5)->post(
                        config('services.roastery.webhook_url'),
                        $validated,
                    );
                    $webhookOk = $response->successful();
                    $reference = $response->json('reference');
                } catch (\Throwable) {
                    $webhookOk = false;
                }
            }

            LogReorder::create([
                'id_stok_biji' => $biji->id_stok_biji,
                'destination' => $validated['destination'],
                'order_quantity_kg' => $validated['order_quantity_kg'],
                'status' => ($webhookOk === false) ? 'gagal' : 'terkirim',
                'response_reference' => $reference,
                'waktu_dispatch' => now(),
            ]);

            return [$biji->fresh(), $webhookOk, $reference];
        });

        /** @var StokBiji $bijiBaru */
        [$bijiBaru, $webhookOk, $reference] = $result;

        return response()->json([
            'message' => 'Reorder berhasil didispatch.',
            'data' => [
                'item' => $bijiBaru->nama_biji,
                'destination' => $validated['destination'],
                'order_quantity_kg' => (float) $validated['order_quantity_kg'],
                'remaining_kg' => (float) $bijiBaru->sisa_kg,
                'webhook_status' => $webhookOk === null ? 'no_webhook_configured' : ($webhookOk ? 'sent' : 'failed'),
                'response_reference' => $reference,
            ],
        ], 201);
    }
}
