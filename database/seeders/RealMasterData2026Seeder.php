<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\SubAssemblyPart;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class RealMasterData2026Seeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        // 1. Currencies 2026 Q1, Q2, Q3, and Q4
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

        $currQ3 = Currency::updateOrCreate(
            ['code' => 'USD', 'year' => 2026, 'quarter' => 'Q3'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate_to_idr' => 16940.0,
                'month' => 7,
                'last_updated_at' => now(),
            ]
        );

        $currQ4 = Currency::updateOrCreate(
            ['code' => 'USD', 'year' => 2026, 'quarter' => 'Q4'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate_to_idr' => 17796.0,
                'month' => 10,
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
                'contact_person' => 'Sales Dept PT USCI',
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

        $supTcf = Supplier::firstOrCreate(
            ['code' => 'VND-TCF'],
            [
                'name' => 'PT. TRI CENTRUM FORTUNA',
                'category' => 'Component',
                'contact_person' => 'Sales Dept PT TCF',
                'is_active' => true,
            ]
        );

        // 3. Material Steel (Daftar Lengkap 43 Varian Spec & Ketebalan Real dari Excel)
        $steelVariants = [
            // SPH270C-OD (Hot Rolled 270)
            ['spec' => 'SPH270C-OD', 'thickness' => '1.6', 'size' => '1.6 x 1219 x C', 'q4_coil' => 0.9740, 'q4_sheet' => 0.9840, 'q1_coil' => 1.1500, 'q1_sheet' => 1.1750, 'q2_coil' => 1.1630, 'q2_sheet' => 1.1880],
            ['spec' => 'SPH270C-OD', 'thickness' => '1.8', 'size' => '1.8 x 1219 x C', 'q4_coil' => 0.9660, 'q4_sheet' => 0.9760, 'q1_coil' => 1.1440, 'q1_sheet' => 1.1690, 'q2_coil' => 1.1570, 'q2_sheet' => 1.1820],
            ['spec' => 'SPH270C-OD', 'thickness' => '2.0', 'size' => '2.0 x 1219 x C', 'q4_coil' => 0.9600, 'q4_sheet' => 0.9700, 'q1_coil' => 1.1400, 'q1_sheet' => 1.1650, 'q2_coil' => 1.1530, 'q2_sheet' => 1.1780],
            ['spec' => 'SPH270C-OD', 'thickness' => '2.3', 'size' => '2.3 x 1219 x C', 'q4_coil' => 0.9600, 'q4_sheet' => 0.9700, 'q1_coil' => 1.1400, 'q1_sheet' => 1.1650, 'q2_coil' => 1.1530, 'q2_sheet' => 1.1780],
            ['spec' => 'SPH270C-OD', 'thickness' => '2.6', 'size' => '2.6 x 1219 x C', 'q4_coil' => 0.9590, 'q4_sheet' => 0.9690, 'q1_coil' => 1.1390, 'q1_sheet' => 1.1640, 'q2_coil' => 1.1520, 'q2_sheet' => 1.1770],
            ['spec' => 'SPH270C-OD', 'thickness' => '3.2', 'size' => '3.2 x 1219 x C', 'q4_coil' => 0.9590, 'q4_sheet' => 0.9690, 'q1_coil' => 1.1390, 'q1_sheet' => 1.1640, 'q2_coil' => 1.1520, 'q2_sheet' => 1.1770],
            ['spec' => 'SPH270C-OD', 'thickness' => '4.0', 'size' => '4.0 x 1219 x C', 'q4_coil' => 0.9590, 'q4_sheet' => 0.9690, 'q1_coil' => 1.1390, 'q1_sheet' => 1.1640, 'q2_coil' => 1.1520, 'q2_sheet' => 1.1770],
            ['spec' => 'SPH270C-OD', 'thickness' => '5.0', 'size' => '5.0 x 1219 x C', 'q4_coil' => 0.9550, 'q4_sheet' => 0.9700, 'q1_coil' => 1.1350, 'q1_sheet' => 1.1650, 'q2_coil' => 1.1480, 'q2_sheet' => 1.1780],

            // SPH440-OD (Hot Rolled 440)
            ['spec' => 'SPH440-OD', 'thickness' => '1.6', 'size' => '1.6 x 1219 x C', 'q4_coil' => 1.0090, 'q4_sheet' => 1.0190, 'q1_coil' => 1.2000, 'q1_sheet' => 1.2250, 'q2_coil' => 1.2130, 'q2_sheet' => 1.2380],
            ['spec' => 'SPH440-OD', 'thickness' => '1.8', 'size' => '1.8 x 1219 x C', 'q4_coil' => 1.0010, 'q4_sheet' => 1.0110, 'q1_coil' => 1.1920, 'q1_sheet' => 1.2170, 'q2_coil' => 1.2050, 'q2_sheet' => 1.2300],
            ['spec' => 'SPH440-OD', 'thickness' => '2.0', 'size' => '2.0 x 1219 x C', 'q4_coil' => 0.9950, 'q4_sheet' => 1.0050, 'q1_coil' => 1.1860, 'q1_sheet' => 1.2110, 'q2_coil' => 1.1990, 'q2_sheet' => 1.2240],
            ['spec' => 'SPH440-OD', 'thickness' => '2.3', 'size' => '2.3 x 1219 x C', 'q4_coil' => 0.9950, 'q4_sheet' => 1.0050, 'q1_coil' => 1.1860, 'q1_sheet' => 1.2110, 'q2_coil' => 1.1990, 'q2_sheet' => 1.2240],
            ['spec' => 'SPH440-OD', 'thickness' => '2.6', 'size' => '2.6 x 1219 x C', 'q4_coil' => 0.9940, 'q4_sheet' => 1.0040, 'q1_coil' => 1.1850, 'q1_sheet' => 1.2100, 'q2_coil' => 1.1980, 'q2_sheet' => 1.2230],
            ['spec' => 'SPH440-OD', 'thickness' => '3.2', 'size' => '3.2 x 1219 x C', 'q4_coil' => 0.9940, 'q4_sheet' => 1.0040, 'q1_coil' => 1.1850, 'q1_sheet' => 1.2100, 'q2_coil' => 1.1980, 'q2_sheet' => 1.2230],
            ['spec' => 'SPH440-OD', 'thickness' => '4.0', 'size' => '4.0 x 1219 x C', 'q4_coil' => 0.9940, 'q4_sheet' => 1.0040, 'q1_coil' => 1.1850, 'q1_sheet' => 1.2100, 'q2_coil' => 1.1980, 'q2_sheet' => 1.2230],

            // SPH590-OD (Hot Rolled 590)
            ['spec' => 'SPH590-OD', 'thickness' => '1.6', 'size' => '1.6 x 1219 x C', 'q4_coil' => 1.0390, 'q4_sheet' => 1.0490, 'q1_coil' => 1.2300, 'q1_sheet' => 1.2550, 'q2_coil' => 1.2430, 'q2_sheet' => 1.2680],
            ['spec' => 'SPH590-OD', 'thickness' => '1.8', 'size' => '1.8 x 1219 x C', 'q4_coil' => 1.0310, 'q4_sheet' => 1.0410, 'q1_coil' => 1.2220, 'q1_sheet' => 1.2470, 'q2_coil' => 1.2350, 'q2_sheet' => 1.2600],
            ['spec' => 'SPH590-OD', 'thickness' => '2.0', 'size' => '2.0 x 1219 x C', 'q4_coil' => 1.0250, 'q4_sheet' => 1.0350, 'q1_coil' => 1.2160, 'q1_sheet' => 1.2410, 'q2_coil' => 1.2290, 'q2_sheet' => 1.2540],
            ['spec' => 'SPH590-OD', 'thickness' => '2.3', 'size' => '2.3 x 1219 x C', 'q4_coil' => 1.0250, 'q4_sheet' => 1.0350, 'q1_coil' => 1.2160, 'q1_sheet' => 1.2410, 'q2_coil' => 1.2290, 'q2_sheet' => 1.2540],
            ['spec' => 'SPH590-OD', 'thickness' => '2.6', 'size' => '2.6 x 1219 x C', 'q4_coil' => 1.0240, 'q4_sheet' => 1.0340, 'q1_coil' => 1.2150, 'q1_sheet' => 1.2400, 'q2_coil' => 1.2280, 'q2_sheet' => 1.2530],
            ['spec' => 'SPH590-OD', 'thickness' => '3.2', 'size' => '3.2 x 1219 x C', 'q4_coil' => 1.0240, 'q4_sheet' => 1.0340, 'q1_coil' => 1.2150, 'q1_sheet' => 1.2400, 'q2_coil' => 1.2280, 'q2_sheet' => 1.2530],
            ['spec' => 'SPH590-OD', 'thickness' => '4.0', 'size' => '4.0 x 1219 x C', 'q4_coil' => 1.0240, 'q4_sheet' => 1.0340, 'q1_coil' => 1.2150, 'q1_sheet' => 1.2400, 'q2_coil' => 1.2280, 'q2_sheet' => 1.2530],

            // SPC270C (Cold Rolled 270)
            ['spec' => 'SPC270C', 'thickness' => '0.5', 'size' => '0.5 x 1251 x C', 'q4_coil' => 1.0240, 'q4_sheet' => 1.0340, 'q1_coil' => 1.2000, 'q1_sheet' => 1.2250, 'q2_coil' => 1.2130, 'q2_sheet' => 1.2380],
            ['spec' => 'SPC270C', 'thickness' => '0.6', 'size' => '0.6 x 1251 x C', 'q4_coil' => 1.0100, 'q4_sheet' => 1.0200, 'q1_coil' => 1.1860, 'q1_sheet' => 1.2110, 'q2_coil' => 1.1990, 'q2_sheet' => 1.2240],
            ['spec' => 'SPC270C', 'thickness' => '0.8', 'size' => '0.8 x 1251 x C', 'q4_coil' => 0.9930, 'q4_sheet' => 1.0030, 'q1_coil' => 1.1690, 'q1_sheet' => 1.1940, 'q2_coil' => 1.1820, 'q2_sheet' => 1.2070],
            ['spec' => 'SPC270C', 'thickness' => '1.0', 'size' => '1.0 x 1251 x C', 'q4_coil' => 0.9790, 'q4_sheet' => 0.9890, 'q1_coil' => 1.1550, 'q1_sheet' => 1.1800, 'q2_coil' => 1.1680, 'q2_sheet' => 1.1930],
            ['spec' => 'SPC270C', 'thickness' => '1.2', 'size' => '1.2 x 1251 x C', 'q4_coil' => 0.9790, 'q4_sheet' => 0.9890, 'q1_coil' => 1.1550, 'q1_sheet' => 1.1800, 'q2_coil' => 1.1680, 'q2_sheet' => 1.1930],
            ['spec' => 'SPC270C', 'thickness' => '1.4', 'size' => '1.4 x 1251 x C', 'q4_coil' => 0.9790, 'q4_sheet' => 0.9890, 'q1_coil' => 1.1550, 'q1_sheet' => 1.1800, 'q2_coil' => 1.1680, 'q2_sheet' => 1.1930],

            // SPC440 (Cold Rolled 440)
            ['spec' => 'SPC440', 'thickness' => '0.6', 'size' => '0.6 x 1251 x C', 'q4_coil' => 1.0550, 'q4_sheet' => 1.0650, 'q1_coil' => 1.2310, 'q1_sheet' => 1.2560, 'q2_coil' => 1.2440, 'q2_sheet' => 1.2690],
            ['spec' => 'SPC440', 'thickness' => '0.8', 'size' => '0.8 x 1251 x C', 'q4_coil' => 1.0380, 'q4_sheet' => 1.0480, 'q1_coil' => 1.2140, 'q1_sheet' => 1.2390, 'q2_coil' => 1.2270, 'q2_sheet' => 1.2520],
            ['spec' => 'SPC440', 'thickness' => '1.0', 'size' => '1.0 x 1251 x C', 'q4_coil' => 1.0240, 'q4_sheet' => 1.0340, 'q1_coil' => 1.2000, 'q1_sheet' => 1.2250, 'q2_coil' => 1.2130, 'q2_sheet' => 1.2380],
            ['spec' => 'SPC440', 'thickness' => '1.2', 'size' => '1.2 x 1251 x C', 'q4_coil' => 1.0240, 'q4_sheet' => 1.0340, 'q1_coil' => 1.2000, 'q1_sheet' => 1.2250, 'q2_coil' => 1.2130, 'q2_sheet' => 1.2380],
            ['spec' => 'SPC440', 'thickness' => '1.4', 'size' => '1.4 x 1251 x C', 'q4_coil' => 1.0240, 'q4_sheet' => 1.0340, 'q1_coil' => 1.2000, 'q1_sheet' => 1.2250, 'q2_coil' => 1.2130, 'q2_sheet' => 1.2380],

            // SPC590 (Cold Rolled 590)
            ['spec' => 'SPC590', 'thickness' => '0.6', 'size' => '0.6 x 1251 x C', 'q4_coil' => 1.0950, 'q4_sheet' => 1.1050, 'q1_coil' => 1.2710, 'q1_sheet' => 1.2960, 'q2_coil' => 1.2840, 'q2_sheet' => 1.3090],
            ['spec' => 'SPC590', 'thickness' => '0.8', 'size' => '0.8 x 1251 x C', 'q4_coil' => 1.0780, 'q4_sheet' => 1.0880, 'q1_coil' => 1.2540, 'q1_sheet' => 1.2790, 'q2_coil' => 1.2670, 'q2_sheet' => 1.2920],
            ['spec' => 'SPC590', 'thickness' => '1.0', 'size' => '1.0 x 1251 x C', 'q4_coil' => 1.0640, 'q4_sheet' => 1.0740, 'q1_coil' => 1.2400, 'q1_sheet' => 1.2650, 'q2_coil' => 1.2530, 'q2_sheet' => 1.2780],
            ['spec' => 'SPC590', 'thickness' => '1.2', 'size' => '1.2 x 1251 x C', 'q4_coil' => 1.0640, 'q4_sheet' => 1.0740, 'q1_coil' => 1.2400, 'q1_sheet' => 1.2650, 'q2_coil' => 1.2530, 'q2_sheet' => 1.2780],
            ['spec' => 'SPC590', 'thickness' => '1.4', 'size' => '1.4 x 1251 x C', 'q4_coil' => 1.0640, 'q4_sheet' => 1.0740, 'q1_coil' => 1.2400, 'q1_sheet' => 1.2650, 'q2_coil' => 1.2530, 'q2_sheet' => 1.2780],
            ['spec' => 'SPC590', 'thickness' => '1.6', 'size' => '1.6 x 1251 x C', 'q4_coil' => 1.0640, 'q4_sheet' => 1.0740, 'q1_coil' => 1.2400, 'q1_sheet' => 1.2650, 'q2_coil' => 1.2530, 'q2_sheet' => 1.2780],
            ['spec' => 'SPC590', 'thickness' => '1.8', 'size' => '1.8 x 1251 x C', 'q4_coil' => 1.0640, 'q4_sheet' => 1.0740, 'q1_coil' => 1.2400, 'q1_sheet' => 1.2650, 'q2_coil' => 1.2530, 'q2_sheet' => 1.2780],

            // Special Specs
            ['spec' => 'SPC440HR', 'thickness' => '0.6', 'size' => '0.6 x 1251 x C', 'q4_coil' => 1.2926, 'q4_sheet' => 1.3176, 'q1_coil' => 1.3050, 'q1_sheet' => 1.3300, 'q2_coil' => 1.3180, 'q2_sheet' => 1.3430],
            ['spec' => 'SPC590DU', 'thickness' => '1.2', 'size' => '1.2 x 1251 x C', 'q4_coil' => 1.2826, 'q4_sheet' => 1.3426, 'q1_coil' => 1.2950, 'q1_sheet' => 1.3550, 'q2_coil' => 1.3080, 'q2_sheet' => 1.3680],
            ['spec' => 'NSC590B',  'thickness' => '1.4', 'size' => '1.4 x 1251 x C', 'q4_coil' => 1.2476, 'q4_sheet' => 1.3076, 'q1_coil' => 1.2600, 'q1_sheet' => 1.3200, 'q2_coil' => 1.2730, 'q2_sheet' => 1.3330],
        ];

        foreach ($steelVariants as $v) {
            $isHr = str_starts_with($v['spec'], 'SPH');
            $q2GapMt = round(($v['q2_coil'] - $v['q1_coil']) * 1000, 4);

            // Seed Q1 2026
            MaterialSteel::updateOrCreate(
                ['year' => 2026, 'quarter' => 'Q1', 'steel_spec' => $v['spec'], 'double_thickness' => $v['thickness']],
                [
                    'supplier_id' => $supHssi->id,
                    'vendor_code' => $supHssi->code,
                    'supplier_name' => $supHssi->name,
                    'size' => $v['size'],
                    'currency_id' => $currQ1->id,
                    'currency_code' => 'USD',
                    'exchange_rate' => 16465.0,
                    'hr_steel_usd' => 0,
                    'cr_steel_usd' => 0,
                    'gap_steel_usd' => 0,
                    'gap_steel_kg_usd' => 0,
                    'coil_price' => $v['q1_coil'],
                    'sheet_price' => $v['q1_sheet'],
                    'coil_price_idr' => round($v['q1_coil'] * 16465.0, 2),
                    'sheet_price_idr' => round($v['q1_sheet'] * 16465.0, 2),
                    'ap_coil_price' => $v['q1_coil'],
                    'ap_sheet_price' => $v['q1_sheet'],
                    'ap_coil_price_idr' => round($v['q1_coil'] * 16465.0, 2),
                    'ap_sheet_price_idr' => round($v['q1_sheet'] * 16465.0, 2),
                    'status' => 'Active',
                    'created_by' => $admin?->id,
                ]
            );

            // Seed Q2 2026
            MaterialSteel::updateOrCreate(
                ['year' => 2026, 'quarter' => 'Q2', 'steel_spec' => $v['spec'], 'double_thickness' => $v['thickness']],
                [
                    'supplier_id' => $supHssi->id,
                    'vendor_code' => $supHssi->code,
                    'supplier_name' => $supHssi->name,
                    'size' => $v['size'],
                    'currency_id' => $currQ2->id,
                    'currency_code' => 'USD',
                    'exchange_rate' => 16725.0,
                    'hr_steel_usd' => $isHr ? $q2GapMt : 0,
                    'cr_steel_usd' => ! $isHr ? $q2GapMt : 0,
                    'gap_steel_usd' => $q2GapMt,
                    'gap_steel_kg_usd' => round($q2GapMt / 1000, 4),
                    'coil_price' => $v['q2_coil'],
                    'sheet_price' => $v['q2_sheet'],
                    'coil_price_idr' => round($v['q2_coil'] * 16725.0, 2),
                    'sheet_price_idr' => round($v['q2_sheet'] * 16725.0, 2),
                    'ap_coil_price' => $v['q2_coil'],
                    'ap_sheet_price' => $v['q2_sheet'],
                    'ap_coil_price_idr' => round($v['q2_coil'] * 16725.0, 2),
                    'ap_sheet_price_idr' => round($v['q2_sheet'] * 16725.0, 2),
                    'status' => 'Active',
                    'created_by' => $admin?->id,
                ]
            );

            // Seed Q4 2026 (Real Excel Prices)
            MaterialSteel::updateOrCreate(
                ['year' => 2026, 'quarter' => 'Q4', 'steel_spec' => $v['spec'], 'double_thickness' => $v['thickness']],
                [
                    'supplier_id' => $supHssi->id,
                    'vendor_code' => $supHssi->code,
                    'supplier_name' => $supHssi->name,
                    'size' => $v['size'],
                    'currency_id' => $currQ4->id,
                    'currency_code' => 'USD',
                    'exchange_rate' => 17796.0,
                    'hr_steel_usd' => $isHr ? -20.0 : 0,
                    'cr_steel_usd' => ! $isHr ? -20.0 : 0,
                    'gap_steel_usd' => -20.0,
                    'gap_steel_kg_usd' => -0.0200,
                    'coil_price' => $v['q4_coil'],
                    'sheet_price' => $v['q4_sheet'],
                    'coil_price_idr' => round($v['q4_coil'] * 17796.0, 2),
                    'sheet_price_idr' => round($v['q4_sheet'] * 17796.0, 2),
                    'ap_coil_price' => $v['q4_coil'],
                    'ap_sheet_price' => $v['q4_sheet'],
                    'ap_coil_price_idr' => round($v['q4_coil'] * 17796.0, 2),
                    'ap_sheet_price_idr' => round($v['q4_sheet'] * 17796.0, 2),
                    'status' => 'Active',
                    'created_by' => $admin?->id,
                ]
            );
        }

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

        // 6. Sub-Assembly Parts (2 data Q1 2026, 2 data Q2 2026)
        // Part 1: 73012-BZ010 (BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH)
        SubAssemblyPart::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q1', 'part_number' => '73012-BZ010'],
            [
                'part_name' => 'BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH',
                'model_name' => 'D26A',
                'monthly_volume' => 16500,
                'drawing_reference' => 'Mass Prod. Drwg (Exchange rate change)',
                'eci_number' => 'F1225',
                'supplier_id' => $supTcf->id,
                'vendor_code' => $supTcf->code,
                'supplier_name' => $supTcf->name,
                'buyer_name' => 'PT. FUJI SEAT INDONESIA',
                'currency_id' => $currQ1->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16465.0,
                'material_breakdown' => [
                    [
                        'part_number' => '72388-X7V00-A',
                        'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
                        'spec' => 'SPH440-OD (2.6 mm)',
                        'quantity' => 1,
                        'qty' => 1,
                        'unit_price' => 10583.53,
                        'price' => 10583.53,
                        'valuation' => 'Posmi',
                    ],
                    [
                        'part_number' => '89911-X7095',
                        'part_name' => 'BOLT',
                        'spec' => 'M8 Bolt',
                        'quantity' => 1,
                        'qty' => 1,
                        'unit_price' => 0,
                        'price' => 0,
                        'valuation' => 'SUPPLY',
                    ],
                    [
                        'part_number' => '89911-X1F05',
                        'part_name' => 'BOLT PWR SEAT WELD',
                        'spec' => 'Weld Bolt',
                        'quantity' => 1,
                        'qty' => 1,
                        'unit_price' => 0,
                        'price' => 0,
                        'valuation' => 'SUPPLY',
                    ],
                ],
                'material_total' => 10583.53,
                'process_breakdown' => [
                    [
                        'name' => '1. WELD CO BOLT WELD 89911-X7095',
                        'process_name' => '1. WELD CO BOLT WELD 89911-X7095',
                        'man_hour' => 20,
                        'charge' => 27,
                        'price' => 540.0,
                    ],
                    [
                        'name' => '2. WELD C0 BOLT WELD 89911-X1F05',
                        'process_name' => '2. WELD C0 BOLT WELD 89911-X1F05',
                        'man_hour' => 34,
                        'charge' => 27,
                        'price' => 918.0,
                    ],
                    [
                        'name' => '3. PAINTING CED',
                        'process_name' => '3. PAINTING CED',
                        'man_hour' => 0,
                        'charge' => 0,
                        'price' => 2800.0,
                    ],
                ],
                'process_total' => 4258.0,
                'subtotal' => 14841.53,
                'overhead_supplied_percent' => 11.0,
                'overhead_supplied_cost' => 468.38,
                'profit_percent' => 0.0,
                'profit_cost' => 0.0,
                'packing_cost' => 120.0,
                'tooling_depreciation' => 0.0,
                'part_cost' => 15429.91,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );

        SubAssemblyPart::updateOrCreate(
            ['year' => 2026, 'quarter' => 'Q2', 'part_number' => '73012-BZ010'],
            [
                'part_name' => 'BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH',
                'model_name' => 'D26A',
                'monthly_volume' => 16500,
                'drawing_reference' => 'Mass Prod. Drwg (Exchange rate change)',
                'eci_number' => 'F1225',
                'supplier_id' => $supTcf->id,
                'vendor_code' => $supTcf->code,
                'supplier_name' => $supTcf->name,
                'buyer_name' => 'PT. FUJI SEAT INDONESIA',
                'currency_id' => $currQ2->id,
                'currency_code' => 'USD',
                'exchange_rate' => 16725.0,
                'material_breakdown' => [
                    [
                        'part_number' => '72388-X7V00-A',
                        'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
                        'spec' => 'SPH440-OD (2.6 mm)',
                        'quantity' => 1,
                        'qty' => 1,
                        'unit_price' => 10793.24,
                        'price' => 10793.24,
                        'valuation' => 'TCF',
                    ],
                    [
                        'part_number' => '89911-X7095',
                        'part_name' => 'BOLT',
                        'spec' => 'M8 Bolt',
                        'quantity' => 1,
                        'qty' => 1,
                        'unit_price' => 0,
                        'price' => 0,
                        'valuation' => 'SUPPLY',
                    ],
                    [
                        'part_number' => '89911-X1F05',
                        'part_name' => 'BOLT PWR SEAT WELD',
                        'spec' => 'Weld Bolt',
                        'quantity' => 1,
                        'qty' => 1,
                        'unit_price' => 0,
                        'price' => 0,
                        'valuation' => 'SUPPLY',
                    ],
                ],
                'material_total' => 10793.24,
                'process_breakdown' => [
                    [
                        'name' => '1. WELD CO BOLT WELD 89911-X7095',
                        'process_name' => '1. WELD CO BOLT WELD 89911-X7095',
                        'man_hour' => 20,
                        'charge' => 27,
                        'price' => 540.0,
                    ],
                    [
                        'name' => '2. WELD C0 BOLT WELD 89911-X1F05',
                        'process_name' => '2. WELD C0 BOLT WELD 89911-X1F05',
                        'man_hour' => 34,
                        'charge' => 27,
                        'price' => 918.0,
                    ],
                    [
                        'name' => '3. PAINTING CED',
                        'process_name' => '3. PAINTING CED',
                        'man_hour' => 0,
                        'charge' => 0,
                        'price' => 2800.0,
                    ],
                ],
                'process_total' => 4258.0,
                'subtotal' => 15051.24,
                'overhead_supplied_percent' => 11.0,
                'overhead_supplied_cost' => 468.38,
                'profit_percent' => 0.0,
                'profit_cost' => 0.0,
                'packing_cost' => 120.0,
                'tooling_depreciation' => 0.0,
                'part_cost' => 15639.62,
                'status' => 'Active',
                'created_by' => $admin?->id,
            ]
        );
    }
}
