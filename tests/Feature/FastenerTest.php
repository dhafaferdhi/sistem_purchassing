<?php

namespace Tests\Feature;

use App\Filament\Pages\MonthlyRollover;
use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\CurrencyResource\RelationManagers\FastenersRelationManager;
use App\Filament\Resources\FastenerResource;
use App\Filament\Resources\FastenerResource\Pages\CreateFastener;
use App\Filament\Resources\FastenerResource\Pages\ListFasteners;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CurrencyRecalculationService;
use App\Services\ExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FastenerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplier;

    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->supplier = Supplier::create([
            'code' => 'VND-TOHO',
            'name' => 'PT Toho Fastener',
            'category' => 'Fastener',
        ]);

        $this->currency = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'year' => 2026,
            'exchange_rate_to_idr' => 16000.0,
        ]);
    }

    public function test_can_view_fasteners_table_and_columns(): void
    {
        $this->actingAs($this->admin);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SWMB',
            'double_thickness' => '3.0',
            'diameter' => '3.0',
            'gap_steel_usd' => 100.0,
            'exchange_rate' => 16000.0,
            'coil_price' => 1.8500,
            'sheet_price' => 1.9500,
        ]);

        Livewire::test(ListFasteners::class)
            ->assertCanSeeTableRecords([$fastener])
            ->assertTableColumnExists('steel_spec')
            ->assertTableColumnExists('double_thickness')
            ->assertTableColumnExists('gap_steel_usd')
            ->assertTableColumnExists('gap_steel_kg_usd')
            ->assertTableColumnExists('exchange_rate')
            ->assertTableColumnExists('coil_price')
            ->assertTableColumnExists('coil_price_idr')
            ->assertTableColumnExists('sheet_price')
            ->assertTableColumnExists('sheet_price_idr')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('delete');
    }

    public function test_fastener_form_has_all_required_fields_and_back_action(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateFastener::class)
            ->assertFormFieldExists('quarter')
            ->assertFormFieldExists('year')
            ->assertFormFieldExists('supplier_id')
            ->assertFormFieldExists('supplier_name')
            ->assertFormFieldExists('vendor_code')
            ->assertFormFieldExists('part_number')
            ->assertFormFieldExists('part_name')
            ->assertFormFieldExists('model_name')
            ->assertFormFieldExists('monthly_volume')
            ->assertFormFieldExists('steel_spec')
            ->assertFormFieldExists('double_thickness')
            ->assertFormFieldExists('wire_spec')
            ->assertFormFieldExists('nett_weight_gr')
            ->assertFormFieldExists('gross_weight_gr')
            ->assertFormFieldExists('material_control_percent')
            ->assertFormFieldExists('washer_price')
            ->assertFormFieldExists('washer_weight_gr')
            ->assertFormFieldExists('heading_cost')
            ->assertFormFieldExists('rolling_cost')
            ->assertFormFieldExists('washing_cost_kg')
            ->assertFormFieldExists('plating_cost_kg')
            ->assertFormFieldExists('inspection_cost')
            ->assertFormFieldExists('packing_cost')
            ->assertFormFieldExists('overhead_self_percent')
            ->assertFormFieldExists('gap_steel_usd')
            ->assertFormFieldExists('gap_steel_kg_usd')
            ->assertFormFieldExists('exchange_rate')
            ->assertFormFieldExists('coil_price')
            ->assertFormFieldExists('coil_price_idr')
            ->assertFormFieldExists('sheet_price')
            ->assertFormFieldExists('sheet_price_idr')
            ->assertFormFieldExists('wire_price_usd_kg')
            ->assertActionExists('back');
    }

    public function test_fastener_calculates_nitto_alam_excel_comparative_quotation_formulas(): void
    {
        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '9004A-01046',
            'part_name' => 'BOLT, REAR BUMPER',
            'model_name' => 'D26A',
            'monthly_volume' => 20000,
            'steel_spec' => 'SWCH-45K',
            'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
            'wire_price_usd_kg' => 1.66,
            'gross_weight_gr' => 4.84,
            'nett_weight_gr' => 4.4,
            'washer_weight_gr' => 4.5,
            'washer_price' => 198,
            'material_control_percent' => 5,
            'heading_cost' => 48,
            'rolling_cost' => 76,
            'washing_cost_kg' => 500,
            'plating_cost_kg' => 10000,
            'inspection_cost' => 5,
            'packing_cost' => 3,
            'overhead_self_percent' => 14.3,
            'exchange_rate' => 16000.0,
            'coil_price' => 1.66,
            'sheet_price' => 1.66,
        ]);

        // wire_price_idr_kg = 1.66 * 16000 = 26560
        $this->assertEquals(26560.00, (float) $fastener->wire_price_idr_kg);
        // wire_price_pc = (4.84 / 1000) * 26560 = 128.5504
        $this->assertEqualsWithDelta(128.5504, (float) $fastener->wire_price_pc, 0.001);
        // material_control_cost = 5% * 128.5504 = 6.4275
        $this->assertEqualsWithDelta(6.4275, (float) $fastener->material_control_cost, 0.001);
        // material_total = 128.5504 + 6.4275 + 198 = 332.9779
        $this->assertEqualsWithDelta(332.9779, (float) $fastener->material_total, 0.001);
        // totalPartWeightKg = (4.4 + 4.5) / 1000 = 0.0089 kg
        // washing_cost = 0.0089 * 500 = 4.45
        $this->assertEqualsWithDelta(4.45, (float) $fastener->washing_cost, 0.001);
        // plating_cost = 0.0089 * 10000 = 89.00
        $this->assertEqualsWithDelta(89.00, (float) $fastener->plating_cost, 0.001);
        // process_total = 48 + 76 + 4.45 + 89.00 + 5 + 3 = 225.45
        $this->assertEqualsWithDelta(225.45, (float) $fastener->process_total, 0.001);
        // subtotal = 332.9779 + 225.45 = 558.4279
        $this->assertEqualsWithDelta(558.4279, (float) $fastener->subtotal, 0.001);
        // overhead_self_cost = 14.3% * 558.4279 = 79.8552
        $this->assertEqualsWithDelta(79.8552, (float) $fastener->overhead_self_cost, 0.001);
        // part_cost = 558.4279 + 79.8552 = 638.2831
        $this->assertEqualsWithDelta(638.2831, (float) $fastener->part_cost, 0.01);
    }

    public function test_can_create_fastener_and_formulas_calculated_correctly(): void
    {
        $this->actingAs($this->admin);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SWRCH10A',
            'double_thickness' => '4.0mm',
            'coil_price' => 1.8500,
            'sheet_price' => 1.9500,
            'gap_steel_usd' => 100.0,
            'exchange_rate' => 16000.0,
        ]);

        $this->assertDatabaseHas('fasteners', [
            'steel_spec' => 'SWRCH10A',
            'double_thickness' => '4.0',
            'diameter' => '4.0',
            'coil_price' => 1.8500,
            'sheet_price' => 1.9500,
            'gap_steel_usd' => 100.0,
            'gap_steel_kg_usd' => 0.1,
            'coil_price_idr' => 29600.00,
            'sheet_price_idr' => 31200.00,
        ]);
    }

    public function test_auto_fill_from_previous_quarter_q1_2027_from_q4_2026(): void
    {
        // Buat data Q4 2026
        Fastener::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SWMB-3.0',
            'double_thickness' => '3.0',
            'gap_steel_usd' => 100.0,
            'exchange_rate' => 16000.0,
            'coil_price' => 1.8500,
            'sheet_price' => 1.9500,
        ]);

        // Cek info kuartal sebelumnya
        $info = Fastener::getPreviousQuarterInfo('Q1', 2027);
        $this->assertEquals('Q4', $info['prev_quarter']);
        $this->assertEquals(2026, $info['prev_year']);
        $this->assertEquals('Q4 2026', $info['label']);

        // Cari data acuan kuartal sebelumnya untuk Q1 2027
        $prevData = Fastener::findPreviousQuarterData('Q1', 2027, $this->supplier->id, 'SWMB-3.0');
        $this->assertNotNull($prevData);
        $this->assertEquals('SWMB', $prevData['steel_spec']);
        $this->assertEquals(1.85, $prevData['coil_price']);
        $this->assertEquals(1.95, $prevData['sheet_price']);
        $this->assertEquals(100.0, $prevData['gap_steel_usd']);
    }

    public function test_currency_recalculation_updates_fastener_idr_prices(): void
    {
        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SWMB',
            'double_thickness' => '3.0',
            'coil_price' => 2.0000,
            'sheet_price' => 2.1000,
            'currency_id' => $this->currency->id,
            'currency_code' => 'USD',
            'exchange_rate' => 16000.0,
        ]);

        $this->assertEquals(32000.0, (float) $fastener->coil_price_idr);
        $this->assertEquals(33600.0, (float) $fastener->sheet_price_idr);

        // Update kurs USD ke 17000
        $this->currency->update(['exchange_rate_to_idr' => 17000.0]);
        CurrencyRecalculationService::recalculateForCurrency($this->currency);

        $fastener->refresh();
        $this->assertEquals(17000.0, (float) $fastener->exchange_rate);
        $this->assertEquals(34000.0, (float) $fastener->coil_price_idr);
        $this->assertEquals(35700.0, (float) $fastener->sheet_price_idr);
    }

    public function test_excel_import_fastener_creates_and_calculates_records(): void
    {
        $csvContent = implode("\n", [
            'Kuartal,Tahun,Vendor Code,Supplier Name,Spec,Diameter/Thickness(mm),Gap/MT (USD),Gap/Kg (USD),Ex Rate (IDR),Coil (USD),Sheet (USD),Status',
            'Q1,2027,VND-TOHO,PT TOHO FASTENER,SWMB 3.0mm,3.0,100,0.1,16500,1.85,1.95,Active',
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'fastener_import_test').'.csv';
        file_put_contents($tempPath, $csvContent);

        $result = ExcelImportService::importFastener($tempPath, ['year' => 2027, 'quarter' => 'Q1']);
        @unlink($tempPath);

        $this->assertEquals(1, $result['imported']);
        $this->assertDatabaseHas('fasteners', [
            'year' => 2027,
            'quarter' => 'Q1',
            'steel_spec' => 'SWMB',
            'double_thickness' => '3.0',
            'diameter' => '3.0',
            'exchange_rate' => 16500.0,
            'coil_price' => 1.85,
            'sheet_price' => 1.95,
            'coil_price_idr' => 30525.00,
            'sheet_price_idr' => 32175.00,
        ]);
    }

    public function test_navigation_label_and_sort_position(): void
    {
        $this->assertEquals('Fasteners', FastenerResource::getNavigationLabel());
        $this->assertEquals('Master Data', FastenerResource::getNavigationGroup());
        $this->assertEquals(4, FastenerResource::getNavigationSort());
        $this->assertEquals(5, MonthlyRollover::getNavigationSort());
    }

    public function test_currency_has_fasteners_relation_and_relation_manager(): void
    {
        $this->actingAs($this->admin);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SWMB',
            'diameter' => '3.0',
            'currency_id' => $this->currency->id,
            'currency_code' => 'USD',
            'exchange_rate' => 16000.0,
            'coil_price' => 1.85,
            'sheet_price' => 1.95,
        ]);

        $this->assertTrue($this->currency->fasteners()->exists());
        $this->assertEquals(1, $this->currency->fasteners()->count());

        $relations = CurrencyResource::getRelations();
        $this->assertContains(FastenersRelationManager::class, $relations);
    }

    public function test_fasteners_table_and_form_harmonized_with_material_steel(): void
    {
        $this->actingAs($this->admin);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SWCH-45K',
            'double_thickness' => '4.4',
            'hr_steel_usd' => 0.0,
            'cr_steel_usd' => -20.0,
            'exchange_rate' => 16000.0,
            'wire_price_usd_kg' => 1.64,
            'coil_price' => 1.64,
            'sheet_price' => 1.64,
        ]);

        Livewire::test(ListFasteners::class)
            ->assertCanSeeTableRecords([$fastener])
            ->assertTableColumnDoesNotExist('hr_steel_usd')
            ->assertTableColumnDoesNotExist('cr_steel_usd')
            ->assertTableColumnExists('exchange_rate')
            ->assertTableColumnExists('wire_price_usd_kg')
            ->assertTableColumnExists('coil_price')
            ->assertTableColumnExists('coil_price_idr')
            ->assertTableColumnExists('sheet_price')
            ->assertTableColumnExists('sheet_price_idr')
            ->assertTableColumnExists('part_cost');

        Livewire::test(CreateFastener::class)
            ->assertFormFieldExists('hr_steel_usd')
            ->assertFormFieldExists('cr_steel_usd')
            ->assertFormFieldExists('exchange_rate')
            ->assertFormFieldExists('wire_price_usd_kg')
            ->assertFormFieldExists('coil_price')
            ->assertFormFieldExists('sheet_price');

        $this->assertEquals('$ 1.1', FastenerResource::formatUsd(1.1));
        $this->assertEquals('$ -20', FastenerResource::formatUsd(-20));
        $this->assertEquals('$ 0', FastenerResource::formatUsd(0));
        $this->assertEquals('Rp 16.000', FastenerResource::formatIdr(16000));
    }

    public function test_fastener_q2_adjustment_pulls_rate_and_calculates_from_q1(): void
    {
        $this->actingAs($this->admin);

        // Q1 Baseline record
        Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SWCH-45K',
            'double_thickness' => '4.4',
            'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
            'exchange_rate' => 16000.0,
            'wire_price_usd_kg' => 1.66,
            'coil_price' => 1.66,
            'sheet_price' => 1.66,
        ]);

        // Create Q2 via Livewire
        Livewire::test(CreateFastener::class)
            ->set('data.year', 2026)
            ->set('data.quarter', 'Q2')
            ->set('data.supplier_id', $this->supplier->id)
            ->set('data.steel_spec', 'SWCH-45K')
            ->set('data.cr_steel_usd', -20)
            ->call('create')
            ->assertHasNoFormErrors();

        $q2 = Fastener::where('year', 2026)->where('quarter', 'Q2')->where('steel_spec', 'SWCH-45K')->first();
        $this->assertNotNull($q2);
        // Kurs IDR ditarik sama dengan Q1 (16000)
        $this->assertEquals(16000.0, (float) $q2->exchange_rate);
        // Coil berkurang -0.02 $/kg (1.66 - 0.02 = 1.64)
        $this->assertEquals(1.64, (float) $q2->coil_price);
    }

    public function test_creating_fastener_connects_to_material_steel(): void
    {
        $this->actingAs($this->admin);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.1000,
            'sheet_price' => 1.3000,
            'exchange_rate' => 17000.0,
        ]);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.0800,
            'sheet_price' => 1.2800,
            'exchange_rate' => 18000.0,
        ]);

        // Q1 auto connect to Material Steel Q1
        Livewire::test(CreateFastener::class)
            ->set('data.year', 2026)
            ->set('data.quarter', 'Q1')
            ->set('data.steel_spec', 'SPH270C-OD')
            ->assertSet('data.coil_price', 1.1)
            ->assertSet('data.sheet_price', 1.3)
            ->assertSet('data.double_thickness', '1.6')
            ->assertSet('data.exchange_rate', 17000.0);

        // Q2 auto connect to Material Steel Q2
        Livewire::test(CreateFastener::class)
            ->set('data.year', 2026)
            ->set('data.quarter', 'Q2')
            ->set('data.steel_spec', 'SPH270C-OD')
            ->assertSet('data.coil_price', 1.08)
            ->assertSet('data.sheet_price', 1.28)
            ->assertSet('data.double_thickness', '1.6')
            ->assertSet('data.exchange_rate', 18000.0);
    }

    public function test_fastener_pull_previous_with_target_quarter_and_year(): void
    {
        $this->actingAs($this->admin);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => 'FAST-TARGET-001',
            'part_name' => 'BOLT TEST',
            'steel_spec' => 'SWRCH10A',
            'double_thickness' => '6.0',
            'diameter' => '6.0',
            'coil_price' => 1.4000,
            'sheet_price' => 1.4500,
            'wire_price_usd_kg' => 1.4000,
            'exchange_rate' => 16000.0,
            'part_cost' => 500.0,
        ]);

        Livewire::test(CreateFastener::class)
            ->callAction('pull_previous', [
                'source_year' => 2026,
                'source_quarter' => 'Q1',
                'target_year' => 2026,
                'target_quarter' => 'Q2',
                'source_record_id' => 'fastener:'.$fastener->id,
            ])
            ->assertSet('data.year', 2026)
            ->assertSet('data.quarter', 'Q2')
            ->assertSet('data.part_number', 'FAST-TARGET-001')
            ->assertSet('data.steel_spec', 'SWRCH10A');
    }
}
