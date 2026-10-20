<?php

namespace App\Services;

class CostCalculationService
{
    /**
     * Hitung Harga Material Dasar (Gross Material Price IDR):
     * - Fastener: Weight * Basic Rate * Exchange Rate
     * - Presspart: Weight * (Basic Rate - Fluktuasi JFE) * Exchange Rate
     */
    public static function calculatePriceIdr(
        string $category,
        float $weight,
        float $basicRate,
        float $exchangeRate,
        float $fluktuasiJfe = 0.0
    ): float {
        if (strcasecmp($category, 'Fastener') === 0) {
            return round($weight * $basicRate * $exchangeRate, 2);
        }

        // Presspart: Effective Rate = basicRate - fluktuasiJfe
        $effectiveRate = $basicRate - $fluktuasiJfe;

        return round($weight * $effectiveRate * $exchangeRate, 2);
    }

    /**
     * Hitung Scrap Recovery (Nilai Jual Sisa Potongan Plat / Gram Besi Tua):
     * Scrap Weight = Gross Weight - Net Weight
     * Scrap Recovery Value = Scrap Weight * Scrap Resale Price/kg
     */
    public static function calculateScrapRecovery(
        float $grossWeight,
        float $netWeight,
        float $scrapPricePerKg = 0.0
    ): array {
        $scrapWeight = max(0.0, round($grossWeight - $netWeight, 4));
        $recoveryValue = round($scrapWeight * $scrapPricePerKg, 2);

        return [
            'scrap_weight' => $scrapWeight,
            'scrap_recovery_value' => $recoveryValue,
        ];
    }

    /**
     * Hitung Harga Material Bersih (Net Material Cost):
     * Gross Material Price IDR - Nilai Recovery Scrap
     */
    public static function calculateNetMaterialCost(
        float $grossPriceIdr,
        float $scrapRecoveryValue
    ): float {
        return max(0.0, round($grossPriceIdr - $scrapRecoveryValue, 2));
    }

    /**
     * Hitung Biaya Mesin per Pcs dengan pertimbangan Cavity Cetakan:
     * Machine Cost = (Hourly Rate / Output Capacity per Hour) / Cavity Count
     */
    public static function calculateMachineCostWithCavity(
        float $hourlyMachineRate,
        int $capacityPerHour,
        int $cavityCount = 1
    ): float {
        $safeCapacity = max(1, $capacityPerHour);
        $safeCavity = max(1, $cavityCount);

        return round(($hourlyMachineRate / $safeCapacity) / $safeCavity, 2);
    }

    /**
     * Hitung Profit: 10% * (Price IDR + Total Biaya Proses)
     */
    public static function calculateProfit(
        float $priceIdr,
        float $totalProcessCost,
        float $profitPercentage = 10.0
    ): float {
        return round(($profitPercentage / 100) * ($priceIdr + $totalProcessCost), 2);
    }

    /**
     * Hitung Total Cost (Grand Total):
     * Price IDR + Total Biaya Proses + Profit + Transport + Packing
     */
    public static function calculateTotalCost(
        float $priceIdr,
        float $totalProcessCost,
        float $profit,
        float $transport = 100.0,
        float $packing = 50.0
    ): float {
        return round($priceIdr + $totalProcessCost + $profit + $transport + $packing, 2);
    }
}
