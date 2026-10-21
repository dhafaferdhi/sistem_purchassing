<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class MaterialSteelSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();

        // 1. Pastikan Currency untuk 2026 dan 2027 (Q1, Q2, Q4) tersedia
        $curr2026Q4 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2026, 'month' => 10],
            ['quarter' => 'Q4', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 17000.0, 'last_updated_at' => now()]
        );
        $curr2027Q1 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2027, 'month' => 1],
            ['quarter' => 'Q1', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 19000.0, 'last_updated_at' => now()]
        );
        $curr2027Q2 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2027, 'month' => 4],
            ['quarter' => 'Q2', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 19500.0, 'last_updated_at' => now()]
        );

        $rate2026Q4 = $curr2026Q4 ? (float) $curr2026Q4->exchange_rate_to_idr : 17000.0;
        $rate2027Q1 = $curr2027Q1 ? (float) $curr2027Q1->exchange_rate_to_idr : 19000.0;
        $rate2027Q2 = $curr2027Q2 ? (float) $curr2027Q2->exchange_rate_to_idr : 19500.0;

        $supHssi = Supplier::firstOrCreate(
            ['code' => 'VND-HSSI'],
            [
                'name' => 'PT HSSI',
                'category' => 'Steel',
                'contact_person' => 'Sales Dept PT HSSI',
                'is_active' => true,
            ]
        );
        $supUsci = Supplier::firstOrCreate(
            ['code' => 'VND-USCI'],
            [
                'name' => 'PT USCI',
                'category' => 'Steel',
                'contact_person' => 'Marketing PT USCI',
                'is_active' => true,
            ]
        );

        MaterialSteel::query()->delete();

        $records = [
            // ==========================================
            // TAHUN 2026 - Q4
            // ==========================================
            [
                'year' => 2026,
                'quarter' => 'Q4',
                'supplier_id' => $supHssi->id,
                'vendor_code' => $supHssi->code,
                'supplier_name' => $supHssi->name,
                'steel_spec' => 'SPCC-SD',
                'double_thickness' => '1.2',
                'currency_id' => $curr2026Q4->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9500,
                'coil_price_idr' => round(1.9500 * $rate2026Q4, 2),
                'sheet_price' => 2.0500,
                'sheet_price_idr' => round(2.0500 * $rate2026Q4, 2),
                'gap_steel_usd' => 100.0000,
                'gap_steel_kg_usd' => 0.1000,
                'gap_steel_idr' => round(100.0000 * $rate2026Q4, 2),
                'steel_price' => 1.9500,
                'exchange_rate' => $rate2026Q4,
                'steel_price_idr' => round(1.9500 * $rate2026Q4, 2),
                'fluktuasi_jfe' => 0.1000,
                'status' => 'Active',
                'notes' => 'Kontrak Baja Q4 2026 Supplier PT HSSI',
                'created_by' => $admin?->id,
            ],
            [
                'year' => 2026,
                'quarter' => 'Q4',
                'supplier_id' => $supUsci->id,
                'vendor_code' => $supUsci->code,
                'supplier_name' => $supUsci->name,
                'steel_spec' => 'SPH270-OD',
                'double_thickness' => '1.6',
                'currency_id' => $curr2026Q4->id,
                'currency_code' => 'USD',
                'coil_price' => 1.8900,
                'coil_price_idr' => round(1.8900 * $rate2026Q4, 2),
                'sheet_price' => 1.9500,
                'sheet_price_idr' => round(1.9500 * $rate2026Q4, 2),
                'gap_steel_usd' => 60.0000,
                'gap_steel_kg_usd' => 0.0600,
                'gap_steel_idr' => round(60.0000 * $rate2026Q4, 2),
                'steel_price' => 1.8900,
                'exchange_rate' => $rate2026Q4,
                'steel_price_idr' => round(1.8900 * $rate2026Q4, 2),
                'fluktuasi_jfe' => 0.0600,
                'status' => 'Active',
                'notes' => 'Kontrak Baja Q4 2026 Supplier PT USCI',
                'created_by' => $admin?->id,
            ],

            // ==========================================
            // TAHUN 2027 - Q1
            // ==========================================
            [
                'year' => 2027,
                'quarter' => 'Q1',
                'supplier_id' => $supHssi->id,
                'vendor_code' => $supHssi->code,
                'supplier_name' => $supHssi->name,
                'steel_spec' => 'SPCC-SD',
                'double_thickness' => '1.2',
                'currency_id' => $curr2027Q1->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9800,
                'coil_price_idr' => round(1.9800 * $rate2027Q1, 2),
                'sheet_price' => 2.0800,
                'sheet_price_idr' => round(2.0800 * $rate2027Q1, 2),
                'gap_steel_usd' => 100.0000,
                'gap_steel_kg_usd' => 0.1000,
                'gap_steel_idr' => round(100.0000 * $rate2027Q1, 2),
                'steel_price' => 1.9800,
                'exchange_rate' => $rate2027Q1,
                'steel_price_idr' => round(1.9800 * $rate2027Q1, 2),
                'fluktuasi_jfe' => 0.1000,
                'status' => 'Active',
                'notes' => 'Kontrak Baja Q1 2027 Supplier PT HSSI',
                'created_by' => $admin?->id,
            ],
            [
                'year' => 2027,
                'quarter' => 'Q1',
                'supplier_id' => $supUsci->id,
                'vendor_code' => $supUsci->code,
                'supplier_name' => $supUsci->name,
                'steel_spec' => 'SPH270-OD',
                'double_thickness' => '2.0',
                'currency_id' => $curr2027Q1->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9200,
                'coil_price_idr' => round(1.9200 * $rate2027Q1, 2),
                'sheet_price' => 2.0200,
                'sheet_price_idr' => round(2.0200 * $rate2027Q1, 2),
                'gap_steel_usd' => 100.0000,
                'gap_steel_kg_usd' => 0.1000,
                'gap_steel_idr' => round(100.0000 * $rate2027Q1, 2),
                'steel_price' => 1.9200,
                'exchange_rate' => $rate2027Q1,
                'steel_price_idr' => round(1.9200 * $rate2027Q1, 2),
                'fluktuasi_jfe' => 0.1000,
                'status' => 'Active',
                'notes' => 'Kontrak Baja Q1 2027 Supplier PT USCI',
                'created_by' => $admin?->id,
            ],

            // ==========================================
            // TAHUN 2027 - Q2 (KENAIKAN MT & KURS DARI Q1)
            // ==========================================
            [
                'year' => 2027,
                'quarter' => 'Q2',
                'supplier_id' => $supHssi->id,
                'vendor_code' => $supHssi->code,
                'supplier_name' => $supHssi->name,
                'steel_spec' => 'SPCC-SD',
                'double_thickness' => '1.2',
                'currency_id' => $curr2027Q2->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9800,
                'coil_price_idr' => round(1.9800 * $rate2027Q2, 2),
                'sheet_price' => 2.1000,
                'sheet_price_idr' => round(2.1000 * $rate2027Q2, 2),
                'gap_steel_usd' => 120.0000,
                'gap_steel_kg_usd' => 0.1200,
                'gap_steel_idr' => round(120.0000 * $rate2027Q2, 2),
                'steel_price' => 1.9800,
                'exchange_rate' => $rate2027Q2,
                'steel_price_idr' => round(1.9800 * $rate2027Q2, 2),
                'fluktuasi_jfe' => 0.1200,
                'prev_month_1_label' => 'Maret 2027',
                'prev_month_1_price' => 1.9800,
                'prev_month_1_rate' => 19000.0000,
                'prev_month_2_label' => 'Februari 2027',
                'prev_month_2_price' => 1.9800,
                'prev_month_2_rate' => 19000.0000,
                'prev_month_3_label' => 'Januari 2027',
                'prev_month_3_price' => 1.9800,
                'prev_month_3_rate' => 19000.0000,
                'avg_3_months_price' => 1.9800,
                'avg_3_months_rate' => 19000.0000,
                'avg_3_months_price_idr' => 37620.00,
                'price_variance_percentage' => 0.00,
                'status' => 'Active',
                'notes' => 'Kontrak Baja Q2 2027 Supplier PT HSSI - Penyesuaian kenaikan MT (+$20/MT) dan Rate Kurs (Rp 19.500)',
                'created_by' => $admin?->id,
            ],
            [
                'year' => 2027,
                'quarter' => 'Q2',
                'supplier_id' => $supUsci->id,
                'vendor_code' => $supUsci->code,
                'supplier_name' => $supUsci->name,
                'steel_spec' => 'SPH270-OD',
                'double_thickness' => '2.0',
                'currency_id' => $curr2027Q2->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9200,
                'coil_price_idr' => round(1.9200 * $rate2027Q2, 2),
                'sheet_price' => 2.0400,
                'sheet_price_idr' => round(2.0400 * $rate2027Q2, 2),
                'gap_steel_usd' => 120.0000,
                'gap_steel_kg_usd' => 0.1200,
                'gap_steel_idr' => round(120.0000 * $rate2027Q2, 2),
                'steel_price' => 1.9200,
                'exchange_rate' => $rate2027Q2,
                'steel_price_idr' => round(1.9200 * $rate2027Q2, 2),
                'fluktuasi_jfe' => 0.1200,
                'prev_month_1_label' => 'Maret 2027',
                'prev_month_1_price' => 1.9200,
                'prev_month_1_rate' => 19000.0000,
                'prev_month_2_label' => 'Februari 2027',
                'prev_month_2_price' => 1.9200,
                'prev_month_2_rate' => 19000.0000,
                'prev_month_3_label' => 'Januari 2027',
                'prev_month_3_price' => 1.9200,
                'prev_month_3_rate' => 19000.0000,
                'avg_3_months_price' => 1.9200,
                'avg_3_months_rate' => 19000.0000,
                'avg_3_months_price_idr' => 36480.00,
                'price_variance_percentage' => 0.00,
                'status' => 'Active',
                'notes' => 'Kontrak Baja Q2 2027 Supplier PT USCI - Penyesuaian kenaikan MT (+$20/MT) dan Rate Kurs (Rp 19.500)',
                'created_by' => $admin?->id,
            ],
        ];

        foreach ($records as $item) {
            MaterialSteel::create($item);
        }
    }
}
