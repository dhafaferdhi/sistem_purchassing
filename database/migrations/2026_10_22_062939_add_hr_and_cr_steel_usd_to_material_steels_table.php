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
        Schema::table('material_steels', function (Blueprint $table) {
            $table->decimal('hr_steel_usd', 16, 4)->nullable()->after('sheet_price_idr');
            $table->decimal('cr_steel_usd', 16, 4)->nullable()->after('hr_steel_usd');
        });

        // Migrasi data lama dari gap_steel_usd jika ada
        DB::table('material_steels')
            ->whereNotNull('gap_steel_usd')
            ->where('gap_steel_usd', '!=', 0)
            ->get(['id', 'steel_spec', 'gap_steel_usd'])
            ->each(function ($row) {
                $spec = strtoupper(trim((string) $row->steel_spec));
                $isHr = str_starts_with($spec, 'SPH') || str_starts_with($spec, 'SAPH');

                if ($isHr) {
                    DB::table('material_steels')
                        ->where('id', $row->id)
                        ->update(['hr_steel_usd' => $row->gap_steel_usd]);
                } else {
                    DB::table('material_steels')
                        ->where('id', $row->id)
                        ->update(['cr_steel_usd' => $row->gap_steel_usd]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_steels', function (Blueprint $table) {
            $table->dropColumn(['hr_steel_usd', 'cr_steel_usd']);
        });
    }
};
