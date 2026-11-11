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
        Schema::table('fasteners', function (Blueprint $table) {
            if (! Schema::hasColumn('fasteners', 'quotation_date')) {
                $table->date('quotation_date')->nullable()->after('eci_number');
            }
            if (! Schema::hasColumn('fasteners', 'overhead_supplied_percent')) {
                $table->decimal('overhead_supplied_percent', 8, 2)->default(0)->after('subtotal');
            }
            if (! Schema::hasColumn('fasteners', 'overhead_supplied_cost')) {
                $table->decimal('overhead_supplied_cost', 16, 4)->default(0)->after('overhead_supplied_percent');
            }
            if (! Schema::hasColumn('fasteners', 'transportation_cost')) {
                $table->decimal('transportation_cost', 16, 2)->default(0)->after('overhead_self_cost');
            }
            if (! Schema::hasColumn('fasteners', 'cost_down')) {
                $table->decimal('cost_down', 16, 2)->default(0)->after('transportation_cost');
            }
            if (! Schema::hasColumn('fasteners', 'part_cost_excluding_tooling')) {
                $table->decimal('part_cost_excluding_tooling', 16, 4)->default(0)->after('cost_down');
            }
            if (! Schema::hasColumn('fasteners', 'comments')) {
                $table->text('comments')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('fasteners', 'remarks')) {
                $table->text('remarks')->nullable()->after('comments');
            }
            if (! Schema::hasColumn('fasteners', 'alteration')) {
                $table->text('alteration')->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('fasteners', 'etc_contents')) {
                $table->text('etc_contents')->nullable()->after('alteration');
            }
        });

        Schema::table('monthly_rollover_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_rollover_logs', 'fasteners_count')) {
                $table->unsignedInteger('fasteners_count')->default(0)->after('stamping_parts_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fasteners', function (Blueprint $table) {
            $columns = [
                'quotation_date',
                'overhead_supplied_percent',
                'overhead_supplied_cost',
                'transportation_cost',
                'cost_down',
                'part_cost_excluding_tooling',
                'comments',
                'remarks',
                'alteration',
                'etc_contents',
            ];
            $existing = array_filter($columns, fn (string $col) => Schema::hasColumn('fasteners', $col));
            if (! empty($existing)) {
                $table->dropColumn($existing);
            }
        });

        Schema::table('monthly_rollover_logs', function (Blueprint $table) {
            if (Schema::hasColumn('monthly_rollover_logs', 'fasteners_count')) {
                $table->dropColumn('fasteners_count');
            }
        });
    }
};
