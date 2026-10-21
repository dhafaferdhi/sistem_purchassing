<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Machine;
use App\Models\Material;
use App\Models\Part;
use App\Models\PartBom;
use App\Models\PartQuotation;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class EnterprisePurchasingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Currencies
        $idr = Currency::firstOrCreate(['code' => 'IDR'], [
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'exchange_rate_to_idr' => 1.0,
            'last_updated_at' => now(),
        ]);

        $usd = Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
            'last_updated_at' => now(),
        ]);

        $jpy = Currency::firstOrCreate(['code' => 'JPY'], [
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'exchange_rate_to_idr' => 105.0,
            'last_updated_at' => now(),
        ]);

        // 2. Suppliers
        $supA = Supplier::firstOrCreate(['code' => 'SUP-A'], [
            'name' => 'PT Steel Jaya Perkasa (Supplier A)',
            'contact_person' => 'Budi Santoso',
            'email' => 'sales@steeljaya.test',
            'phone' => '021-5551234',
            'category' => 'Steel',
            'is_active' => true,
        ]);

        $supB = Supplier::firstOrCreate(['code' => 'SUP-B'], [
            'name' => 'PT Cipta Logam Presisi (Supplier B)',
            'contact_person' => 'Dewi Lestari',
            'email' => 'marketing@ciptalogam.test',
            'phone' => '021-5559876',
            'category' => 'Steel',
            'is_active' => true,
        ]);

        $supC = Supplier::firstOrCreate(['code' => 'SUP-C'], [
            'name' => 'PT Polimer Indo Plastik (Supplier C)',
            'contact_person' => 'Hendro Wijaya',
            'email' => 'sales@polimerindo.test',
            'phone' => '021-5554321',
            'category' => 'Plastic',
            'is_active' => true,
        ]);

        $supD = Supplier::firstOrCreate(['code' => 'SUP-D'], [
            'name' => 'PT Foam & Fabric Tekstil (Supplier D)',
            'contact_person' => 'Siti Aminah',
            'email' => 'order@foamfabric.test',
            'phone' => '021-5557788',
            'category' => 'Chemical & Fabric',
            'is_active' => true,
        ]);

        // 3. Machines & Kapasitas (Machine Rate)
        $mStamping500 = Machine::firstOrCreate(['name' => 'Mesin Stamping 500 Ton'], [
            'category' => 'Steel',
            'tonnage' => '500 Ton',
            'hourly_rate' => 350000.0, // Rp 350.000 / jam
            'operator_hourly_rate' => 75000.0, // Rp 75.000 / jam
            'default_capacity_per_hour' => 120, // 120 pcs/jam
            'is_active' => true,
        ]);

        $mStamping800 = Machine::firstOrCreate(['name' => 'Mesin Stamping 800 Ton'], [
            'category' => 'Steel',
            'tonnage' => '800 Ton',
            'hourly_rate' => 550000.0,
            'operator_hourly_rate' => 85000.0,
            'default_capacity_per_hour' => 80,
            'is_active' => true,
        ]);

        $mInjection800 = Machine::firstOrCreate(['name' => 'Mesin Injection 800 Ton'], [
            'category' => 'Non-Steel',
            'tonnage' => '800 Ton',
            'hourly_rate' => 450000.0,
            'operator_hourly_rate' => 70000.0,
            'default_capacity_per_hour' => 60,
            'is_active' => true,
        ]);

        $mFoaming = Machine::firstOrCreate(['name' => 'Line Foaming Urethane'], [
            'category' => 'Non-Steel',
            'tonnage' => 'High Pressure',
            'hourly_rate' => 280000.0,
            'operator_hourly_rate' => 65000.0,
            'default_capacity_per_hour' => 40,
            'is_active' => true,
        ]);

        $mSewing = Machine::firstOrCreate(['name' => 'Line Sewing & Jahit'], [
            'category' => 'Non-Steel',
            'tonnage' => 'Standard',
            'hourly_rate' => 120000.0,
            'operator_hourly_rate' => 50000.0,
            'default_capacity_per_hour' => 30,
            'is_active' => true,
        ]);

        // 4. Materials (Steel & Non-Steel)
        $matSPHC = Material::updateOrCreate(['spec' => 'SPHC-Steel'], [
            'category' => 'Presspart',
            'main_category' => 'Steel',
            'sub_category' => 'Bracket',
            'supplier_id' => $supA->id,
            'currency_id' => $usd->id,
            'basic_rate' => 1.85,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $matSWMB = Material::updateOrCreate(['spec' => 'SWMB-BoltSteel'], [
            'category' => 'Fastener',
            'main_category' => 'Steel',
            'sub_category' => 'Bolt',
            'supplier_id' => $supA->id,
            'currency_id' => $usd->id,
            'basic_rate' => 2.135,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $matFramePipe = Material::updateOrCreate(['spec' => 'STKM11A-Pipe'], [
            'category' => 'Presspart',
            'main_category' => 'Steel',
            'sub_category' => 'Frame',
            'supplier_id' => $supB->id,
            'currency_id' => $jpy->id,
            'basic_rate' => 280.0, // ¥ 280/kg
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $matResinPP = Material::updateOrCreate(['spec' => 'PP-Resin-GradeX'], [
            'category' => 'Presspart',
            'main_category' => 'Non-Steel',
            'sub_category' => 'Plastik',
            'supplier_id' => $supC->id,
            'currency_id' => $usd->id,
            'basic_rate' => 2.50, // $ 2.50/kg
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $matPUFoam = Material::updateOrCreate(['spec' => 'PU-Polyurethane-Foam'], [
            'category' => 'Presspart',
            'main_category' => 'Non-Steel',
            'sub_category' => 'Busa (Urethane)',
            'supplier_id' => $supD->id,
            'currency_id' => $idr->id,
            'basic_rate' => 65000.0, // Rp 65.000/kg
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $matFabric = Material::updateOrCreate(['spec' => 'Fabric-Polyester-Seat'], [
            'category' => 'Presspart',
            'main_category' => 'Non-Steel',
            'sub_category' => 'Cover (Kain)',
            'supplier_id' => $supD->id,
            'currency_id' => $idr->id,
            'basic_rate' => 45000.0, // Rp 45.000/meter
            'unit' => 'meter',
            'is_active' => true,
        ]);

        // 5. Parts (Struktur Hirarki BOM: Kursi Depan)
        // Level 0: Top Assembly
        $partSeat = Part::updateOrCreate(['part_number' => 'SEAT-FRONT-01'], [
            'part_name' => 'Kursi Depan (Front Seat Complete Assembly)',
            'main_category' => 'Assembly',
            'sub_category' => 'Seat Assembly',
            'type' => 'Assembly',
            'material_id' => null,
            'weight' => 14.5,
            'unit' => 'set',
            'is_active' => true,
        ]);

        // Level 1: Frame Assembly
        $partFrameAsm = Part::updateOrCreate(['part_number' => 'FR-ASM-01'], [
            'part_name' => 'Frame Assembly Kursi',
            'main_category' => 'Steel',
            'sub_category' => 'Frame',
            'type' => 'Assembly',
            'material_id' => null,
            'weight' => 8.2,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        // Level 1: Cushion & Cover Assembly
        $partCushionAsm = Part::updateOrCreate(['part_number' => 'CUSH-ASM-01'], [
            'part_name' => 'Busa & Cover Assembly',
            'main_category' => 'Non-Steel',
            'sub_category' => 'Busa',
            'type' => 'Assembly',
            'material_id' => null,
            'weight' => 6.3,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        // Level 2 (Bawah Frame): Bracket & Bolt
        $partBracket = Part::updateOrCreate(['part_number' => 'BRKT-FRONT-01'], [
            'part_name' => 'Bracket Depan Kiri (Steel)',
            'main_category' => 'Steel',
            'sub_category' => 'Bracket',
            'type' => 'Component',
            'material_id' => $matSPHC->id,
            'weight' => 0.45,
            'dimensions' => '150x80x2 mm',
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $partBolt = Part::updateOrCreate(['part_number' => 'BOLT-M6-FLANGE'], [
            'part_name' => 'Bolt M6 Flange Head (Besi)',
            'main_category' => 'Steel',
            'sub_category' => 'Bolt',
            'type' => 'Component',
            'material_id' => $matSWMB->id,
            'weight' => 0.05,
            'dimensions' => 'M6 x 25 mm',
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        // Level 2 (Bawah Cushion): Busa Urethane & Kain Cover
        $partFoam = Part::updateOrCreate(['part_number' => 'FOAM-PAD-01'], [
            'part_name' => 'Busa Urethane Bantalan Kursi',
            'main_category' => 'Non-Steel',
            'sub_category' => 'Busa (Urethane)',
            'type' => 'Component',
            'material_id' => $matPUFoam->id,
            'weight' => 4.2,
            'dimensions' => '500x500x120 mm',
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $partCover = Part::updateOrCreate(['part_number' => 'FAB-COV-01'], [
            'part_name' => 'Cover Kain Jok Polyester',
            'main_category' => 'Non-Steel',
            'sub_category' => 'Cover (Kain)',
            'type' => 'Component',
            'material_id' => $matFabric->id,
            'weight' => 2.1,
            'dimensions' => '1.5 x 2.0 meter',
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        // 6. Hubungkan Struktur BOM (PartBom)
        // Level 0 -> Level 1
        PartBom::updateOrCreate(
            ['parent_part_id' => $partSeat->id, 'component_part_id' => $partFrameAsm->id],
            ['quantity' => 1, 'level' => 1, 'notes' => 'Rangka utama']
        );
        PartBom::updateOrCreate(
            ['parent_part_id' => $partSeat->id, 'component_part_id' => $partCushionAsm->id],
            ['quantity' => 1, 'level' => 1, 'notes' => 'Bantalan jok']
        );

        // Level 1 Frame -> Level 2 Bracket & Bolt
        PartBom::updateOrCreate(
            ['parent_part_id' => $partFrameAsm->id, 'component_part_id' => $partBracket->id],
            ['quantity' => 2, 'level' => 2, 'notes' => 'Kiri & Kanan']
        );
        PartBom::updateOrCreate(
            ['parent_part_id' => $partFrameAsm->id, 'component_part_id' => $partBolt->id],
            ['quantity' => 8, 'level' => 2, 'notes' => 'Baut pengikat frame']
        );

        // Level 1 Cushion -> Level 2 Foam & Cover
        PartBom::updateOrCreate(
            ['parent_part_id' => $partCushionAsm->id, 'component_part_id' => $partFoam->id],
            ['quantity' => 1, 'level' => 2, 'notes' => 'Busa cetakan urethane']
        );
        PartBom::updateOrCreate(
            ['parent_part_id' => $partCushionAsm->id, 'component_part_id' => $partCover->id],
            ['quantity' => 1, 'level' => 2, 'notes' => 'Kain jahitan']
        );

        // 7. Quotation Multi-Supplier Komparasi untuk Bracket Depan (BRKT-FRONT-01)
        // Penawaran dari Supplier A (PT Steel Jaya Perkasa)
        PartQuotation::updateOrCreate(
            ['part_id' => $partBracket->id, 'supplier_id' => $supA->id],
            [
                'currency_id' => $usd->id,
                'exchange_rate_to_idr' => 16000.0,
                'material_cost_original' => 1.20, // $ 1.20 * 16000 = Rp 19.200
                'machine_cost_original' => 0.90,  // $ 0.90 * 16000 = Rp 14.400
                'manpower_cost_idr' => 5000.0,
                'packaging_cost_idr' => 2000.0,
                'transport_cost_idr' => 1500.0,
                'tooling_type' => 'Dies',
                'tooling_payment_type' => 'Amortisasi',
                'tooling_cost_total' => 50000000.0, // Rp 50.000.000
                'tooling_amortization_qty' => 10000, // 10.000 pcs = Rp 5.000/pcs
                'status' => 'active',
                'is_selected' => false,
                'notes' => 'Dies Stamping 500T garansi 200.000 stroke',
            ]
        );

        // Penawaran dari Supplier B (PT Cipta Logam Presisi) - Lebih murah!
        PartQuotation::updateOrCreate(
            ['part_id' => $partBracket->id, 'supplier_id' => $supB->id],
            [
                'currency_id' => $usd->id,
                'exchange_rate_to_idr' => 16000.0,
                'material_cost_original' => 1.10, // $ 1.10 * 16000 = Rp 17.600
                'machine_cost_original' => 0.85,  // $ 0.85 * 16000 = Rp 13.600
                'manpower_cost_idr' => 4500.0,
                'packaging_cost_idr' => 2000.0,
                'transport_cost_idr' => 1200.0,
                'tooling_type' => 'Dies',
                'tooling_payment_type' => 'Amortisasi',
                'tooling_cost_total' => 45000000.0, // Rp 45.000.000
                'tooling_amortization_qty' => 10000, // 10.000 pcs = Rp 4.500/pcs
                'status' => 'active',
                'is_selected' => true, // Best Price!
                'notes' => 'Supplier B menawarkan harga lebih kompetitif & lead time lebih cepat',
            ]
        );
    }
}
