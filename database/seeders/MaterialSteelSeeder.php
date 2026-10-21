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

        // 1. Pastikan Currency untuk 2026 dan 2027 (Q1 & Q2) tersedia dengan month sesuai kuartal (Q1 = month 1, Q2 = month 4)
        $curr2026Q1 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2026, 'month' => 1],
            ['quarter' => 'Q1', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 16500.0, 'last_updated_at' => now()]
        );
        $curr2026Q2 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2026, 'month' => 4],
            ['quarter' => 'Q2', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 17000.0, 'last_updated_at' => now()]
        );
        $curr2027Q1 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2027, 'month' => 1],
            ['quarter' => 'Q1', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 19000.0, 'last_updated_at' => now()]
        );
        $curr2027Q2 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2027, 'month' => 4],
            ['quarter' => 'Q2', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 19500.0, 'last_updated_at' => now()]
        );

        // Pastikan juga JPY untuk Q1 & Q2
        Currency::firstOrCreate(
            ['code' => 'JPY', 'year' => 2027, 'month' => 1],
            ['quarter' => 'Q1', 'name' => 'Japanese Yen', 'symbol' => '¥', 'exchange_rate_to_idr' => 105.0, 'last_updated_at' => now()]
        );
        Currency::firstOrCreate(
            ['code' => 'JPY', 'year' => 2027, 'month' => 4],
            ['quarter' => 'Q2', 'name' => 'Japanese Yen', 'symbol' => '¥', 'exchange_rate_to_idr' => 110.0, 'last_updated_at' => now()]
        );

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

        // Hapus data lama agar sinkron
        MaterialSteel::query()->delete();

        $records = [
            // ==========================================
            // TAHUN 2027 - Q1 (ACUAN AWAL)
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
                'coil_price_idr' => 37620.00,
                'sheet_price' => 2.0800,
                'sheet_price_idr' => 39520.00,
                'gap_steel_usd' => 100.0000,
                'gap_steel_kg_usd' => 0.1000,
                'gap_steel_idr' => 1900000.00,
                'steel_price' => 1.9800,
                'exchange_rate' => 19000.0000,
                'steel_price_idr' => 37620.00,
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
                'coil_price_idr' => 36480.00,
                'sheet_price' => 2.0200,
                'sheet_price_idr' => 38380.00,
                'gap_steel_usd' => 100.0000,
                'gap_steel_kg_usd' => 0.1000,
                'gap_steel_idr' => 1900000.00,
                'steel_price' => 1.9200,
                'exchange_rate' => 19000.0000,
                'steel_price_idr' => 36480.00,
                'fluktuasi_jfe' => 0.1000,
                'status' => 'Active',
                'notes' => 'Kontrak Baja Q1 2027 Supplier PT USCI',
                'created_by' => $admin?->id,
            ],

            // ==========================================
            // TAHUN 2027 - Q2 (KENAIKAN MT & KURS DARI Q1)
            // MT naik +$20/MT ($100 -> $120 / MT atau $0.1200/kg)
            // Kurs naik Rp 19.000 -> Rp 19.500
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
                'coil_price_idr' => 38610.00, // 1.98 * 19500
                'sheet_price' => 2.1000,      // 1.98 + 0.12
                'sheet_price_idr' => 40950.00, // 2.10 * 19500
                'gap_steel_usd' => 120.0000,  // Naik dari 100 ke 120
                'gap_steel_kg_usd' => 0.1200,
                'gap_steel_idr' => 2340000.00, // 120 * 19500
                'steel_price' => 1.9800,
                'exchange_rate' => 19500.0000, // Naik dari 19000 ke 19500
                'steel_price_idr' => 38610.00,
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
                'coil_price_idr' => 37440.00, // 1.92 * 19500
                'sheet_price' => 2.0400,      // 1.92 + 0.12
                'sheet_price_idr' => 39780.00, // 2.04 * 19500
                'gap_steel_usd' => 120.0000,  // Naik dari 100 ke 120
                'gap_steel_kg_usd' => 0.1200,
                'gap_steel_idr' => 2340000.00,
                'steel_price' => 1.9200,
                'exchange_rate' => 19500.0000, // Naik dari 19000 ke 19500
                'steel_price_idr' => 37440.00,
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

            // ==========================================
            // TAHUN 2026 - Q1 & Q2 (Fallback agar filter 2026 juga lengkap)
            // ==========================================
            [
                'year' => 2026,
                'quarter' => 'Q1',
                'supplier_id' => $supHssi->id,
                'vendor_code' => $supHssi->code,
                'supplier_name' => $supHssi->name,
                'steel_spec' => 'SPCC-SD',
                'double_thickness' => '1.2',
                'currency_id' => $curr2026Q1->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9500,
                'coil_price_idr' => round(1.9500 * 16500, 2),
                'sheet_price' => 2.0500,
                'sheet_price_idr' => round(2.0500 * 16500, 2),
                'gap_steel_usd' => 100.0000,
                'gap_steel_kg_usd' => 0.1000,
                'gap_steel_idr' => round(100.0000 * 16500, 2),
                'steel_price' => 1.9500,
                'exchange_rate' => 16500.0000,
                'steel_price_idr' => round(1.9500 * 16500, 2),
                'status' => 'Active',
                'notes' => 'Kontrak Baja Q1 2026 Supplier PT HSSI',
                'created_by' => $admin?->id,
            ],
            [
                'year' => 2026,
                'quarter' => 'Q2',
                'supplier_id' => $supHssi->id,
                'vendor_code' => $supHssi->code,
                'supplier_name' => $supHssi->name,
                'steel_spec' => 'SPCC-SD',
                'double_thickness' => '1.2',
                'currency_id' => $curr2026Q2->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9500,
                'coil_price_idr' => round(1.9500 * 17000, 2),
                'sheet_price' => 2.0700,
                'sheet_price_idr' => round(2.0700 * 17000, 2),
                'gap_steel_usd' => 120.0000,
                'gap_steel_kg_usd' => 0.1200,
                'gap_steel_idr' => round(120.0000 * 17000, 2),
                'steel_price' => 1.9500,
                'exchange_rate' => 17000.0000,
                'steel_price_idr' => round(1.9500 * 17000, 2),
                'status' => 'Active',
                'notes' => 'Kontrak Baja Q2 2026 Supplier PT HSSI - Kenaikan MT dan Kurs',
                'created_by' => $admin?->id,
            ],
        ];

        foreach ($records as $item) {
            MaterialSteel::create($item);
        }
    }
}
