<?php

namespace Tests\Feature;

use App\Filament\Resources\SubAssemblyPartResource;
use App\Filament\Resources\SubAssemblyPartResource\Pages\CreateSubAssemblyPart;
use App\Filament\Resources\SubAssemblyPartResource\Pages\ListSubAssemblyParts;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\SubAssemblyPart;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubAssemblyPartTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplierTcf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->supplierTcf = Supplier::create([
            'code' => 'VND-TCF',
            'name' => 'PT. TRI CENTRUM FORTUNA',
            'category' => 'Component',
        ]);
    }

    public function test_sub_assembly_part_calculates_exact_tcf_excel_formulas(): void
    {
        // Parameter persis dari Excel PT. TRI CENTRUM FORTUNA:
        // Part No: 73012-BZ010
        // Child 1: 72388-X7V00-A @ 10583.5294 (Qty 1)
        // Child 2: 89911-X7095 BOLT @ 0 (Supply)
        // Child 3: 89911-X1F05 BOLT @ 0 (Supply)
        // Material Total: 10583.5294
        // Process 1: Las Bolt 1 = 540 (20 sec * 27)
        // Process 2: Las Bolt 2 = 918 (34 sec * 27)
        // Process 3: Cat CED = 2800
        // Process Total = 540 + 918 + 2800 = 4258.00
        // Subtotal = 10583.5294 + 4258.00 = 14841.5294
        // O/H Supplied (11% of process total) = 11% * 4258 = 468.38
        // Profit (0%) = 0
        // Packing = 120.00
        // Tooling depr = 0
        // Total Part Cost = 14841.5294 + 468.38 + 120 = 15429.9094
        $subAssy = SubAssemblyPart::create([
            'year' => 2022,
            'quarter' => 'Q2',
            'part_number' => '73012-BZ010',
            'part_name' => 'BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH',
            'model_name' => 'D26A',
            'monthly_volume' => 16500,
            'supplier_id' => $this->supplierTcf->id,
            'supplier_name' => $this->supplierTcf->name,
            'drawing_reference' => 'Mass Prod. Drwg (Exchange rate change)',
            'eci_number' => 'F1225',
            'exchange_rate' => 14300,
            'material_breakdown' => [
                ['part_number' => '72388-X7V00-A', 'part_name' => 'BRKT, SEAT BELT ANCHOR LH', 'quantity' => 1, 'unit_price' => 10583.5294, 'price' => 10583.5294, 'valuation' => 'Posmi'],
                ['part_number' => '89911-X7095', 'part_name' => 'BOLT WELD', 'quantity' => 1, 'unit_price' => 0, 'price' => 0, 'valuation' => 'SUPPLY'],
                ['part_number' => '89911-X1F05', 'part_name' => 'BOLT PWR SEAT WELD', 'quantity' => 1, 'unit_price' => 0, 'price' => 0, 'valuation' => 'SUPPLY'],
            ],
            'process_breakdown' => [
                ['process_name' => '1. WELD CO BOLT WELD 89911-X7095', 'man_hour' => 20, 'charge' => 27, 'price' => 540],
                ['process_name' => '2. WELD C0 BOLT WELD 89911-X1F05', 'man_hour' => 34, 'charge' => 27, 'price' => 918],
                ['process_name' => '3. PAINTING CED', 'man_hour' => 0, 'charge' => 0, 'price' => 2800],
            ],
            'overhead_supplied_percent' => 11,
            'profit_percent' => 0,
            'packing_cost' => 120,
            'tooling_depreciation' => 0,
        ]);

        $this->assertEqualsWithDelta(10583.5294, (float) $subAssy->material_total, 0.01);
        $this->assertEqualsWithDelta(4258.00, (float) $subAssy->process_total, 0.01);
        $this->assertEqualsWithDelta(14841.5294, (float) $subAssy->subtotal, 0.01);
        $this->assertEqualsWithDelta(468.38, (float) $subAssy->overhead_supplied_cost, 0.01);
        $this->assertEqualsWithDelta(15429.91, (float) $subAssy->part_cost, 0.05);
    }

    public function test_sub_assembly_navigation_and_list_view(): void
    {
        $this->actingAs($this->admin);

        $this->assertEquals('Sub-Assembly Parts', SubAssemblyPartResource::getNavigationLabel());
        $this->assertEquals('Master Data', SubAssemblyPartResource::getNavigationGroup());
        $this->assertEquals(5, SubAssemblyPartResource::getNavigationSort());

        $subAssy = SubAssemblyPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'part_number' => '73012-BZ010',
            'part_name' => 'BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH',
            'supplier_id' => $this->supplierTcf->id,
            'supplier_name' => $this->supplierTcf->name,
            'part_cost' => 19838.79,
        ]);

        Livewire::test(ListSubAssemblyParts::class)
            ->assertCanSeeTableRecords([$subAssy])
            ->assertTableColumnExists('part_number')
            ->assertTableColumnExists('part_name')
            ->assertTableColumnExists('model_name')
            ->assertTableColumnExists('material_total')
            ->assertTableColumnExists('process_total')
            ->assertTableColumnExists('subtotal')
            ->assertTableColumnExists('part_cost');
    }

    public function test_sub_assembly_create_and_pull_previous_quarter(): void
    {
        $this->actingAs($this->admin);

        // Buat data Q1 2026
        SubAssemblyPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'part_number' => '73012-BZ010',
            'part_name' => 'BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH',
            'supplier_id' => $this->supplierTcf->id,
            'supplier_name' => $this->supplierTcf->name,
            'drawing_reference' => 'Mass Prod. Drwg',
            'eci_number' => 'F1225',
            'material_breakdown' => [
                ['part_number' => '72388-X7V00-A', 'part_name' => 'BRKT', 'quantity' => 1, 'unit_price' => 10000, 'price' => 10000],
            ],
            'process_breakdown' => [
                ['process_name' => 'WELDING', 'price' => 4000],
            ],
            'packing_cost' => 120,
        ]);

        // Uji helper lookup
        $prevData = SubAssemblyPart::findPreviousQuarterData('Q2', 2026, $this->supplierTcf->id, '73012-BZ010');
        $this->assertNotNull($prevData);
        $this->assertEquals(2026, $prevData['prev_year']);
        $this->assertEquals('Q1', $prevData['prev_quarter']);
        $this->assertEquals('73012-BZ010', $prevData['part_number']);
        $this->assertEquals('Mass Prod. Drwg', $prevData['drawing_reference']);
        $this->assertEquals('F1225', $prevData['eci_number']);

        // Uji Create action pull_previous
        Livewire::test(CreateSubAssemblyPart::class)
            ->fillForm([
                'year' => 2026,
                'quarter' => 'Q2',
                'supplier_id' => $this->supplierTcf->id,
                'part_number' => '73012-BZ010',
            ])
            ->callAction('pull_previous')
            ->assertFormSet([
                'part_name' => 'BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH',
                'drawing_reference' => 'Mass Prod. Drwg',
                'eci_number' => 'F1225',
            ]);
    }

    public function test_material_steel_supports_ap_prices(): void
    {
        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplierTcf->id,
            'supplier_name' => $this->supplierTcf->name,
            'vendor_code' => $this->supplierTcf->code,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.2000,
            'sheet_price' => 1.2300,
            'ap_coil_price' => 1.1500,
            'ap_sheet_price' => 1.1800,
            'exchange_rate' => 16000,
        ]);

        $this->assertEquals(19200.0, (float) $steel->coil_price_idr);
        $this->assertEquals(19680.0, (float) $steel->sheet_price_idr);
        $this->assertEquals(18400.0, (float) $steel->ap_coil_price_idr);
        $this->assertEquals(18880.0, (float) $steel->ap_sheet_price_idr);
    }

    public function test_stamping_part_supports_tooling_depreciation_and_metadata(): void
    {
        $part = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplierTcf->id,
            'supplier_name' => $this->supplierTcf->name,
            'vendor_code' => $this->supplierTcf->code,
            'part_number' => '72388-X7V00-A',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'yield_rate' => 2,
            'drawing_reference' => 'Mass Prod. Drwg',
            'eci_number' => 'F1225',
            'berat' => 0.5890326,
            'scrap_weight' => 0.2780,
            'scrap_price_per_kg' => -1500,
            'process_man_hour' => 9,
            'process_charge' => 45,
            'overhead_supplied_percent' => 11,
            'overhead_self_percent' => 4,
            'exchange_rate' => 14300,
            'coil_price' => 1.232,
            'sheet_price' => 1.232,
            'tooling_depreciation' => 50.0,
        ]);

        $this->assertEquals('Mass Prod. Drwg', $part->drawing_reference);
        $this->assertEquals('F1225', $part->eci_number);
        $this->assertEquals(50.0, (float) $part->tooling_depreciation);
        // Base part_cost (10582.94) + tooling_depreciation (50.0) = 10632.94
        $this->assertEqualsWithDelta(10632.94, (float) $part->part_cost, 0.05);
    }

    public function test_fastener_supports_tooling_depreciation_and_exchange_fluctuation(): void
    {
        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplierTcf->id,
            'supplier_name' => $this->supplierTcf->name,
            'vendor_code' => $this->supplierTcf->code,
            'part_number' => '90041-19960',
            'part_name' => 'BOLT,W/WASHER',
            'steel_spec' => 'SWCH-45K',
            'drawing_reference' => 'Official Dwg (supplier selection)',
            'eci_number' => 'F1225',
            'old_exchange_rate' => 14555.0,
            'exchange_rate' => 14752.0,
            'tooling_depreciation' => 10.0,
        ]);

        $this->assertEquals('Official Dwg (supplier selection)', $fastener->drawing_reference);
        $this->assertEquals('F1225', $fastener->eci_number);
        $this->assertEquals(14555.0, (float) $fastener->old_exchange_rate);
        $this->assertEquals(14752.0, (float) $fastener->exchange_rate);
        // 14752 / 14555 = 1.013535
        $this->assertEqualsWithDelta(1.013535, (float) $fastener->exchange_rate_fluctuation, 0.00001);
        $this->assertEquals(10.0, (float) $fastener->tooling_depreciation);
    }

    public function test_sub_assembly_material_breakdown_auto_fills_from_stamping_fastener_and_steel(): void
    {
        $this->actingAs($this->admin);

        $stamping = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplierTcf->id,
            'supplier_name' => $this->supplierTcf->name,
            'vendor_code' => $this->supplierTcf->code,
            'part_number' => '72388-X7V00-A',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'steel_spec' => 'SPH440-OD',
        ]);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplierTcf->id,
            'supplier_name' => $this->supplierTcf->name,
            'vendor_code' => $this->supplierTcf->code,
            'part_number' => '89911-X7095',
            'part_name' => 'BOLT WELD 89911-X7095',
            'steel_spec' => 'SWCH-45K',
        ]);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplierTcf->id,
            'supplier_name' => $this->supplierTcf->name,
            'vendor_code' => $this->supplierTcf->code,
            'steel_spec' => 'SPH270C-OD',
            'sheet_price_idr' => 22100.0,
        ]);

        Livewire::test(CreateSubAssemblyPart::class)
            ->set('data.material_breakdown.0.part_number', '72388-X7V00-A')
            ->assertSet('data.material_breakdown.0.part_name', 'BRKT, SEAT BELT ANCHOR LH')
            ->assertSet('data.material_breakdown.0.unit_price', (float) $stamping->part_cost)
            ->set('data.material_breakdown.1.part_number', '89911-X7095')
            ->assertSet('data.material_breakdown.1.part_name', 'BOLT WELD 89911-X7095')
            ->assertSet('data.material_breakdown.1.unit_price', (float) $fastener->part_cost)
            ->set('data.material_breakdown.2.part_number', 'SPH270C-OD')
            ->assertSet('data.material_breakdown.2.part_name', 'RAW STEEL SPH270C-OD')
            ->assertSet('data.material_breakdown.2.unit_price', (float) $steel->sheet_price_idr);
    }
}
