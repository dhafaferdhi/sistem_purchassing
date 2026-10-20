<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Tooling;
use App\Models\User;
use Illuminate\Database\Seeder;

class ToolingAndPurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();
        $approver = User::where('role', 'approver')->first() ?? $admin;

        $idr = Currency::where('code', 'IDR')->first();
        $supplier = Supplier::where('code', 'VND-105')->first() ?? Supplier::first();
        $part = Part::where('item_code', 'STP-001')->orWhere('part_number', 'BRKT-01')->first() ?? Part::first();

        // 1. Tooling / Dies & Mold Assets
        if ($supplier && $part) {
            Tooling::firstOrCreate(
                ['tooling_code' => 'DIES-STP-001'],
                [
                    'name' => 'Dies Progressive Bracket Bumper Front',
                    'type' => 'Dies',
                    'ownership' => 'Customer Owned',
                    'supplier_id' => $supplier->id,
                    'part_id' => $part->id,
                    'tonnage' => '500 Ton',
                    'cavity_count' => 2,
                    'initial_cost' => 175000000,
                    'lifetime_strokes' => 300000,
                    'current_strokes' => 125000,
                    'status' => 'Ready',
                    'location_notes' => 'Plant 1 Line Press 4 - Rak A-03',
                    'notes' => 'Dies 2-Cavity untuk Stamping Bracket Baja. Kondisi siap produksi.',
                ]
            );

            Tooling::firstOrCreate(
                ['tooling_code' => 'MOLD-INJ-001'],
                [
                    'name' => 'Mold Injection Cover Cushion Seat',
                    'type' => 'Mold',
                    'ownership' => 'Vendor Owned',
                    'supplier_id' => $supplier->id,
                    'part_id' => $part->id,
                    'tonnage' => '800 Ton',
                    'cavity_count' => 1,
                    'initial_cost' => 210000000,
                    'lifetime_strokes' => 500000,
                    'current_strokes' => 460000,
                    'status' => 'Maintenance',
                    'location_notes' => 'Vendor Molding Area - Bay 2',
                    'notes' => 'Sudah 92% lifetime stroke, perlu pengasahan cavity dan poles ulang.',
                ]
            );

            Tooling::firstOrCreate(
                ['tooling_code' => 'JIG-WLD-003'],
                [
                    'name' => 'Jig Inspection & Welding Sub-Assembly',
                    'type' => 'Jig',
                    'ownership' => 'Customer Owned',
                    'supplier_id' => $supplier->id,
                    'part_id' => $part->id,
                    'tonnage' => 'N/A',
                    'cavity_count' => 1,
                    'initial_cost' => 45000000,
                    'lifetime_strokes' => 1000000,
                    'current_strokes' => 85000,
                    'status' => 'Ready',
                    'location_notes' => 'Quality Inspection Room - Bench 01',
                    'notes' => 'Jig checking dimensi dan akurasi lubang baut.',
                ]
            );
        }

        // 2. Purchase Order & Line Items
        if ($admin && $supplier && $idr) {
            $po = PurchaseOrder::firstOrCreate(
                ['po_number' => 'PO-'.date('Ym').'-0001'],
                [
                    'supplier_id' => $supplier->id,
                    'user_id' => $admin->id,
                    'order_date' => now()->subDays(2),
                    'delivery_date' => now()->addDays(12),
                    'payment_terms' => 'Net 30 Days',
                    'currency_id' => $idr->id,
                    'exchange_rate' => 1.0,
                    'subtotal_idr' => 32500000.0,
                    'tax_percentage' => 11.0,
                    'tax_amount_idr' => 3575000.0,
                    'total_amount_idr' => 36075000.0,
                    'status' => 'Approved',
                    'approved_by' => $approver->id,
                    'notes' => 'Pengiriman harap dilakukan sesuai jadwal. Wajib melampirkan Certificate of Analysis (CoA) & Surat Jalan resmi.',
                ]
            );

            // Line Item 1
            PurchaseOrderItem::firstOrCreate(
                [
                    'purchase_order_id' => $po->id,
                    'item_code' => $part ? ($part->item_code ?? $part->part_number) : 'STP-001',
                ],
                [
                    'part_id' => $part?->id,
                    'description' => $part ? ($part->description ?? $part->part_name) : 'Bracket Bumper Depan (Baja Stamping)',
                    'vendor_item_code' => 'MBS-BKT-889',
                    'quantity' => 1000,
                    'unit' => 'pcs',
                    'unit_price_idr' => 25000.0,
                    'total_price_idr' => 25000000.0,
                ]
            );

            // Line Item 2
            PurchaseOrderItem::firstOrCreate(
                [
                    'purchase_order_id' => $po->id,
                    'item_code' => 'FST-M6-25',
                ],
                [
                    'part_id' => null,
                    'description' => 'Hex Bolt Flange M6 x 25 Grade 8.8 Black Oxide',
                    'vendor_item_code' => 'MBS-FST-0625',
                    'quantity' => 5000,
                    'unit' => 'pcs',
                    'unit_price_idr' => 1500.0,
                    'total_price_idr' => 7500000.0,
                ]
            );
        }
    }
}
