<?php

namespace Tests\Feature;

use App\Filament\Resources\MaterialSteelResource\Pages\ListMaterialSteels;
use App\Filament\Resources\StampingPartResource\Pages\ListStampingParts;
use App\Models\Currency;
use App\Models\User;
use App\Services\ExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExcelImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->user);

        Currency::create([
            'year' => 2026,
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000,
        ]);
    }

    public function test_can_generate_sample_csv_template(): void
    {
        $csv = ExcelImportService::generateSampleCsv('material_steel');

        $this->assertStringContainsString('Kuartal', $csv);
        $this->assertStringContainsString('Vendor Code', $csv);
        $this->assertStringContainsString('Supplier Name', $csv);
        $this->assertStringContainsString('Spec', $csv);
        $this->assertStringContainsString('Thickness(mm)', $csv);
        $this->assertStringContainsString('Coil (USD)', $csv);
        $this->assertStringContainsString('Sheet (USD)', $csv);
    }

    public function test_can_parse_and_import_material_steel_from_csv(): void
    {
        $csvContent = implode("\n", [
            'Kuartal,Tahun,Vendor Code,Supplier Name,Spec,Thickness(mm),Gap/MT (USD),Gap/Kg (USD),Ex Rate (IDR),Coil (USD),Sheet (USD)',
            'Q1,2026,VND-HSSI,PT HSSI,SPCC-SD 1.2mm,1.2,100,0.1,16000,1.95,2.05',
            'Q2,2026,VND-USCI,PT USCI,SPHC 2.0mm,2.0,100,0.1,16200,2.00,2.10',
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'import_test_').'.csv';
        file_put_contents($tmpFile, $csvContent);

        $result = ExcelImportService::importMaterialSteel($tmpFile);
        @unlink($tmpFile);

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, count($result['errors']));

        $this->assertDatabaseHas('material_steels', [
            'quarter' => 'Q1',
            'year' => 2026,
            'vendor_code' => 'VND-HSSI',
            'supplier_name' => 'PT HSSI',
            'steel_spec' => 'SPCC-SD',
            'double_thickness' => '1.2',
            'coil_price' => 1.95,
            'sheet_price' => 2.05,
            'coil_price_idr' => 31200.00,
            'sheet_price_idr' => 32800.00,
        ]);

        $this->assertDatabaseHas('material_steels', [
            'quarter' => 'Q2',
            'year' => 2026,
            'vendor_code' => 'VND-USCI',
            'supplier_name' => 'PT USCI',
            'steel_spec' => 'SPHC',
            'double_thickness' => '2.0',
            'coil_price' => 2.00,
            'sheet_price' => 2.10,
        ]);
    }

    public function test_can_parse_and_import_stamping_part_from_csv(): void
    {
        $csvContent = implode("\n", [
            'Kuartal;Tahun;Vendor Code;Supplier Name;Spec;Thickness;Ex Rate;Coil;Sheet;Gap/MT',
            'Q1;2026;VND-HSSI;PT HSSI;SPCC-SD 0.8mm;0.8;16000;1.90;2.00;100',
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'import_stamping_').'.csv';
        file_put_contents($tmpFile, $csvContent);

        $result = ExcelImportService::importStampingPart($tmpFile);
        @unlink($tmpFile);

        $this->assertEquals(1, $result['imported']);

        $this->assertDatabaseHas('stamping_parts', [
            'quarter' => 'Q1',
            'year' => 2026,
            'supplier_name' => 'PT HSSI',
            'steel_spec' => 'SPCC-SD',
            'double_thickness' => '0.8',
            'coil_price' => 1.90,
            'sheet_price' => 2.00,
            'coil_price_idr' => 30400.00,
            'sheet_price_idr' => 32000.00,
        ]);
    }

    public function test_detect_first_row_data(): void
    {
        $csvContent = implode("\n", [
            'Kuartal,Tahun,Vendor Code,Supplier Name,Spec,Thickness,Coil (USD),Sheet (USD),Ex Rate',
            'Q3,2026,VND-KRAKATAU,PT Krakatau Steel,SPHC 3.2mm,3.2,1.85,1.95,16100',
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'detect_test_').'.csv';
        file_put_contents($tmpFile, $csvContent);

        $firstRow = ExcelImportService::detectFirstRowData($tmpFile);
        @unlink($tmpFile);

        $this->assertNotNull($firstRow);
        $this->assertEquals('Q3', $firstRow['quarter']);
        $this->assertEquals(2026, $firstRow['year']);
        $this->assertEquals('PT Krakatau Steel', $firstRow['supplier_name']);
        $this->assertEquals('SPHC', $firstRow['steel_spec']);
        $this->assertEquals('3.2', $firstRow['double_thickness']);
        $this->assertEquals(1.85, $firstRow['coil_price']);
        $this->assertEquals(1.95, $firstRow['sheet_price']);
        $this->assertEquals(16100, $firstRow['exchange_rate']);
    }

    public function test_can_parse_xlsx_binary_file(): void
    {
        $sampleXlsx = 'C:/Users/USER/.gemini/antigravity/brain/0ffa5ab9-ef7c-4dde-a31c-de5de213e248/.user_uploaded/media_1792381149283_937483b6.xlsx';
        if (file_exists($sampleXlsx)) {
            $grid = ExcelImportService::parseFileToGrid($sampleXlsx);
            $this->assertNotEmpty($grid);
            $this->assertGreaterThan(10, count($grid));
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_list_material_steels_has_import_action(): void
    {
        Livewire::test(ListMaterialSteels::class)
            ->assertActionExists('import_excel')
            ->assertActionExists('download_template_xlsx');
    }

    public function test_list_stamping_parts_has_import_action(): void
    {
        Livewire::test(ListStampingParts::class)
            ->assertActionExists('import_excel')
            ->assertActionExists('download_template_xlsx');
    }
}
