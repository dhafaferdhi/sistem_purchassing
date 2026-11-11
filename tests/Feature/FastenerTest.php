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
use App\Services\ExcelExportService;
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
            ->assertTableColumnDoesNotExist('coil_price')
            ->assertTableColumnDoesNotExist('coil_price_idr')
            ->assertTableColumnDoesNotExist('sheet_price')
            ->assertTableColumnDoesNotExist('sheet_price_idr')
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
            ->assertFormFieldExists('wire_price_idr_kg')
            ->assertFormFieldExists('tooling_details')
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
            ->assertTableColumnDoesNotExist('coil_price')
            ->assertTableColumnDoesNotExist('coil_price_idr')
            ->assertTableColumnDoesNotExist('sheet_price')
            ->assertTableColumnDoesNotExist('sheet_price_idr')
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

    public function test_fastener_pull_previous_applies_manual_rate_and_wire_price_across_quarters(): void
    {
        $this->actingAs($this->admin);

        $fastener = Fastener::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => 'FAST-Q2-Q4-001',
            'part_name' => 'BOLT JUMP QUARTER',
            'steel_spec' => 'SWCH-45K',
            'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
            'double_thickness' => '6.0',
            'diameter' => '6.0',
            'coil_price' => 1.6600,
            'wire_price_usd_kg' => 1.6600,
            'gross_weight_gr' => 4.84,
            'washer_price' => 198,
            'exchange_rate' => 16000.0,
            'tooling_details' => [
                ['contents' => 'Heading Die', 'total_cost_krp' => 10000, 'monthly_volume' => 20000, 'depreciation_per_pc' => 15.5],
            ],
        ]);

        // Tooling details automatically summed when tooling_depreciation was 0
        $this->assertEquals(15.5, (float) $fastener->tooling_depreciation);

        Livewire::test(CreateFastener::class)
            ->callAction('pull_previous', [
                'source_year' => 2025,
                'source_quarter' => 'Q2',
                'target_year' => 2025,
                'target_quarter' => 'Q4',
                'source_record_id' => 'fastener:'.$fastener->id,
                'exchange_rate' => 19500,
                'wire_price_usd_kg' => 2.0200,
            ])
            ->assertSet('data.year', 2025)
            ->assertSet('data.quarter', 'Q4')
            ->assertSet('data.exchange_rate', 19500.0)
            ->assertSet('data.old_exchange_rate', 16000.0)
            ->assertSet('data.exchange_rate_fluctuation', round(19500 / 16000, 6))
            ->assertSet('data.wire_price_usd_kg', 2.02)
            ->assertSet('data.wire_price_idr_kg', 39390.0)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fasteners', [
            'year' => 2025,
            'quarter' => 'Q4',
            'part_number' => 'FAST-Q2-Q4-001',
            'exchange_rate' => 19500.0,
            'wire_price_usd_kg' => 2.02,
            'wire_price_idr_kg' => 39390.0,
        ]);
    }

    public function test_fastener_pull_previous_copies_all_sections_one_to_three_identically(): void
    {
        $this->actingAs($this->admin);

        $q1 = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '90041-19960',
            'old_part_number' => '90041-19959',
            'part_name' => 'BOLT, W/WASHER CUSTOM',
            'model_name' => 'D99Z',
            'monthly_volume' => 35000,
            'drawing_reference' => 'Official Dwg R3',
            'eci_number' => 'ECI-FAST-09',
            'steel_spec' => 'SWCH-45K',
            'double_thickness' => '8.0',
            'diameter' => '8.0',
            'wire_spec' => 'CUSTOM WIRE SPEC',
            'gross_weight_gr' => 5.5,
            'nett_weight_gr' => 5.0,
            'wire_price_usd_kg' => 2.15,
            'coil_price' => 2.15,
            'material_control_percent' => 7.0,
            'washer_price' => 250,
            'washer_weight_gr' => 4.0,
            'heading_cost' => 55,
            'rolling_cost' => 85,
            'washing_cost_kg' => 600,
            'plating_cost_kg' => 12000,
            'inspection_cost' => 10,
            'packing_cost' => 6,
            'overhead_self_percent' => 15.0,
            'tooling_depreciation' => 20.0,
            'tooling_details' => [
                ['contents' => 'HEADING DIE', 'total_cost_krp' => 15000, 'monthly_volume' => 35000, 'depreciation_per_pc' => 20],
            ],
            'exchange_rate' => 17150.0,
            'notes' => 'Nitto Alam agreement 2026',
        ]);

        Livewire::test(CreateFastener::class)
            ->mountAction('pull_previous')
            ->set('mountedActionsData.0.source_year', 2026)
            ->set('mountedActionsData.0.source_quarter', 'Q1')
            ->set('mountedActionsData.0.target_year', 2026)
            ->set('mountedActionsData.0.target_quarter', 'Q2')
            ->set('mountedActionsData.0.source_record_id', 'fastener:'.$q1->id)
            ->assertSet('mountedActionsData.0.exchange_rate', 17150)
            ->assertSet('mountedActionsData.0.wire_price_usd_kg', 2.15)
            ->callMountedAction()
            // Section 1
            ->assertSet('data.supplier_id', $this->supplier->id)
            ->assertSet('data.part_number', '90041-19960')
            ->assertSet('data.old_part_number', '90041-19959')
            ->assertSet('data.part_name', 'BOLT, W/WASHER CUSTOM')
            ->assertSet('data.model_name', 'D99Z')
            ->assertSet('data.monthly_volume', 35000)
            ->assertSet('data.drawing_reference', 'Official Dwg R3')
            ->assertSet('data.eci_number', 'ECI-FAST-09')
            ->assertSet('data.steel_spec', 'SWCH-45K')
            ->assertSet('data.double_thickness', '8.0')
            ->assertSet('data.wire_spec', 'CUSTOM WIRE SPEC')
            // Section 2
            ->assertSet('data.exchange_rate', 17150.0)
            ->assertSet('data.wire_price_usd_kg', 2.15)
            ->assertSet('data.wire_price_idr_kg', (float) $q1->wire_price_idr_kg)
            // Section 3
            ->assertSet('data.gross_weight_gr', 5.5)
            ->assertSet('data.nett_weight_gr', 5.0)
            ->assertSet('data.material_control_percent', 7.0)
            ->assertSet('data.washer_price', 250.0)
            ->assertSet('data.washer_weight_gr', 4.0)
            ->assertSet('data.heading_cost', 55.0)
            ->assertSet('data.rolling_cost', 85.0)
            ->assertSet('data.washing_cost_kg', 600.0)
            ->assertSet('data.plating_cost_kg', 12000.0)
            ->assertSet('data.inspection_cost', 10.0)
            ->assertSet('data.packing_cost', 6.0)
            ->assertSet('data.overhead_self_percent', 15.0)
            ->assertSet('data.tooling_depreciation', 20.0)
            ->assertSet('data.part_cost', (float) $q1->part_cost);
    }

    public function test_fastener_excel_template_and_export_download(): void
    {
        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '90041-19960',
            'part_name' => 'BOLT,W/WASHER',
            'model_name' => 'D26A',
            'steel_spec' => 'SWCH-45K',
            'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
            'wire_price_usd_kg' => 2.02,
            'gross_weight_gr' => 4.84,
            'nett_weight_gr' => 4.4,
            'washer_weight_gr' => 4.5,
            'washer_price' => 198,
            'exchange_rate' => 16465,
        ]);

        $templateResponse = ExcelExportService::downloadFastenerTemplate('xlsx');
        $this->assertEquals(200, $templateResponse->getStatusCode());
        $this->assertStringContainsString('Template_Import_Fastener.xlsx', $templateResponse->headers->get('Content-Disposition') ?? '');

        $exportResponse = ExcelExportService::exportFastener([$fastener], 'xlsx');
        $this->assertEquals(200, $exportResponse->getStatusCode());
        $this->assertStringContainsString('Fasteners_Export_', $exportResponse->headers->get('Content-Disposition') ?? '');
    }

    public function test_can_call_bulk_action_export_and_delete_on_fasteners(): void
    {
        $this->actingAs($this->admin);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '90041-19960',
            'part_name' => 'BOLT,W/WASHER',
            'model_name' => 'D26A',
            'steel_spec' => 'SWCH-45K',
            'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
            'wire_price_usd_kg' => 2.02,
            'gross_weight_gr' => 4.84,
            'nett_weight_gr' => 4.4,
            'washer_weight_gr' => 4.5,
            'washer_price' => 198,
            'exchange_rate' => 16465,
        ]);

        Livewire::test(ListFasteners::class)
            ->callTableBulkAction('export_selected_xlsx', [$fastener])
            ->assertSuccessful();

        Livewire::test(ListFasteners::class)
            ->callTableBulkAction('export_selected_csv', [$fastener])
            ->assertSuccessful();

        Livewire::test(ListFasteners::class)
            ->callTableBulkAction('delete', [$fastener])
            ->assertSuccessful();

        $this->assertDatabaseMissing('fasteners', ['id' => $fastener->id]);
    }

    public function test_fastener_book2_excel_q1_to_q4_2026_and_extended_breakdown_formulas(): void
    {
        // Verifikasi Q1 2026 Book2.xlsx (5. 90041-19960): Kurs 16465, Wire 2.02 -> Rp 677,20
        $q1 = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => 'PT. NITTO ALAM INDONESIA',
            'part_number' => '90041-19960',
            'part_name' => 'BOLT,W/WASHER',
            'steel_spec' => 'SWCH-45K',
            'wire_spec' => 'WIRE OF RIVET [SWCH-45K](TAIWAN)',
            'nett_weight_gr' => 4.4,
            'gross_weight_gr' => 0, // Otomatis terhitung Nett * 1.1 = 4.84
            'wire_price_usd_kg' => 2.02,
            'material_control_percent' => 5,
            'washer_price' => 198,
            'washer_weight_gr' => 4.5,
            'heading_cost' => 48,
            'rolling_cost' => 76,
            'washing_cost_kg' => 500,
            'plating_cost_kg' => 10000,
            'inspection_cost' => 5,
            'packing_cost' => 3,
            'overhead_self_percent' => 14.3,
            'old_exchange_rate' => 16321,
            'exchange_rate' => 16465,
        ]);

        $this->assertEqualsWithDelta(4.84, (float) $q1->gross_weight_gr, 0.0001);
        $this->assertEquals(33259.30, (float) $q1->wire_price_idr_kg);
        $this->assertEqualsWithDelta(160.9750, (float) $q1->wire_price_pc, 0.001);
        $this->assertEqualsWithDelta(367.0238, (float) $q1->material_total, 0.002);
        $this->assertEqualsWithDelta(225.45, (float) $q1->process_total, 0.001);
        $this->assertEqualsWithDelta(592.4738, (float) $q1->subtotal, 0.002);
        $this->assertEqualsWithDelta(84.7238, (float) $q1->overhead_self_cost, 0.002);
        $this->assertEqualsWithDelta(677.1976, (float) $q1->part_cost_excluding_tooling, 0.01);
        $this->assertEqualsWithDelta(677.1976, (float) $q1->part_cost, 0.01);

        // Uji komponen tambahan (4) OH Supplied, (6) Transport, (7) Cost Down, dan update Nett -> Gross auto * 1.1
        $q1->update([
            'nett_weight_gr' => 5.0,
            'overhead_supplied_percent' => 5.0,
            'transportation_cost' => 12.0,
            'cost_down' => -7.0,
            'tooling_depreciation' => 10.0,
        ]);

        $q1->refresh();
        $this->assertEqualsWithDelta(5.5, (float) $q1->gross_weight_gr, 0.0001);
        $this->assertEqualsWithDelta((0.05 * (float) $q1->subtotal), (float) $q1->overhead_supplied_cost, 0.01);
        $this->assertEqualsWithDelta(
            (float) $q1->subtotal + (float) $q1->overhead_supplied_cost + (float) $q1->overhead_self_cost + 12.0 - 7.0,
            (float) $q1->part_cost_excluding_tooling,
            0.01
        );
        $this->assertEqualsWithDelta((float) $q1->part_cost_excluding_tooling + 10.0, (float) $q1->part_cost, 0.01);
    }

    public function test_fastener_direct_idr_input_and_part_number_quarter_lookup(): void
    {
        Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'part_number' => 'PART-A',
            'steel_spec' => 'SWCH-45K',
            'wire_price_usd_kg' => 1.80,
            'exchange_rate' => 16000,
        ]);

        Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'part_number' => 'PART-B',
            'steel_spec' => 'SWCH-45K',
            'wire_price_usd_kg' => 2.50,
            'exchange_rate' => 16000,
        ]);

        $lookupA = Fastener::findPreviousQuarterData('Q2', 2026, $this->supplier->id, 'SWCH-45K', 'PART-A');
        $this->assertNotNull($lookupA);
        $this->assertEquals('PART-A', $lookupA['part_number']);
        $this->assertEquals(1.80, $lookupA['wire_price_usd_kg']);

        // Input harga IDR langsung tanpa USD
        $directIdr = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'part_number' => 'DIRECT-IDR-01',
            'steel_spec' => 'SWCH-45K',
            'exchange_rate' => 0,
            'wire_price_usd_kg' => 0,
            'wire_price_idr_kg' => 35000.00,
            'nett_weight_gr' => 4.0,
            'gross_weight_gr' => 4.4,
        ]);

        $this->assertEquals(35000.00, (float) $directIdr->wire_price_idr_kg);
        $this->assertEqualsWithDelta(154.0, (float) $directIdr->wire_price_pc, 0.01);
    }

    public function test_excel_import_fastener_preserves_all_fastener_fields(): void
    {
        $csvContent = implode("\n", [
            'Kuartal,Tahun,Supplier,Part Number,Old Part No.,Part Name,Model,Monthly Volume,Wire Spec,Nett Weight,Gross Weight,Old Rate,Kurs USD,Wire ($/kg),Mat Control (%),Washer Price,Washer Weight,Heading Cost,Rolling Cost,Washing Cost/kg,Plating Cost/kg,Inspection Cost,Packing Cost,OH Supplied %,OH Self %,Transportation Cost,Cost Down,Comments,Remarks',
            'Q1,2026,PT. NITTO ALAM INDONESIA,90041-19960,90041-OLD,BOLT W/WASHER,D26A,20000,WIRE OF RIVET [SWCH-45K](TAIWAN),4.4,4.84,16321,16465,2.02,5,198,4.5,48,76,500,10000,5,3,0,14.3,0,-5,Nitto Quote Q1,Kurs BoI',
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'fastener_full_import').'.csv';
        file_put_contents($tempPath, $csvContent);

        $result = ExcelImportService::importFastener($tempPath, ['year' => 2026, 'quarter' => 'Q1']);
        @unlink($tempPath);

        $this->assertEquals(1, $result['imported']);
        $imported = Fastener::where('part_number', '90041-19960')->where('quarter', 'Q1')->where('year', 2026)->first();
        $this->assertNotNull($imported);
        $this->assertEquals('90041-OLD', $imported->old_part_number);
        $this->assertEquals(20000, (int) $imported->monthly_volume);
        $this->assertEquals('WIRE OF RIVET [SWCH-45K](TAIWAN)', $imported->wire_spec);
        $this->assertEquals(2.02, (float) $imported->wire_price_usd_kg);
        $this->assertEquals(14.3, (float) $imported->overhead_self_percent);
        $this->assertEquals(-5.0, (float) $imported->cost_down);
        $this->assertEquals(16321.0, (float) $imported->old_exchange_rate);
        $this->assertEquals('Nitto Quote Q1', $imported->comments);
        $this->assertEquals('Kurs BoI', $imported->remarks);
        $this->assertEqualsWithDelta(672.1976, (float) $imported->part_cost, 0.02);
    }

    public function test_fastener_sheet_modal_renders_complete_breakdown_and_safe_empty_part_number(): void
    {
        // Dua record dengan part_number kosong TIDAK boleh saling dibandingkan di modal
        $emptyPart = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'part_number' => null,
            'steel_spec' => 'SWMB',
            'overhead_self_percent' => 15.0,
        ]);
        Fastener::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'part_number' => null,
            'steel_spec' => 'SWRCH10A',
            'overhead_self_percent' => 14.3,
        ]);

        $emptyHtml = view('filament.resources.fasteners.sheet-modal', ['record' => $emptyPart])->render();
        $this->assertStringContainsString('Belum ada data kuartal pembanding', $emptyHtml);

        // Dua record dengan part_number sama dan beda O/H Self % (15% vs 14.3%), footer & heading die total
        $q1 = Fastener::create([
            'year' => 2025,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'part_number' => '90041-MODAL',
            'part_name' => 'BOLT MODAL TEST',
            'steel_spec' => 'SWCH-45K',
            'overhead_self_percent' => 15.0,
            'exchange_rate' => 14752,
        ]);
        $q2 = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'part_number' => '90041-MODAL',
            'part_name' => 'BOLT MODAL TEST',
            'steel_spec' => 'SWCH-45K',
            'overhead_self_percent' => 14.3,
            'old_exchange_rate' => 14752,
            'exchange_rate' => 16465,
            'transportation_cost' => 10,
            'cost_down' => -5,
            'comments' => 'Valid Q1 Comment',
            'remarks' => 'Valid Q1 Remark',
            'tooling_details' => [
                ['contents' => 'Heading Die', 'total_cost_krp' => 20000, 'monthly_volume' => 20000, 'depreciation_per_pc' => 10],
            ],
        ]);

        $modalHtml = view('filament.resources.fasteners.sheet-modal', ['record' => $q2])->render();
        $this->assertStringContainsString('15% ➔ 14.3%', $modalHtml);
        $this->assertStringContainsString('(4) : O/H &amp; Profit supplied portion', $modalHtml);
        $this->assertStringContainsString('(6) : Transportation or others', $modalHtml);
        $this->assertStringContainsString('(7) : Cost Down', $modalHtml);
        $this->assertStringContainsString('(8) : Part Cost (excluding tooling cost)', $modalHtml);
        $this->assertStringContainsString('Total Tooling', $modalHtml);
        $this->assertStringContainsString('Valid Q1 Comment', $modalHtml);
        $this->assertStringContainsString('Valid Q1 Remark', $modalHtml);
    }
}
