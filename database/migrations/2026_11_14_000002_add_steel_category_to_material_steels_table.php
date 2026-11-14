<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('material_steels', 'steel_category')) {
            Schema::table('material_steels', function (Blueprint $table) {
                $table->string('steel_category', 10)->default('HRC')->after('steel_spec')->index();
            });

            // Update existing data berdasarkan spesifikasi baja
            DB::table('material_steels')->where(function ($q) {
                $q->where('steel_spec', 'like', 'SPC%')
                    ->orWhere('steel_spec', 'like', 'NSC%');
            })->update(['steel_category' => 'CRC']);

            DB::table('material_steels')->where(function ($q) {
                $q->where('steel_spec', 'like', 'SPH%')
                    ->orWhere('steel_spec', 'like', 'SAPH%');
            })->update(['steel_category' => 'HRC']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('material_steels', 'steel_category')) {
            Schema::table('material_steels', function (Blueprint $table) {
                $table->dropColumn('steel_category');
            });
        }
    }
};
