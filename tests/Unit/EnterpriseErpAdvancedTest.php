<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Part;
use App\Models\PartPriceHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseErpAdvancedTest extends TestCase
{
    use RefreshDatabase;

    public function test_moq_and_lead_time_mapping_for_po(): void
    {
        $supplier = Supplier::create([
            'code' => 'VND-MBS',
            'name' => 'PT Maju Baja Stamping',
        ]);

        $currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'exchange_rate_to_idr' => 1.0,
        ]);

        $part = Part::create([
            'part_number' => 'STP-BRKT-01',
            'part_name' => 'Bracket Bumper Depan',
            'type' => 'Component',
        ]);

        // Mapping supplier dengan MOQ 500 pcs dan lead time 14 hari
        $vendorItem = VendorItem::create([
            'supplier_id' => $supplier->id,
            'part_id' => $part->id,
            'vendor_item_code' => 'MBS-BKT-889',
            'unit_price' => 25000.0,
            'currency_id' => $currency->id,
            'moq' => 500,
            'lead_time_days' => 14,
        ]);

        $this->assertEquals(500, $vendorItem->moq);
        $this->assertEquals(14, $vendorItem->lead_time_days);

        // Simulasi item PO yang membaca MOQ & lead time
        $user = User::factory()->create();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'order_date' => now(),
            'currency_id' => $currency->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'part_id' => $part->id,
            'item_code' => $part->part_number,
            'description' => $part->part_name,
            'vendor_item_code' => $vendorItem->vendor_item_code,
            'moq' => $vendorItem->moq,
            'lead_time_days' => $vendorItem->lead_time_days,
            'quantity' => 1000,
            'unit_price_idr' => 25000.0,
        ]);

        $this->assertEquals(500, $poItem->moq);
        $this->assertEquals(14, $poItem->lead_time_days);
        $this->assertTrue($poItem->quantity >= $poItem->moq);
    }

    public function test_part_price_history_and_fluctuation_calculation(): void
    {
        $part = Part::create([
            'part_number' => 'BOLT-TEST-M6',
            'part_name' => 'Flange Bolt M6',
            'type' => 'Component',
        ]);

        $supplier = Supplier::create([
            'code' => 'VND-FAST',
            'name' => 'PT Fastenerindo',
        ]);

        // Harga Awal Rp 2.000
        $p1 = PartPriceHistory::logPrice(
            partId: $part->id,
            supplierId: $supplier->id,
            unitPriceIdr: 2000.0,
            sourceType: 'Quotation',
            referenceNo: 'QUO-001',
            notes: 'Harga dasar penawaran awal'
        );

        $this->assertNull($p1->previous_price_idr);
        $this->assertNull($p1->price_change_percentage);

        // Kenaikan Harga kedua menjadi Rp 2.200 (+10.00%)
        $p2 = PartPriceHistory::logPrice(
            partId: $part->id,
            supplierId: $supplier->id,
            unitPriceIdr: 2200.0,
            sourceType: 'Purchase Order',
            referenceNo: 'PO-001',
            notes: 'Kenaikan bahan baku kawat baja'
        );

        $this->assertEquals(2000.0, (float) $p2->previous_price_idr);
        $this->assertEquals(10.0, (float) $p2->price_change_percentage);

        // Penurunan Harga ketiga menjadi Rp 2.090 (-5.00%)
        $p3 = PartPriceHistory::logPrice(
            partId: $part->id,
            supplierId: $supplier->id,
            unitPriceIdr: 2090.0,
            sourceType: 'Purchase Order',
            referenceNo: 'PO-002',
            notes: 'Diskon negosiasi volume'
        );

        $this->assertEquals(2200.0, (float) $p3->previous_price_idr);
        $this->assertEquals(-5.0, (float) $p3->price_change_percentage);
    }

    public function test_partial_goods_receipt_and_po_delivery_status(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['code' => 'VND-LOG', 'name' => 'Logam Presisi']);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'exchange_rate_to_idr' => 1.0]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'order_date' => now(),
            'currency_id' => $currency->id,
            'status' => 'Approved',
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_code' => 'BRKT-01',
            'description' => 'Bracket Steel',
            'quantity' => 1000,
            'unit_price_idr' => 10000.0,
        ]);

        // Kondisi awal: Belum ada pengiriman (Unfulfilled)
        $this->assertEquals('Unfulfilled', $po->delivery_status);
        $this->assertEquals(1000, $item->remaining_quantity);

        // Pengiriman Parsial 1: Datang 400 pcs (Sisa 600 pcs)
        $grn1 = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'delivery_order_number' => 'SJ-001',
            'received_date' => now(),
            'received_by' => $user->id,
            'status' => 'Verified',
        ]);

        GoodsReceiptItem::create([
            'goods_receipt_id' => $grn1->id,
            'purchase_order_item_id' => $item->id,
            'item_code' => $item->item_code,
            'description' => $item->description,
            'quantity_ordered' => 1000,
            'quantity_previously_received' => 0,
            'quantity_received' => 400,
            'quantity_remaining' => 600,
        ]);

        $item->refresh();
        $po->refresh();

        $this->assertEquals(400.0, (float) $item->quantity_received);
        $this->assertEquals(600.0, (float) $item->remaining_quantity);
        $this->assertEquals('Partially Received', $po->delivery_status);
        $this->assertEquals(40.0, $po->delivery_percentage);

        // Pengiriman Parsial 2: Datang sisa 600 pcs (Lengkap / Fully Received)
        $grn2 = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'delivery_order_number' => 'SJ-002',
            'received_date' => now(),
            'received_by' => $user->id,
            'status' => 'Verified',
        ]);

        GoodsReceiptItem::create([
            'goods_receipt_id' => $grn2->id,
            'purchase_order_item_id' => $item->id,
            'item_code' => $item->item_code,
            'description' => $item->description,
            'quantity_ordered' => 1000,
            'quantity_previously_received' => 400,
            'quantity_received' => 600,
            'quantity_remaining' => 0,
        ]);

        $item->refresh();
        $po->refresh();

        $this->assertEquals(1000.0, (float) $item->quantity_received);
        $this->assertEquals(0.0, (float) $item->remaining_quantity);
        $this->assertEquals('Fully Received', $po->delivery_status);
        $this->assertEquals(100.0, $po->delivery_percentage);
    }
}
