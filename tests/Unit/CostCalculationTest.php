<?php

namespace Tests\Unit;

use App\Services\CostCalculationService;
use PHPUnit\Framework\TestCase;

class CostCalculationTest extends TestCase
{
    public function test_fastener_calculation_matches_book5_sample(): void
    {
        // Parameter Fastener dari Book5.xlsx
        $category = 'Fastener';
        $weight = 0.05;
        $basicRate = 2.135;
        $exchangeRate = 20000.0;
        $fluktuasi = 0.0;

        $priceIdr = CostCalculationService::calculatePriceIdr(
            $category,
            $weight,
            $basicRate,
            $exchangeRate,
            $fluktuasi
        );

        // Price IDR: 2135.00
        $this->assertEquals(2135.00, $priceIdr);

        // Total Proses: 100 + 100 + 200 + 150 = 550
        $totalProcessCost = 550.00;

        // Profit: 10% * (2135 + 550) = 268.50
        $profit = CostCalculationService::calculateProfit($priceIdr, $totalProcessCost, 10.0);
        $this->assertEquals(268.50, $profit);

        // Total Cost: 2135 + 550 + 268.5 + 100 + 50 = 3103.50
        $totalCost = CostCalculationService::calculateTotalCost(
            $priceIdr,
            $totalProcessCost,
            $profit,
            100.0,
            50.0
        );
        $this->assertEquals(3103.50, $totalCost);
    }

    public function test_presspart_calculation_matches_book5_sample(): void
    {
        // Parameter Presspart dari Book5.xlsx
        $category = 'Presspart';
        $weight = 0.15;
        $basicRate = 1.99;
        $fluktuasi = -0.02;
        $exchangeRate = 20000.0;

        $priceIdr = CostCalculationService::calculatePriceIdr(
            $category,
            $weight,
            $basicRate,
            $exchangeRate,
            $fluktuasi
        );

        // Price IDR: 0.15 * (1.99 - (-0.02)) * 20000 = 6030.00
        $this->assertEquals(6030.00, $priceIdr);

        // Total Proses: 7 * 100 = 700
        $totalProcessCost = 700.00;

        // Profit: 10% * (6030 + 700) = 673.00
        $profit = CostCalculationService::calculateProfit($priceIdr, $totalProcessCost, 10.0);
        $this->assertEquals(673.00, $profit);

        // Total Cost: 6030 + 700 + 673 + 100 + 50 = 7553.00
        $totalCost = CostCalculationService::calculateTotalCost(
            $priceIdr,
            $totalProcessCost,
            $profit,
            100.0,
            50.0
        );
        $this->assertEquals(7553.00, $totalCost);
    }
}
