<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class RealMasterData2026Seeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        // 1. Currencies 2026 Q1 and Q2
        $currQ1 = Currency::updateOrCreate(
            ['code' => 'USD', 'year' => 2026, 'quarter' => 'Q1'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate_to_idr' => 16465.0,
                'month' => 1,
                'last_updated_at' => now(),
            ]
        );

        $currQ2 = Currency::updateOrCreate(
            ['code' => 'USD', 'year' => 2026, 'quarter' => 'Q2'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate_to_idr' => 16725.0,
                'month' => 4,
                'last_updated_at' => now(),
            ]
        );

        // 2. Suppliers
        $supPosmi = Supplier::firstOrCreate(
            ['code' => 'VND-POSMI'],
            [
                'name' => 'PT. POSMI STEEL INDONESIA',
                'category' => 'Steel',
                'contact_person' => 'Sales Dept PT POSMI',
                'is_active' => true,
            ]
        );

        $supNitto = Supplier::firstOrCreate(
            ['code' => 'VND-NITTO'],
            [
                'name' => 'PT. NITTO ALAM INDONESIA',
                'category' => 'Fastener',
                'contact_person' => 'Sales Dept PT NITTO',
                'is_active' => true,
            ]
        );

        // 3. Material Steel (2 data Q1 2026, 2 data Q2 2026)
        // Q1 2026
        MaterialSteel::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q1', 'steel_spec' => 'SPH440-OD', 'double_thickness' => '2.6'],
            [
                'supplier_id' => $supPosmi->id,
                'vendor_code' => $supPosmi->code,
                'supplier_name' => $supPosmi->name,
                'size' => '2.6 x 1219 x C',
                'currency_id' => $currQ1->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16465.0,
                'coil_price' => 1.1850,
                'sheet_price' => 1.2100,
                'coil_price_idr' => round(1.1850 * 16465.0, 2),
                'sheet_price_idr' => round(1.2100 * 16465.0, 2),
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        MaterialSteel::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q1', 'steel_spec' => 'SPH270C-OD', 'double_thickness' => '1.6'],
            [
                'supplier_id' => $supPosmi->id,
                'vendor_code' => $supPosmi->code,
                'supplier_name' => $supPosmi->name,
                'size' => '1.6 x 1219 x C',
                'currency_id' => $currQ1->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16465.0,
                'coil_price' => 1.1500,
                'sheet_price' => 1.1750,
                'coil_price_idr' => round(1.1500 * 16465.0, 2),
                'sheet_price_idr' => round(1.1750 * 16465.0, 2),
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        // Q2 2026
        MaterialSteel::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q2', 'steel_spec' => 'SPH440-OD', 'double_thickness' => '2.6'],
            [
                'supplier_id' => $supPosmi->id,
                'vendor_code' => $supPosmi->code,
                'supplier_name' => $supPosmi->name,
                'size' => '2.6 x 1219 x C',
                'currency_id' => $currQ2->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16725.0,
                'coil_price' => 1.1980,
                'sheet_price' => 1.2230,
                'coil_price_idr' => round(1.1980 * 16725.0, 2),
                'sheet_price_idr' => round(1.2230 * 16725.0, 2),
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        MaterialSteel::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q2', 'steel_spec' => 'SPH270C-OD', 'double_thickness' => '1.6'],
            [
                'supplier_id' => $supPosmi->id,
                'vendor_code' => $supPosmi->code,
                'supplier_name' => $supPosmi->name,
                'size' => '1.6 x 1219 x C',
                'currency_id' => $currQ2->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16725.0,
                'coil_price' => 1.1630,
                'sheet_price' => 1.1880,
                'coil_price_idr' => round(1.1630 * 16725.0, 2),
                'sheet_price_idr' => round(1.1880 * 16725.0, 2),
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        // 4. Stamping Part (2 data Q1 2026, 2 data Q2 2026)
        // Part 1: 72388-X7V00-A
        StampingPart::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q1', 'part_number' => '72388-X7V00-A'],
            [
                'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
                'model_name' => 'D26A',
                'monthly_volume' => 16500,
                'drawing_reference' => 'Mass Prod. Drwg',
                'eci_number' => 'F1225',
                'supplier_id' => $supPosmi->id,
                'vendor_code' => $supPosmi->code,
                'supplier_name' => $supPosmi->name,
                'steel_spec' => 'SPH440-OD',
                'double_thickness' => '2.6',
                'size' => '2.6 x 1219 x C',
                'wide' => 390.0,
                'pitch' => 148.0,
                'yield_rate' => 2.0,
                'berat' => 0.5890,
                'scrap_weight' => 0.2780,
                'scrap_price_per_kg' => -1500.0,
                'process_name' => '1. PROGRESSIVE 250T',
                'process_man_hour' => 9.0,
                'process_charge' => 45.0,
                'overhead_supplied_percent' => 11.0,
                'overhead_self_percent' => 4.0,
                'tooling_depreciation' => 0.0,
                'currency_id' => $currQ1->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16465.0,
                'coil_price' => 1.1850,
                'sheet_price' => 1.2100,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        StampingPart::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q2', 'part_number' => '72388-X7V00-A'],
            [
                'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
                'model_name' => 'D26A',
                'monthly_volume' => 16500,
                'drawing_reference' => 'Mass Prod. Drwg',
                'eci_number' => 'F1225',
                'supplier_id' => $supPosmi->id,
                'vendor_code' => $supPosmi->code,
                'supplier_name' => $supPosmi->name,
                'steel_spec' => 'SPH440-OD',
                'double_thickness' => '2.6',
                'size' => '2.6 x 1219 x C',
                'wide' => 390.0,
                'pitch' => 148.0,
                'yield_rate' => 2.0,
                'berat' => 0.5890,
                'scrap_weight' => 0.2780,
                'scrap_price_per_kg' => -1500.0,
                'process_name' => '1. PROGRESSIVE 250T',
                'process_man_hour' => 9.0,
                'process_charge' => 45.0,
                'overhead_supplied_percent' => 11.0,
                'overhead_self_percent' => 4.0,
                'tooling_depreciation' => 0.0,
                'currency_id' => $currQ2->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16725.0,
                'coil_price' => 1.1980,
                'sheet_price' => 1.2230,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        // Part 2: 72389-X7V00-A
        StampingPart::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q1', 'part_number' => '72389-X7V00-A'],
            [
                'part_name' => 'BRKT, SEAT BELT ANCHOR RH',
                'model_name' => 'D26A',
                'monthly_volume' => 16500,
                'drawing_reference' => 'Mass Prod. Drwg',
                'eci_number' => 'F1225',
                'supplier_id' => $supPosmi->id,
                'vendor_code' => $supPosmi->code,
                'supplier_name' => $supPosmi->name,
                'steel_spec' => 'SPH270C-OD',
                'double_thickness' => '1.6',
                'size' => '1.6 x 1219 x C',
                'wide' => 280.0,
                'pitch' => 125.0,
                'yield_rate' => 1.0,
                'berat' => 0.4396,
                'scrap_weight' => 0.1850,
                'scrap_price_per_kg' => -1500.0,
                'process_name' => '1. PROGRESSIVE 250T',
                'process_man_hour' => 8.5,
                'process_charge' => 45.0,
                'overhead_supplied_percent' => 11.0,
                'overhead_self_percent' => 4.0,
                'tooling_depreciation' => 0.0,
                'currency_id' => $currQ1->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16465.0,
                'coil_price' => 1.1500,
                'sheet_price' => 1.1750,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        StampingPart::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q2', 'part_number' => '72389-X7V00-A'],
            [
                'part_name' => 'BRKT, SEAT BELT ANCHOR RH',
                'model_name' => 'D26A',
                'monthly_volume' => 16500,
                'drawing_reference' => 'Mass Prod. Drwg',
                'eci_number' => 'F1225',
                'supplier_id' => $supPosmi->id,
                'vendor_code' => $supPosmi->code,
                'supplier_name' => $supPosmi->name,
                'steel_spec' => 'SPH270C-OD',
                'double_thickness' => '1.6',
                'size' => '1.6 x 1219 x C',
                'wide' => 280.0,
                'pitch' => 125.0,
                'yield_rate' => 1.0,
                'berat' => 0.4396,
                'scrap_weight' => 0.1850,
                'scrap_price_per_kg' => -1500.0,
                'process_name' => '1. PROGRESSIVE 250T',
                'process_man_hour' => 8.5,
                'process_charge' => 45.0,
                'overhead_supplied_percent' => 11.0,
                'overhead_self_percent' => 4.0,
                'tooling_depreciation' => 0.0,
                'currency_id' => $currQ2->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16725.0,
                'coil_price' => 1.1630,
                'sheet_price' => 1.1880,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        // 5. Fasteners (2 data Q1 2026, 2 data Q2 2026)
        // Part 1: 90041-19960 (BOLT,W/WASHER)
        Fastener::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q1', 'part_number' => '90041-19960'],
            [
                'part_name' => 'BOLT,W/WASHER',
                'model_name' => 'D26A',
                'monthly_volume' => 20000,
                'drawing_reference' => 'Official Dwg',
                'supplier_id' => $supNitto->id,
                'vendor_code' => $supNitto->code,
                'supplier_name' => $supNitto->name,
                'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
                'steel_spec' => 'SWCH-45K',
                'diameter' => '6.0',
                'double_thickness' => '6.0',
                'currency_id' => $currQ1->id,
                'currency_code' => 'USD',
                'old_exchange_rate' => 16321.0,
                'exchange_rate' => 16465.0,
                'exchange_rate_fluctuation' => round(16465.0 / 16321.0, 4),
                'wire_price_usd_kg' => 2.02,
                'wire_price_idr_kg' => round(2.02 * 16465.0, 2),
                'nett_weight_gr' => 4.40,
                'gross_weight_gr' => 4.84,
                'wire_price_pc' => round((4.84 / 1000) * (2.02 * 16465.0), 2),
                'material_control_percent' => 5.0,
                'washer_weight_gr' => 4.50,
                'washer_price' => 198.0,
                'heading_cost' => 48.0,
                'rolling_cost' => 76.0,
                'washing_cost_kg' => 500.0,
                'plating_cost_kg' => 10000.0,
                'inspection_cost' => 5.0,
                'packing_cost' => 3.0,
                'overhead_self_percent' => 14.3,
                'tooling_depreciation' => 0.0,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        Fastener::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q2', 'part_number' => '90041-19960'],
            [
                'part_name' => 'BOLT,W/WASHER',
                'model_name' => 'D26A',
                'monthly_volume' => 20000,
                'drawing_reference' => 'Official Dwg',
                'supplier_id' => $supNitto->id,
                'vendor_code' => $supNitto->code,
                'supplier_name' => $supNitto->name,
                'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
                'steel_spec' => 'SWCH-45K',
                'diameter' => '6.0',
                'double_thickness' => '6.0',
                'currency_id' => $currQ2->id,
                'currency_code' => 'USD',
                'old_exchange_rate' => 16465.0,
                'exchange_rate' => 16725.0,
                'exchange_rate_fluctuation' => round(16725.0 / 16465.0, 4),
                'wire_price_usd_kg' => 2.02,
                'wire_price_idr_kg' => round(2.02 * 16725.0, 2),
                'nett_weight_gr' => 4.40,
                'gross_weight_gr' => 4.84,
                'wire_price_pc' => round((4.84 / 1000) * (2.02 * 16725.0), 2),
                'material_control_percent' => 5.0,
                'washer_weight_gr' => 4.50,
                'washer_price' => 198.0,
                'heading_cost' => 48.0,
                'rolling_cost' => 76.0,
                'washing_cost_kg' => 500.0,
                'plating_cost_kg' => 10000.0,
                'inspection_cost' => 5.0,
                'packing_cost' => 3.0,
                'overhead_self_percent' => 14.3,
                'tooling_depreciation' => 0.0,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        // Part 2: 90041-05244 (HEXAGON FLANGE BOLT)
        Fastener::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q1', 'part_number' => '90041-05244'],
            [
                'part_name' => 'HEXAGON FLANGE BOLT',
                'model_name' => 'D26A',
                'monthly_volume' => 25000,
                'drawing_reference' => 'Official Dwg',
                'supplier_id' => $supNitto->id,
                'vendor_code' => $supNitto->code,
                'supplier_name' => $supNitto->name,
                'wire_spec' => 'SWCH-10R',
                'steel_spec' => 'SWCH-10R',
                'diameter' => '5.0',
                'double_thickness' => '5.0',
                'currency_id' => $currQ1->id,
                'currency_code' => 'USD',
                'old_exchange_rate' => 16321.0,
                'exchange_rate' => 16465.0,
                'exchange_rate_fluctuation' => round(16465.0 / 16321.0, 4),
                'wire_price_usd_kg' => 1.85,
                'wire_price_idr_kg' => round(1.85 * 16465.0, 2),
                'nett_weight_gr' => 6.20,
                'gross_weight_gr' => 6.82,
                'wire_price_pc' => round((6.82 / 1000) * (1.85 * 16465.0), 2),
                'material_control_percent' => 5.0,
                'washer_weight_gr' => 0.0,
                'washer_price' => 0.0,
                'heading_cost' => 55.0,
                'rolling_cost' => 82.0,
                'washing_cost_kg' => 500.0,
                'plating_cost_kg' => 10000.0,
                'inspection_cost' => 5.0,
                'packing_cost' => 3.0,
                'overhead_self_percent' => 14.3,
                'tooling_depreciation' => 0.0,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        Fastener::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q2', 'part_number' => '90041-05244'],
            [
                'part_name' => 'HEXAGON FLANGE BOLT',
                'model_name' => 'D26A',
                'monthly_volume' => 25000,
                'drawing_reference' => 'Official Dwg',
                'supplier_id' => $supNitto->id,
                'vendor_code' => $supNitto->code,
                'supplier_name' => $supNitto->name,
                'wire_spec' => 'SWCH-10R',
                'steel_spec' => 'SWCH-10R',
                'diameter' => '5.0',
                'double_thickness' => '5.0',
                'currency_id' => $currQ2->id,
                'currency_code' => 'USD',
                'old_exchange_rate' => 16465.0,
                'exchange_rate' => 16725.0,
                'exchange_rate_fluctuation' => round(16725.0 / 16465.0, 4),
                'wire_price_usd_kg' => 1.85,
                'wire_price_idr_kg' => round(1.85 * 16725.0, 2),
                'nett_weight_gr' => 6.20,
                'gross_weight_gr' => 6.82,
                'wire_price_pc' => round((6.82 / 1000) * (1.85 * 16725.0), 2),
                'material_control_percent' => 5.0,
                'washer_weight_gr' => 0.0,
                'washer_price' => 0.0,
                'heading_cost' => 55.0,
                'rolling_cost' => 82.0,
                'washing_cost_kg' => 500.0,
                'plating_cost_kg' => 10000.0,
                'inspection_cost' => 5.0,
                'packing_cost' => 3.0,
                'overhead_self_percent' => 14.3,
                'tooling_depreciation' => 0.0,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );
    }
}
