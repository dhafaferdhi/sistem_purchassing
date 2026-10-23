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
        Schema::create('comparative_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('part_number')->index();
            $table->string('part_name');
            $table->string('part_type')->default('Sub-Assembly'); // Sub-Assembly, Stamping, Fastener, Component
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name')->index();
            $table->string('buyer_name')->default('PT. FUJI SEAT INDONESIA');
            $table->string('model_name')->nullable()->default('D26A');
            $table->integer('monthly_volume')->default(0);
            $table->string('drawing_reference')->nullable();
            $table->string('eci_number')->nullable();

            // Periode Kuartal & Urutan
            $table->string('period_label'); // e.g. "Periode Q2 April-June '22"
            $table->date('period_date')->nullable();
            $table->integer('year')->default(2026);
            $table->string('quarter')->default('Q1');
            $table->integer('sequence')->default(1);

            // Kurs Mata Uang
            $table->decimal('old_exchange_rate', 16, 4)->nullable();
            $table->decimal('new_exchange_rate', 16, 4)->default(16000);
            $table->decimal('exchange_fluctuation', 16, 6)->default(1.0);

            // Komponen Biaya Rinci (Material & Process)
            $table->json('material_breakdown')->nullable();
            $table->decimal('material_total', 16, 4)->default(0);

            $table->json('process_breakdown')->nullable();
            $table->decimal('process_total', 16, 4)->default(0);

            // Subtotal & O/H Profit
            $table->decimal('subtotal', 16, 4)->default(0);
            $table->decimal('overhead_supplied_percent', 8, 2)->default(0);
            $table->decimal('overhead_supplied_cost', 16, 4)->default(0);
            $table->decimal('overhead_self_percent', 8, 2)->default(0);
            $table->decimal('overhead_self_cost', 16, 4)->default(0);
            $table->decimal('profit_percent', 8, 2)->default(0);
            $table->decimal('profit_cost', 16, 4)->default(0);

            // Biaya Logistik & Tooling
            $table->decimal('transport_cost', 16, 4)->default(0);
            $table->decimal('packing_cost', 16, 4)->default(0);
            $table->decimal('part_cost', 16, 4)->default(0); // (8) Part Cost (excluding tooling)
            $table->decimal('tooling_cost', 16, 4)->default(0); // (9) Die & Jig depreciation
            $table->json('tooling_details')->nullable();
            $table->decimal('total_cost', 16, 4)->default(0); // Total = (8) + (9)

            // Variance / Selisih dari kuartal sebelumnya
            $table->decimal('cost_difference', 16, 4)->default(0);
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['part_number', 'sequence']);
            $table->index(['year', 'quarter']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comparative_quotations');
    }
};
