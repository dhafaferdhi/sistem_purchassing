<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Fastener;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class FastenerSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $curr2026 = Currency::where('code', 'USD')->where('year', 2026)->first() ?? Currency::where('code', 'USD')->first();
        $curr2027 = Currency::where('code', 'USD')->where('year', 2027)->first() ?? $curr2026;

        $rate2026 = $curr2026 ? (float) $curr2026->exchange_rate_to_idr : 16000.0;
        $rate2027 = $curr2027 ? (float) $curr2027->exchange_rate_to_idr : 16200.0;

        $supHssi = Supplier::firstOrCreate(
            ['code' => 'VND-HSSI'],
            [
                'name' => 'PT HSSI',
                'category' => 'Steel',
                'contact_person' => 'Marketing PT HSSI',
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

        // Hapus data lama agar persis sama 4 data
        Fastener::query()->delete();

        $records = [
            // 1. Q4 2026 - PT HSSI
            [
                'year' => 2026,
                'quarter' => 'Q4',
                'supplier_id' => $supHssi->id,
                'vendor_code' => $supHssi->code,
                'supplier_name' => $supHssi->name,
                'steel_spec' => 'SPCC-SD',
                'diameter' => '1.2',
                'double_thickness' => '1.2',
                'currency_id' => $curr2026?->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9500,
                'coil_price_idr' => round(1.9500 * $rate2026, 2),
                'sheet_price' => 2.0500,
                'sheet_price_idr' => round(2.0500 * $rate2026, 2),
                'gap_steel_usd' => 100.0000,
                'gap_steel_kg_usd' => 0.1000,
                'exchange_rate' => $rate2026,
                'status' => 'Active',
                'notes' => 'Kontrak Pasterner Q4 2026 Supplier PT HSSI',
                'created_by' => $admin?->id,
            ],

            // 2. Q4 2026 - PT USCI
            [
                'year' => 2026,
                'quarter' => 'Q4',
                'supplier_id' => $supUsci->id,
                'vendor_code' => $supUsci->code,
                'supplier_name' => $supUsci->name,
                'steel_spec' => 'SPH270-OD',
                'diameter' => '1.6',
                'double_thickness' => '1.6',
                'currency_id' => $curr2026?->id,
                'currency_code' => 'USD',
                'coil_price' => 1.8900,
                'coil_price_idr' => round(1.8900 * $rate2026, 2),
                'sheet_price' => 1.9500,
                'sheet_price_idr' => round(1.9500 * $rate2026, 2),
                'gap_steel_usd' => 60.0000,
                'gap_steel_kg_usd' => 0.0600,
                'exchange_rate' => $rate2026,
                'status' => 'Active',
                'notes' => 'Kontrak Pasterner Q4 2026 Supplier PT USCI',
                'created_by' => $admin?->id,
            ],

            // 3. Q1 2027 - PT HSSI
            [
                'year' => 2027,
                'quarter' => 'Q1',
                'supplier_id' => $supHssi->id,
                'vendor_code' => $supHssi->code,
                'supplier_name' => $supHssi->name,
                'steel_spec' => 'SPCC-SD',
                'diameter' => '1.2',
                'double_thickness' => '1.2',
                'currency_id' => $curr2027?->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9800,
                'coil_price_idr' => round(1.9800 * $rate2027, 2),
                'sheet_price' => 2.0800,
                'sheet_price_idr' => round(2.0800 * $rate2027, 2),
                'gap_steel_usd' => 100.0000,
                'gap_steel_kg_usd' => 0.1000,
                'exchange_rate' => $rate2027,
                'status' => 'Active',
                'notes' => 'Kontrak Pasterner Q1 2027 Supplier PT HSSI',
                'created_by' => $admin?->id,
            ],

            // 4. Q1 2027 - PT USCI
            [
                'year' => 2027,
                'quarter' => 'Q1',
                'supplier_id' => $supUsci->id,
                'vendor_code' => $supUsci->code,
                'supplier_name' => $supUsci->name,
                'steel_spec' => 'SPH270-OD',
                'diameter' => '2.0',
                'double_thickness' => '2.0',
                'currency_id' => $curr2027?->id,
                'currency_code' => 'USD',
                'coil_price' => 1.9200,
                'coil_price_idr' => round(1.9200 * $rate2027, 2),
                'sheet_price' => 2.0200,
                'sheet_price_idr' => round(2.0200 * $rate2027, 2),
                'gap_steel_usd' => 100.0000,
                'gap_steel_kg_usd' => 0.1000,
                'exchange_rate' => $rate2027,
                'status' => 'Active',
                'notes' => 'Kontrak Pasterner Q1 2027 Supplier PT USCI',
                'created_by' => $admin?->id,
            ],
        ];

        foreach ($records as $item) {
            Fastener::create($item);
        }
    }
}
