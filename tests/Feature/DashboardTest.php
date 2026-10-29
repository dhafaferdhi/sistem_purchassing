<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CostBreakdownDoughnutChart;
use App\Filament\Widgets\ExecutivePurchasingStatsWidget;
use App\Filament\Widgets\LatestPartsPriceMatrixWidget;
use App\Filament\Widgets\PurchasingHeaderWidget;
use App\Filament\Widgets\SteelPriceTrendChart;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->supplier = Supplier::create([
            'code' => 'VND-POSMI',
            'name' => 'PT. POSMI STEEL INDONESIA',
            'category' => 'Steel',
        ]);

        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'year' => 2026,
            'quarter' => 'Q1',
            'exchange_rate_to_idr' => 16465,
        ]);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '1.6',
            'coil_price' => 1.15,
            'sheet_price' => 1.175,
            'exchange_rate' => 16465,
        ]);

        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'part_number' => '72388-X7V00-A',
            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'exchange_rate' => 16465,
            'material_total_cost' => 8000,
            'process_total_cost' => 3000,
            'overhead_self_cost' => 994.92,
            'total_cost' => 11994.92,
        ]);

        Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'part_number' => '90041-19960',
            'part_name' => 'BOLT,W/WASHER',
            'steel_spec' => 'SWCH-45K',
            'exchange_rate' => 16465,
            'material_total' => 367.02,
            'process_total' => 225.45,
            'overhead_self_cost' => 84.72,
            'part_cost' => 677.20,
        ]);
    }

    public function test_can_access_dashboard_page(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin');
        $response->assertSuccessful();
    }

    public function test_dashboard_renders_interactive_widgets(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertSuccessful()
            ->assertFormFieldExists('year', 'filtersForm')
            ->assertFormFieldExists('quarter', 'filtersForm')
            ->assertFormFieldExists('supplier_id', 'filtersForm');

        Livewire::test(PurchasingHeaderWidget::class)
            ->assertSuccessful()
            ->assertSee('PT. FUJI SEAT INDONESIA')
            ->assertSee('Cost Planning System');

        Livewire::test(ExecutivePurchasingStatsWidget::class)
            ->assertSuccessful()
            ->assertSee('Kurs USD Acuan')
            ->assertSee('Stamping Parts')
            ->assertSee('Fasteners (Baut & Washer)');

        Livewire::test(SteelPriceTrendChart::class)
            ->assertSuccessful();

        Livewire::test(CostBreakdownDoughnutChart::class)
            ->assertSuccessful();

        Livewire::test(LatestPartsPriceMatrixWidget::class)
            ->assertSuccessful()
            ->assertSee('BRKT, SEAT BELT ANCHOR LH')
            ->assertSee('72388-X7V00-A');
    }
}
