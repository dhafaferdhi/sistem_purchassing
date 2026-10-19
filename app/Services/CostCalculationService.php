<?php

namespace App\Services;

class CostCalculationService
{
    /**
     * Hitung Harga Material (Price IDR) berdasarkan Book5.xlsx:
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

        // Untuk Presspart, Fluktuasi JFE mengurangi/menyesuaikan Basic Rate
        // Contoh: 1.99 - (-0.02) = 2.01 * 0.15 * 20000 = 6030
        $effectiveRate = $basicRate - $fluktuasiJfe;
        return round($weight * $effectiveRate * $exchangeRate, 2);
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

