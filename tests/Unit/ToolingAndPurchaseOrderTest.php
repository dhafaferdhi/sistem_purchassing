<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Tooling;
use App\Models\User;
use App\Services\CostCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolingAndPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_tooling_wear_percentage_and_stroke_tracking(): void
    {
        $supplier = Supplier::create([
            'code' => 'VND-105',
            'name' => 'PT Maju Baja Stamping',
        ]);

        $part = Part::create([
            'part_number' => 'STP-BKT-01',
            'part_name' => 'Steel Bracket Bumper',
            'type' => 'Component',
            'main_category' => 'Steel',
            'sub_category' => 'Stamping',
        ]);

        $tooling = Tooling::create([
            'tooling_code' => 'DIES-STP-001',
            'name' => 'Dies Progressive Bracket Bumper',
            'type' => 'Dies',
            'ownership' => 'Customer Owned',
            'supplier_id' => $supplier->id,
            'part_id' => $part->id,
            'tonnage' => '500 Ton',
            'cavity_count' => 2,
            'initial_cost' => 150000000,
            'lifetime_strokes' => 300000,
            'current_strokes' => 150000,
            'status' => 'Ready',
        ]);

        $this->assertEquals(50.0, $tooling->wear_percentage);
        $this->assertEquals(150000, $tooling->strokes_remaining);

        // Simulasi penambahan stroke produksi
        $tooling->current_strokes += 140000; // total 290,000 / 300,000 = 96.7%
        $tooling->save();

        $this->assertEquals(96.7, $tooling->wear_percentage);
        $this->assertEquals(10000, $tooling->strokes_remaining);
    }

    public function test_purchase_order_auto_number_and_ppn_calculation(): void
    {
        $user = User::factory()->create([
            'name' => 'Purchasing Staff',
            'email' => 'purchasing@test.com',
            'role' => 'estimator',
        ]);

        $supplier = Supplier::create([
            'code' => 'VND-200',
            'name' => 'PT Multi Fastener Mandiri',
        ]);

        $currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'exchange_rate_to_idr' => 1.0,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'order_date' => now(),
            'payment_terms' => 'Net 30 Days',
            'currency_id' => $currency->id,
            'exchange_rate' => 1.0,
            'subtotal_idr' => 1000000.0,
            'tax_percentage' => 11.0,
            'status' => 'Draft',
        ]);

        // Verifikasi auto-generate nomor PO PO-YYYYMM-0001
        $expectedPrefix = 'PO-'.date('Ym').'-';
        $this->assertStringStartsWith($expectedPrefix, $po->po_number);

        // Verifikasi perhitungan PPN 11% (1.000.000 * 11% = 110.000, Total = 1.110.000)
        $this->assertEquals(110000.0, (float) $po->tax_amount_idr);
        $this->assertEquals(1110000.0, (float) $po->total_amount_idr);

        // Tambah line item
        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_code' => 'BOLT-M6-20',
            'description' => 'Hex Bolt M6 x 20 Zinc Plated',
            'quantity' => 500,
            'unit' => 'pcs',
            'unit_price_idr' => 2000.0,
        ]);

        $this->assertEquals(1000000.0, (float) $item->total_price_idr);
    }

    public function test_scrap_recovery_calculation_service(): void
    {
        $grossWeight = 0.20; // 200 gram
        $netWeight = 0.15;   // 150 gram
        $scrapPricePerKg = 6000.0; // Rp 6.000 per kg

        $scrap = CostCalculationService::calculateScrapRecovery($grossWeight, $netWeight, $scrapPricePerKg);

        // Scrap weight: 0.20 - 0.15 = 0.05 kg
        $this->assertEquals(0.05, $scrap['scrap_weight']);

        // Recovery value: 0.05 * 6000 = Rp 300
        $this->assertEquals(300.0, $scrap['scrap_recovery_value']);

        // Net material cost
        $grossMaterialPrice = 6000.0;
        $netCost = CostCalculationService::calculateNetMaterialCost($grossMaterialPrice, $scrap['scrap_recovery_value']);
        $this->assertEquals(5700.0, $netCost);
    }

    public function test_cavity_machine_rate_calculation(): void
    {
        $machineHourlyRate = 300000.0; // Rp 300.000 / jam
        $capacityPerHour = 600;        // 600 stroke / jam
        $cavityCount = 2;              // Cetakan 2 cavity (2 part per stroke)

        // Tanpa cavity (1 part per stroke) = 300.000 / 600 = Rp 500 / part
        $costSingle = CostCalculationService::calculateMachineCostWithCavity($machineHourlyRate, $capacityPerHour, 1);
        $this->assertEquals(500.0, $costSingle);

        // Dengan 2 cavity = Rp 500 / 2 = Rp 250 / part
        $costDouble = CostCalculationService::calculateMachineCostWithCavity($machineHourlyRate, $capacityPerHour, $cavityCount);
        $this->assertEquals(250.0, $costDouble);
    }
}
