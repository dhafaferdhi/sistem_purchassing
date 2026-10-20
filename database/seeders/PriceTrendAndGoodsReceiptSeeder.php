<?php

namespace Database\Seeders;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Part;
use App\Models\PartPriceHistory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class PriceTrendAndGoodsReceiptSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();
        $supplier = Supplier::where('code', 'VND-105')->first() ?? Supplier::first();
        $part = Part::where('item_code', 'STP-001')->orWhere('part_number', 'BRKT-01')->first() ?? Part::first();

        // 1. Seed Multi-Month Price Trend Log
        if ($part && $supplier) {
            // Bulan 1: Awal tahun - Harga Dasar Rp 23.500
            PartPriceHistory::updateOrCreate(
                ['reference_number' => 'QUO-202601-0010', 'part_id' => $part->id],
                [
                    'supplier_id' => $supplier->id,
                    'price_date' => now()->subMonths(5),
                    'source_type' => 'Quotation',
                    'unit_price_idr' => 23500.0,
                    'previous_price_idr' => null,
                    'price_change_percentage' => null,
                    'notes' => 'Penawaran awal tahun fiskal (Volume 500 pcs)',
                    'created_by' => $admin?->id,
                ]
            );

            // Bulan 2: Kenaikan bahan baku baja plat (+4.26%)
            PartPriceHistory::updateOrCreate(
                ['reference_number' => 'PO-202604-0018', 'part_id' => $part->id],
                [
                    'supplier_id' => $supplier->id,
                    'price_date' => now()->subMonths(3),
                    'source_type' => 'Purchase Order',
                    'unit_price_idr' => 24500.0,
                    'previous_price_idr' => 23500.0,
                    'price_change_percentage' => 4.26,
                    'notes' => 'Kenaikan basic rate bahan baku SPHC di pabrikan baja (+4.26%)',
                    'created_by' => $admin?->id,
                ]
            );

            // Bulan 3: Kenaikan kurs USD (+6.12%)
            PartPriceHistory::updateOrCreate(
                ['reference_number' => 'PO-202607-0042', 'part_id' => $part->id],
                [
                    'supplier_id' => $supplier->id,
                    'price_date' => now()->subMonths(1),
                    'source_type' => 'Purchase Order',
                    'unit_price_idr' => 26000.0,
                    'previous_price_idr' => 24500.0,
                    'price_change_percentage' => 6.12,
                    'notes' => 'Penyesuaian kurs USD dari Rp 15.500 ke Rp 16.200 per dollar',
                    'created_by' => $admin?->id,
                ]
            );

            // Bulan 4: Negosiasi volume besar batch 5.000 pcs (-3.85%)
            PartPriceHistory::updateOrCreate(
                ['reference_number' => 'PO-202610-0088', 'part_id' => $part->id],
                [
                    'supplier_id' => $supplier->id,
                    'price_date' => now(),
                    'source_type' => 'Purchase Order',
                    'unit_price_idr' => 25000.0,
                    'previous_price_idr' => 26000.0,
                    'price_change_percentage' => -3.85,
                    'notes' => 'Efisiensi dan diskon volume negosiasi batch 1.000 pcs (-3.85%)',
                    'created_by' => $admin?->id,
                ]
            );
        }

        // 2. Seed Partial Goods Receipt
        $po = PurchaseOrder::where('status', 'Approved')->first();
        if ($po && $admin) {
            $poItem = $po->items()->first();
            if ($poItem) {
                // Pengiriman Pertama: 600 pcs dari 1000 pcs (Sisa 400 pcs)
                $grn1 = GoodsReceipt::updateOrCreate(
                    ['grn_number' => 'GRN-202610-0001'],
                    [
                        'purchase_order_id' => $po->id,
                        'supplier_id' => $po->supplier_id,
                        'delivery_order_number' => 'SJ-MBS-9912',
                        'received_date' => now()->subDays(1),
                        'received_by' => $admin->id,
                        'driver_name' => 'Wahyudi (Ekspedisi Truk Vendor)',
                        'vehicle_number' => 'B 9182 KXA',
                        'status' => 'Verified',
                        'notes' => 'Pengiriman tahap 1: 600 pcs. Kemasan palet kayu utuh, hasil sampling dimensi OK.',
                    ]
                );

                GoodsReceiptItem::updateOrCreate(
                    [
                        'goods_receipt_id' => $grn1->id,
                        'purchase_order_item_id' => $poItem->id,
                    ],
                    [
                        'part_id' => $poItem->part_id,
                        'item_code' => $poItem->item_code,
                        'description' => $poItem->description,
                        'quantity_ordered' => $poItem->quantity,
                        'quantity_previously_received' => 0,
                        'quantity_received' => 600,
                        'quantity_rejected' => 0,
                        'quantity_remaining' => max(0, $poItem->quantity - 600),
                        'unit' => $poItem->unit,
                        'notes' => 'Sampling 50 pcs dicek caliper QC pas.',
                    ]
                );

                // Sinkronkan status pengiriman PO
                $po->recalculateDeliveryStatus();
            }
        }
    }
}
