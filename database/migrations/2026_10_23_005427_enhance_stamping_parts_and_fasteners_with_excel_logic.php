<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stamping_parts', function (Blueprint $table) {
            $table->string('part_number')->nullable()->after('vendor_code')->index();
            $table->string('part_name')->nullable()->after('part_number');
            $table->string('model_name')->nullable()->default('D26A')->after('part_name');
            $table->integer('monthly_volume')->default(16500)->after('model_name');

            // Dimensi & Blanking
            $table->decimal('wide', 12, 2)->nullable()->after('double_thickness');
            $table->decimal('pitch', 12, 2)->nullable()->after('wide');
            $table->decimal('yield_rate', 8, 2)->default(1)->after('pitch');

            // Material & Scrap Calculation
            $table->decimal('gross_material_cost', 16, 2)->default(0)->after('berat');
            $table->decimal('scrap_weight', 12, 4)->default(0)->after('gross_material_cost');
            $table->decimal('scrap_price_per_kg', 16, 2)->default(-1500)->after('scrap_weight');
            $table->decimal('scrap_cost', 16, 2)->default(0)->after('scrap_price_per_kg');
            $table->decimal('material_total', 16, 2)->default(0)->after('scrap_cost');

            // Process Calculation
            $table->string('process_name')->default('1. PROGRESSIVE 250T')->after('material_total');
            $table->decimal('process_man_hour', 10, 2)->default(9)->after('process_name');
            $table->decimal('process_charge', 10, 2)->default(45)->after('process_man_hour');
            $table->decimal('process_total', 16, 2)->default(202.50)->after('process_charge');

            // Subtotal, O/H, Profit, & Final Part Cost
            $table->decimal('subtotal', 16, 2)->default(0)->after('process_total');
            $table->decimal('overhead_supplied_percent', 8, 2)->default(11)->after('subtotal');
            $table->decimal('overhead_supplied_cost', 16, 2)->default(0)->after('overhead_supplied_percent');
            $table->decimal('overhead_self_percent', 8, 2)->default(4)->after('overhead_supplied_cost');
            $table->decimal('overhead_self_cost', 16, 2)->default(0)->after('overhead_self_percent');
            $table->decimal('part_cost', 16, 4)->default(0)->after('overhead_self_cost');
        });

        Schema::table('fasteners', function (Blueprint $table) {
            $table->string('part_number')->nullable()->after('vendor_code')->index();
            $table->string('part_name')->nullable()->after('part_number');
            $table->string('model_name')->nullable()->default('D26A')->after('part_name');
            $table->integer('monthly_volume')->default(20000)->after('model_name');

            // Wire Breakdown
            $table->string('wire_spec')->default('WIRE OF RIVET [SWCH-45K]')->after('steel_spec');
            $table->decimal('nett_weight_gr', 10, 4)->default(4.4)->after('wire_spec');
            $table->decimal('gross_weight_gr', 10, 4)->default(4.84)->after('nett_weight_gr');
            $table->decimal('wire_price_usd_kg', 12, 4)->default(1.66)->after('gross_weight_gr');
            $table->decimal('wire_price_idr_kg', 16, 2)->default(0)->after('wire_price_usd_kg');
            $table->decimal('wire_price_pc', 16, 4)->default(0)->after('wire_price_idr_kg');
            $table->decimal('material_control_percent', 8, 2)->default(5)->after('wire_price_pc');
            $table->decimal('material_control_cost', 16, 4)->default(0)->after('material_control_percent');

            // Washer Breakdown
            $table->decimal('washer_price', 16, 2)->default(198)->after('material_control_cost');
            $table->decimal('washer_weight_gr', 10, 4)->default(4.5)->after('washer_price');
            $table->decimal('material_total', 16, 4)->default(0)->after('washer_weight_gr');

            // Fastener Process Breakdown
            $table->decimal('heading_cost', 16, 2)->default(48)->after('material_total');
            $table->decimal('rolling_cost', 16, 2)->default(76)->after('heading_cost');
            $table->decimal('washing_cost_kg', 16, 2)->default(500)->after('rolling_cost');
            $table->decimal('washing_cost', 16, 4)->default(4.45)->after('washing_cost_kg');
            $table->decimal('plating_cost_kg', 16, 2)->default(10000)->after('washing_cost');
            $table->decimal('plating_cost', 16, 4)->default(89)->after('plating_cost_kg');
            $table->decimal('inspection_cost', 16, 2)->default(5)->after('plating_cost');
            $table->decimal('packing_cost', 16, 2)->default(3)->after('inspection_cost');
            $table->decimal('process_total', 16, 4)->default(225.45)->after('packing_cost');

            // Subtotal, O/H, Profit, & Final Part Cost
            $table->decimal('subtotal', 16, 4)->default(0)->after('process_total');
            $table->decimal('overhead_self_percent', 8, 2)->default(14.3)->after('subtotal');
            $table->decimal('overhead_self_cost', 16, 4)->default(0)->after('overhead_self_percent');
            $table->decimal('part_cost', 16, 4)->default(0)->after('overhead_self_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stamping_parts', function (Blueprint $table) {
            $table->dropColumn([
                'part_number',
                'part_name',
                'model_name',
                'monthly_volume',
                'wide',
                'pitch',
                'yield_rate',
                'gross_material_cost',
                'scrap_weight',
                'scrap_price_per_kg',
                'scrap_cost',
                'material_total',
                'process_name',
                'process_man_hour',
                'process_charge',
                'process_total',
                'subtotal',
                'overhead_supplied_percent',
                'overhead_supplied_cost',
                'overhead_self_percent',
                'overhead_self_cost',
                'part_cost',
            ]);
        });

        Schema::table('fasteners', function (Blueprint $table) {
            $table->dropColumn([
                'part_number',
                'part_name',
                'model_name',
                'monthly_volume',
                'wire_spec',
                'nett_weight_gr',
                'gross_weight_gr',
                'wire_price_usd_kg',
                'wire_price_idr_kg',
                'wire_price_pc',
                'material_control_percent',
                'material_control_cost',
                'washer_price',
                'washer_weight_gr',
                'material_total',
                'heading_cost',
                'rolling_cost',
                'washing_cost_kg',
                'washing_cost',
                'plating_cost_kg',
                'plating_cost',
                'inspection_cost',
                'packing_cost',
                'process_total',
                'subtotal',
                'overhead_self_percent',
                'overhead_self_cost',
                'part_cost',
            ]);
        });
    }
};
