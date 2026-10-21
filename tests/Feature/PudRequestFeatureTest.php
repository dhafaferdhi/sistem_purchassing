<?php

namespace Tests\Feature;

use App\Filament\Resources\PudMonitoringResource;
use App\Filament\Resources\PudMonitoringResource\Pages\EditPudMonitoring;
use App\Filament\Resources\PudMonitoringResource\Pages\ListPudMonitorings;
use App\Filament\Resources\PudMonitoringResource\Pages\ViewPudMonitoring;
use App\Filament\Resources\UserRequestResource\Pages\CreateUserRequest;
use App\Filament\Resources\UserRequestResource\Pages\ListUserRequests;
use App\Filament\Resources\UserRequestResource\Pages\ViewUserRequest;
use App\Filament\Widgets\PudProgressSlaWidget;
use App\Filament\Widgets\PudRequestStatsWidget;
use App\Filament\Widgets\PudRequestTrendChart;
use App\Models\PudRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PudRequestFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_can_view_user_requests_table(): void
    {
        $request = PudRequest::create([
            'request_number' => 'PUD-2026-000099',
            'request_date' => now(),
            'user_name' => 'BUDI TEST',
            'division' => 'ASSY',
            'plant' => 'KIIC',
            'part_name' => 'JIG CLAMP PNEUMATIC',
            'brand' => 'SMC',
            'type' => 'CKG1B50',
            'qty' => 5,
            'unit' => 'Pcs',
            'purpose' => 'REPLACEMENT JIG',
            'spec' => 'Clamping Cylinder 50mm Bore',
            'status' => PudRequest::STATUS_DELIVERED,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListUserRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->assertSee('PUD-2026-000099')
            ->assertSee('JIG CLAMP PNEUMATIC')
            ->assertSee('KIIC');
    }

    public function test_can_create_user_request_via_form(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUserRequest::class)
            ->fillForm([
                'user_name' => 'EKO WIDODO',
                'plant' => 'SUNTER',
                'division' => 'MTC',
                'part_name' => 'BEARING 6204 2RS',
                'brand' => 'NSK',
                'type' => 'DEEP GROOVE',
                'qty' => 10,
                'unit' => 'Pcs',
                'purpose' => 'PERIODIC OVERHAUL MOTOR',
                'spec' => 'ID 20mm, OD 47mm, Thickness 14mm, Rubber Sealed',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pud_requests', [
            'user_name' => 'EKO WIDODO',
            'plant' => 'SUNTER',
            'division' => 'MTC',
            'part_name' => 'BEARING 6204 2RS',
            'brand' => 'NSK',
            'status' => PudRequest::STATUS_DELIVERED,
        ]);

        $created = PudRequest::where('part_name', 'BEARING 6204 2RS')->first();
        $this->assertNotNull($created);
        $this->assertStringStartsWith('PUD-', $created->request_number);
    }

    public function test_can_cancel_user_request(): void
    {
        $request = PudRequest::create([
            'request_number' => 'PUD-2026-000100',
            'request_date' => now(),
            'user_name' => 'DONI',
            'division' => 'STAMPING',
            'plant' => 'KIIC',
            'part_name' => 'PUNCH PIN D10',
            'brand' => 'MISUMI',
            'type' => 'SHP10',
            'qty' => 4,
            'unit' => 'Pcs',
            'purpose' => 'DIE SPARE',
            'spec' => 'Dia 10mm Length 70mm',
            'status' => PudRequest::STATUS_DELIVERED,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListUserRequests::class)
            ->callTableAction('cancel_request', $request);

        $request->refresh();
        $this->assertEquals(PudRequest::STATUS_CANCELLED, $request->status);
        $this->assertNotNull($request->cancelled_at);
        $this->assertEquals('Cancelled', $request->sla_progress);
    }

    public function test_can_view_pud_monitoring_table_and_open_modal(): void
    {
        $request = PudRequest::create([
            'request_number' => 'PUD-2026-000101',
            'request_date' => now(),
            'user_name' => 'AGUS',
            'division' => 'GA',
            'plant' => 'SC1',
            'part_name' => 'EXHAUST FAN MOTOR',
            'brand' => 'PANASONIC',
            'type' => 'FV-40',
            'qty' => 2,
            'unit' => 'Unit',
            'purpose' => 'VENTILATION FACTORY',
            'spec' => 'Blade 40cm, 220V 50Hz',
            'status' => PudRequest::STATUS_REQUEST_QUOTATION,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListPudMonitorings::class)
            ->assertCanSeeTableRecords([$request])
            ->assertSee('PUD-2026-000101')
            ->assertSee('EXHAUST FAN MOTOR');
    }

    public function test_pud_monitoring_can_update_suppliers_and_close_with_item_code(): void
    {
        $request = PudRequest::create([
            'request_number' => 'PUD-2026-000102',
            'request_date' => now(),
            'user_name' => 'RUDI',
            'division' => 'IT',
            'plant' => 'SC2',
            'part_name' => 'BARCODE SCANNER WIRELESS',
            'brand' => 'ZEBRA',
            'type' => 'DS2278',
            'qty' => 3,
            'unit' => 'Unit',
            'purpose' => 'WAREHOUSE DISPATCH SCANNING',
            'spec' => '2D Bluetooth Scanner with Cradle',
            'status' => PudRequest::STATUS_DELIVERED,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EditPudMonitoring::class, ['record' => $request->id])
            ->fillForm([
                'supplier_1_name' => 'PT ZEBRA INDONESIA',
                'supplier_1_price_1' => 3500000,
                'supplier_1_price_2' => 3200000,
                'selected_supplier' => 'PT ZEBRA INDONESIA',
                'status' => PudRequest::STATUS_CLOSED,
                'item_code' => 'IT-SCN-ZBR-02278',
                'feedback' => 'Barang selesai diproses dan siap digunakan.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $request->refresh();
        $this->assertEquals(PudRequest::STATUS_CLOSED, $request->status);
        $this->assertEquals('PT ZEBRA INDONESIA', $request->selected_supplier);
        $this->assertEquals('IT-SCN-ZBR-02278', $request->item_code);
        $this->assertNotNull($request->closed_at);
        $this->assertEquals('Closed', $request->sla_progress);
    }

    public function test_closing_pud_request_requires_item_code(): void
    {
        $request = PudRequest::create([
            'request_number' => 'PUD-2026-000103',
            'request_date' => now(),
            'user_name' => 'TEST USER',
            'division' => 'PE',
            'plant' => 'KIIC',
            'part_name' => 'LIMIT SWITCH HEAVY DUTY',
            'brand' => 'OMRON',
            'type' => 'WLCA2-2',
            'qty' => 5,
            'unit' => 'Pcs',
            'purpose' => 'SAFETY INTERLOCK',
            'spec' => 'Roller Lever Type IP67',
            'status' => PudRequest::STATUS_REVIEW_NEGOTIATION,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EditPudMonitoring::class, ['record' => $request->id])
            ->fillForm([
                'status' => PudRequest::STATUS_CLOSED,
                'item_code' => '', // Empty item_code should trigger validation error
            ])
            ->call('save')
            ->assertHasFormErrors(['item_code' => 'required']);
    }

    public function test_sla_progress_calculation_logic(): void
    {
        // 1. Request dibuat hari ini (Hari 1) -> On Track
        $req1 = PudRequest::create([
            'request_number' => 'PUD-2026-000104',
            'request_date' => now(),
            'user_name' => 'USER 1',
            'division' => 'GA',
            'plant' => 'SUNTER',
            'part_name' => 'ITEM 1',
            'purpose' => 'TEST',
            'spec' => 'SPEC 1',
            'created_at' => now(),
        ]);
        $this->assertEquals('On Track', $req1->sla_progress);

        // 2. Request status Closed -> Closed
        $req2 = PudRequest::create([
            'request_number' => 'PUD-2026-000105',
            'request_date' => now()->subDays(10),
            'user_name' => 'USER 2',
            'division' => 'GA',
            'plant' => 'SUNTER',
            'part_name' => 'ITEM 2',
            'purpose' => 'TEST',
            'spec' => 'SPEC 2',
            'status' => PudRequest::STATUS_CLOSED,
            'created_at' => now()->subDays(10),
        ]);
        $this->assertEquals('Closed', $req2->sla_progress);

        // 3. Request status Cancelled -> Cancelled
        $req3 = PudRequest::create([
            'request_number' => 'PUD-2026-000106',
            'request_date' => now()->subDays(10),
            'user_name' => 'USER 3',
            'division' => 'GA',
            'plant' => 'SUNTER',
            'part_name' => 'ITEM 3',
            'purpose' => 'TEST',
            'spec' => 'SPEC 3',
            'status' => PudRequest::STATUS_CANCELLED,
            'created_at' => now()->subDays(10),
        ]);
        $this->assertEquals('Cancelled', $req3->sla_progress);
    }

    public function test_dashboard_widgets_render_successfully(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PudRequestStatsWidget::class)
            ->assertSuccessful()
            ->assertSee('Total Request')
            ->assertSee('Delivered to PUD')
            ->assertSee('Request Quotation')
            ->assertSee('Review & Negotiation')
            ->assertSee('Item Code Created')
            ->assertSee('Closed');

        Livewire::actingAs($this->admin)
            ->test(PudProgressSlaWidget::class)
            ->assertSuccessful()
            ->assertSee('On Track')
            ->assertSee('Attention')
            ->assertSee('Due Soon')
            ->assertSee('Overdue');

        Livewire::actingAs($this->admin)
            ->test(PudRequestTrendChart::class)
            ->assertSuccessful()
            ->assertSee('Request Trend All Plant');
    }

    public function test_kalkulator_and_purchasing_navigation_group_only_contains_active_features(): void
    {
        $this->actingAs($this->admin);

        $panel = filament()->getPanel('admin');
        filament()->setCurrentPanel($panel);

        $navigation = $panel->getNavigation();

        $kalkulatorGroup = null;
        foreach ($navigation as $group) {
            if ($group->getLabel() === 'Kalkulator & Purchasing') {
                $kalkulatorGroup = $group;
                break;
            }
        }

        $this->assertNotNull($kalkulatorGroup, 'Kalkulator & Purchasing navigation group should exist');

        $labels = $kalkulatorGroup->getItems()->map(fn ($item) => $item->getLabel())->toArray();

        // Memuat: Request User, PUD Monitoring, Purchase Order (PO)
        $this->assertContains('Request User', $labels);
        $this->assertContains('PUD Monitoring', $labels);
        $this->assertContains('Purchase Order (PO)', $labels);

        // Hapus: Komparasi Harga Supplier, Estimasi Biaya (Quotation), Tren Harga Part, Penerimaan Barang (GRN)
        $this->assertNotContains('Komparasi Harga Supplier', $labels);
        $this->assertNotContains('Estimasi Biaya (Quotation)', $labels);
        $this->assertNotContains('Tren Harga Part (Price Trend)', $labels);
        $this->assertNotContains('Penerimaan Barang (GRN)', $labels);
    }

    public function test_can_view_user_request_detail_page_without_error(): void
    {
        $request = PudRequest::create([
            'request_number' => 'PUD-2026-000109',
            'request_date' => now(),
            'user_name' => 'VIEW TESTER',
            'division' => 'QUALITY',
            'plant' => 'SUNTER',
            'part_name' => 'CALIPER DIGITAL MITUTOYO',
            'brand' => 'MITUTOYO',
            'type' => '500-196-30',
            'qty' => 1,
            'unit' => 'Pcs',
            'purpose' => 'QC INSPECTION',
            'spec' => 'Range 0-150mm, Resolution 0.01mm',
            'status' => PudRequest::STATUS_DELIVERED,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ViewUserRequest::class, ['record' => $request->id])
            ->assertSuccessful()
            ->assertSee('PUD-2026-000109')
            ->assertSee('CALIPER DIGITAL MITUTOYO')
            ->assertSee('QC INSPECTION');
    }

    public function test_can_view_pud_monitoring_detail_page_without_error(): void
    {
        $request = PudRequest::create([
            'request_number' => 'PUD-2026-000110',
            'request_date' => now(),
            'user_name' => 'VIEW PUD TESTER',
            'division' => 'MTC',
            'plant' => 'KIIC',
            'part_name' => 'HYDRAULIC VALVE YUKEN',
            'brand' => 'YUKEN',
            'type' => 'DSG-01-3C2',
            'qty' => 2,
            'unit' => 'Pcs',
            'purpose' => 'REPAIR PRESS 500T',
            'spec' => 'Solenoid Directional Valve 24VDC',
            'status' => PudRequest::STATUS_REQUEST_QUOTATION,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ViewPudMonitoring::class, ['record' => $request->id])
            ->assertSuccessful()
            ->assertSee('PUD-2026-000110')
            ->assertSee('HYDRAULIC VALVE YUKEN')
            ->assertSee('REPAIR PRESS 500T');
    }

    public function test_pud_monitoring_does_not_have_create_request_action(): void
    {
        $this->assertFalse(PudMonitoringResource::canCreate());

        Livewire::actingAs($this->admin)
            ->test(ListPudMonitorings::class)
            ->assertActionDoesNotExist('create_request')
            ->assertActionDoesNotExist('create');
    }
}
