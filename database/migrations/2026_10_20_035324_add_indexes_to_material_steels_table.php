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
            $table->index(['year', 'quarter']);
            $table->index('vendor_code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_steels', function (Blueprint $table) {
            $table->dropIndex(['year', 'quarter']);
            $table->dropIndex(['vendor_code']);
            $table->dropIndex(['status']);
        });
    }
};
