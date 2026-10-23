<?php

namespace Database\Seeders;

use App\Models\ComparativeQuotation;
use App\Models\Part;
use App\Models\PartBom;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ComparativeQuotationService;
use Illuminate\Database\Seeder;

class ComparativeQuotationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        // 1. Seed Suppliers
        $supTcf = Supplier::firstOrCreate(
            ['code' => 'VND-TCF'],
            [
                'name' => 'PT. TRI CENTRUM FORTUNA',
                'category' => 'Component',
                'is_active' => true,
            ]
        );

        $supPosmi = Supplier::firstOrCreate(
            ['code' => 'VND-POSMI'],
            [
                'name' => 'PT. POSMI STEEL INDONESIA',
                'category' => 'Steel',
                'is_active' => true,
            ]
        );

        $supNitto = Supplier::firstOrCreate(
            ['code' => 'VND-NITTO'],
            [
                'name' => 'PT. NITTO ALAM INDONESIA',
                'category' => 'Component',
                'is_active' => true,
            ]
        );

        $supMeidoh = Supplier::firstOrCreate(
            ['code' => 'VND-MEIDOH'],
            [
                'name' => 'PT. Meidoh Indonesia',
                'category' => 'Component',
                'is_active' => true,
            ]
        );

        // 2. Seed Parts
        $partSubAssy = Part::firstOrCreate(
            ['part_number' => '73012-BZ010'],
            [
                'part_name' => 'BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH',
                'main_category' => 'Steel',
                'sub_category' => 'Assembly',
                'type' => 'Assembly',
                'weight' => 0.6500,
                'unit' => 'pcs',
                'is_active' => true,
            ]
        );

        $partStamping = Part::firstOrCreate(
            ['part_number' => '72388-X7V00-A'],
            [
                'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
                'main_category' => 'Steel',
                'sub_category' => 'Bracket',
                'type' => 'Component',
                'weight' => 0.5890,
                'unit' => 'pcs',
                'is_active' => true,
            ]
        );

        $partBoltWasher = Part::firstOrCreate(
            ['part_number' => '90041-19960'],
            [
                'part_name' => 'BOLT,W/WASHER',
                'main_category' => 'Steel',
                'sub_category' => 'Bolt',
                'type' => 'Component',
                'weight' => 0.0089,
                'unit' => 'pcs',
                'is_active' => true,
            ]
        );

        $partBolt1 = Part::firstOrCreate(
            ['part_number' => '89911-X7095'],
            [
                'part_name' => 'BOLT WELD 89911-X7095',
                'main_category' => 'Steel',
                'sub_category' => 'Bolt',
                'type' => 'Component',
                'weight' => 0.0200,
                'unit' => 'pcs',
                'is_active' => true,
            ]
        );

        $partBolt2 = Part::firstOrCreate(
            ['part_number' => '89911-X1F05'],
            [
                'part_name' => 'BOLT PWR SEAT WELD 89911-X1F05',
                'main_category' => 'Steel',
                'sub_category' => 'Bolt',
                'type' => 'Component',
                'weight' => 0.0350,
                'unit' => 'pcs',
                'is_active' => true,
            ]
        );

        // 3. Seed BOM: 73012-BZ010 -> 72388-X7V00-A + 89911-X7095 + 89911-X1F05
        PartBom::updateOrCreate(
            ['parent_part_id' => $partSubAssy->id, 'component_part_id' => $partStamping->id],
            ['quantity' => 1, 'level' => 1, 'notes' => 'Stamping child from Posmi']
        );
        PartBom::updateOrCreate(
            ['parent_part_id' => $partSubAssy->id, 'component_part_id' => $partBolt1->id],
            ['quantity' => 1, 'level' => 1, 'notes' => 'Bolt 1 from Meidoh/Supply']
        );
        PartBom::updateOrCreate(
            ['parent_part_id' => $partSubAssy->id, 'component_part_id' => $partBolt2->id],
            ['quantity' => 1, 'level' => 1, 'notes' => 'Bolt 2 from Meidoh/Supply']
        );

        // Clear existing comparative quotations
        ComparativeQuotation::query()->delete();

        // 4. Data 1: POSMI - Stamping Part 72388-X7V00-A
        $posmiPeriods = [
            ['label' => "Periode Q2 April-June '22", 'year' => 2022, 'quarter' => 'Q2', 'rate' => 14300, 'steel_rate' => 17617.6, 'unit_price' => 10583.5293631104],
            ['label' => "Periode Q3 July-September  '22", 'year' => 2022, 'quarter' => 'Q3', 'rate' => 14345, 'steel_rate' => 17959.94, 'unit_price' => 10793.244760205758],
            ['label' => "Periode Q4 October-December'22", 'year' => 2022, 'quarter' => 'Q4', 'rate' => 14741, 'steel_rate' => 17851.351, 'unit_price' => 10726.723800764306],
            ['label' => "Periode Q1 Januari- Maret'23", 'year' => 2023, 'quarter' => 'Q1', 'rate' => 15076, 'steel_rate' => 18076.124, 'unit_price' => 10864.418370348098],
            ['label' => "Periode Q2 April '23", 'year' => 2023, 'quarter' => 'Q2', 'rate' => 15500, 'steel_rate' => 19359.5, 'unit_price' => 11650.606684488],
            ['label' => "Periode Q2 June'23", 'year' => 2023, 'quarter' => 'Q2', 'rate' => 15500, 'steel_rate' => 21663.0, 'unit_price' => 13061.716742352],
            ['label' => "Periode Q3 Juli -September '23", 'year' => 2023, 'quarter' => 'Q3', 'rate' => 15086, 'steel_rate' => 21084.0, 'unit_price' => 12707.024871936],
            ['label' => "Periode Q4 december '23", 'year' => 2023, 'quarter' => 'Q4', 'rate' => 14917, 'steel_rate' => 16230.0, 'unit_price' => 9733.49406192],
            ['label' => "Periode Q1 Januari- Maret  '24", 'year' => 2024, 'quarter' => 'Q1', 'rate' => 15433, 'steel_rate' => 16791.0, 'unit_price' => 10077.159242064],
            ['label' => "Jul-Sep'26 Price / Pcs (Rp)", 'year' => 2026, 'quarter' => 'Q3', 'rate' => 15816, 'steel_rate' => 17321.0, 'unit_price' => 10401.834011184],
            ['label' => "Jul-Sep'26 Price / Pcs (Rp)", 'year' => 2026, 'quarter' => 'Q3', 'rate' => 17796, 'steel_rate' => 17177.0, 'unit_price' => 10313.620489008],
            ['label' => "Oct-Dec'26 Price / Pcs (Rp)", 'year' => 2026, 'quarter' => 'Q4', 'rate' => 17796, 'steel_rate' => 17689.0, 'unit_price' => 10627.268567856],
        ];

        foreach ($posmiPeriods as $idx => $p) {
            $blankGross = round(0.5890326 * $p['steel_rate'], 4);
            $scrapVal = -417.0; // 0.278 * -1500
            $matTotal = round($blankGross + $scrapVal, 4);
            $procTotal = 202.5; // Progressive 250T (9 sec * 45)
            $subtotal = round($matTotal + $procTotal, 4);
            $ohSupplied = 22.275; // 11%
            $ohSelf = round(0.04 * $subtotal, 4);
            $partCost = round($subtotal + $ohSupplied + $ohSelf, 4);

            ComparativeQuotation::create([
                'part_id' => $partStamping->id,
                'part_number' => '72388-X7V00-A',
                'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
                'part_type' => 'Stamping',
                'supplier_id' => $supPosmi->id,
                'supplier_name' => $supPosmi->name,
                'buyer_name' => 'PT. FUJI SEAT INDONESIA',
                'model_name' => 'D26A',
                'monthly_volume' => 16500,
                'drawing_reference' => 'Mass Prod. Drwg',
                'eci_number' => 'F1225',
                'period_label' => $p['label'],
                'year' => $p['year'],
                'quarter' => $p['quarter'],
                'sequence' => $idx + 1,
                'new_exchange_rate' => $p['rate'],
                'material_breakdown' => [
                    [
                        'name' => 'SPH440-OD (2.6 x 390 x 148)',
                        'spec' => 'SPH440-OD',
                        'thickness' => 2.6,
                        'qty' => 0.5890326,
                        'unit_price' => $p['steel_rate'],
                        'price' => $blankGross,
                        'valuation' => 'Posmi Steel',
                    ],
                    [
                        'name' => 'SCRAP RECOVERY',
                        'spec' => 'SCRAP',
                        'qty' => 0.278,
                        'unit_price' => -1500,
                        'price' => $scrapVal,
                        'valuation' => 'Posmi Steel',
                    ],
                ],
                'material_total' => $matTotal,
                'process_breakdown' => [
                    [
                        'name' => '1. PROGRESSIVE 250T',
                        'machine' => '250T',
                        'man_hour' => 9,
                        'charge' => 45,
                        'price' => 202.5,
                    ],
                ],
                'process_total' => $procTotal,
                'subtotal' => $subtotal,
                'overhead_supplied_percent' => 11,
                'overhead_supplied_cost' => $ohSupplied,
                'overhead_self_percent' => 4,
                'overhead_self_cost' => $ohSelf,
                'transport_cost' => 0,
                'packing_cost' => 0,
                'part_cost' => $p['unit_price'],
                'tooling_cost' => 0,
                'total_cost' => $p['unit_price'],
                'created_by' => $admin?->id,
            ]);
        }

        // 5. Data 2: TCF - Sub-Assembly Part 73012-BZ010
        foreach ($posmiPeriods as $idx => $p) {
            $hasPaidBolts = ($idx >= 10); // di periode 2026 ke atas baut dibeli sendiri
            $bolt1Price = $hasPaidBolts ? 1238.0 : 0.0;
            $bolt2Price = $hasPaidBolts ? 3000.0 : 0.0;
            $bolt1Val = $hasPaidBolts ? 'Meidoh' : 'SUPPLY';
            $bolt2Val = $hasPaidBolts ? 'Meidoh' : 'SUPPLY';

            $matTotal = round($p['unit_price'] + $bolt1Price + $bolt2Price, 4);
            $procTotal = 4258.0; // 540 + 918 + 2800
            $subtotal = round($matTotal + $procTotal, 4);
            $ohSupplied = 468.38; // 11% of process
            $profit = $hasPaidBolts ? 127.14 : 0.0; // 3%
            $packing = 120.0;
            $partCost = round($subtotal + $ohSupplied + $profit + $packing, 4);

            ComparativeQuotation::create([
                'part_id' => $partSubAssy->id,
                'part_number' => '73012-BZ010',
                'part_name' => 'BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH',
                'part_type' => 'Sub-Assembly',
                'supplier_id' => $supTcf->id,
                'supplier_name' => $supTcf->name,
                'buyer_name' => 'PT. FUJI SEAT INDONESIA',
                'model_name' => 'D26A',
                'monthly_volume' => 16500,
                'drawing_reference' => 'Mass Prod. Drwg (Exchange rate change)',
                'eci_number' => 'F1225',
                'period_label' => $p['label'],
                'year' => $p['year'],
                'quarter' => $p['quarter'],
                'sequence' => $idx + 1,
                'new_exchange_rate' => $p['rate'],
                'material_breakdown' => [
                    [
                        'name' => '72388-X7V00-A BRKT, SEAT BELT ANCHOR LH',
                        'qty' => 1,
                        'unit_price' => $p['unit_price'],
                        'price' => $p['unit_price'],
                        'valuation' => ($idx < 2 ? 'Posmi' : 'TCF'),
                    ],
                    [
                        'name' => '89911 - X7095 BOLT',
                        'qty' => 1,
                        'unit_price' => $bolt1Price,
                        'price' => $bolt1Price,
                        'valuation' => $bolt1Val,
                    ],
                    [
                        'name' => '89911 - X1F05 BOLT PWR SEAT WELD',
                        'qty' => 1,
                        'unit_price' => $bolt2Price,
                        'price' => $bolt2Price,
                        'valuation' => $bolt2Val,
                    ],
                ],
                'material_total' => $matTotal,
                'process_breakdown' => [
                    [
                        'name' => '1. WELD CO BOLT WELD 89911-X7095',
                        'man_hour' => 20,
                        'charge' => 27,
                        'price' => 540.0,
                    ],
                    [
                        'name' => '2. WELD C0 BOLT WELD 89911-X1F05',
                        'man_hour' => 34,
                        'charge' => 27,
                        'price' => 918.0,
                    ],
                    [
                        'name' => '3. PAINTING CED',
                        'man_hour' => 0,
                        'charge' => 0,
                        'price' => 2800.0,
                    ],
                ],
                'process_total' => $procTotal,
                'subtotal' => $subtotal,
                'overhead_supplied_percent' => 11,
                'overhead_supplied_cost' => $ohSupplied,
                'profit_percent' => ($hasPaidBolts ? 3 : 0),
                'profit_cost' => $profit,
                'transport_cost' => 0,
                'packing_cost' => $packing,
                'part_cost' => $partCost,
                'tooling_cost' => 0,
                'tooling_details' => [
                    ['name' => '1. JIG WELDING 1 & 2', 'total_krp' => 25250, 'monthly_vol' => 16500],
                ],
                'total_cost' => $partCost,
                'created_by' => $admin?->id,
            ]);
        }

        // 6. Data 3: NITTO ALAM - Fastener Part 90041-19960
        $nittoPeriods = [
            ['label' => 'Q1-2026', 'year' => 2026, 'quarter' => 'Q1', 'old_rate' => 14555, 'rate' => 14752, 'wire_usd' => 1.66, 'oh_percent' => 15.0, 'part_cost' => 630.084588576],
            ['label' => 'Q2-2026', 'year' => 2026, 'quarter' => 'Q2', 'old_rate' => 16321, 'rate' => 16465, 'wire_usd' => 2.02, 'oh_percent' => 14.3, 'part_cost' => 677.1975106518],
            ['label' => 'Q3-2026', 'year' => 2026, 'quarter' => 'Q3', 'old_rate' => 16465, 'rate' => 16725, 'wire_usd' => 2.02, 'oh_percent' => 14.3, 'part_cost' => 680.248253547],
            ['label' => 'Q4-2026', 'year' => 2026, 'quarter' => 'Q4', 'old_rate' => 16725, 'rate' => 16940, 'wire_usd' => 2.02, 'oh_percent' => 14.3, 'part_cost' => 682.7709832488],
            ['label' => 'Next-2026', 'year' => 2026, 'quarter' => 'Q4', 'old_rate' => 16940, 'rate' => 17796, 'wire_usd' => 2.02, 'oh_percent' => 14.3, 'part_cost' => 692.8149675499],
        ];

        foreach ($nittoPeriods as $idx => $p) {
            $wireIdrKg = round($p['wire_usd'] * $p['rate'], 2);
            $wirePc = round((4.84 / 1000) * $wireIdrKg, 8);
            $matCtrl = round(0.05 * $wirePc, 8);
            $washerPrice = 198.0;
            $matTotal = round($wirePc + $matCtrl + $washerPrice, 4);

            $procTotal = 225.45; // 48 + 76 + 4.45 + 89 + 5 + 3
            $subtotal = round($matTotal + $procTotal, 4);
            $ohCost = round(($p['oh_percent'] / 100) * $subtotal, 4);
            $partCost = round($subtotal + $ohCost, 4);

            ComparativeQuotation::create([
                'part_id' => $partBoltWasher->id,
                'part_number' => '90041-19960',
                'part_name' => 'BOLT,W/WASHER',
                'part_type' => 'Fastener',
                'supplier_id' => $supNitto->id,
                'supplier_name' => $supNitto->name,
                'buyer_name' => 'PT. FUJI SEAT INDONESIA',
                'model_name' => 'D26A',
                'monthly_volume' => 20000,
                'drawing_reference' => 'Official Dwg (supplier selection)',
                'eci_number' => '90041-19960',
                'period_label' => $p['label'],
                'year' => $p['year'],
                'quarter' => $p['quarter'],
                'sequence' => $idx + 1,
                'old_exchange_rate' => $p['old_rate'],
                'new_exchange_rate' => $p['rate'],
                'exchange_fluctuation' => round($p['rate'] / $p['old_rate'], 6),
                'material_breakdown' => [
                    [
                        'name' => 'WIRE OF RIVET [SWCH-45K] (4.84 gr x Rp '.$wireIdrKg.'/kg)',
                        'spec' => 'SWCH-45K',
                        'qty' => 4.84,
                        'unit_price' => $wireIdrKg,
                        'price' => $wirePc,
                    ],
                    [
                        'name' => 'Material Control ( 5 % )',
                        'qty' => 1,
                        'unit_price' => $matCtrl,
                        'price' => $matCtrl,
                    ],
                    [
                        'name' => 'WASHER (4.5 gr)',
                        'qty' => 1,
                        'unit_price' => 198.0,
                        'price' => 198.0,
                    ],
                ],
                'material_total' => $matTotal,
                'process_breakdown' => [
                    ['name' => 'a. Heading', 'price' => 48.0],
                    ['name' => 'b. Rolling', 'price' => 76.0],
                    ['name' => 'c. Washing ((NW+WW) x COST/kg)', 'price' => 4.45],
                    ['name' => 'd. Plating ((NW+WW) x COST/kg)', 'price' => 89.0],
                    ['name' => 'e. Final Inspection', 'price' => 5.0],
                    ['name' => 'f. Packing', 'price' => 3.0],
                ],
                'process_total' => $procTotal,
                'subtotal' => $subtotal,
                'overhead_self_percent' => $p['oh_percent'],
                'overhead_self_cost' => $ohCost,
                'part_cost' => $p['part_cost'],
                'tooling_cost' => 0,
                'total_cost' => $p['part_cost'],
                'created_by' => $admin?->id,
            ]);
        }

        // Hitung ulang semua differences
        ComparativeQuotationService::recalculateAllDifferences('73012-BZ010');
        ComparativeQuotationService::recalculateAllDifferences('72388-X7V00-A');
        ComparativeQuotationService::recalculateAllDifferences('90041-19960');
    }
}
