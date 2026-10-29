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
            $table->string('part_no_to_compare')->nullable()->after('part_number');
            $table->date('quotation_date')->nullable()->after('eci_number');
            $table->decimal('exchange_rate_fluctuation', 10, 6)->default(1.000000)->after('exchange_rate');
            $table->decimal('transportation_cost', 12, 2)->default(0)->after('overhead_self_cost');
            $table->decimal('packing_cost', 12, 2)->default(0)->after('transportation_cost');
            $table->decimal('part_cost_excluding_tooling', 16, 4)->default(0)->after('packing_cost');
            $table->text('comments')->nullable()->after('notes');
            $table->text('remarks')->nullable()->after('comments');
            $table->text('alteration')->nullable()->after('remarks');
            $table->text('etc_contents')->nullable()->after('alteration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stamping_parts', function (Blueprint $table) {
            $table->dropColumn([
                'part_no_to_compare',
                'quotation_date',
                'exchange_rate_fluctuation',
                'transportation_cost',
                'packing_cost',
                'part_cost_excluding_tooling',
                'comments',
                'remarks',
                'alteration',
                'etc_contents',
            ]);
        });
    }
};
