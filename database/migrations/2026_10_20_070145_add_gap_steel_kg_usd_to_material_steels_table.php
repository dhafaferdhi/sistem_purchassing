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
            $table->decimal('gap_steel_kg_usd', 16, 4)->nullable()->default(0)->after('gap_steel_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_steels', function (Blueprint $table) {
            $table->dropColumn('gap_steel_kg_usd');
        });
    }
};
