<?php

namespace Tests\Feature;

use App\Filament\Pages\MonthlyRollover;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MonthlyDataRolloverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyDataRolloverTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplier;

    protected Currency $usd2026;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->supplier = Supplier::create([
            'code' => 'VND-HSSI',
            'name' => 'PT HSSI',
            'category' => 'Steel',
        ]);

        $this->usd2026 = Currency::create([
            'year' => 2026,
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 15000.0,
        ]);
    }

    public function test_rollover_currencies_creates_new_year_currency_with_new_rate(): void
    {
        $result = MonthlyDataRolloverService::rolloverCurrencies(2026, 2027, 16500.0);

        $this->assertEquals(1, $result['total']);

        $this->assertDatabaseHas('currencies', [
            'year' => 2027,
            'code' => 'USD',
            'exchange_rate_to_idr' => 16500.0,
        ]);
    }

    public function test_rollover_material_steels_duplicates_records_and_recalculates(): void
    {
        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-ROLLOVER-TEST',
            'double_thickness' => '1.4',
            'currency_id' => $this->usd2026->id,
            'currency_code' => 'USD',
            'steel_price' => 1.8000,
            'coil_price' => 1.8000,
            'sheet_price' => 1.9000,
            'gap_steel_usd' => 50.0,
            'exchange_rate' => 15000.0,
            'coil_price_idr' => 27000.0,
            'sheet_price_idr' => 28500.0,
        ]);

        $stats = MonthlyDataRolloverService::rolloverMaterialSteels(
            2026,
            'Q4',
            2027,
            'Q1',
            16000.0,
            null,
            null,
            'Active',
            'overwrite'
        );

        $this->assertEquals(1, $stats['copied']);

        $targetSteel = MaterialSteel::where('year', 2027)->where('quarter', 'Q1')->first();
        $this->assertNotNull($targetSteel);
        $this->assertEquals('SPCC-ROLLOVER-TEST', $targetSteel->steel_spec);
        $this->assertEquals(16000.0, (float) $targetSteel->exchange_rate);
        $this->assertEquals(1.8000, (float) $targetSteel->coil_price);
        $this->assertEquals(28800.0, (float) $targetSteel->coil_price_idr); // 1.80 * 16000
        $this->assertEquals(1.9000, (float) $targetSteel->sheet_price);
        $this->assertEquals(30400.0, (float) $targetSteel->sheet_price_idr); // 1.90 * 16000
        $this->assertEquals(800000.0, (float) $targetSteel->gap_steel_idr); // 50 * 16000
    }

    public function test_rollover_stamping_parts_duplicates_and_recalculates_prices(): void
    {
        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH270-STAMP-ROLLOVER',
            'double_thickness' => '1.6',
            'currency_id' => $this->usd2026->id,
            'currency_code' => 'USD',
            'coil_price' => 1.1000,
            'sheet_price' => 1.2000,
            'gap_steel_usd' => 100.0,
            'exchange_rate' => 15000.0,
            'coil_price_idr' => 16500.0,
            'sheet_price_idr' => 18000.0,
        ]);

        $stats = MonthlyDataRolloverService::rolloverStampingParts(
            2026,
            'Q4',
            2027,
            'Q1',
            16000.0,
            null,
            'Active',
            'overwrite',
            null,
            false
        );

        $this->assertEquals(1, $stats['copied']);

        $targetPart = StampingPart::where('year', 2027)->where('quarter', 'Q1')->first();
        $this->assertNotNull($targetPart);
        $this->assertEquals('SPH270-STAMP-ROLLOVER', $targetPart->steel_spec);
        $this->assertEquals(16000.0, (float) $targetPart->exchange_rate);
        $this->assertEquals(1.1000, (float) $targetPart->coil_price);
        $this->assertEquals(17600.0, (float) $targetPart->coil_price_idr); // 1.10 * 16000
        $this->assertEquals(1.2000, (float) $targetPart->sheet_price);
        $this->assertEquals(19200.0, (float) $targetPart->sheet_price_idr); // 1.20 * 16000
    }

    public function test_rollover_all_in_one_creates_log_and_rolls_over_everything(): void
    {
        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPEC-ALL-IN-ONE',
            'double_thickness' => '2.0',
            'currency_id' => $this->usd2026->id,
            'currency_code' => 'USD',
            'coil_price' => 2.0000,
            'sheet_price' => 2.1000,
            'gap_steel_usd' => 50.0,
            'exchange_rate' => 15000.0,
        ]);

        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPEC-ALL-IN-ONE',
            'double_thickness' => '2.0',
            'currency_id' => $this->usd2026->id,
            'currency_code' => 'USD',
            'coil_price' => 1.5000,
            'sheet_price' => 1.6000,
            'gap_steel_usd' => 50.0,
            'exchange_rate' => 15000.0,
        ]);

        Fastener::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => 'FAST-ALL-IN-ONE',
            'part_name' => 'BOLT ALL IN ONE',
            'steel_spec' => 'SWCH-45K',
            'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
            'wire_price_usd_kg' => 2.02,
            'coil_price' => 2.02,
            'sheet_price' => 2.02,
            'currency_id' => $this->usd2026->id,
            'currency_code' => 'USD',
            'exchange_rate' => 15000.0,
        ]);

        $result = MonthlyDataRolloverService::rolloverAll(
            2026,
            'Q4',
            2027,
            'Q1',
            16200.0,
            null,
            null,
            'Active',
            'overwrite',
            'Tes Rollover Bulanan Q4 2026 ke Q1 2027'
        );

        $this->assertDatabaseHas('currencies', [
            'year' => 2027,
            'code' => 'USD',
            'exchange_rate_to_idr' => 16200.0,
        ]);

        $this->assertDatabaseHas('material_steels', [
            'year' => 2027,
            'quarter' => 'Q1',
            'steel_spec' => 'SPEC-ALL-IN-ONE',
            'exchange_rate' => 16200.0,
            'coil_price_idr' => 32400.0, // 2.00 * 16200
        ]);

        $this->assertDatabaseHas('stamping_parts', [
            'year' => 2027,
            'quarter' => 'Q1',
            'steel_spec' => 'SPEC-ALL-IN-ONE',
            'exchange_rate' => 16200.0,
        ]);

        $this->assertDatabaseHas('fasteners', [
            'year' => 2027,
            'quarter' => 'Q1',
            'part_number' => 'FAST-ALL-IN-ONE',
            'old_exchange_rate' => 15000.0,
            'exchange_rate' => 16200.0,
        ]);

        $this->assertDatabaseHas('monthly_rollover_logs', [
            'type' => 'all',
            'source_period' => '2026-Q4',
            'target_period' => '2027-Q1',
            'exchange_rate' => 16200.0,
            'fasteners_count' => 1,
        ]);
    }

    public function test_monthly_rollover_filament_page_renders_and_executes_successfully(): void
    {
        $this->actingAs($this->admin);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'LIVEWIRE-PAGE-TEST',
            'currency_id' => $this->usd2026->id,
            'currency_code' => 'USD',
            'coil_price' => 1.9000,
            'sheet_price' => 2.0000,
            'exchange_rate' => 15000.0,
        ]);

        Livewire::test(MonthlyRollover::class)
            ->assertSuccessful()
            ->set('sourceYear', 2026)
            ->set('sourceQuarter', 'Q4')
            ->set('targetYear', 2027)
            ->set('targetQuarter', 'Q1')
            ->set('usdRate', 16500.0)
            ->call('executeAllRollover')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('material_steels', [
            'year' => 2027,
            'quarter' => 'Q1',
            'steel_spec' => 'LIVEWIRE-PAGE-TEST',
            'exchange_rate' => 16500.0,
            'coil_price_idr' => 31350.0, // 1.90 * 16500
        ]);

        $this->assertDatabaseHas('monthly_rollover_logs', [
            'target_period' => '2027-Q1',
        ]);
    }

    public function test_rollover_fasteners_duplicates_and_recalculates_prices(): void
    {
        Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '90041-ROLLOVER',
            'part_name' => 'BOLT,W/WASHER',
            'steel_spec' => 'SWCH-45K',
            'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
            'nett_weight_gr' => 4.4,
            'gross_weight_gr' => 4.84,
            'wire_price_usd_kg' => 2.02,
            'washer_price' => 198,
            'washer_weight_gr' => 4.5,
            'overhead_self_percent' => 14.3,
            'exchange_rate' => 16465.0,
        ]);

        $stats = MonthlyDataRolloverService::rolloverFasteners(
            2026,
            'Q1',
            2026,
            'Q2',
            16725.0,
            null,
            'Active',
            'overwrite'
        );

        $this->assertEquals(1, $stats['copied']);

        $q2 = Fastener::where('year', 2026)->where('quarter', 'Q2')->where('part_number', '90041-ROLLOVER')->first();
        $this->assertNotNull($q2);
        $this->assertEquals(16465.0, (float) $q2->old_exchange_rate);
        $this->assertEquals(16725.0, (float) $q2->exchange_rate);
        $this->assertEqualsWithDelta(round(16725 / 16465, 6), (float) $q2->exchange_rate_fluctuation, 0.000001);
        $this->assertEquals(33784.50, (float) $q2->wire_price_idr_kg);
        $this->assertEqualsWithDelta(680.2483, (float) $q2->part_cost, 0.01);
    }

    public function test_rollover_stamping_parts_preserves_direct_idr_prices_and_skips_usd_steel_sync(): void
    {
        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'currency_id' => $this->usd2026->id,
            'currency_code' => 'USD',
            'coil_price' => 1.2500,
            'sheet_price' => 1.3500,
            'exchange_rate' => 17000.0,
        ]);

        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q3',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '72388-DIRECT-IDR',
            'part_name' => 'BRKT DIRECT IDR',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'wide' => 390,
            'pitch' => 148,
            'yield_rate' => 2,
            'berat' => 0.5890326,
            'scrap_weight' => 0.2780,
            'scrap_price_per_kg' => -1500,
            'process_man_hour' => 9,
            'process_charge' => 45,
            'overhead_supplied_percent' => 11,
            'overhead_self_percent' => 4,
            'exchange_rate' => 17796.0,
            'coil_price' => 0,
            'sheet_price' => 0,
            'coil_price_idr' => 17689.0,
            'sheet_price_idr' => 17689.0,
        ]);

        $stats = MonthlyDataRolloverService::rolloverStampingParts(
            2026,
            'Q3',
            2026,
            'Q4',
            17000.0,
            null,
            'Active',
            'overwrite',
            null,
            true
        );

        $this->assertEquals(1, $stats['copied']);

        $rolled = StampingPart::where('year', 2026)
            ->where('quarter', 'Q4')
            ->where('part_number', '72388-DIRECT-IDR')
            ->first();

        $this->assertNotNull($rolled);
        $this->assertEquals(0.0, (float) $rolled->coil_price);
        $this->assertEquals(0.0, (float) $rolled->sheet_price);
        $this->assertEquals(17689.0, (float) $rolled->sheet_price_idr);
        $this->assertEquals(17689.0, (float) $rolled->coil_price_idr);
        $this->assertEqualsWithDelta(0.5890326, (float) $rolled->berat, 0.0000001);
        $this->assertEqualsWithDelta(10627.28, (float) $rolled->part_cost, 0.02);
    }
}
