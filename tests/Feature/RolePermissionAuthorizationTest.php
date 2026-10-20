<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\GoodsReceipt;
use App\Models\Machine;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\Tooling;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RolePermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $estimator;

    protected User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin_test@purchasing.test',
            'role' => 'admin',
        ]);

        $this->estimator = User::factory()->create([
            'email' => 'estimator_test@purchasing.test',
            'role' => 'estimator',
        ]);

        $this->approver = User::factory()->create([
            'email' => 'approver_test@purchasing.test',
            'role' => 'approver',
        ]);
    }

    public function test_user_role_detection_helpers(): void
    {
        $this->assertTrue($this->admin->isAdmin());
        $this->assertFalse($this->admin->isEstimator());
        $this->assertFalse($this->admin->isApprover());

        $this->assertFalse($this->estimator->isAdmin());
        $this->assertTrue($this->estimator->isEstimator());
        $this->assertFalse($this->estimator->isApprover());

        $this->assertFalse($this->approver->isAdmin());
        $this->assertFalse($this->approver->isEstimator());
        $this->assertTrue($this->approver->isApprover());
    }

    public function test_master_data_material_authorization_rules(): void
    {
        $material = Material::create([
            'spec' => 'SPCC-1.2mm',
            'category' => 'Steel',
            'basic_rate' => 15000.0,
            'unit' => 'kg',
        ]);

        // Everyone can view master materials
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $material));
        $this->assertTrue(Gate::forUser($this->estimator)->allows('view', $material));
        $this->assertTrue(Gate::forUser($this->approver)->allows('view', $material));

        // Only Admin can create, update, or delete master material
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', Material::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $material));
        $this->assertTrue(Gate::forUser($this->admin)->allows('delete', $material));

        // Estimator & Approver cannot create, update, or delete master material
        $this->assertFalse(Gate::forUser($this->estimator)->allows('create', Material::class));
        $this->assertFalse(Gate::forUser($this->estimator)->allows('update', $material));
        $this->assertFalse(Gate::forUser($this->estimator)->allows('delete', $material));

        $this->assertFalse(Gate::forUser($this->approver)->allows('create', Material::class));
        $this->assertFalse(Gate::forUser($this->approver)->allows('update', $material));
        $this->assertFalse(Gate::forUser($this->approver)->allows('delete', $material));
    }

    public function test_currency_and_machine_rates_restricted_to_admin(): void
    {
        $currency = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'exchange_rate_to_idr' => 16000.0,
        ]);

        $machine = Machine::create([
            'name' => 'Stamping 500 Ton',
            'category' => 'Stamping',
            'tonnage' => 500,
            'hourly_rate' => 450000.0,
        ]);

        // Admin can update currency & machine
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $currency));
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $machine));

        // Estimator cannot modify economic rates or machine rates
        $this->assertFalse(Gate::forUser($this->estimator)->allows('update', $currency));
        $this->assertFalse(Gate::forUser($this->estimator)->allows('update', $machine));

        // Approver cannot modify economic rates or machine rates
        $this->assertFalse(Gate::forUser($this->approver)->allows('update', $currency));
        $this->assertFalse(Gate::forUser($this->approver)->allows('update', $machine));
    }

    public function test_quotation_workflow_authorization(): void
    {
        $material = Material::create([
            'spec' => 'SPCC-0.8mm',
            'category' => 'Steel',
            'basic_rate' => 14000.0,
        ]);

        $quotation = Quotation::create([
            'user_id' => $this->estimator->id,
            'material_id' => $material->id,
            'category' => 'presspart',
            'weight' => 0.25,
            'snapshot_basic_rate' => 14000.0,
            'snapshot_exchange_rate' => 1.0,
            'snapshot_fluktuasi' => 0.0,
            'price_idr' => 3500.0,
            'total_cost' => 5000.0,
            'status' => 'draft',
        ]);

        // Estimator can create and update Draft quotation
        $this->assertTrue(Gate::forUser($this->estimator)->allows('create', Quotation::class));
        $this->assertTrue(Gate::forUser($this->estimator)->allows('update', $quotation));

        // Approver cannot create or edit quotation draft directly
        $this->assertFalse(Gate::forUser($this->approver)->allows('create', Quotation::class));
        $this->assertFalse(Gate::forUser($this->approver)->allows('update', $quotation));

        // Change status to approved
        $quotation->update(['status' => 'approved']);

        // Estimator can no longer update an approved quotation
        $this->assertFalse(Gate::forUser($this->estimator)->allows('update', $quotation));

        // Admin can still maintain approved quotation if emergency correction is required
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $quotation));
    }

    public function test_purchase_order_approval_matrix(): void
    {
        $supplier = Supplier::create([
            'code' => 'VND-AUTH',
            'name' => 'PT Baja Unggul',
        ]);

        $currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'exchange_rate_to_idr' => 1.0,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'user_id' => $this->estimator->id,
            'currency_id' => $currency->id,
            'po_number' => 'PO-2026-AUTH-01',
            'order_date' => now(),
            'status' => 'Draft',
        ]);

        // Estimator can create PO and edit draft PO
        $this->assertTrue(Gate::forUser($this->estimator)->allows('create', PurchaseOrder::class));
        $this->assertTrue(Gate::forUser($this->estimator)->allows('update', $po));

        // Estimator CANNOT approve PO
        $this->assertFalse(Gate::forUser($this->estimator)->allows('approve', $po));

        // Approver CAN approve PO
        $this->assertTrue(Gate::forUser($this->approver)->allows('approve', $po));

        // Admin CAN approve PO
        $this->assertTrue(Gate::forUser($this->admin)->allows('approve', $po));

        // Once PO is approved, estimator can no longer edit it
        $po->update(['status' => 'Approved']);
        $this->assertFalse(Gate::forUser($this->estimator)->allows('update', $po));
    }

    public function test_goods_receipt_authorization(): void
    {
        $supplier = Supplier::create([
            'code' => 'VND-RCV',
            'name' => 'PT Logistik Steel',
        ]);

        $currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'exchange_rate_to_idr' => 1.0,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'user_id' => $this->estimator->id,
            'currency_id' => $currency->id,
            'po_number' => 'PO-RCV-01',
            'order_date' => now(),
            'status' => 'Approved',
        ]);

        $grn = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'delivery_order_number' => 'SJ-AUTH-01',
            'received_date' => now(),
            'received_by' => $this->estimator->id,
            'status' => 'Draft',
        ]);

        // Estimator & Admin can create & update draft GRN
        $this->assertTrue(Gate::forUser($this->estimator)->allows('create', GoodsReceipt::class));
        $this->assertTrue(Gate::forUser($this->estimator)->allows('update', $grn));
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', GoodsReceipt::class));

        // Approver cannot create GRN
        $this->assertFalse(Gate::forUser($this->approver)->allows('create', GoodsReceipt::class));

        // Once confirmed, estimator cannot edit GRN
        $grn->update(['status' => 'Confirmed']);
        $this->assertFalse(Gate::forUser($this->estimator)->allows('update', $grn));
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $grn));
    }

    public function test_tooling_asset_maintenance_authorization(): void
    {
        $tooling = Tooling::create([
            'tooling_code' => 'MOLD-001',
            'name' => 'Dies Bumper Stamping',
            'type' => 'Dies',
            'initial_cost' => 150000000.0,
            'lifetime_strokes' => 500000,
        ]);

        // Only Admin can register new tooling assets or delete them
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', Tooling::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('delete', $tooling));
        $this->assertFalse(Gate::forUser($this->estimator)->allows('create', Tooling::class));
        $this->assertFalse(Gate::forUser($this->estimator)->allows('delete', $tooling));

        // Both Admin and Estimator can update tooling (e.g. record actual stroke counts)
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $tooling));
        $this->assertTrue(Gate::forUser($this->estimator)->allows('update', $tooling));
        $this->assertFalse(Gate::forUser($this->approver)->allows('update', $tooling));
    }
}
