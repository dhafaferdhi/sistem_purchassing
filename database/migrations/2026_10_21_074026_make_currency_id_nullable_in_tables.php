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
        $tables = ['material_steels', 'part_quotations', 'purchase_orders', 'vendor_items'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'currency_id')) {
                try {
                    Schema::table($table, function (Blueprint $t) {
                        $t->dropForeign(['currency_id']);
                    });
                } catch (Throwable $e) {
                    // Foreign key might already be dropped
                }

                try {
                    Schema::table($table, function (Blueprint $t) {
                        $t->foreignId('currency_id')->nullable()->change();
                    });
                } catch (Throwable $e) {
                    // Column might already be nullable
                }

                try {
                    Schema::table($table, function (Blueprint $t) {
                        $t->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
                    });
                } catch (Throwable $e) {
                    // Foreign key might already exist
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['material_steels', 'part_quotations', 'purchase_orders', 'vendor_items'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'currency_id')) {
                try {
                    Schema::table($table, function (Blueprint $t) {
                        $t->dropForeign(['currency_id']);
                        $t->foreign('currency_id')->references('id')->on('currencies');
                    });
                } catch (Throwable $e) {
                }
            }
        }
    }
};
