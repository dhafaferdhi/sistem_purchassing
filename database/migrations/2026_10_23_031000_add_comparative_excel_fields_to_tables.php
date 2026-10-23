<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_steels', function (Blueprint $table) {
            $table->decimal('ap_coil_price', 12, 4)->nullable()->after('sheet_price_idr');
            $table->decimal('ap_coil_price_idr', 14, 2)->nullable()->after('ap_coil_price');
            $table->decimal('ap_sheet_price', 12, 4)->nullable()->after('ap_coil_price_idr');
            $table->decimal('ap_sheet_price_idr', 14, 2)->nullable()->after('ap_sheet_price');
        });

        Schema::table('stamping_parts', function (Blueprint $table) {
            $table->string('drawing_reference')->nullable()->after('model_name');
            $table->string('eci_number')->nullable()->after('drawing_reference');
            $table->decimal('tooling_depreciation', 14, 4)->default(0)->after('part_cost');
            $table->json('tooling_details')->nullable()->after('tooling_depreciation');
        });

        Schema::table('fasteners', function (Blueprint $table) {
            $table->string('drawing_reference')->nullable()->after('model_name');
            $table->string('eci_number')->nullable()->after('drawing_reference');
            $table->decimal('old_exchange_rate', 12, 2)->nullable()->after('exchange_rate');
            $table->decimal('exchange_rate_fluctuation', 10, 6)->nullable()->after('old_exchange_rate');
            $table->decimal('tooling_depreciation', 14, 4)->default(0)->after('part_cost');
            $table->json('tooling_details')->nullable()->after('tooling_depreciation');
        });
    }

    public function down(): void
    {
        Schema::table('material_steels', function (Blueprint $table) {
            $table->dropColumn([
                'ap_coil_price',
                'ap_coil_price_idr',
                'ap_sheet_price',
                'ap_sheet_price_idr',
            ]);
        });

        Schema::table('stamping_parts', function (Blueprint $table) {
            $table->dropColumn([
                'drawing_reference',
                'eci_number',
                'tooling_depreciation',
                'tooling_details',
            ]);
        });

        Schema::table('fasteners', function (Blueprint $table) {
            $table->dropColumn([
                'drawing_reference',
                'eci_number',
                'old_exchange_rate',
                'exchange_rate_fluctuation',
                'tooling_depreciation',
                'tooling_details',
            ]);
        });
    }
};
