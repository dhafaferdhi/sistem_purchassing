<?php

namespace Tests\Feature;

use App\Filament\Pages\ComparativeQuotationSheet;
use App\Filament\Pages\MonthlyRollover;
use App\Filament\Pages\PartPriceTrend;
use App\Filament\Pages\PriceComparison;
use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\FastenerResource;
use App\Filament\Resources\GoodsReceiptResource;
use App\Filament\Resources\MaterialSteelResource;
use App\Filament\Resources\PartResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\StampingPartResource;
use App\Filament\Resources\SubAssemblyPartResource;
use App\Filament\Widgets\PudPlantStatusOverviewWidget;
use App\Filament\Widgets\PudProgressSlaWidget;
use App\Filament\Widgets\PudRequestAllPlantTableWidget;
use App\Filament\Widgets\PudRequestStatsWidget;
use App\Filament\Widgets\PudRequestTrendChart;
use App\Jobs\ProcessMonthlyRolloverJob;
use App\Jobs\RecalculateCurrencyRatesJob;
use App\Models\ComparativeQuotation;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\MaterialSteel;
use App\Models\Part;
use App\Models\PartPriceHistory;
use App\Models\PartQuotation;
use App\Models\PudRequest;
use App\Models\PurchaseOrder;
use App\Models\StampingPart;
use App\Models\SubAssemblyPart;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CurrencyRecalculationService;
use App\Services\ExcelExportService;
use App\Services\MonthlyDataRolloverService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class PerformanceAndScalabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_performance_indexes_migration_creates_expected_indexes(): void
    {
        $expectedIndexes = [
            'material_steels' => [
                'idx_ms_year_quarter_status',
                'idx_ms_year_quarter_currency',
                'idx_ms_steel_spec',
                'idx_ms_supplier_name',
                'idx_ms_currency_id',
            ],
            'stamping_parts' => [
                'idx_sp_year_quarter_status',
                'idx_sp_year_quarter_currency',
                'idx_sp_year_quarter_part',
                'idx_sp_supplier_name',
                'idx_sp_steel_spec',
                'idx_sp_part_name',
                'idx_sp_currency_id',
            ],
            'fasteners' => [
                'idx_fst_year_quarter_status',
                'idx_fst_year_quarter_currency',
                'idx_fst_year_quarter_part',
                'idx_fst_supplier_name',
                'idx_fst_vendor_code',
                'idx_fst_part_name',
                'idx_fst_currency_id',
            ],
            'sub_assembly_parts' => [
                'idx_sap_year_quarter_status',
                'idx_sap_year_quarter_currency',
                'idx_sap_currency_id',
            ],
            'purchase_orders' => [
                'idx_po_status_delivery',
                'idx_po_order_date',
                'idx_po_supplier_order_date',
            ],
            'pud_requests' => [
                'idx_pud_del_date_plant_status',
                'idx_pud_del_status_created',
                'idx_pud_del_plant_div',
            ],
            'currencies' => [
                'idx_cur_code_year_quarter',
                'idx_cur_year_quarter_month',
            ],
            'suppliers' => [
                'idx_sup_active_name',
                'idx_sup_name',
            ],
            'parts' => [
                'idx_parts_item_code',
                'idx_parts_part_name',
                'idx_parts_active_number',
            ],
        ];

        foreach ($expectedIndexes as $table => $names) {
            $actualNames = collect(Schema::getIndexes($table))->pluck('name')->all();

            foreach ($names as $indexName) {
                $this->assertContains($indexName, $actualNames, "Missing index {$indexName} on {$table}");
            }
        }
    }

    public function test_currency_recalculation_chunks_over_200_rows_uses_batch_cache_and_queues_when_async(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-CHUNK',
            'name' => 'PT Chunk Steel',
            'category' => 'Steel',
        ]);

        $currency = Currency::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 15000.0,
        ]);

        Currency::withBatchCache(function () use ($supplier, $currency): void {
            for ($i = 1; $i <= 205; $i++) {
                MaterialSteel::create([
                    'year' => 2026,
                    'quarter' => 'Q1',
                    'supplier_id' => $supplier->id,
                    'currency_id' => $currency->id,
                    'currency_code' => 'USD',
                    'steel_spec' => "SPEC-{$i}-OD",
                    'double_thickness' => '1.2',
                    'steel_price' => 2.0,
                    'coil_price' => 2.0,
                    'sheet_price' => 2.5,
                    'exchange_rate' => 15000.0,
                ]);
            }

            StampingPart::create([
                'year' => 2026,
                'quarter' => 'Q1',
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'currency_id' => $currency->id,
                'part_number' => 'SP-205',
                'part_name' => 'STAMPING TEST',
                'steel_spec' => 'SPEC-1-OD',
                'double_thickness' => '1.2',
                'exchange_rate' => 15000.0,
                'coil_price' => 2.0,
                'sheet_price' => 2.5,
            ]);

            Fastener::create([
                'year' => 2026,
                'quarter' => 'Q1',
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'currency_id' => $currency->id,
                'part_number' => 'FN-205',
                'part_name' => 'FASTENER TEST',
                'steel_spec' => 'SPEC-1-OD',
                'exchange_rate' => 15000.0,
                'wire_price' => 2.0,
            ]);
        });

        Currency::withoutAutoRecalculate(function () use ($currency): void {
            $currency->update(['exchange_rate_to_idr' => 16000.0]);
        });

        $freshCurrency = $currency->fresh();
        $currencyQueries = 0;
        DB::listen(function ($query) use (&$currencyQueries): void {
            if (str_contains($query->sql, 'from "currencies"') && str_contains(strtolower($query->sql), 'select')) {
                $currencyQueries++;
            }
        });

        $stats = CurrencyRecalculationService::recalculateForCurrencyWithStats($freshCurrency);

        $this->assertSame(205, $stats['material_steels']);
        $this->assertSame(1, $stats['stamping_parts']);
        $this->assertSame(1, $stats['fasteners']);
        $this->assertSame(207, $stats['total']);
        $this->assertLessThanOrEqual(5, $currencyQueries);
        $this->assertSame(16000.0, (float) MaterialSteel::where('steel_spec', 'SPEC-1-OD')->value('exchange_rate'));
        $this->assertSame(16000.0, (float) MaterialSteel::where('steel_spec', 'SPEC-205-OD')->value('exchange_rate'));
        $this->assertSame(32000.0, (float) MaterialSteel::where('steel_spec', 'SPEC-205-OD')->value('coil_price_idr'));

        Queue::fake([RecalculateCurrencyRatesJob::class]);
        config(['queue.default' => 'database']);

        $queued = CurrencyRecalculationService::dispatchOrRunForCurrency($currency->fresh());

        $this->assertTrue($queued);
        Queue::assertPushed(RecalculateCurrencyRatesJob::class, 1);
        Queue::assertPushed(
            RecalculateCurrencyRatesJob::class,
            fn (RecalculateCurrencyRatesJob $job): bool => $job->currencyId === $currency->id
        );
    }

    public function test_monthly_rollover_preserves_currency_quarter_chunks_batches_and_dispatches_job_when_async(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'code' => 'SUP-ROLL',
            'name' => 'PT Rollover Chunk',
            'category' => 'Steel',
        ]);

        Currency::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'month' => null,
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 15500.0,
        ]);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $supplier->id,
            'currency_code' => 'USD',
            'steel_spec' => 'SPCC-CHUNK',
            'double_thickness' => '1.4',
            'steel_price' => 1.5,
            'coil_price' => 1.5,
            'sheet_price' => 1.6,
            'exchange_rate' => 15500.0,
        ]);

        Currency::withBatchCache(function () use ($supplier): void {
            for ($i = 1; $i <= 205; $i++) {
                StampingPart::create([
                    'year' => 2026,
                    'quarter' => 'Q1',
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'part_number' => sprintf('STAMP-ROLL-%03d', $i),
                    'part_name' => "Part Roll {$i}",
                    'steel_spec' => 'SPCC-CHUNK',
                    'double_thickness' => '1.4',
                    'exchange_rate' => 15500.0,
                ]);
            }
        });

        $currStats = MonthlyDataRolloverService::rolloverCurrencies(2026, 2027, 16200.0);

        $this->assertSame(1, $currStats['copied']);
        $q2Target = Currency::where('year', 2027)->where('quarter', 'Q2')->where('code', 'USD')->first();
        $this->assertNotNull($q2Target);
        $this->assertSame(16200.0, (float) $q2Target->exchange_rate_to_idr);

        $stampingStats = MonthlyDataRolloverService::rolloverStampingParts(
            sourceYear: 2026,
            sourceQuarter: 'Q1',
            targetYear: 2026,
            targetQuarter: 'Q2',
            exchangeRate: 16200.0,
            newGapSteelUsd: null,
            status: 'Active',
            duplicateHandling: 'overwrite',
            supplierId: $supplier->id,
            syncFromMaterialSteel: true,
        );

        $this->assertSame(205, $stampingStats['copied']);
        $this->assertSame(205, StampingPart::where('year', 2026)->where('quarter', 'Q2')->count());

        Queue::fake([ProcessMonthlyRolloverJob::class]);
        config(['queue.default' => 'database']);

        Livewire::actingAs($admin)
            ->test(MonthlyRollover::class)
            ->set('sourceYear', 2026)
            ->set('sourceQuarter', 'Q1')
            ->set('targetYear', 2026)
            ->set('targetQuarter', 'Q3')
            ->call('executeAllRollover');

        Queue::assertPushed(ProcessMonthlyRolloverJob::class, 1);
        Queue::assertPushed(
            ProcessMonthlyRolloverJob::class,
            fn (ProcessMonthlyRolloverJob $job): bool => $job->type === 'all'
                && ($job->params['targetQuarter'] ?? null) === 'Q3'
        );
    }

    public function test_excel_export_streams_query_xlsx_with_numeric_zero_and_decimals_and_csv(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-XLS',
            'name' => 'PT Streaming Excel',
            'category' => 'Steel',
        ]);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $supplier->id,
            'currency_code' => 'USD',
            'steel_spec' => 'ZERO-DECIMAL-SPEC',
            'double_thickness' => '1.0',
            'steel_price' => 0,
            'coil_price' => 0,
            'sheet_price' => 0.2780,
            'exchange_rate' => 16000,
        ]);

        $query = MaterialSteel::query()->where('steel_spec', 'ZERO-DECIMAL-SPEC');
        $xlsxResponse = ExcelExportService::exportMaterialSteel($query, 'xlsx');

        ob_start();
        $xlsxResponse->sendContent();
        $xlsxBytes = ob_get_clean();

        $tempZipPath = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        file_put_contents($tempZipPath, (string) $xlsxBytes);

        $zip = new ZipArchive;
        $opened = $zip->open($tempZipPath);
        $sheetXml = $opened === true ? $zip->getFromName('xl/worksheets/sheet1.xml') : false;
        if ($opened === true) {
            $zip->close();
        }
        @unlink($tempZipPath);

        $this->assertTrue($opened === true);
        $this->assertIsString($sheetXml);
        $this->assertStringContainsString('<v>0</v>', $sheetXml);
        $this->assertStringContainsString('<v>0.278</v>', $sheetXml);
        $this->assertStringContainsString('ZERO-DECIMAL-SPEC', $sheetXml);

        $csvResponse = ExcelExportService::exportMaterialSteel(
            MaterialSteel::query()->where('steel_spec', 'ZERO-DECIMAL-SPEC'),
            'csv'
        );

        ob_start();
        $csvResponse->sendContent();
        $csvContent = ob_get_clean();

        $this->assertIsString($csvContent);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);
        $this->assertStringContainsString('ZERO-DECIMAL-SPEC', $csvContent);
        $this->assertStringContainsString('0.278', $csvContent);
    }

    public function test_pud_request_workdays_o1_and_sql_aggregated_widgets_match_exact_counts(): void
    {
        $this->travelTo('2026-11-11 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $today = Carbon::now();

        $this->assertSame(1, PudRequest::calculateWorkDaysBetween(Carbon::parse('2026-11-11'), $today));
        $this->assertSame(3, PudRequest::calculateWorkDaysBetween(Carbon::parse('2026-11-09'), $today));
        $this->assertSame(4, PudRequest::calculateWorkDaysBetween(Carbon::parse('2026-11-06'), $today));
        $this->assertSame(1, PudRequest::calculateWorkDaysBetween(Carbon::parse('2026-11-07'), Carbon::parse('2026-11-08')));
        $this->assertSame(748, PudRequest::calculateWorkDaysBetween(Carbon::parse('2024-01-01'), $today));

        PudRequest::create([
            'request_number' => 'PUD-2026-900001',
            'request_date' => '2026-11-11',
            'user_name' => 'USER A',
            'plant' => 'SUNTER',
            'division' => 'MTC',
            'part_name' => 'SENSOR ON TRACK',
            'purpose' => 'LINE A',
            'spec' => '24V',
            'status' => PudRequest::STATUS_DELIVERED,
            'created_at' => '2026-11-11 08:00:00',
        ]);

        PudRequest::create([
            'request_number' => 'PUD-2026-900002',
            'request_date' => '2026-11-09',
            'user_name' => 'USER B',
            'plant' => 'SUNTER',
            'division' => 'MTC',
            'part_name' => 'VALVE ATTENTION',
            'purpose' => 'LINE B',
            'spec' => '1/2 INCH',
            'status' => PudRequest::STATUS_REQUEST_QUOTATION,
            'created_at' => '2026-11-09 08:00:00',
        ]);

        PudRequest::create([
            'request_number' => 'PUD-2026-900003',
            'request_date' => '2026-11-06',
            'user_name' => 'USER C',
            'plant' => 'KIIC',
            'division' => 'IT',
            'part_name' => 'SWITCH DUE SOON',
            'purpose' => 'NETWORK',
            'spec' => '24 PORT',
            'status' => PudRequest::STATUS_REVIEW_NEGOTIATION,
            'created_at' => '2026-11-06 08:00:00',
        ]);

        PudRequest::create([
            'request_number' => 'PUD-2026-900004',
            'request_date' => '2026-11-02',
            'user_name' => 'USER D',
            'plant' => 'KIIC',
            'division' => 'IT',
            'part_name' => 'MOTOR OVERDUE',
            'purpose' => 'PRESS',
            'spec' => '5KW',
            'status' => PudRequest::STATUS_ITEM_CODE_CREATED,
            'created_at' => '2026-11-02 08:00:00',
        ]);

        PudRequest::create([
            'request_number' => 'PUD-2026-900005',
            'request_date' => '2026-11-05',
            'user_name' => 'USER E',
            'plant' => 'SC1',
            'division' => 'GA',
            'part_name' => 'CLOSED ITEM',
            'purpose' => 'OFFICE',
            'spec' => 'STANDARD',
            'status' => PudRequest::STATUS_CLOSED,
            'created_at' => '2026-11-05 08:00:00',
        ]);

        PudRequest::create([
            'request_number' => 'PUD-2026-900006',
            'request_date' => '2026-11-11',
            'user_name' => 'DELETED USER',
            'plant' => 'SUNTER',
            'division' => 'MTC',
            'part_name' => 'SOFT DELETED',
            'purpose' => 'IGNORE',
            'spec' => 'IGNORE',
            'status' => PudRequest::STATUS_DELIVERED,
            'is_deleted' => true,
            'created_at' => '2026-11-11 08:00:00',
        ]);

        Livewire::actingAs($admin)
            ->test(PudRequestStatsWidget::class)
            ->assertSuccessful()
            ->assertSee('Total Request')
            ->assertSee('5');

        Livewire::actingAs($admin)
            ->test(PudProgressSlaWidget::class)
            ->assertSuccessful()
            ->assertSee('On Track (Hari 1-2)')
            ->assertSee('Attention (Hari 3)')
            ->assertSee('Due Soon (Hari 4)')
            ->assertSee('Overdue (> Hari 5)');

        Livewire::actingAs($admin)
            ->test(PudRequestTrendChart::class)
            ->assertSuccessful()
            ->assertSee('SUNTER: 2 | KIIC: 2 | SC1: 1 | SC2: 0');

        Livewire::actingAs($admin)
            ->test(PudPlantStatusOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Total: 2');

        Livewire::actingAs($admin)
            ->test(PudRequestAllPlantTableWidget::class)
            ->assertSuccessful()
            ->assertSee('MTC')
            ->assertSee('2');
    }

    public function test_lazy_loading_protection_and_filament_resources_pages_render_without_n_plus_1(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'code' => 'SUP-N1',
            'name' => 'PT No Lazy Loading',
            'category' => 'Steel',
        ]);

        $currency = Currency::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
        ]);

        $material = Material::create([
            'spec' => 'SPH270C-N1',
            'category' => 'Presspart',
            'supplier_id' => $supplier->id,
            'currency_id' => $currency->id,
            'basic_rate' => 1.5,
            'price_idr' => 24000,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $part = Part::create([
            'item_code' => 'PART-N1-001',
            'part_number' => 'PART-N1-001',
            'part_name' => 'BRACKET N1',
            'description' => 'BRACKET N1',
            'item_category' => 'Stamping',
            'material_id' => $material->id,
            'weight' => 0.45,
        ]);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $supplier->id,
            'currency_id' => $currency->id,
            'steel_spec' => 'SPH270C-N1',
            'double_thickness' => '1.6',
            'coil_price' => 1.2,
            'sheet_price' => 1.25,
            'exchange_rate' => 16000.0,
        ]);

        $stamping = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'currency_id' => $currency->id,
            'part_id' => $part->id,
            'part_number' => 'SP-CHILD-001',
            'part_name' => 'STAMPING CHILD',
            'steel_spec' => 'SPH270C-N1',
            'double_thickness' => '1.6',
            'exchange_rate' => 16000.0,
        ]);

        Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'currency_id' => $currency->id,
            'part_number' => 'FN-CHILD-001',
            'part_name' => 'FASTENER CHILD',
            'steel_spec' => 'SWCH-45K',
            'exchange_rate' => 16000.0,
        ]);

        $subAssembly = SubAssemblyPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'part_number' => 'SA-PARENT-001',
            'part_name' => 'SUB ASSY PARENT',
            'material_breakdown' => [
                ['part_number' => 'sp-child-001', 'part_name' => 'STAMPING CHILD', 'qty' => 1, 'unit_price' => 5000],
            ],
        ]);

        PartPriceHistory::create([
            'part_id' => $part->id,
            'supplier_id' => $supplier->id,
            'price_date' => '2026-11-01',
            'source_type' => 'Purchase Order',
            'reference_number' => 'PO-2026-0001',
            'unit_price_idr' => 12500.0,
            'currency_code' => 'IDR',
            'original_price' => 12500.0,
        ]);

        PartQuotation::create([
            'part_id' => $part->id,
            'supplier_id' => $supplier->id,
            'currency_id' => $currency->id,
            'exchange_rate_to_idr' => 16000.0,
            'material_cost_original' => 0.5,
            'material_cost_idr' => 8000.0,
            'total_cost_idr' => 12000.0,
            'status' => 'Quoted',
        ]);

        ComparativeQuotation::create([
            'part_id' => $part->id,
            'part_number' => 'PART-N1-001',
            'part_name' => 'BRACKET N1',
            'part_type' => 'Stamping',
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'period_label' => 'Q1 2026',
            'year' => 2026,
            'quarter' => 'Q1',
            'sequence' => 1,
            'part_cost' => 12000.0,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'order_date' => '2026-11-01',
            'delivery_date' => '2026-11-15',
            'payment_terms' => 'Net 30 Days',
            'currency_id' => $currency->id,
            'exchange_rate' => 16000.0,
            'status' => 'Issued',
        ]);

        GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'delivery_order_number' => 'DO-N1-001',
            'received_date' => '2026-11-05',
            'received_by' => $admin->id,
            'status' => 'Completed',
        ]);

        $usedInSubAssemblies = $stamping->used_in_sub_assemblies;

        $this->assertCount(1, $usedInSubAssemblies);
        $this->assertSame($subAssembly->id, $usedInSubAssemblies->first()->id);

        $this->actingAs($admin);

        foreach ([
            CurrencyResource::getUrl('index'),
            MaterialSteelResource::getUrl('index'),
            StampingPartResource::getUrl('index'),
            FastenerResource::getUrl('index'),
            SubAssemblyPartResource::getUrl('index'),
            PartResource::getUrl('index'),
            QuotationResource::getUrl('index'),
            PurchaseOrderResource::getUrl('index'),
            GoodsReceiptResource::getUrl('index'),
        ] as $url) {
            $this->get($url)->assertSuccessful();
        }

        Livewire::actingAs($admin)->test(PartPriceTrend::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(PriceComparison::class)->assertSuccessful();
        Livewire::actingAs($admin)->test(ComparativeQuotationSheet::class)->assertSuccessful();
    }
}
