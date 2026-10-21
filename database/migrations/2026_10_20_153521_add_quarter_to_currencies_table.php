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
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('quarter', 5)->default('Q1')->after('year');
        });

        // Update data eksisting agar kuartal terisi sesuai bulan
        DB::table('currencies')->whereIn('month', [1, 2, 3])->update(['quarter' => 'Q1']);
        DB::table('currencies')->whereIn('month', [4, 5, 6])->update(['quarter' => 'Q2']);
        DB::table('currencies')->whereIn('month', [7, 8, 9])->update(['quarter' => 'Q3']);
        DB::table('currencies')->whereIn('month', [10, 11, 12])->update(['quarter' => 'Q4']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('quarter');
        });
    }
};
