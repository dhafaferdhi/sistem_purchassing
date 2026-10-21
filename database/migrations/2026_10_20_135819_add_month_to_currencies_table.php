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
            $table->unsignedTinyInteger('month')->default((int) date('n'))->after('year');
        });

        try {
            Schema::table('currencies', function (Blueprint $table) {
                $table->dropUnique(['code', 'year']);
            });
        } catch (Throwable $e) {
            // Index might not exist or sqlite constraint
        }

        try {
            Schema::table('currencies', function (Blueprint $table) {
                $table->unique(['code', 'year', 'month']);
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
                $table->dropUnique(['code', 'year', 'month']);
            });
        } catch (Throwable $e) {
        }

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('month');
        });

        try {
            Schema::table('currencies', function (Blueprint $table) {
                $table->unique(['code', 'year']);
            });
        } catch (Throwable $e) {
        }
    }
};
