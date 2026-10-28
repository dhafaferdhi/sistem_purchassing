<?php

namespace Tests\Feature;

use App\Filament\Resources\MaterialSteelResource\Pages\ListMaterialSteels;
use App\Models\Currency;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExcelExportService;
use App\Services\ExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExcelImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplier;

    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->supplier = Supplier::create([
            'code' => 'VND-POSCO',
            'name' => 'PT Krakatau Posco',
            'category' => 'Steel',
        ]);

        $this->currency = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'exchange_rate_to_idr' => 16000.0,
        ]);
    }

    public function test_can_export_material_steel_to_xlsx(): void
    {
        MaterialSteel::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.2096,
            'sheet_price' => 1.2346,
            'exchange_rate' => 16022.0,
        ]);

        $records = MaterialSteel::all();
        $response = ExcelExportService::exportMaterialSteel($records, 'xlsx');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_can_export_material_steel_to_csv(): void
    {
        MaterialSteel::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.2096,
            'sheet_price' => 1.2346,
            'exchange_rate' => 16022.0,
        ]);

        $records = MaterialSteel::all();
        $response = ExcelExportService::exportMaterialSteel($records, 'csv');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_can_download_material_steel_template(): void
    {
        $responseXlsx = ExcelExportService::downloadMaterialSteelTemplate('xlsx');
        $this->assertEquals(200, $responseXlsx->getStatusCode());
        $this->assertStringContainsString('.xlsx', $responseXlsx->headers->get('Content-Disposition'));

        $responseCsv = ExcelExportService::downloadMaterialSteelTemplate('csv');
        $this->assertEquals(200, $responseCsv->getStatusCode());
        $this->assertStringContainsString('.csv', $responseCsv->headers->get('Content-Disposition'));
    }

    public function test_list_material_steels_page_renders_import_export_header_actions(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListMaterialSteels::class)
            ->assertActionExists('import_excel')
            ->assertActionExists('export_excel')
            ->assertActionExists('download_template_xlsx')
            ->assertActionDoesNotExist('download_template_csv');
    }

    public function test_roundtrip_export_and_import_material_steel(): void
    {
        $this->actingAs($this->admin);

        // 1. Buat data awal
        MaterialSteel::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.2096,
            'sheet_price' => 1.2346,
            'exchange_rate' => 16022.0,
        ]);

        // 2. Export ke file sementara
        $tempPath = storage_path('app/temp_test_roundtrip.xlsx');
        $headers = ['Kuartal', 'Tahun', 'Supplier', 'Spesifikasi Baja', 'Thickness', 'Coil ($)', 'Sheet ($)', 'Kurs USD'];
        $rows = [
            ['Q2', 2025, 'PT Krakatau Posco', 'SPH270C-OD', '1.6', 1.2096, 1.2346, 16022],
            ['Q3', 2025, 'PT Krakatau Posco', 'SPH270C-OD', '1.6', 1.2096, 1.2346, 16517],
        ];
        ExcelExportService::createXlsxFile($tempPath, $headers, $rows);

        $this->assertFileExists($tempPath);

        // 3. Impor kembali menggunakan ExcelImportService
        $result = ExcelImportService::importMaterialSteel($tempPath, ['year' => 2025, 'quarter' => 'Q2'], true);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertEmpty($result['errors']);

        // Verifikasi data masuk ke database
        $q3Record = MaterialSteel::where('year', 2025)->where('quarter', 'Q3')->where('steel_spec', 'SPH270C-OD')->first();
        $this->assertNotNull($q3Record);
        $this->assertEquals(1.2096, (float) $q3Record->coil_price);
        $this->assertEquals(19978.96, (float) $q3Record->coil_price_idr);

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
}
