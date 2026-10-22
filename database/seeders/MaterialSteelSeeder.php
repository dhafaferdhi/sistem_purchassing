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

        // 1. Pastikan Currency tersedia
        $curr2026Q1 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2026, 'quarter' => 'Q1'],
            ['name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 16000.0, 'month' => 1, 'last_updated_at' => now()]
        );

        $curr2026Q4 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2026, 'quarter' => 'Q4'],
            ['name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 17796.0, 'month' => 10, 'last_updated_at' => now()]
        );
        $curr2026Q4->update(['exchange_rate_to_idr' => 17796.0]);

        $curr2027Q1 = Currency::firstOrCreate(
            ['code' => 'USD', 'year' => 2027, 'quarter' => 'Q1'],
            ['name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_idr' => 18000.0, 'month' => 1, 'last_updated_at' => now()]
        );

        $rate2026Q4 = 17796.0;
        $rate2026Q1 = 16000.0;

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

        // Master item baja aktual sesuai spreadsheet purchasing
        $steelItems = [
            // SPH270C-OD (Hot Rolled 270)
            ['spec' => 'SPH270C-OD', 'size' => '1.6 x 1219 x C', 'thickness' => '1.6', 'coil' => 0.9740, 'sheet' => 0.9840],
            ['spec' => 'SPH270C-OD', 'size' => '1.8 x 1219 x C', 'thickness' => '1.8', 'coil' => 0.9660, 'sheet' => 0.9760],
            ['spec' => 'SPH270C-OD', 'size' => '2.0 x 1219 x C', 'thickness' => '2.0', 'coil' => 0.9600, 'sheet' => 0.9700],
            ['spec' => 'SPH270C-OD', 'size' => '2.3 x 1219 x C', 'thickness' => '2.3', 'coil' => 0.9600, 'sheet' => 0.9700],
            ['spec' => 'SPH270C-OD', 'size' => '2.6 x 1219 x C', 'thickness' => '2.6', 'coil' => 0.9590, 'sheet' => 0.9690],
            ['spec' => 'SPH270C-OD', 'size' => '3.2 x 1219 x C', 'thickness' => '3.2', 'coil' => 0.9590, 'sheet' => 0.9690],
            ['spec' => 'SPH270C-OD', 'size' => '4.0 x 1219 x C', 'thickness' => '4.0', 'coil' => 0.9590, 'sheet' => 0.9690],
            ['spec' => 'SPH270C-OD', 'size' => '5.0 x 1219 x C', 'thickness' => '5.0', 'coil' => 0.9550, 'sheet' => 0.9700],

            // SPH440-OD (Hot Rolled 440)
            ['spec' => 'SPH440-OD', 'size' => '1.6 x 1219 x C', 'thickness' => '1.6', 'coil' => 1.0090, 'sheet' => 1.0190],
            ['spec' => 'SPH440-OD', 'size' => '1.8 x 1219 x C', 'thickness' => '1.8', 'coil' => 1.0010, 'sheet' => 1.0110],
            ['spec' => 'SPH440-OD', 'size' => '2.0 x 1219 x C', 'thickness' => '2.0', 'coil' => 0.9950, 'sheet' => 1.0050],
            ['spec' => 'SPH440-OD', 'size' => '2.3 x 1219 x C', 'thickness' => '2.3', 'coil' => 0.9950, 'sheet' => 1.0050],
            ['spec' => 'SPH440-OD', 'size' => '2.6 x 1219 x C', 'thickness' => '2.6', 'coil' => 0.9940, 'sheet' => 1.0040],
            ['spec' => 'SPH440-OD', 'size' => '3.2 x 1219 x C', 'thickness' => '3.2', 'coil' => 0.9940, 'sheet' => 1.0040],
            ['spec' => 'SPH440-OD', 'size' => '4.0 x 1219 x C', 'thickness' => '4.0', 'coil' => 0.9940, 'sheet' => 1.0040],

            // SPH590-OD (Hot Rolled 590)
            ['spec' => 'SPH590-OD', 'size' => '1.6 x 1219 x C', 'thickness' => '1.6', 'coil' => 1.0390, 'sheet' => 1.0490],
            ['spec' => 'SPH590-OD', 'size' => '1.8 x 1219 x C', 'thickness' => '1.8', 'coil' => 1.0310, 'sheet' => 1.0410],
            ['spec' => 'SPH590-OD', 'size' => '2.0 x 1219 x C', 'thickness' => '2.0', 'coil' => 1.0250, 'sheet' => 1.0350],
            ['spec' => 'SPH590-OD', 'size' => '2.3 x 1219 x C', 'thickness' => '2.3', 'coil' => 1.0250, 'sheet' => 1.0350],
            ['spec' => 'SPH590-OD', 'size' => '2.6 x 1219 x C', 'thickness' => '2.6', 'coil' => 1.0240, 'sheet' => 1.0340],
            ['spec' => 'SPH590-OD', 'size' => '3.2 x 1219 x C', 'thickness' => '3.2', 'coil' => 1.0240, 'sheet' => 1.0340],
            ['spec' => 'SPH590-OD', 'size' => '4.0 x 1219 x C', 'thickness' => '4.0', 'coil' => 1.0240, 'sheet' => 1.0340],

            // SPC270C (Cold Rolled 270)
            ['spec' => 'SPC270C', 'size' => '0.5 x 1251 x C', 'thickness' => '0.5', 'coil' => 1.0240, 'sheet' => 1.0340],
            ['spec' => 'SPC270C', 'size' => '0.6 x 1251 x C', 'thickness' => '0.6', 'coil' => 1.0100, 'sheet' => 1.0200],
            ['spec' => 'SPC270C', 'size' => '0.8 x 1251 x C', 'thickness' => '0.8', 'coil' => 0.9930, 'sheet' => 1.0030],
            ['spec' => 'SPC270C', 'size' => '1.0 x 1251 x C', 'thickness' => '1.0', 'coil' => 0.9790, 'sheet' => 0.9890],
            ['spec' => 'SPC270C', 'size' => '1.2 x 1251 x C', 'thickness' => '1.2', 'coil' => 0.9790, 'sheet' => 0.9890],
            ['spec' => 'SPC270C', 'size' => '1.4 x 1251 x C', 'thickness' => '1.4', 'coil' => 0.9790, 'sheet' => 0.9890],

            // SPC440 (Cold Rolled 440)
            ['spec' => 'SPC440', 'size' => '0.6 x 1251 x C', 'thickness' => '0.6', 'coil' => 1.0550, 'sheet' => 1.0650],
            ['spec' => 'SPC440', 'size' => '0.8 x 1251 x C', 'thickness' => '0.8', 'coil' => 1.0380, 'sheet' => 1.0480],
            ['spec' => 'SPC440', 'size' => '1.0 x 1251 x C', 'thickness' => '1.0', 'coil' => 1.0240, 'sheet' => 1.0340],
            ['spec' => 'SPC440', 'size' => '1.2 x 1251 x C', 'thickness' => '1.2', 'coil' => 1.0240, 'sheet' => 1.0340],
            ['spec' => 'SPC440', 'size' => '1.4 x 1251 x C', 'thickness' => '1.4', 'coil' => 1.0240, 'sheet' => 1.0340],

            // SPC590 (Cold Rolled 590)
            ['spec' => 'SPC590', 'size' => '0.6 x 1251 x C', 'thickness' => '0.6', 'coil' => 1.0950, 'sheet' => 1.1050],
            ['spec' => 'SPC590', 'size' => '0.8 x 1251 x C', 'thickness' => '0.8', 'coil' => 1.0780, 'sheet' => 1.0880],
            ['spec' => 'SPC590', 'size' => '1.0 x 1251 x C', 'thickness' => '1.0', 'coil' => 1.0640, 'sheet' => 1.0740],
            ['spec' => 'SPC590', 'size' => '1.2 x 1251 x C', 'thickness' => '1.2', 'coil' => 1.0640, 'sheet' => 1.0740],
            ['spec' => 'SPC590', 'size' => '1.4 x 1251 x C', 'thickness' => '1.4', 'coil' => 1.0640, 'sheet' => 1.0740],
            ['spec' => 'SPC590', 'size' => '1.6 x 1251 x C', 'thickness' => '1.6', 'coil' => 1.0640, 'sheet' => 1.0740],
            ['spec' => 'SPC590', 'size' => '1.8 x 1251 x C', 'thickness' => '1.8', 'coil' => 1.0640, 'sheet' => 1.0740],
        ];

        // SEED Q4 2026 (Ada Kenaikan/Gap MT: -20 USD/MT, Kurs: 17796)
        foreach ($steelItems as $item) {
            $coilIdr = round($item['coil'] * $rate2026Q4, 2);
            $sheetIdr = round($item['sheet'] * $rate2026Q4, 2);

            MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q4',
                'supplier_id' => $supHssi->id,
                'vendor_code' => $supHssi->code,
                'supplier_name' => $supHssi->name,
                'steel_spec' => $item['spec'],
                'size' => $item['size'],
                'double_thickness' => $item['thickness'],
                'currency_id' => $curr2026Q4->id,
                'currency_code' => 'USD',
                'coil_price' => $item['coil'],
                'coil_price_idr' => $coilIdr,
                'sheet_price' => $item['sheet'],
                'sheet_price_idr' => $sheetIdr,
                'gap_steel_usd' => -20.0000,
                'gap_steel_kg_usd' => -0.0200,
                'gap_steel_idr' => round(-20.0000 * $rate2026Q4, 2),
                'steel_price' => $item['coil'],
                'exchange_rate' => $rate2026Q4,
                'steel_price_idr' => $coilIdr,
                'fluktuasi_jfe' => -0.0200,
                'status' => 'Active',
                'notes' => 'Q4-2026 A.R (HR -20$/MT CR -20$/MT) PT HSSI',
                'created_by' => $admin?->id,
            ]);
        }

        // SEED Q1 2026 (Data Awal / Baseline — Tanpa Gap MT / Gap = 0)
        foreach ($steelItems as $item) {
            $baseCoil = round($item['coil'] + 0.0200, 4); // Sebelum diskon -20$/MT di Q4
            $baseSheet = round($item['sheet'] + 0.0200, 4);
            $coilIdr = round($baseCoil * $rate2026Q1, 2);
            $sheetIdr = round($baseSheet * $rate2026Q1, 2);

            MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q1',
                'supplier_id' => $supHssi->id,
                'vendor_code' => $supHssi->code,
                'supplier_name' => $supHssi->name,
                'steel_spec' => $item['spec'],
                'size' => $item['size'],
                'double_thickness' => $item['thickness'],
                'currency_id' => $curr2026Q1->id,
                'currency_code' => 'USD',
                'coil_price' => $baseCoil,
                'coil_price_idr' => $coilIdr,
                'sheet_price' => $baseSheet,
                'sheet_price_idr' => $sheetIdr,
                'gap_steel_usd' => 0,
                'gap_steel_kg_usd' => 0,
                'gap_steel_idr' => 0,
                'steel_price' => $baseCoil,
                'exchange_rate' => $rate2026Q1,
                'steel_price_idr' => $coilIdr,
                'fluktuasi_jfe' => 0,
                'status' => 'Active',
                'notes' => 'Q1-2026 Baseline Patokan Awal PT HSSI',
                'created_by' => $admin?->id,
            ]);
        }
    }
}
