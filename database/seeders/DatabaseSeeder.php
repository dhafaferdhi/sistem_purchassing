<?php

namespace Database\Seeders;

use App\Models\GlobalVariable;
use App\Models\Material;
use App\Models\Process;
use App\Models\Quotation;
use App\Models\QuotationProcess;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Users (Roles: Admin, Estimator, Approver)
        $admin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin Purchasing',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@purchasing.test'],
            [
                'name' => 'Admin Purchasing',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        $estimator = User::firstOrCreate(
            ['email' => 'estimator@purchasing.test'],
            [
                'name' => 'Estimator Staff',
                'password' => Hash::make('password'),
                'role' => 'estimator',
            ]
        );

        $approver = User::firstOrCreate(
            ['email' => 'approver@purchasing.test'],
            [
                'name' => 'Management Approver',
                'password' => Hash::make('password'),
                'role' => 'approver',
            ]
        );

        // 2. Seed Global Variables
        $globalVars = [
            [
                'key' => 'exchange_rate',
                'name' => 'Exchange Rate (USD to IDR)',
                'value' => 20000.0000,
                'updated_by' => $admin->id,
            ],
            [
                'key' => 'fluktuasi_jfe',
                'name' => 'Fluktuasi JFE',
                'value' => -0.0200,
                'updated_by' => $admin->id,
            ],
            [
                'key' => 'default_profit_percentage',
                'name' => 'Default Profit Margin (%)',
                'value' => 10.0000,
                'updated_by' => $admin->id,
            ],
        ];

        foreach ($globalVars as $var) {
            GlobalVariable::updateOrCreate(['key' => $var['key']], $var);
        }

        // 3. Seed Materials (from Book5.xlsx)
        $materials = [
            [
                'spec' => 'SWMB',
                'category' => 'Fastener',
                'basic_rate' => 2.1350,
                'is_active' => true,
            ],
            [
                'spec' => 'SPH270-OD',
                'category' => 'Presspart',
                'basic_rate' => 1.9900,
                'is_active' => true,
            ],
            [
                'spec' => 'SPHC-P',
                'category' => 'Presspart',
                'basic_rate' => 1.8500,
                'is_active' => true,
            ],
            [
                'spec' => 'S10C',
                'category' => 'Fastener',
                'basic_rate' => 2.0500,
                'is_active' => true,
            ],
        ];

        foreach ($materials as $mat) {
            Material::updateOrCreate(['spec' => $mat['spec']], $mat);
        }

        // 4. Seed Processes (from Book5.xlsx)
        $processes = [
            // Fastener processes
            ['name' => 'Heading', 'category' => 'Fastener', 'default_cost' => 100.0000, 'is_active' => true],
            ['name' => 'Forging', 'category' => 'Fastener', 'default_cost' => 100.0000, 'is_active' => true],
            ['name' => 'Heat treatment', 'category' => 'Fastener', 'default_cost' => 200.0000, 'is_active' => true],
            ['name' => 'Inspection', 'category' => 'Fastener', 'default_cost' => 150.0000, 'is_active' => true],

            // Presspart processes
            ['name' => 'Progressive', 'category' => 'Presspart', 'default_cost' => 100.0000, 'is_active' => true],
            ['name' => 'Bending 1', 'category' => 'Presspart', 'default_cost' => 100.0000, 'is_active' => true],
            ['name' => 'Bending 2', 'category' => 'Presspart', 'default_cost' => 100.0000, 'is_active' => true],
            ['name' => 'Forming', 'category' => 'Presspart', 'default_cost' => 100.0000, 'is_active' => true],
            ['name' => 'Ristrake', 'category' => 'Presspart', 'default_cost' => 100.0000, 'is_active' => true],
            ['name' => 'Painting', 'category' => 'Presspart', 'default_cost' => 100.0000, 'is_active' => true],
            ['name' => 'Final Check', 'category' => 'Presspart', 'default_cost' => 100.0000, 'is_active' => true],
        ];

        foreach ($processes as $proc) {
            Process::updateOrCreate(['name' => $proc['name']], $proc);
        }

        // 5. Seed Sample Quotations (Fastener & Presspart dari Book5.xlsx)
        $swmb = Material::where('spec', 'SWMB')->first();
        if ($swmb) {
            $quoFastener = Quotation::firstOrCreate(
                ['document_number' => 'QUO-202610-0001'],
                [
                    'user_id' => $estimator->id,
                    'material_id' => $swmb->id,
                    'category' => 'Fastener',
                    'diameter' => '6',
                    'weight' => 0.05,
                    'snapshot_basic_rate' => 2.1350,
                    'snapshot_exchange_rate' => 20000.0,
                    'snapshot_fluktuasi' => 0.0,
                    'price_idr' => 2135.00,
                    'total_process_cost' => 550.00,
                    'profit_percentage' => 10.0,
                    'profit' => 268.50,
                    'transport' => 100.0,
                    'packing' => 50.0,
                    'total_cost' => 3103.50,
                    'status' => 'approved',
                    'approved_by' => $approver->id,
                    'approved_at' => now(),
                    'notes' => 'Sample Fastener dari dataset Book5.xlsx',
                ]
            );

            // Fastener processes
            $fastenerProcesses = ['Heading', 'Forging', 'Heat treatment', 'Inspection'];
            foreach ($fastenerProcesses as $pName) {
                $p = Process::where('name', $pName)->first();
                if ($p) {
                    QuotationProcess::firstOrCreate(
                        ['quotation_id' => $quoFastener->id, 'process_id' => $p->id],
                        ['process_name' => $p->name, 'applied_cost' => $p->default_cost]
                    );
                }
            }
        }

        $sph = Material::where('spec', 'SPH270-OD')->first();
        if ($sph) {
            $quoPresspart = Quotation::firstOrCreate(
                ['document_number' => 'QUO-202610-0002'],
                [
                    'user_id' => $estimator->id,
                    'material_id' => $sph->id,
                    'category' => 'Presspart',
                    'thickness' => '2',
                    'size_material' => '200X1219',
                    'weight' => 0.15,
                    'snapshot_basic_rate' => 1.9900,
                    'snapshot_exchange_rate' => 20000.0,
                    'snapshot_fluktuasi' => -0.02,
                    'price_idr' => 6030.00,
                    'total_process_cost' => 700.00,
                    'profit_percentage' => 10.0,
                    'profit' => 673.00,
                    'transport' => 100.0,
                    'packing' => 50.0,
                    'total_cost' => 7553.00,
                    'status' => 'submitted',
                    'notes' => 'Sample Presspart dari dataset Book5.xlsx',
                ]
            );

            // Presspart processes
            $presspartProcesses = ['Progressive', 'Bending 1', 'Bending 2', 'Forming', 'Ristrake', 'Painting', 'Final Check'];
            foreach ($presspartProcesses as $pName) {
                $p = Process::where('name', $pName)->first();
                if ($p) {
                    QuotationProcess::firstOrCreate(
                        ['quotation_id' => $quoPresspart->id, 'process_id' => $p->id],
                        ['process_name' => $p->name, 'applied_cost' => $p->default_cost]
                    );
                }
            }
        }

        // Call Advanced Enterprise Seeders
        $this->call([
            EnterprisePurchasingSeeder::class,
            ItemMasterRoutingSeeder::class,
            ToolingAndPurchaseOrderSeeder::class,
            PriceTrendAndGoodsReceiptSeeder::class,
            MaterialSteelSeeder::class,
            StampingPartSeeder::class,
            FastenerSeeder::class,
            PudRequestSeeder::class,
        ]);
    }
}
