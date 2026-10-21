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
        Schema::table('currencies', function (Blueprint $table) {
            $table->integer('year')->default(2026)->after('symbol');
        });

        // Drop previous single-column unique constraint on 'code' and make (code, year) composite unique
        try {
            Schema::table('currencies', function (Blueprint $table) {
                $table->dropUnique('currencies_code_unique');
            });
        } catch (Throwable $e) {
            // Index might not exist or driver already dropped it
        }

        try {
            Schema::table('currencies', function (Blueprint $table) {
                $table->unique(['code', 'year']);
            });
        } catch (Throwable $e) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('currencies', function (Blueprint $table) {
                $table->dropUnique(['code', 'year']);
            });
        } catch (Throwable $e) {
        }

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('year');
            $table->unique('code');
        });
    }
};
