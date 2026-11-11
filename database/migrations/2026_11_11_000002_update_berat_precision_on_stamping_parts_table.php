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
            if (Schema::hasColumn('stamping_parts', 'berat')) {
                $table->decimal('berat', 14, 7)->nullable()->default(0)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stamping_parts', function (Blueprint $table) {
            if (Schema::hasColumn('stamping_parts', 'berat')) {
                $table->decimal('berat', 12, 4)->nullable()->default(0)->change();
            }
        });
    }
};
