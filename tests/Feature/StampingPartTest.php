<?php

namespace Tests\Feature;

use App\Filament\Resources\StampingPartResource;
use App\Filament\Resources\StampingPartResource\Pages\CreateStampingPart;
use App\Filament\Resources\StampingPartResource\Pages\ListStampingParts;
use App\Models\Currency;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CurrencyRecalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StampingPartTest extends TestCase
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
            'code' => 'VND-HSSI',
            'name' => 'PT HSSI',
            'category' => 'Steel',
        ]);

        $this->currency = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'year' => 2026,
            'exchange_rate_to_idr' => 16000.0,
        ]);
    }

    public function test_can_view_stamping_parts_table_and_columns(): void
    {
        $this->actingAs($this->admin);

        $stampingPart = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-SD',
            'double_thickness' => '1.2',
            'size' => '1200 x 2400',
            'berat' => 0.8500,
            'gap_steel_usd' => 50.0,
            'exchange_rate' => 16000.0,
            'coil_price' => 1.8500,
            'sheet_price' => 1.9000,
        ]);

        Livewire::test(ListStampingParts::class)
            ->assertCanSeeTableRecords([$stampingPart])
            ->assertTableColumnExists('steel_spec')
            ->assertTableColumnExists('double_thickness')
            ->assertTableColumnExists('size')
            ->assertTableColumnExists('berat')
            ->assertTableColumnExists('gap_steel_usd')
            ->assertTableColumnExists('gap_steel_kg_usd')
            ->assertTableColumnExists('exchange_rate')
            ->assertTableColumnExists('coil_price')
            ->assertTableColumnExists('coil_price_idr')
            ->assertTableColumnExists('sheet_price')
            ->assertTableColumnExists('sheet_price_idr')
            ->assertTableColumnExists('part_cost')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('delete')
            ->mountTableAction('view_sheet', $stampingPart);
    }

    public function test_stamping_part_form_has_all_required_fields_and_back_action(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateStampingPart::class)
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
            ->assertFormFieldExists('wide')
            ->assertFormFieldExists('pitch')
            ->assertFormFieldExists('yield_rate')
            ->assertFormFieldExists('size')
            ->assertFormFieldExists('berat')
            ->assertFormFieldExists('scrap_weight')
            ->assertFormFieldExists('scrap_price_per_kg')
            ->assertFormFieldExists('process_name')
            ->assertFormFieldExists('process_man_hour')
            ->assertFormFieldExists('process_charge')
            ->assertFormFieldExists('overhead_supplied_percent')
            ->assertFormFieldExists('overhead_self_percent')
            ->assertFormFieldExists('gap_steel_usd')
            ->assertFormFieldExists('gap_steel_kg_usd')
            ->assertFormFieldExists('exchange_rate')
            ->assertFormFieldExists('coil_price')
            ->assertFormFieldExists('coil_price_idr')
            ->assertFormFieldExists('sheet_price')
            ->assertFormFieldExists('sheet_price_idr')
            ->assertActionExists('back');
    }

    public function test_stamping_part_calculates_posmi_excel_comparative_quotation_formulas(): void
    {
        $part = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '72388-X7V00-A',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'model_name' => 'D26A',
            'monthly_volume' => 16500,
            'steel_spec' => 'SPH270-OD',
            'double_thickness' => '1.2',
            'wide' => 177,
            'pitch' => 165,
            'yield_rate' => 1,
            'berat' => 0.2780,
            'scrap_weight' => 0.2780,
            'scrap_price_per_kg' => -1500,
            'process_name' => '1. PROGRESSIVE 250T',
            'process_man_hour' => 9,
            'process_charge' => 45,
            'overhead_supplied_percent' => 11,
            'overhead_self_percent' => 4,
            'exchange_rate' => 16000.0,
            'coil_price' => 1.8500,
            'sheet_price' => 1.8500,
        ]);

        // coil_price_idr = 1.85 * 16000 = 29600
        $this->assertEquals(29600.00, (float) $part->coil_price_idr);
        // gross_material_cost = 0.2780 * 29600 = 8228.80
        $this->assertEquals(8228.80, (float) $part->gross_material_cost);
        // scrap_cost = 0.2780 * -1500 = -417.00
        $this->assertEquals(-417.00, (float) $part->scrap_cost);
        // material_total = 8228.80 + (-417.00) = 7811.80
        $this->assertEquals(7811.80, (float) $part->material_total);
        // process_total = 9 * 45 = 405.00
        $this->assertEquals(405.00, (float) $part->process_total);
        // subtotal = 7811.80 + 405.00 = 8216.80
        $this->assertEquals(8216.80, (float) $part->subtotal);
        // overhead_supplied_cost = 11% * 405.00 = 44.55
        $this->assertEquals(44.55, (float) $part->overhead_supplied_cost);
        // overhead_self_cost = 4% * 7811.80 = 312.47
        $this->assertEquals(312.47, (float) $part->overhead_self_cost);
        // part_cost = 8216.80 + 44.55 + 312.47 = 8573.82
        $this->assertEquals(8573.82, (float) $part->part_cost);
    }

    public function test_stamping_part_matches_exact_posmi_excel_with_yield_rate_2(): void
    {
        // Parameter persis dari Excel PT Posmi Steel:
        // SPH440-OD, Thickness 2.6, Wide 390, Pitch 148, Yield 2, Weight 0.5890326 kg
        // Steel Rate IDR: 17617.60, Scrap: 0.278 kg @ -1500
        // Progressive 250T: 9 sec, 45 charge, yield 2 -> (9 * 45) / 2 = 202.50
        // O/H Supplied (11% of process) = 22.275, O/H Self (4% of material total) = 398.4136
        // Part Cost = 10583.5294
        $part = StampingPart::create([
            'year' => 2022,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => 'PT. POSMI STEEL INDONESIA',
            'part_number' => '72388-X7V00-A',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'model_name' => 'D26A',
            'monthly_volume' => 16500,
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'wide' => 390,
            'pitch' => 148,
            'yield_rate' => 2,
            'berat' => 0.5890,
            'scrap_weight' => 0.2780,
            'scrap_price_per_kg' => -1500,
            'process_name' => '1. PROGRESSIVE 250T',
            'process_man_hour' => 9,
            'process_charge' => 45,
            'overhead_supplied_percent' => 11,
            'overhead_self_percent' => 4,
            'exchange_rate' => 14300.0,
            'coil_price' => 1.232, // 17617.6 / 14300
            'sheet_price' => 1.232,
        ]);

        $this->assertEquals(202.50, (float) $part->process_total);
        $this->assertEquals(22.28, (float) $part->overhead_supplied_cost);
    }

    public function test_stamping_part_calculates_formulas_matching_material_steel(): void
    {
        // Uji perhitungan rumus:
        // Gap/MT = -20 USD/MT -> Gap/Kg = -0.02 USD/Kg
        // Coil = 1.8000, Sheet = 1.7800
        // Ex Rate = 18.000 IDR
        // Coil IDR = 1.80 * 18000 = 32400
        // Sheet IDR = 1.78 * 18000 = 32040
        // Thickness = '1.6 mm' -> dibersihkan jadi '1.6'
        $part = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPHC-PO',
            'double_thickness' => '1.6 mm',
            'size' => '1200 x 2400',
            'berat' => 1.2500,
            'gap_steel_usd' => -20.0,
            'exchange_rate' => 18000.0,
            'coil_price' => 1.8000,
            'sheet_price' => 1.7800,
        ]);

        $this->assertEquals('1.6', $part->double_thickness);
        $this->assertEquals('1200 x 2400', $part->size);
        $this->assertEquals(1.2500, (float) $part->berat);
        $this->assertEquals(1.2500, (float) $part->weight);
        $this->assertEquals(-20.0000, (float) $part->gap_steel_usd);
        $this->assertEquals(-0.0200, (float) $part->gap_steel_kg_usd);
        $this->assertEquals(32400.00, (float) $part->coil_price_idr);
        $this->assertEquals(32040.00, (float) $part->sheet_price_idr);
    }

    public function test_q1_2027_pulls_data_from_q4_2026(): void
    {
        $this->actingAs($this->admin);

        // Buat data acuan di Q4 2026 pada StampingPart
        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-SD-AUTOMOTIVE',
            'double_thickness' => '2.0',
            'size' => '1200 x 2400',
            'berat' => 1.5500,
            'gap_steel_usd' => 45.0,
            'gap_steel_kg_usd' => 0.045,
            'exchange_rate' => 16500.0,
            'coil_price' => 1.9500,
            'sheet_price' => 1.9950,
        ]);

        // Uji fungsi helper lookup: Q1 2027 harus mengambil data dari Q4 2026
        $prevData = StampingPart::findPreviousQuarterData('Q1', 2027, $this->supplier->id);

        $this->assertNotNull($prevData);
        $this->assertEquals(2026, $prevData['prev_year']);
        $this->assertEquals('Q4', $prevData['prev_quarter']);
        $this->assertEquals('SPCC-SD-AUTOMOTIVE', $prevData['steel_spec']);
        $this->assertEquals('2.0', $prevData['double_thickness']);
        $this->assertEquals('1200 x 2400', $prevData['size']);
        $this->assertEquals(1.5500, (float) $prevData['berat']);
        $this->assertEquals(45.0, $prevData['gap_steel_usd']);
        $this->assertEquals(0.045, $prevData['gap_steel_kg_usd']);
        $this->assertEquals(1.9500, $prevData['coil_price']);
        $this->assertEquals(1.9950, $prevData['sheet_price']);

        // Uji action 'pull_previous' pada Create form
        Livewire::test(CreateStampingPart::class)
            ->fillForm([
                'year' => 2027,
                'quarter' => 'Q1',
                'supplier_id' => $this->supplier->id,
            ])
            ->callAction('pull_previous')
            ->assertFormSet([
                'steel_spec' => 'SPCC-SD-AUTOMOTIVE',
                'double_thickness' => '2.0',
                'size' => '1200 x 2400',
                'berat' => '1.5500',
            ]);
    }

    public function test_q1_2027_pulls_from_material_steel_q4_2026_if_no_stamping_part_record(): void
    {
        $this->actingAs($this->admin);

        // Buat data acuan di Q4 2026 pada MaterialSteel
        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPHC-FALLBACK-SPEC',
            'double_thickness' => '1.4',
            'currency_id' => $this->currency->id,
            'gap_steel_usd' => 60.0,
            'gap_steel_kg_usd' => 0.06,
            'exchange_rate' => 16200.0,
            'coil_price' => 1.8800,
            'sheet_price' => 1.9400,
        ]);

        // Uji fallback ke MaterialSteel: Q1 2027 mengambil data dari MaterialSteel Q4 2026
        $prevData = StampingPart::findPreviousQuarterData('Q1', 2027, $this->supplier->id);

        $this->assertNotNull($prevData);
        $this->assertEquals('MaterialSteel', $prevData['source']);
        $this->assertEquals(2026, $prevData['prev_year']);
        $this->assertEquals('Q4', $prevData['prev_quarter']);
        $this->assertEquals('SPHC-FALLBACK-SPEC', $prevData['steel_spec']);
        $this->assertEquals(60.0, $prevData['gap_steel_usd']);
    }

    public function test_can_create_stamping_part_successfully_with_selected_supplier(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateStampingPart::class)
            ->fillForm([
                'year' => 2026,
                'quarter' => 'Q3',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPCC-TEST-CREATE',
                'double_thickness' => '1.4',
                'gap_steel_usd' => 50.0,
                'exchange_rate' => 16000.0,
                'coil_price' => 1.8000,
                'sheet_price' => 1.8500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stamping_parts', [
            'year' => 2026,
            'quarter' => 'Q3',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => 'PT HSSI',
            'vendor_code' => 'VND-HSSI',
            'steel_spec' => 'SPCC-TEST-CREATE',
            'double_thickness' => '1.4',
            'gap_steel_usd' => 50.0000,
            'gap_steel_kg_usd' => 0.0500,
            'coil_price' => 1.8000,
            'sheet_price' => 1.8500,
        ]);
    }

    public function test_can_create_stamping_part_with_custom_typed_supplier(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateStampingPart::class)
            ->fillForm([
                'year' => 2026,
                'quarter' => 'Q4',
                'supplier_name' => 'PT Metal Baja Presisi',
                'vendor_code' => 'VND-MBP-01',
                'steel_spec' => 'SPHC-CUSTOM-STAMP',
                'double_thickness' => '2.5 mm',
                'gap_steel_usd' => -30.0,
                'exchange_rate' => 16500.0,
                'coil_price' => 2.0000,
                'sheet_price' => 1.9700,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stamping_parts', [
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_name' => 'PT Metal Baja Presisi',
            'vendor_code' => 'VND-MBP-01',
            'steel_spec' => 'SPHC-CUSTOM-STAMP',
            'double_thickness' => '2.5',
            'gap_steel_usd' => -30.0000,
            'gap_steel_kg_usd' => -0.0300,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'PT Metal Baja Presisi',
            'code' => 'VND-MBP-01',
        ]);
    }

    public function test_can_switch_years_and_view_all_years_by_default(): void
    {
        $this->actingAs($this->admin);

        $part2026 = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPEC-2026',
        ]);

        $part2027 = StampingPart::create([
            'year' => 2027,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPEC-2027',
        ]);

        // Default: Semua Tahun aktif (keduanya terlihat)
        Livewire::test(ListStampingParts::class)
            ->assertCanSeeTableRecords([$part2026, $part2027])
            ->set('selectedYear', '2027')
            ->assertCanSeeTableRecords([$part2027])
            ->assertCanNotSeeTableRecords([$part2026])
            ->set('selectedYear', '2026')
            ->assertCanSeeTableRecords([$part2026])
            ->assertCanNotSeeTableRecords([$part2027])
            ->set('selectedYear', '')
            ->assertCanSeeTableRecords([$part2026, $part2027]);
    }

    public function test_creating_q1_2027_auto_populates_from_q4_2026_and_saves_successfully(): void
    {
        $this->actingAs($this->admin);

        // Buat data acuan di Q4 2026
        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH270-AUTO',
            'double_thickness' => '1.6',
            'size' => '150 x 200',
            'berat' => 0.8500,
            'gap_steel_usd' => 80.0,
            'exchange_rate' => 16000.0,
            'coil_price' => 1.85,
            'sheet_price' => 1.93,
        ]);

        // Simulasikan pembuatan data Q1 2027:
        // Saat user mengubah year ke 2027 dan quarter ke Q1, data otomatis terisi dari Q4 2026
        Livewire::test(CreateStampingPart::class)
            ->set('data.year', 2027)
            ->set('data.quarter', 'Q1')
            ->assertFormSet([
                'steel_spec' => 'SPH270-AUTO',
                'double_thickness' => '1.6',
                'size' => '150 x 200',
                'berat' => '0.8500',
                'gap_steel_usd' => 0,
                'coil_price' => '1.85',
                'sheet_price' => '1.93',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stamping_parts', [
            'year' => 2027,
            'quarter' => 'Q1',
            'steel_spec' => 'SPH270-AUTO',
            'double_thickness' => '1.6',
            'size' => '150 x 200',
            'berat' => 0.8500,
            'gap_steel_usd' => 0.0,
            'gap_steel_kg_usd' => 0.0,
        ]);
    }

    public function test_stamping_parts_table_and_form_harmonized_with_material_steel(): void
    {
        $this->actingAs($this->admin);

        $part = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH270-OD',
            'double_thickness' => '1.2',
            'hr_steel_usd' => -20.0,
            'cr_steel_usd' => 0.0,
            'exchange_rate' => 16000.0,
            'coil_price' => 1.1006,
            'sheet_price' => 1.1256,
        ]);

        Livewire::test(ListStampingParts::class)
            ->assertCanSeeTableRecords([$part])
            ->assertTableColumnDoesNotExist('hr_steel_usd')
            ->assertTableColumnDoesNotExist('cr_steel_usd')
            ->assertTableColumnExists('exchange_rate')
            ->assertTableColumnExists('coil_price')
            ->assertTableColumnExists('coil_price_idr')
            ->assertTableColumnExists('sheet_price')
            ->assertTableColumnExists('sheet_price_idr')
            ->assertTableColumnExists('part_cost');

        Livewire::test(CreateStampingPart::class)
            ->assertFormFieldExists('hr_steel_usd')
            ->assertFormFieldExists('cr_steel_usd')
            ->assertFormFieldExists('exchange_rate')
            ->assertFormFieldExists('coil_price')
            ->assertFormFieldExists('sheet_price');

        // Uji helper format mata uang tanpa desimal nol tak perlu
        $this->assertEquals('$ 1.1', StampingPartResource::formatUsd(1.1));
        $this->assertEquals('$ -20', StampingPartResource::formatUsd(-20));
        $this->assertEquals('$ 0', StampingPartResource::formatUsd(0));
        $this->assertEquals('Rp 16.000', StampingPartResource::formatIdr(16000));
    }

    public function test_stamping_part_q2_adjustment_pulls_rate_and_calculates_from_q1(): void
    {
        $this->actingAs($this->admin);

        // Q1 Baseline record
        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH270-OD',
            'double_thickness' => '1.2',
            'exchange_rate' => 16000.0,
            'coil_price' => 1.1206,
            'sheet_price' => 1.1456,
            'berat' => 0.2780,
            'scrap_weight' => 0.2780,
        ]);

        // Create Q2 via Livewire
        Livewire::test(CreateStampingPart::class)
            ->set('data.year', 2026)
            ->set('data.quarter', 'Q2')
            ->set('data.supplier_id', $this->supplier->id)
            ->set('data.steel_spec', 'SPH270-OD')
            ->set('data.hr_steel_usd', -20)
            ->call('create')
            ->assertHasNoFormErrors();

        $q2 = StampingPart::where('year', 2026)->where('quarter', 'Q2')->where('steel_spec', 'SPH270-OD')->first();
        $this->assertNotNull($q2);
        // Kurs IDR ditarik sama dengan Q1 (16000)
        $this->assertEquals(16000.0, (float) $q2->exchange_rate);
        // Coil berkurang -0.02 $/kg (1.1206 - 0.02 = 1.1006)
        $this->assertEquals(1.1006, (float) $q2->coil_price);
        $this->assertEquals(1.1256, (float) $q2->sheet_price);
    }

    public function test_creating_stamping_part_connects_to_material_steel(): void
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
        Livewire::test(CreateStampingPart::class)
            ->set('data.year', 2026)
            ->set('data.quarter', 'Q1')
            ->set('data.steel_spec', 'SPH270C-OD')
            ->assertSet('data.coil_price', 1.1)
            ->assertSet('data.sheet_price', 1.3)
            ->assertSet('data.double_thickness', '1.6')
            ->assertSet('data.exchange_rate', 17000.0);

        // Q2 auto connect to Material Steel Q2
        Livewire::test(CreateStampingPart::class)
            ->set('data.year', 2026)
            ->set('data.quarter', 'Q2')
            ->set('data.steel_spec', 'SPH270C-OD')
            ->assertSet('data.coil_price', 1.08)
            ->assertSet('data.sheet_price', 1.28)
            ->assertSet('data.double_thickness', '1.6')
            ->assertSet('data.exchange_rate', 18000.0);
    }

    public function test_stamping_part_pull_previous_with_target_quarter_and_year(): void
    {
        $this->actingAs($this->admin);

        $stamping = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => 'STAMP-TARGET-001',
            'part_name' => 'BRACKET TEST',
            'steel_spec' => 'SPHC-PULL',
            'double_thickness' => '2.0',
            'size' => '2.0 x 100 x 200',
            'berat' => 0.5000,
            'coil_price' => 1.5000,
            'sheet_price' => 1.5500,
            'exchange_rate' => 16000.0,
            'part_cost' => 15000.0,
        ]);

        Livewire::test(CreateStampingPart::class)
            ->callAction('pull_previous', [
                'source_year' => 2026,
                'source_quarter' => 'Q1',
                'target_year' => 2026,
                'target_quarter' => 'Q2',
                'source_record_id' => 'stamping:'.$stamping->id,
            ])
            ->assertSet('data.year', 2026)
            ->assertSet('data.quarter', 'Q2')
            ->assertSet('data.part_number', 'STAMP-TARGET-001')
            ->assertSet('data.steel_spec', 'SPHC-PULL')
            ->assertSet('data.coil_price', 1.5)
            ->assertSet('data.sheet_price', 1.55);
    }

    public function test_stamping_part_pull_previous_excludes_already_existing_parts_in_target_quarter(): void
    {
        $this->actingAs($this->admin);

        // Part 1: Ada di Q1 dan SUDAH ada di Q2
        $part1Q1 = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => 'STAMP-EXCLUDE-001',
            'part_name' => 'SUDAH ADA DI Q2',
            'steel_spec' => 'SPHC 1.6',
            'sheet_price' => 1.55,
            'part_cost' => 12000.0,
        ]);

        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => 'STAMP-EXCLUDE-001',
            'part_name' => 'SUDAH ADA DI Q2',
            'steel_spec' => 'SPHC 1.6',
            'sheet_price' => 1.55,
            'part_cost' => 12000.0,
        ]);

        // Part 2: Ada di Q1 dan BELUM ada di Q2
        $part2Q1 = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => 'STAMP-REMAINING-002',
            'part_name' => 'BELUM ADA DI Q2',
            'steel_spec' => 'SPHC 1.6',
            'sheet_price' => 1.60,
            'part_cost' => 13500.0,
        ]);

        $component = Livewire::test(CreateStampingPart::class)
            ->set('data.year', 2026)
            ->set('data.quarter', 'Q2')
            ->mountAction('pull_previous');

        $form = $component->instance()->getMountedActionForm();
        $select = collect($form->getFlatComponents())->first(fn ($c) => $c instanceof \Filament\Forms\Components\Select && $c->getName() === 'source_record_id');
        $this->assertNotNull($select);
        $options = $select->getOptions();

        $this->assertArrayHasKey('stamping:'.$part2Q1->id, $options);
        $this->assertArrayNotHasKey('stamping:'.$part1Q1->id, $options);
    }

    public function test_stamping_part_pull_previous_applies_manual_rate_and_sheet_price_across_quarters(): void
    {
        $this->actingAs($this->admin);

        $stamping = StampingPart::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => 'STAMP-Q2-Q4-001',
            'part_name' => 'BRACKET JUMP QUARTER',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.0',
            'size' => '2.0 x 100 x 200',
            'berat' => 0.5000,
            'scrap_weight' => 0.1000,
            'scrap_price_per_kg' => -1500,
            'coil_price' => 1.2000,
            'sheet_price' => 1.2500,
            'exchange_rate' => 16000.0,
        ]);

        Livewire::test(CreateStampingPart::class)
            ->callAction('pull_previous', [
                'source_year' => 2025,
                'source_quarter' => 'Q2',
                'target_year' => 2025,
                'target_quarter' => 'Q4',
                'source_record_id' => 'stamping:'.$stamping->id,
                'exchange_rate' => 19500,
                'sheet_price' => 1.2520,
            ])
            ->assertSet('data.year', 2025)
            ->assertSet('data.quarter', 'Q4')
            ->assertSet('data.exchange_rate', 19500.0)
            ->assertSet('data.coil_price', 1.202)
            ->assertSet('data.sheet_price', 1.252)
            ->assertSet('data.sheet_price_idr', 24414.0)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stamping_parts', [
            'year' => 2025,
            'quarter' => 'Q4',
            'part_number' => 'STAMP-Q2-Q4-001',
            'exchange_rate' => 19500.0,
            'coil_price' => 1.202,
            'sheet_price' => 1.252,
            'sheet_price_idr' => 24414.0,
        ]);
    }

    public function test_stamping_part_pull_previous_copies_all_sections_one_to_three_identically(): void
    {
        $this->actingAs($this->admin);

        $q1 = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '72388-X7V00-A',
            'part_no_to_compare' => '72388-X7V00-OLD',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'model_name' => 'D99X',
            'monthly_volume' => 25000,
            'quotation_date' => '2026-01-15',
            'drawing_reference' => 'Custom Drwg Rev2',
            'eci_number' => 'ECI-999',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'wide' => 390,
            'pitch' => 148,
            'yield_rate' => 2,
            'size' => '2.6 x 390 x 148',
            'berat' => 0.5890,
            'scrap_weight' => 0.2780,
            'scrap_price_per_kg' => -1750,
            'process_name' => 'TANDEM PRESS 300T',
            'process_man_hour' => 12,
            'process_charge' => 60,
            'overhead_supplied_percent' => 12,
            'overhead_self_percent' => 5,
            'transportation_cost' => 85,
            'packing_cost' => 45,
            'tooling_depreciation' => 125,
            'tooling_details' => [
                ['contents' => 'PROGRESSIVE DIE', 'total_cost_krp' => 50000, 'monthly_volume' => 25000, 'depreciation_per_pc' => 125],
            ],
            'exchange_rate' => 17250.0,
            'exchange_rate_fluctuation' => 1.015,
            'coil_price' => 1.2100,
            'sheet_price' => 1.2500,
            'comments' => 'Supplier standard note',
            'remarks' => 'Quarterly rate term',
            'alteration' => 'Hole resized',
            'etc_contents' => 'Anti rust oil',
            'notes' => 'SKH-01/2026',
        ]);

        Livewire::test(CreateStampingPart::class)
            ->mountAction('pull_previous')
            ->set('mountedActionsData.0.source_year', 2026)
            ->set('mountedActionsData.0.source_quarter', 'Q1')
            ->set('mountedActionsData.0.target_year', 2026)
            ->set('mountedActionsData.0.target_quarter', 'Q2')
            ->set('mountedActionsData.0.source_record_id', 'stamping:'.$q1->id)
            ->assertSet('mountedActionsData.0.exchange_rate', 17250)
            ->assertSet('mountedActionsData.0.sheet_price', 1.25)
            ->callMountedAction()
            // Section 1
            ->assertSet('data.supplier_id', $this->supplier->id)
            ->assertSet('data.part_number', '72388-X7V00-A')
            ->assertSet('data.part_no_to_compare', '72388-X7V00-OLD')
            ->assertSet('data.part_name', 'BRKT, SEAT BELT ANCHOR LH')
            ->assertSet('data.model_name', 'D99X')
            ->assertSet('data.monthly_volume', 25000)
            ->assertSet('data.quotation_date', '2026-01-15')
            ->assertSet('data.drawing_reference', 'Custom Drwg Rev2')
            ->assertSet('data.eci_number', 'ECI-999')
            ->assertSet('data.steel_spec', 'SPH440-OD')
            ->assertSet('data.double_thickness', '2.6')
            ->assertSet('data.size', '2.6 x 390 x 148')
            // Section 2
            ->assertSet('data.exchange_rate', 17250.0)
            ->assertSet('data.exchange_rate_fluctuation', 1.015)
            ->assertSet('data.coil_price', 1.21)
            ->assertSet('data.sheet_price', 1.25)
            // Section 3
            ->assertSet('data.wide', 390.0)
            ->assertSet('data.pitch', 148.0)
            ->assertSet('data.yield_rate', 2.0)
            ->assertSet('data.berat', 0.589)
            ->assertSet('data.scrap_weight', 0.278)
            ->assertSet('data.scrap_price_per_kg', -1750.0)
            ->assertSet('data.process_name', 'TANDEM PRESS 300T')
            ->assertSet('data.process_man_hour', 12.0)
            ->assertSet('data.process_charge', 60.0)
            ->assertSet('data.overhead_supplied_percent', 12.0)
            ->assertSet('data.overhead_self_percent', 5.0)
            ->assertSet('data.transportation_cost', 85.0)
            ->assertSet('data.packing_cost', 45.0)
            ->assertSet('data.tooling_depreciation', 125.0)
            ->assertSet('data.part_cost', (float) $q1->part_cost);
    }

    public function test_stamping_part_calculates_excel_nine_line_breakdown(): void
    {
        $stamping = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '72388-X7V00-A',
            'part_no_to_compare' => '72388-X7V00',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'model_name' => 'D26A',
            'quotation_date' => '2026-04-01',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'wide' => 390,
            'pitch' => 148,
            'yield_rate' => 2,
            'berat' => 0.8874,
            'sheet_price' => 1.1456,
            'exchange_rate' => 16000,
            'scrap_weight' => 0.278,
            'scrap_price_per_kg' => -1500,
            'process_man_hour' => 9,
            'process_charge' => 45,
            'overhead_supplied_percent' => 11,
            'overhead_self_percent' => 4,
            'transportation_cost' => 50.0,
            'packing_cost' => 30.0,
            'tooling_depreciation' => 150.0,
            'comments' => '1. Part price based on standard production',
            'remarks' => '1. Exchange Rate USD 16000',
            'alteration' => 'Initial drawing ECI-2026',
        ]);

        $this->assertEquals(0.8874, (float) $stamping->berat);
        $this->assertGreaterThan(0, (float) $stamping->material_total);
        $this->assertEquals(202.50, (float) $stamping->process_total);
        $this->assertGreaterThan(0, (float) $stamping->part_cost_excluding_tooling);
        $this->assertEquals(
            round((float) $stamping->part_cost_excluding_tooling + (float) $stamping->tooling_depreciation, 4),
            (float) $stamping->part_cost
        );
        $this->assertEquals('72388-X7V00', $stamping->part_no_to_compare);
        $this->assertEquals('2026-04-01', $stamping->quotation_date->format('Y-m-d'));
        $this->assertEquals('1. Part price based on standard production', $stamping->comments);
    }

    public function test_can_call_bulk_action_export_and_delete_on_stamping_parts(): void
    {
        $this->actingAs($this->admin);

        $stamping = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '72387-BZ010',
            'part_name' => 'BRACKET SUB-ASSY',
            'steel_spec' => 'SPCC-SD',
            'double_thickness' => '1.2',
            'berat' => 0.8874,
            'sheet_price' => 1.1456,
            'exchange_rate' => 16000,
        ]);

        Livewire::test(ListStampingParts::class)
            ->callTableBulkAction('export_selected_xlsx', [$stamping])
            ->assertSuccessful();

        Livewire::test(ListStampingParts::class)
            ->callTableBulkAction('export_selected_csv', [$stamping])
            ->assertSuccessful();

        Livewire::test(ListStampingParts::class)
            ->callTableBulkAction('delete', [$stamping])
            ->assertSuccessful();

        $this->assertDatabaseMissing('stamping_parts', ['id' => $stamping->id]);
    }

    public function test_stamping_part_supports_direct_idr_price_tooling_resync_and_dimension_recalc(): void
    {
        $part = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_number' => '72388-X7V00-A',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'wide' => 390,
            'pitch' => 148,
            'yield_rate' => 2,
            'scrap_weight' => 0.2780,
            'scrap_price_per_kg' => -1500,
            'process_man_hour' => 9,
            'process_charge' => 45,
            'exchange_rate' => 0.0,
            'coil_price' => 0.0,
            'sheet_price' => 0.0,
            'sheet_price_idr' => 17689.00,
            'tooling_details' => [
                ['contents' => 'PROGRESSIVE DIE', 'total_cost_krp' => 25000, 'monthly_volume' => 16500, 'depreciation_per_pc' => 100.0],
            ],
        ]);

        // Berat auto-calculated 7 desimal: (2.6 * 390 * 148 * 7.85) / (1,000,000 * 2) = 0.5890326
        $this->assertEqualsWithDelta(0.5890326, (float) $part->berat, 0.0000001);
        // Direct IDR price dipertahankan walau USD = 0 & Kurs = 0
        $this->assertEquals(17689.00, (float) $part->sheet_price_idr);
        $this->assertEquals(round(0.5890326 * 17689.00, 2), (float) $part->gross_material_cost);
        $this->assertEquals(100.0000, (float) $part->tooling_depreciation);

        // Update Currency tidak boleh menghapus harga IDR langsung ketika USD = 0
        CurrencyRecalculationService::recalculateStampingPartsForCurrency($this->currency);
        $part->refresh();
        $this->assertEquals(17689.00, (float) $part->sheet_price_idr);

        // Update tooling_details harus ikut memperbarui tooling_depreciation, lalu mereset ke 0 saat dikosongkan
        $part->update([
            'tooling_details' => [
                ['contents' => 'PROGRESSIVE DIE', 'total_cost_krp' => 50000, 'monthly_volume' => 16500, 'depreciation_per_pc' => 250.0],
            ],
        ]);
        $this->assertEquals(250.0, (float) $part->fresh()->tooling_depreciation);

        $part->update(['tooling_details' => []]);
        $this->assertEquals(0.0, (float) $part->fresh()->tooling_depreciation);

        // Mengubah wide tanpa menyentuh berat memicu recalculation berat otomatis
        $part->update(['wide' => 400]);
        $expectedWeight = round((2.6 * 400 * 148 * 7.85) / (1000000 * 2), 7);
        $this->assertEqualsWithDelta($expectedWeight, (float) $part->fresh()->berat, 0.0000001);
    }

    public function test_stamping_part_quarter_lookup_prefers_matching_part_number_and_renders_clean_modal_and_export(): void
    {
        $this->actingAs($this->admin);

        $posmi = Supplier::create([
            'code' => 'VND-POSMI',
            'name' => 'PT. POSMI STEEL INDONESIA',
            'category' => 'Steel',
            'is_active' => true,
        ]);

        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $posmi->id,
            'vendor_code' => $posmi->code,
            'supplier_name' => $posmi->name,
            'part_number' => 'PART-ALPHA',
            'part_name' => 'BRACKET ALPHA',
            'steel_spec' => 'SPH440-OD',
            'sheet_price' => 1.2500,
            'exchange_rate' => 16465.50,
        ]);

        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $posmi->id,
            'vendor_code' => $posmi->code,
            'supplier_name' => $posmi->name,
            'part_number' => 'PART-BETA',
            'part_name' => 'BRACKET BETA',
            'steel_spec' => 'SPH440-OD',
            'sheet_price' => 1.8500,
            'exchange_rate' => 16465.50,
        ]);

        // Lookup dengan part_number PART-ALPHA tidak boleh tertukar ke PART-BETA walau ID PART-BETA lebih besar
        $prev = StampingPart::findPreviousQuarterData('Q2', 2026, $posmi->id, 'SPH440-OD', 'PART-ALPHA');
        $this->assertNotNull($prev);
        $this->assertEquals('PART-ALPHA', $prev['part_number']);
        $this->assertEquals(1.25, $prev['sheet_price']);

        // Record tanpa part_number dan tanpa relasi Sub-Assembly tidak boleh menampilkan blok Sub-Assembly kosong
        $standalone = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $posmi->id,
            'vendor_code' => $posmi->code,
            'supplier_name' => $posmi->name,
            'steel_spec' => 'SPH440-OD',
            'sheet_price' => 1.3000,
            'exchange_rate' => 16465.50,
        ]);

        $modalHtml = view('filament.resources.stamping-parts.sheet-modal', ['record' => $standalone])->render();
        $this->assertStringNotContainsString('Komponen ini Terpasang Pada Sub-Assembly Berikut', $modalHtml);

        // Export Excel via header action tidak boleh error karena missing relationship materialSteel
        Livewire::test(ListStampingParts::class)
            ->callAction('export_excel', [
                'format' => 'xlsx',
                'year' => 'all',
                'quarter' => 'all',
            ])
            ->assertHasNoActionErrors();
    }

    public function test_excel_exact_72388_calculations_dynamic_modal_and_stacked_csv_import(): void
    {
        $posmi = Supplier::create([
            'code' => 'VND-POSMI',
            'name' => 'PT. POSMI STEEL INDONESIA',
            'category' => 'Steel',
        ]);

        // 1. Data Q2 2022 Posmi USD ($1.232 * 14300 = Rp 17.617,60, Berat 7 desimal 0.5890326) -> Rp 10.583,5294
        $q2 = StampingPart::create([
            'year' => 2022,
            'quarter' => 'Q2',
            'supplier_id' => $posmi->id,
            'vendor_code' => $posmi->code,
            'supplier_name' => $posmi->name,
            'part_number' => '72388-X7V00-A',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'wide' => 390,
            'pitch' => 148,
            'yield_rate' => 2,
            'berat' => 0.5890326,
            'scrap_weight' => 0.2780,
            'scrap_price_per_kg' => -1500,
            'process_name' => '1. PROGRESSIVE',
            'process_man_hour' => 9,
            'process_charge' => 45,
            'overhead_supplied_percent' => 11,
            'overhead_self_percent' => 4,
            'exchange_rate' => 14300,
            'sheet_price' => 1.232,
        ]);

        $this->assertEqualsWithDelta(17617.60, (float) $q2->sheet_price_idr, 0.01);
        $this->assertEqualsWithDelta(10583.5294, (float) $q2->part_cost, 0.001);

        // 2. Data Q3 2022 dengan perubahan O/H, scrap, dan wide agar sheet-modal merender diff dinamis & tanpa emoji
        $q3 = StampingPart::create([
            'year' => 2022,
            'quarter' => 'Q3',
            'supplier_id' => $posmi->id,
            'vendor_code' => $posmi->code,
            'supplier_name' => $posmi->name,
            'part_number' => '72388-X7V00-A',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'wide' => 400,
            'pitch' => 148,
            'yield_rate' => 2,
            'scrap_weight' => 0.2900,
            'scrap_price_per_kg' => -1600,
            'process_name' => '1. PROGRESSIVE',
            'process_man_hour' => 9,
            'process_charge' => 45,
            'overhead_supplied_percent' => 12,
            'overhead_self_percent' => 5,
            'exchange_rate' => 0,
            'sheet_price' => 0,
            'coil_price' => 0,
            'sheet_price_idr' => 17689.00,
        ]);

        $modalHtml = view('filament.resources.stamping-parts.sheet-modal', ['record' => $q3])->render();
        $this->assertStringNotContainsString('🧩', $modalHtml);
        $this->assertStringContainsString('11% ➔ 12%', $modalHtml);
        $this->assertStringContainsString('4% ➔ 5%', $modalHtml);
        $this->assertStringContainsString('0,5890326', $modalHtml);

        // 3. Import format Comparative Quotation bertumpuk (Sub-Assy block + Stamping Part block dengan New Rate=0 Direct IDR)
        $csvPath = sys_get_temp_dir().'/stamping_comparative_stacked_test.csv';
        $csvRows = [
            ['Part No', '', '73012-BZ010', '', '', '', '', '', '', '', '', '', ''],
            ['Periode Q2 April-June \'22', '', 'Part Name', '', 'BRACKET SUB-ASSY', '', '', '', '', '', '', '', ''],
            ['Material Breakdown', '', '', '', '', '', 'Quantity.', 'Unit Price', 'Price', 'Difference', 'Valuation', '', ''],
            ['72388-X7V00-A', 'BRKT', '', '', '', '', '1', 'SUPPLY', '0', '0', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', 'Part No', '', '72388-X7V00-A', '', '', '', '', '', '', 'Part No', '', '72388-X7V00-A'],
            ['Periode Q2 April-June \'22', '', '', 'Part Name', '', 'BRKT, SEAT BELT ANCHOR LH', '', '', '', '', '', '', 'Oct-Dec\'26 Price / Pcs (Rp)', '', 'Part Name', '', 'BRKT, SEAT BELT ANCHOR LH'],
            ['2022-03-15', '', '', '', '', '', '', '', '', '', '', '', '2026-06-01', '', '', '', ''],
            ['PT. POSMI STEEL INDONESIA', '', '', '', '', '', '', '', '', '', '', '', 'PT. POSMI STEEL INDONESIA', '', '', '', ''],
            ['Model', '', 'D26A', '', '(A) : Monthly Volume', '', '', '16500', '', '', '', '', 'Model', '', 'D26A', '', '(A) : Monthly Volume', '', '', '16500'],
            ['Exchange Rate', '', 'Qtn condition', '', '14300', '', 'New Rate', '', '14300', '', '', '', 'Exchange Rate', '', '', '', '', '', 'New Rate', '', '0'],
            ['Material Breakdown', '', '', '', '', '', 'Quantity.', 'Unit Price', 'Price', 'Difference', 'Valuation', '', 'Material Breakdown', '', '', '', '', '', 'Quantity.', 'Unit Price', 'Price', 'Difference', 'Valuation'],
            ['Spec', 'Thickness', 'Wide', 'Pitch', 'yield rate', 'Weight', '', '', '', '', '', '', 'Spec', 'Thickness', 'Wide', 'Pitch', 'yield rate', 'Weight', '', '', '', '', ''],
            ['SPH440-OD', '2.6', '390', '148', '2', '0.5890326', '0.5890326', '17617.6', '10377.34', '', '', '', 'SPH440-OD', '2.6', '390', '148', '2', '0.5890326', '0.5890326', '17689', '10419.40', '', 'USCI'],
            ['', '', '', '', '', '', '', '1.232', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', 'SCRAP', '0.278', '', '-1500', '-417', '', '', '', '', '', '', '', 'SCRAP', '0.278', '', '-1500', '-417', '', ''],
            ['(1) : Material Total', '', '', '', '', '', '', '', '9960.34', '', '', '', '(1) : Material Total', '', '', '', '', '', '', '', '10002.40', '', ''],
            ['Process Breakdown', '', '', '', '', '', 'Man-hour', 'Charge', 'Price', 'Difference', 'Valuation', '', 'Process Breakdown', '', '', '', '', '', 'Man-hour', 'Charge', 'Price', 'Difference', 'Valuation'],
            ['1. PROGRESSIVE', '', '250T', '', '', '', '9', '45', '202.5', '', '', '', '1. PROGRESSIVE', '', '250T', '', '', '', '9', '45', '202.5', '', ''],
            ['(2) : Process Total', '', '', '', '', '', '', '', '202.5', '', '', '', '(2) : Process Total', '', '', '', '', '', '', '', '202.5', '', ''],
            ['(4) : O/H & Profit supplied portion ( 11 %)', '', '', '', '', '', '', '', '22.28', '', '', '', '(4) : O/H & Profit supplied portion ( 11 %)', '', '', '', '', '', '', '', '22.28', '', ''],
            ['(5) : O/H & Profit self-procuring portion ( 4 %)', '', '', '', '', '', '', '', '398.41', '', '', '', '(5) : O/H & Profit self-procuring portion ( 4 %)', '', '', '', '', '', '', '', '400.10', '', ''],
            ['(6) : Transportation', '', '', '', '', '', '', '', '0', '', '', '', '(6) : Transportation', '', '', '', '', '', '', '', '0', '', ''],
            ['(7) : Packing', '', '', '', '', '', '', '', '0', '', '', '', '(7) : Packing', '', '', '', '', '', '', '', '0', '', ''],
            ['(9) : Die & Jig depreciation', '', '', '', '', '', '', '', '#REF!', '', '', '', '(9) : Die & Jig depreciation', '', '', '', '', '', '', '', '0', '', ''],
        ];

        $fp = fopen($csvPath, 'w');
        foreach ($csvRows as $r) {
            fputcsv($fp, $r);
        }
        fclose($fp);

        try {
            $res = \App\Services\ExcelImportService::importStampingPart($csvPath);
            $this->assertEquals(0, count($res['errors']));
            $this->assertNull(StampingPart::where('part_number', '73012-BZ010')->first());

            $octDec26 = StampingPart::where('year', 2026)
                ->where('quarter', 'Q4')
                ->where('part_number', '72388-X7V00-A')
                ->first();
            $this->assertNotNull($octDec26);
            $this->assertEquals(0.0, (float) $octDec26->exchange_rate);
            $this->assertEquals(0.0, (float) $octDec26->sheet_price);
            $this->assertEquals(17689.0, (float) $octDec26->sheet_price_idr);
            $this->assertEqualsWithDelta(0.5890326, (float) $octDec26->berat, 0.0000001);
            $this->assertEqualsWithDelta(10627.28, (float) $octDec26->part_cost, 0.02);
        } finally {
            @unlink($csvPath);
        }
    }
}
