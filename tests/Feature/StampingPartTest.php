<?php

namespace Tests\Feature;

use App\Filament\Resources\StampingPartResource\Pages\CreateStampingPart;
use App\Filament\Resources\StampingPartResource\Pages\ListStampingParts;
use App\Models\Currency;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Models\User;
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
            ->assertTableActionExists('edit')
            ->assertTableActionExists('delete');
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
            ->assertFormFieldExists('steel_spec')
            ->assertFormFieldExists('double_thickness')
            ->assertFormFieldExists('size')
            ->assertFormFieldExists('berat')
            ->assertFormFieldExists('gap_steel_usd')
            ->assertFormFieldExists('gap_steel_kg_usd')
            ->assertFormFieldExists('exchange_rate')
            ->assertFormFieldExists('coil_price')
            ->assertFormFieldExists('coil_price_idr')
            ->assertFormFieldExists('sheet_price')
            ->assertFormFieldExists('sheet_price_idr')
            ->assertActionExists('back');
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
                'gap_steel_usd' => '80',
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
            'gap_steel_usd' => 80.0000,
            'gap_steel_kg_usd' => 0.0800,
        ]);
    }
}
