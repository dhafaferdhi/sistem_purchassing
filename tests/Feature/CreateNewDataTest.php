<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\Part;
use App\Models\Process;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNewDataTest extends TestCase
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

    public function test_can_create_material_without_explicit_category(): void
    {
        // Simulasi input form Filament Material yang sebelumnya error karena field 'category'
        $material = Material::create([
            'spec' => 'FCC-BETON',
            'main_category' => 'Steel',
            'sub_category' => 'Frame',
            'basic_rate' => 150000.0,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->assertNotNull($material->id);
        $this->assertEquals('FCC-BETON', $material->spec);
        // Category harus otomatis terisi dari sub_category atau main_category
        $this->assertEquals('Frame', $material->category);
    }

    public function test_can_create_part_without_explicit_part_number_and_part_name(): void
    {
        // Simulasi input form Filament Part menggunakan item_code dan description
        $part = Part::create([
            'item_code' => 'STP-999',
            'description' => 'Bracket Support Bumper',
            'item_category' => 'Stamping',
            'unit' => 'pcs',
        ]);

        $this->assertNotNull($part->id);
        $this->assertEquals('STP-999', $part->part_number);
        $this->assertEquals('Bracket Support Bumper', $part->part_name);
        $this->assertEquals('Steel', $part->main_category);
    }

    public function test_can_create_process_without_explicit_category(): void
    {
        $process = Process::create([
            'name' => 'Blanking Tandem 500T',
            'stage_type' => 'Blanking',
            'default_cost' => 1250.0,
        ]);

        $this->assertNotNull($process->id);
        $this->assertEquals('Both', $process->category);
    }

    public function test_can_create_purchase_order_with_automatic_user_and_po_number(): void
    {
        $supplier = Supplier::create([
            'code' => 'VND-NEW',
            'name' => 'PT Baja Maju',
        ]);

        $currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'exchange_rate_to_idr' => 1.0,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now(),
            'currency_id' => $currency->id,
        ]);

        $this->assertNotNull($po->id);
        $this->assertNotNull($po->po_number);
        $this->assertStringStartsWith('PO-', $po->po_number);
        $this->assertNotNull($po->user_id);
    }

    public function test_can_create_goods_receipt_with_automatic_number(): void
    {
        $supplier = Supplier::create(['code' => 'VND-GRN', 'name' => 'Vendor Test']);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'exchange_rate_to_idr' => 1.0]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now(),
            'currency_id' => $currency->id,
            'status' => 'Approved',
        ]);

        $grn = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'delivery_order_number' => 'SJ-TEST-001',
            'received_date' => now(),
        ]);

        $this->assertNotNull($grn->id);
        $this->assertNotNull($grn->grn_number);
        $this->assertStringStartsWith('GRN-', $grn->grn_number);
        $this->assertNotNull($grn->received_by);
    }

    public function test_can_create_vendor_item_mapping(): void
    {
        $supplier = Supplier::create(['code' => 'VND-MAP', 'name' => 'Vendor Mapping']);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'exchange_rate_to_idr' => 1.0]);
        $part = Part::create(['item_code' => 'PRT-MAP-01', 'description' => 'Test Part']);

        $vendorItem = VendorItem::create([
            'supplier_id' => $supplier->id,
            'part_id' => $part->id,
            'vendor_item_code' => 'VND-PRT-99',
            'unit_price' => 5000.0,
            'currency_id' => $currency->id,
        ]);

        $this->assertNotNull($vendorItem->id);
        $this->assertEquals(100, $vendorItem->moq);
        $this->assertEquals(14, $vendorItem->lead_time_days);
    }
}
