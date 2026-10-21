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
            $table->string('double_thickness')->nullable()->after('steel_spec');
            $table->decimal('gap_steel_usd', 16, 4)->nullable()->default(0)->after('sheet_price_idr');
            $table->decimal('gap_steel_idr', 16, 2)->nullable()->default(0)->after('gap_steel_usd');
            $table->decimal('steel_price', 16, 4)->nullable()->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_steels', function (Blueprint $table) {
            $table->dropColumn([
                'double_thickness',
                'gap_steel_usd',
                'gap_steel_idr',
            ]);
        });
    }
};
