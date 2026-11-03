<?php

namespace App\Services\Agents;

use App\Models\StokBiji;
use Carbon\CarbonInterface;

/**
 * InventoryAgent — Sub-threshold Material Tracker & Automated Replenishment.
 *
 * Memantau stok biji, memproyeksikan waktu deplesi berdasarkan laju konsumsi,
 * dan menghitung quantity reorder (safety cap - sisa).
 */
class InventoryAgent
{
    /**
     * Telemetri inventori untuk endpoint /api/v1/inventory/alerts.
     * Mengembalikan alert kritis (sisa < threshold); null jika aman.
     */
    public function criticalAlerts(): array
    {
        return StokBiji::query()
            ->get()
            ->filter(fn (StokBiji $biji) => $biji->isKritis())
            ->map(fn (StokBiji $biji) => $this->alertPayload($biji))
            ->values()
            ->all();
    }

    /**
     * Payload satu alert sesuai spesifikasi endpoint 3.3.
     */
    public function alertPayload(StokBiji $biji): array
    {
        $depleasi = $this->estimatedDepletion($biji);

        return [
            'item' => $biji->nama_biji,
            'remaining_kg' => (float) $biji->sisa_kg,
            'threshold_kg' => (float) $biji->threshold_kg,
            'estimated_depletion' => $depleasi?->format('Y-m-d\TH:i:s'),
        ];
    }

    /**
     * Predictive Logic: proyeksi deplesi dari laju konsumsi real-time.
     * Deplesi = sekarang + (sisa_kg / laju_kg_per_jam) jam.
     * Return null jika laju 0 (tidak bisa diproyeksikan).
     */
    public function estimatedDepletion(StokBiji $biji): ?CarbonInterface
    {
        $laju = (float) $biji->laju_konsumsi_kg_per_jam;
        $sisa = (float) $biji->sisa_kg;

        if ($laju <= 0 || $sisa <= 0) {
            return null;
        }

        return now()->addHours($sisa / $laju);
    }

    /**
     * Quantity reorder = safety cap - sisa (mengisi sampai safety cap).
     */
    public function reorderQuantity(StokBiji $biji): float
    {
        return max(0, (float) $biji->safety_cap_kg - (float) $biji->sisa_kg);
    }
}
