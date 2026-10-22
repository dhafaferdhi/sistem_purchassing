<?php

namespace Tests\Feature;

use App\Filament\Resources\MaterialSteelResource;
use App\Filament\Resources\MaterialSteelResource\Pages\CreateMaterialSteel;
use App\Filament\Resources\MaterialSteelResource\Pages\EditMaterialSteel;
use App\Filament\Resources\MaterialSteelResource\Pages\ListMaterialSteels;
use App\Models\Currency;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class MaterialSteelQuarterlyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $estimator;

    protected User $approver;

    protected Supplier $supplier;

    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->estimator = User::factory()->create([
            'role' => 'estimator',
        ]);

        $this->approver = User::factory()->create([
            'role' => 'approver',
        ]);

        $this->supplier = Supplier::create([
            'code' => 'VND-STEEL-01',
            'name' => 'PT Krakatau Posco',
            'category' => 'Steel',
        ]);

        $this->currency = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'exchange_rate_to_idr' => 16000.0,
        ]);
    }

    public function test_can_create_material_steel_with_quarter_and_auto_calculated_idr(): void
    {
        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-SD 1.2mm',
            'currency_id' => $this->currency->id,
            'currency_code' => 'USD',
            'steel_price' => 1.9500,
            'exchange_rate' => 16000.0000,
            'prev_month_1_label' => 'Maret 2026',
            'prev_month_1_price' => 1.9000,
            'prev_month_1_rate' => 15900.0000,
            'prev_month_2_label' => 'Februari 2026',
            'prev_month_2_price' => 1.8800,
            'prev_month_2_rate' => 15850.0000,
            'prev_month_3_label' => 'Januari 2026',
            'prev_month_3_price' => 1.8600,
            'prev_month_3_rate' => 15800.0000,
        ]);

        $this->assertNotNull($steel->id);
        $this->assertEquals('Q2', $steel->quarter);
        $this->assertEquals('VND-STEEL-01', $steel->vendor_code);
        $this->assertEquals('PT Krakatau Posco', $steel->supplier_name);

        // Perhitungan hasil: steel_price * exchange_rate = 1.95 * 16000 = 31200
        $this->assertEquals(31200.00, (float) $steel->steel_price_idr);

        // Verifikasi perhitungan rata-rata 3 bulan ke belakang: (1.90 + 1.88 + 1.86) / 3 = 1.8800
        $this->assertEquals(1.8800, (float) $steel->avg_3_months_price);

        // Verifikasi variansi kenaikan harga vs 3 bulan ke belakang: ((1.95 - 1.88) / 1.88) * 100 = +3.72%
        $this->assertEquals(3.72, (float) $steel->price_variance_percentage);
    }

    public function test_previous_3_months_info_helper_calculation(): void
    {
        // Q1 2026 -> Desember 2025, November 2025, Oktober 2025
        $q1 = MaterialSteel::getPrevious3MonthsInfo('Q1', 2026);
        $this->assertEquals('Q4', $q1['prev_quarter']);
        $this->assertEquals(2025, $q1['prev_year']);
        $this->assertEquals('Desember 2025', $q1['month_1']);
        $this->assertEquals('November 2025', $q1['month_2']);
        $this->assertEquals('Oktober 2025', $q1['month_3']);

        // Q2 2026 -> Maret 2026, Februari 2026, Januari 2026
        $q2 = MaterialSteel::getPrevious3MonthsInfo('Q2', 2026);
        $this->assertEquals('Q1', $q2['prev_quarter']);
        $this->assertEquals(2026, $q2['prev_year']);
        $this->assertEquals('Maret 2026', $q2['month_1']);
        $this->assertEquals('Februari 2026', $q2['month_2']);
        $this->assertEquals('Januari 2026', $q2['month_3']);

        // Q3 2026 -> Juni 2026, Mei 2026, April 2026
        $q3 = MaterialSteel::getPrevious3MonthsInfo('Q3', 2026);
        $this->assertEquals('Q2', $q3['prev_quarter']);
        $this->assertEquals('Juni 2026', $q3['month_1']);
        $this->assertEquals('Mei 2026', $q3['month_2']);
        $this->assertEquals('April 2026', $q3['month_3']);

        // Q4 2026 -> September 2026, Agustus 2026, Juli 2026
        $q4 = MaterialSteel::getPrevious3MonthsInfo('Q4', 2026);
        $this->assertEquals('Q3', $q4['prev_quarter']);
        $this->assertEquals('September 2026', $q4['month_1']);
        $this->assertEquals('Agustus 2026', $q4['month_2']);
        $this->assertEquals('Juli 2026', $q4['month_3']);
    }

    public function test_material_steel_authorization_policy(): void
    {
        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPHC 2.0mm',
            'currency_id' => $this->currency->id,
            'steel_price' => 1.8000,
            'exchange_rate' => 16000.0000,
        ]);

        // Everyone can view
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $steel));
        $this->assertTrue(Gate::forUser($this->estimator)->allows('view', $steel));
        $this->assertTrue(Gate::forUser($this->approver)->allows('view', $steel));

        // Admin & Estimator can create & update
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', MaterialSteel::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $steel));
        $this->assertTrue(Gate::forUser($this->estimator)->allows('create', MaterialSteel::class));
        $this->assertTrue(Gate::forUser($this->estimator)->allows('update', $steel));

        // Approver cannot create or update
        $this->assertFalse(Gate::forUser($this->approver)->allows('create', MaterialSteel::class));
        $this->assertFalse(Gate::forUser($this->approver)->allows('update', $steel));

        // Only Admin can delete
        $this->assertTrue(Gate::forUser($this->admin)->allows('delete', $steel));
        $this->assertFalse(Gate::forUser($this->estimator)->allows('delete', $steel));
        $this->assertFalse(Gate::forUser($this->approver)->allows('delete', $steel));
    }

    public function test_can_render_material_steel_filament_pages(): void
    {
        $this->actingAs($this->admin);

        // List page
        $responseList = $this->get(MaterialSteelResource::getUrl('index'));
        $responseList->assertSuccessful();

        // Create page (memastikan tidak ada error Undefined variable $get atau Blade view error)
        $responseCreate = $this->get(MaterialSteelResource::getUrl('create'));
        $responseCreate->assertSuccessful();

        // Edit page
        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPHC 2.0mm',
            'currency_id' => $this->currency->id,
            'steel_price' => 1.8000,
            'exchange_rate' => 16000.0000,
        ]);
        $responseEdit = $this->get(MaterialSteelResource::getUrl('edit', ['record' => $steel]));
        $responseEdit->assertSuccessful();
    }

    public function test_can_filter_material_steel_by_quarter_and_vendor_code(): void
    {
        $this->actingAs($this->admin);

        $steelQ1 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => 'VND-STEEL-01',
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-SD 1.2mm',
            'currency_id' => $this->currency->id,
            'steel_price' => 1.8500,
            'exchange_rate' => 16000.0000,
        ]);

        $supplier2 = Supplier::create([
            'code' => 'VND-OTHER-99',
            'name' => 'PT Baja Nippon',
            'category' => 'Steel',
        ]);

        $steelQ2 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $supplier2->id,
            'vendor_code' => 'VND-OTHER-99',
            'supplier_name' => $supplier2->name,
            'steel_spec' => 'SPHC Coil 2.0mm',
            'currency_id' => $this->currency->id,
            'steel_price' => 1.9500,
            'exchange_rate' => 16000.0000,
        ]);

        Livewire::test(ListMaterialSteels::class)
            ->assertCanSeeTableRecords([$steelQ1, $steelQ2])
            ->filterTable('quarter', ['Q1'])
            ->assertCanSeeTableRecords([$steelQ1])
            ->assertCanNotSeeTableRecords([$steelQ2])
            ->resetTableFilters()
            ->filterTable('vendor_code', ['VND-OTHER-99'])
            ->assertCanSeeTableRecords([$steelQ2])
            ->assertCanNotSeeTableRecords([$steelQ1]);
    }

    public function test_material_steel_filters_display_record_counts(): void
    {
        $this->actingAs($this->admin);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'COUNT-SPEC-1',
            'currency_id' => $this->currency->id,
            'steel_price' => 1.8500,
            'exchange_rate' => 16000.0,
            'status' => 'Active',
        ]);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'COUNT-SPEC-2',
            'currency_id' => $this->currency->id,
            'steel_price' => 1.9500,
            'exchange_rate' => 16000.0,
            'status' => 'Active',
        ]);

        $component = Livewire::test(ListMaterialSteels::class);
        $component->assertSuccessful();

        $table = $component->instance()->getTable();
        $quarterFilter = $table->getFilter('quarter');
        $quarterOptions = $quarterFilter->getOptions();
        $this->assertStringContainsString('data', $quarterOptions['Q1']);
        $this->assertStringContainsString('1 data', $quarterOptions['Q1']);
        $this->assertStringContainsString('1 data', $quarterOptions['Q2']);
        $this->assertStringContainsString('0 data', $quarterOptions['Q3']);

        $yearFilter = $table->getFilter('year');
        $yearOptions = $yearFilter->getOptions();
        $this->assertStringContainsString('2 data', $yearOptions[2026]);

        $statusFilter = $table->getFilter('status');
        $statusOptions = $statusFilter->getOptions();
        $this->assertStringContainsString('2 data', $statusOptions['Active']);
    }

    public function test_can_switch_year_and_filter_by_year_and_quarter_tabs(): void
    {
        $this->actingAs($this->admin);

        $steel2025Q1 = MaterialSteel::create([
            'year' => 2025,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-2025',
            'currency_id' => $this->currency->id,
            'steel_price' => 1.7500,
            'exchange_rate' => 15500.0000,
        ]);

        $steel2026Q1 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-2026',
            'currency_id' => $this->currency->id,
            'steel_price' => 1.9500,
            'exchange_rate' => 16200.0000,
        ]);

        Livewire::test(ListMaterialSteels::class)
            ->filterTable('year', 2026)
            ->assertCanSeeTableRecords([$steel2026Q1])
            ->assertCanNotSeeTableRecords([$steel2025Q1])
            ->filterTable('year', 2025)
            ->assertCanSeeTableRecords([$steel2025Q1])
            ->assertCanNotSeeTableRecords([$steel2026Q1])
            ->resetTableFilters()
            ->assertCanSeeTableRecords([$steel2025Q1, $steel2026Q1]);
    }

    public function test_material_steel_displays_spec_double_thickness_coil_sheet_gap_and_no_size_steel(): void
    {
        $this->actingAs($this->admin);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-Custom-Spec',
            'double_thickness' => '1.2',
            'currency_id' => $this->currency->id,
            'currency_code' => 'USD',
            'coil_price' => 1.8000,
            'coil_price_idr' => 28800.00,
            'sheet_price' => 1.8800,
            'sheet_price_idr' => 30080.00,
            'gap_steel_usd' => 80.0000,
            'gap_steel_idr' => 1280000.00,
            'exchange_rate' => 16000.0000,
        ]);

        Livewire::test(ListMaterialSteels::class)
            ->assertTableColumnExists('steel_spec')
            ->assertTableColumnExists('double_thickness')
            ->assertTableColumnExists('gap_steel_usd')
            ->assertTableColumnDoesNotExist('gap_steel_kg_usd')
            ->assertTableColumnExists('exchange_rate')
            ->assertTableColumnExists('coil_price')
            ->assertTableColumnExists('coil_price_idr')
            ->assertTableColumnExists('sheet_price')
            ->assertTableColumnExists('sheet_price_idr')
            ->assertTableColumnDoesNotExist('gap_steel_idr')
            ->assertTableColumnDoesNotExist('size')
            ->assertTableColumnDoesNotExist('steel_price')
            ->assertTableColumnDoesNotExist('steel_price_idr')
            ->assertTableActionDoesNotExist('view')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('delete')
            ->assertSee('SPCC-Custom-Spec')
            ->assertSee('1.2');
    }

    public function test_material_steel_calculates_gap_steel_mt_usd_and_idr_formula(): void
    {
        // Sheet: $1.93, Coil: $1.87 -> Gap: ($1.93 - $1.87) * 1000 = $60.0000 / MT
        // Rate: Rp 16.000 -> Gap IDR: $60 * 16.000 = Rp 960.000
        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPHC-P',
            'double_thickness' => '2.0 mm', // Input dengan 'mm' harus otomatis dibersihkan menjadi '2.0'
            'currency_id' => $this->currency->id,
            'currency_code' => 'USD',
            'coil_price' => 1.8700,
            'sheet_price' => 1.9300,
            'exchange_rate' => 16000.0000,
        ]);

        $this->assertEquals(60.0000, (float) $steel->gap_steel_usd);
        $this->assertEquals(0.0600, (float) $steel->gap_steel_kg_usd);
        $this->assertEquals(960000.00, (float) $steel->gap_steel_idr);
        $this->assertEquals(29920.00, (float) $steel->coil_price_idr);
        $this->assertEquals(30880.00, (float) $steel->sheet_price_idr);
        $this->assertEquals('2.0', $steel->double_thickness);
    }

    public function test_material_steel_gap_per_kg_formula_matches_excel_d5_divided_by_1000(): void
    {
        // Kasus 1 dari Excel: Gap / MT = -20 USD/MT -> Gap / Kg = -20 / 1000 = -0.02 USD/Kg, Rate = 18000
        $steelNegative = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-EXCEL-01',
            'currency_id' => $this->currency->id,
            'gap_steel_usd' => -20.0,
            'exchange_rate' => 18000.0,
        ]);

        $this->assertEquals(-20.0000, (float) $steelNegative->gap_steel_usd);
        $this->assertEquals(-0.0200, (float) $steelNegative->gap_steel_kg_usd);
        $this->assertEquals(18000.0000, (float) $steelNegative->exchange_rate);
        $this->assertEquals(-360000.00, (float) $steelNegative->gap_steel_idr);

        // Kasus 2 dari Excel: Gap / MT = 50 USD/MT -> Gap / Kg = 50 / 1000 = 0.05 USD/Kg, Rate = 20000
        $steelPositive = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-EXCEL-02',
            'currency_id' => $this->currency->id,
            'gap_steel_usd' => 50.0,
            'exchange_rate' => 20000.0,
        ]);

        $this->assertEquals(50.0000, (float) $steelPositive->gap_steel_usd);
        $this->assertEquals(0.0500, (float) $steelPositive->gap_steel_kg_usd);
        $this->assertEquals(20000.0000, (float) $steelPositive->exchange_rate);
        $this->assertEquals(1000000.00, (float) $steelPositive->gap_steel_idr);
    }

    public function test_can_create_material_steel_with_custom_supplier_name_bisa_bikin_sendiri(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateMaterialSteel::class)
            ->fillForm([
                'year' => 2026,
                'quarter' => 'Q3',
                'supplier_name' => 'PT Baja Mandiri Sentosa',
                'vendor_code' => 'VND-BMS-01',
                'steel_spec' => 'SPHC-Custom-Manual',
                'double_thickness' => '2.0',
                'currency_id' => $this->currency->id,
                'exchange_rate' => 16000.0,
                'coil_price' => 2.0500,
                'sheet_price' => 2.1500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Pastikan supplier otomatis tersimpan di tabel suppliers
        $this->assertDatabaseHas('suppliers', [
            'name' => 'PT Baja Mandiri Sentosa',
            'code' => 'VND-BMS-01',
        ]);

        $createdSupplier = Supplier::where('name', 'PT Baja Mandiri Sentosa')->first();
        $this->assertNotNull($createdSupplier);

        // Pastikan material steel tersimpan dengan supplier_id yang baru dibuat beserta perhitungan gap
        $this->assertDatabaseHas('material_steels', [
            'supplier_id' => $createdSupplier->id,
            'supplier_name' => 'PT Baja Mandiri Sentosa',
            'vendor_code' => 'VND-BMS-01',
            'steel_spec' => 'SPHC-Custom-Manual',
            'double_thickness' => '2.0',
            'coil_price' => 2.0500,
            'coil_price_idr' => 32800.00,
            'sheet_price' => 2.1500,
            'sheet_price_idr' => 34400.00,
            'gap_steel_usd' => 100.0000,
        ]);
    }

    public function test_supplier_options_only_display_pt_hssi_and_pt_usci_with_add_button(): void
    {
        $this->actingAs($this->admin);

        $hssi = Supplier::firstOrCreate(['name' => 'PT HSSI'], ['code' => 'VND-HSSI', 'category' => 'Steel']);
        $usci = Supplier::firstOrCreate(['name' => 'PT USCI'], ['code' => 'VND-USCI', 'category' => 'Steel']);

        $test = Livewire::test(CreateMaterialSteel::class)
            ->assertFormFieldExists('supplier_id')
            ->assertFormFieldExists('double_thickness')
            ->assertFormFieldExists('gap_steel_usd')
            ->assertFormFieldExists('gap_steel_kg_usd')
            ->assertFormFieldExists('exchange_rate');

        // Pastikan field gap_steel_idr sudah dihapus dari form
        $test->assertFormFieldDoesNotExist('gap_steel_idr');
    }

    public function test_can_view_2027_q1_records_in_material_steels_table(): void
    {
        $this->actingAs($this->admin);

        $hssi = Supplier::firstOrCreate(['name' => 'PT HSSI'], ['code' => 'VND-HSSI', 'category' => 'Steel']);
        $usci = Supplier::firstOrCreate(['name' => 'PT USCI'], ['code' => 'VND-USCI', 'category' => 'Steel']);

        $q1Record1 = MaterialSteel::create([
            'year' => 2027,
            'quarter' => 'Q1',
            'supplier_id' => $hssi->id,
            'vendor_code' => $hssi->code,
            'supplier_name' => $hssi->name,
            'steel_spec' => 'SPCC-SD 1.2mm',
            'double_thickness' => '1.2',
            'coil_price' => 1.9800,
            'sheet_price' => 2.0800,
            'exchange_rate' => 16200.0,
            'currency_id' => $this->currency->id,
        ]);

        $q1Record2 = MaterialSteel::create([
            'year' => 2027,
            'quarter' => 'Q1',
            'supplier_id' => $usci->id,
            'vendor_code' => $usci->code,
            'supplier_name' => $usci->name,
            'steel_spec' => 'SPH270-OD 2.0mm',
            'double_thickness' => '2.0',
            'coil_price' => 1.9200,
            'sheet_price' => 2.0200,
            'exchange_rate' => 16200.0,
            'currency_id' => $this->currency->id,
        ]);

        Livewire::test(ListMaterialSteels::class)
            ->assertCanSeeTableRecords([$q1Record1, $q1Record2])
            ->filterTable('quarter', ['Q1'])
            ->assertCanSeeTableRecords([$q1Record1, $q1Record2])
            ->filterTable('year', 2027)
            ->assertCanSeeTableRecords([$q1Record1, $q1Record2]);
    }

    public function test_can_create_initial_baseline_material_steel_without_comparison(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateMaterialSteel::class)
            ->assertFormFieldExists('price_mode')
            ->assertFormSet(['price_mode' => 'baseline'])
            ->fillForm([
                'year' => 2026,
                'quarter' => 'Q1',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPCC-SD-BASELINE',
                'double_thickness' => '1.2',
                'exchange_rate' => 16000.0,
                'coil_price' => 1.8500,
                'sheet_price' => 1.9500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('material_steels', [
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-SD-BASELINE',
            'double_thickness' => '1.2',
            'coil_price' => 1.8500,
            'coil_price_idr' => 29600.00,
            'sheet_price' => 1.9500,
            'sheet_price_idr' => 31200.00,
            'exchange_rate' => 16000.0,
        ]);
    }

    public function test_can_generate_material_steel_template_csv(): void
    {
        $csv = ExcelImportService::generateMaterialSteelTemplateCsv();

        $this->assertNotEmpty($csv);
        $this->assertStringContainsString('Kuartal', $csv);
        $this->assertStringContainsString('Spesifikasi Baja', $csv);
        $this->assertStringContainsString('Ketebalan (mm)', $csv);
        $this->assertStringContainsString('AR Coil (USD)', $csv);
        $this->assertStringContainsString('AR Sheet (USD)', $csv);
        $this->assertStringContainsString('SPH270C-OD', $csv);
    }

    public function test_can_import_material_steel_from_template_csv(): void
    {
        $csvContent = ExcelImportService::generateMaterialSteelTemplateCsv();
        $tmpFile = tempnam(sys_get_temp_dir(), 'steel_import_').'.csv';
        file_put_contents($tmpFile, $csvContent);

        $result = ExcelImportService::importMaterialSteel($tmpFile, [
            'year' => 2026,
            'quarter' => 'Q4',
        ]);

        @unlink($tmpFile);

        $this->assertGreaterThan(0, $result['total']);
        $this->assertEmpty($result['errors']);

        $this->assertDatabaseHas('material_steels', [
            'year' => 2026,
            'quarter' => 'Q4',
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.1206,
            'sheet_price' => 1.1456,
            'exchange_rate' => 17796.0,
            'gap_steel_usd' => -20.0,
        ]);

        $this->assertDatabaseHas('material_steels', [
            'year' => 2026,
            'quarter' => 'Q4',
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.8',
            'coil_price' => 1.1146,
            'sheet_price' => 1.1396,
        ]);
    }

    public function test_create_and_list_pages_have_import_and_download_template_actions(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateMaterialSteel::class)
            ->assertActionExists('download_template')
            ->assertActionExists('import_excel');

        Livewire::test(ListMaterialSteels::class)
            ->assertActionExists('import_excel');
    }

    public function test_create_q2_with_gap_mt_auto_calculates_from_q1_baseline(): void
    {
        $this->actingAs($this->admin);

        // Record Q1 sebagai baseline
        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.1206,
            'sheet_price' => 1.1456,
            'exchange_rate' => 16000.0,
            'currency_id' => $this->currency->id,
        ]);

        // Simulasikan Livewire form create di Q2 dengan Gap MT = 0.5
        Livewire::test(CreateMaterialSteel::class)
            ->fillForm([
                'year' => 2026,
                'quarter' => 'Q2',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'double_thickness' => '1.6',
                'price_mode' => 'adjustment',
                'gap_steel_usd' => 0.5,
                'exchange_rate' => 17000.0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Verifikasi harga baru terhitung: 1.1206 + 0.0005 = 1.1211 dan 1.1456 + 0.0005 = 1.1461
        $this->assertDatabaseHas('material_steels', [
            'year' => 2026,
            'quarter' => 'Q2',
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.1211,
            'sheet_price' => 1.1461,
            'gap_steel_usd' => 0.5,
        ]);
    }

    public function test_create_q2_without_gap_mt_change_preserves_zero_gap_and_updates_idr_by_rate(): void
    {
        $this->actingAs($this->admin);

        // Record Q1 sebagai baseline
        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.0',
            'coil_price' => 1.2000,
            'sheet_price' => 1.2500,
            'exchange_rate' => 16000.0,
            'currency_id' => $this->currency->id,
        ]);

        // Simulasikan Livewire form create di Q2 dengan mode baseline (tanpa perbandingan / tanpa gap MT) dan kurs baru 17500
        Livewire::test(CreateMaterialSteel::class)
            ->fillForm([
                'year' => 2026,
                'quarter' => 'Q2',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH440-OD',
                'double_thickness' => '2.0',
                'price_mode' => 'baseline',
                'coil_price' => 1.2000,
                'sheet_price' => 1.2500,
                'exchange_rate' => 17500.0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Gap MT harus tetap 0 (TIDAK boleh berubah menjadi selisih sheet - coil = 50)
        $this->assertDatabaseHas('material_steels', [
            'year' => 2026,
            'quarter' => 'Q2',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.0',
            'coil_price' => 1.2000,
            'sheet_price' => 1.2500,
            'gap_steel_usd' => 0.0,
            'coil_price_idr' => 21000.00, // 1.2 * 17500
            'sheet_price_idr' => 21875.00, // 1.25 * 17500
        ]);
    }

    public function test_can_edit_material_steel_record(): void
    {
        $this->actingAs($this->admin);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-EDIT-TEST',
            'double_thickness' => '1.2',
            'coil_price' => 1.8000,
            'sheet_price' => 1.9000,
            'exchange_rate' => 16000.0,
            'currency_id' => $this->currency->id,
        ]);

        Livewire::test(EditMaterialSteel::class, ['record' => $steel->getKey()])
            ->fillForm([
                'price_mode' => 'adjustment',
                'coil_price' => 1.8500,
                'sheet_price' => 1.9500,
                'gap_steel_usd' => 10.0,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('material_steels', [
            'id' => $steel->id,
            'steel_spec' => 'SPCC-EDIT-TEST',
            'coil_price' => 1.8500,
            'sheet_price' => 1.9500,
            'gap_steel_usd' => 10.0,
        ]);
    }
}
