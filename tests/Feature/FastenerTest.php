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
            ->assertFormFieldExists('steel_spec')
            ->assertFormFieldExists('double_thickness')
            ->assertFormFieldExists('gap_steel_usd')
            ->assertFormFieldExists('gap_steel_kg_usd')
            ->assertFormFieldExists('exchange_rate')
            ->assertFormFieldExists('coil_price')
            ->assertFormFieldExists('coil_price_idr')
            ->assertFormFieldExists('sheet_price')
            ->assertFormFieldExists('sheet_price_idr')
            ->assertActionExists('back');
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
        $this->assertEquals('Pasterner', FastenerResource::getNavigationLabel());
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
}
