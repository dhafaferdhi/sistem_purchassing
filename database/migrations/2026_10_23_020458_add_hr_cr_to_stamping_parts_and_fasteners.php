<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stamping_parts', function (Blueprint $table) {
            $table->decimal('hr_steel_usd', 12, 4)->nullable()->default(0)->after('currency_code');
            $table->decimal('cr_steel_usd', 12, 4)->nullable()->default(0)->after('hr_steel_usd');
        });

        Schema::table('fasteners', function (Blueprint $table) {
            $table->decimal('hr_steel_usd', 12, 4)->nullable()->default(0)->after('currency_code');
            $table->decimal('cr_steel_usd', 12, 4)->nullable()->default(0)->after('hr_steel_usd');
        });
    }

    public function down(): void
    {
        Schema::table('stamping_parts', function (Blueprint $table) {
            $table->dropColumn(['hr_steel_usd', 'cr_steel_usd']);
        });

        Schema::table('fasteners', function (Blueprint $table) {
            $table->dropColumn(['hr_steel_usd', 'cr_steel_usd']);
        });
    }
};
