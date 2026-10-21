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
        Schema::table('material_steels', function (Blueprint $table) {
            $table->string('size')->nullable()->after('steel_spec');
            $table->decimal('coil_price', 16, 4)->nullable()->default(0)->after('steel_price_idr');
            $table->decimal('coil_price_idr', 16, 2)->nullable()->default(0)->after('coil_price');
            $table->decimal('sheet_price', 16, 4)->nullable()->default(0)->after('coil_price_idr');
            $table->decimal('sheet_price_idr', 16, 2)->nullable()->default(0)->after('sheet_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_steels', function (Blueprint $table) {
            $table->dropColumn([
                'size',
                'coil_price',
                'coil_price_idr',
                'sheet_price',
                'sheet_price_idr',
            ]);
        });
    }
};
