<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Machine;
use App\Models\Material;
use App\Models\Part;
use App\Models\Process;
use App\Models\Supplier;
use App\Models\VendorItem;
use Illuminate\Database\Seeder;

class ItemMasterRoutingSeeder extends Seeder
{
    public function run(): void
    {
        $idr = Currency::where('code', 'IDR')->first();
        $matSPHC = Material::where('spec', 'SPHC-Steel')->first();
        $matSWMB = Material::where('spec', 'SWMB-BoltSteel')->first();

        // 1. Vendors
        $vndMajuBaja = Supplier::updateOrCreate(['code' => 'VND-105'], [
            'name' => 'PT Maju Baja Stamping',
            'contact_person' => 'Agus Priyono',
            'email' => 'sales@majubaja.test',
            'phone' => '021-89871105',
            'category' => 'Stamping & Steel',
            'is_active' => true,
        ]);

        $vndCiptaFastener = Supplier::updateOrCreate(['code' => 'VND-201'], [
            'name' => 'PT Cipta Presisi Fastener',
            'contact_person' => 'Rina Marlina',
            'email' => 'order@ciptafastener.test',
            'phone' => '021-89872201',
            'category' => 'Fastener',
            'is_active' => true,
        ]);

        // 2. Machines (Progressive vs Tandem & Tonnages)
        $m100Tandem = Machine::updateOrCreate(['name' => 'Mesin Stamping 100 Ton Tandem'], [
            'category' => 'Steel',
            'stamping_type' => 'Tandem',
            'tonnage' => '100 Ton',
            'hourly_rate' => 150000.0,
            'operator_hourly_rate' => 50000.0,
            'default_capacity_per_hour' => 180,
            'is_active' => true,
        ]);

        $m300Tandem = Machine::updateOrCreate(['name' => 'Mesin Stamping 300 Ton Tandem'], [
            'category' => 'Steel',
            'stamping_type' => 'Tandem',
            'tonnage' => '300 Ton',
            'hourly_rate' => 280000.0,
            'operator_hourly_rate' => 60000.0,
            'default_capacity_per_hour' => 140,
            'is_active' => true,
        ]);

        $m500Prog = Machine::updateOrCreate(['name' => 'Mesin Stamping 500 Ton Progressive'], [
            'category' => 'Steel',
            'stamping_type' => 'Progressive',
            'tonnage' => '500 Ton',
            'hourly_rate' => 450000.0,
            'operator_hourly_rate' => 75000.0,
            'default_capacity_per_hour' => 150,
            'is_active' => true,
        ]);

        $m800Prog = Machine::updateOrCreate(['name' => 'Mesin Stamping 800 Ton Progressive'], [
            'category' => 'Steel',
            'stamping_type' => 'Progressive',
            'tonnage' => '800 Ton',
            'hourly_rate' => 650000.0,
            'operator_hourly_rate' => 85000.0,
            'default_capacity_per_hour' => 90,
            'is_active' => true,
        ]);

        // 3. Routing Processes (Blanking, Piercing, Bending dengan Tooling Dies)
        Process::updateOrCreate(['name' => 'Blanking & Piercing Progressive STP-001'], [
            'stage_type' => 'Blanking',
            'category' => 'Presspart',
            'machine_id' => $m500Prog->id,
            'cycle_time_seconds' => 10,
            'tooling_type' => 'Dies',
            'dies_code' => 'DIES-STP-001-PROG',
            'dies_name' => 'Dies Progressive Bracket Bumper 500T',
            'default_cost' => 150.0,
            'is_active' => true,
        ]);

        Process::updateOrCreate(['name' => 'Bending Bracket Tandem STP-001'], [
            'stage_type' => 'Bending',
            'category' => 'Presspart',
            'machine_id' => $m300Tandem->id,
            'cycle_time_seconds' => 15,
            'tooling_type' => 'Dies',
            'dies_code' => 'DIES-STP-001-BEND',
            'dies_name' => 'Dies Bending Manual 300T',
            'default_cost' => 120.0,
            'is_active' => true,
        ]);

        Process::updateOrCreate(['name' => 'Trimming STP-001'], [
            'stage_type' => 'Trimming',
            'category' => 'Presspart',
            'machine_id' => $m100Tandem->id,
            'cycle_time_seconds' => 8,
            'tooling_type' => 'Dies',
            'dies_code' => 'DIES-STP-001-TRIM',
            'dies_name' => 'Dies Trimming Flange 100T',
            'default_cost' => 90.0,
            'is_active' => true,
        ]);

        // 4. Item Master (Barang Internal)
        $itemStp001 = Part::updateOrCreate(['item_code' => 'STP-001'], [
            'part_number' => 'STP-001',
            'description' => 'Bracket Bumper Depan (Stamping Press Steel)',
            'part_name' => 'Bracket Bumper Depan (Stamping Press Steel)',
            'item_category' => 'Stamping',
            'main_category' => 'Steel',
            'sub_category' => 'Bracket',
            'type' => 'Component',
            'material_id' => $matSPHC?->id,
            'weight' => 0.45,
            'dimensions' => '180x95x2.3 mm',
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $itemFst001 = Part::updateOrCreate(['item_code' => 'FST-001'], [
            'part_number' => 'FST-001',
            'description' => 'Bolt Flange M6x25 mm Grade 8.8',
            'part_name' => 'Bolt Flange M6x25 mm Grade 8.8',
            'item_category' => 'Fastener',
            'main_category' => 'Steel',
            'sub_category' => 'Bolt',
            'type' => 'Component',
            'material_id' => $matSWMB?->id,
            'weight' => 0.05,
            'dimensions' => 'M6 x 25 mm',
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $itemMat001 = Part::updateOrCreate(['item_code' => 'MAT-001'], [
            'part_number' => 'MAT-001',
            'description' => 'Gulungan Plat Baja Coil SPHC JIS G3131',
            'part_name' => 'Gulungan Plat Baja Coil SPHC JIS G3131',
            'item_category' => 'Material',
            'main_category' => 'Steel',
            'sub_category' => 'Sheet Metal',
            'type' => 'Component',
            'material_id' => $matSPHC?->id,
            'weight' => 2500.0,
            'dimensions' => 'Tebal 2.3 mm x Lebar 1219 mm',
            'unit' => 'coil',
            'is_active' => true,
        ]);

        // 5. Vendor Item Mapping (Pemetaan Kode Barang Internal vs Kode Barang Vendor)
        VendorItem::updateOrCreate(
            ['supplier_id' => $vndMajuBaja->id, 'part_id' => $itemStp001->id],
            [
                'vendor_item_code' => 'MBS-BKT-889',
                'vendor_item_name' => 'Bracket Bumper Front LH Stamping High Strength',
                'unit_price' => 43500.0,
                'currency_id' => $idr?->id ?? 1,
                'moq' => 500,
                'lead_time_days' => 10,
                'is_primary_vendor' => true,
                'notes' => 'Telah lolos audit PPAP Level 3',
            ]
        );

        VendorItem::updateOrCreate(
            ['supplier_id' => $vndCiptaFastener->id, 'part_id' => $itemFst001->id],
            [
                'vendor_item_code' => 'CPF-BLT-M625',
                'vendor_item_name' => 'Hex Flange Bolt M6x25 Class 8.8 Yellow Zinc',
                'unit_price' => 850.0,
                'currency_id' => $idr?->id ?? 1,
                'moq' => 5000,
                'lead_time_days' => 7,
                'is_primary_vendor' => true,
                'notes' => 'Sertifikat material & uji tarik disertakan per lot delivery',
            ]
        );
    }
}
