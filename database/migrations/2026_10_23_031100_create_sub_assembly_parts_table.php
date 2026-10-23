<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_assembly_parts', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->default(2026)->index();
            $table->string('quarter', 10)->default('Q1')->index();
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('part_number')->index();
            $table->string('part_name');
            $table->string('model_name')->nullable()->default('D26A');
            $table->integer('monthly_volume')->default(16500);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('vendor_code')->nullable();
            $table->string('supplier_name')->nullable()->index();
            $table->string('buyer_name')->nullable()->default('PT. FUJI SEAT INDONESIA');
            $table->string('drawing_reference')->nullable()->default('Mass Prod. Drwg (Exchange rate change)');
            $table->string('eci_number')->nullable()->default('F1225');
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('currency_code', 10)->default('USD');
            $table->decimal('exchange_rate', 12, 2)->default(16000.00);

            // Rincian Child Parts / Bahan Baku (Array JSON)
            $table->json('material_breakdown')->nullable();
            $table->decimal('material_total', 14, 4)->default(0.0000);

            // Rincian Biaya Proses Pengelasan & Finishing (Array JSON)
            $table->json('process_breakdown')->nullable();
            $table->decimal('process_total', 14, 4)->default(0.0000);

            // Subtotal Biaya
            $table->decimal('subtotal', 14, 4)->default(0.0000);

            // Overhead & Keuntungan
            $table->decimal('overhead_supplied_percent', 5, 2)->default(11.00);
            $table->decimal('overhead_supplied_cost', 14, 4)->default(0.0000);
            $table->decimal('profit_percent', 5, 2)->default(0.00);
            $table->decimal('profit_cost', 14, 4)->default(0.0000);

            // Biaya Tambahan
            $table->decimal('packing_cost', 14, 4)->default(120.0000);
            $table->decimal('tooling_depreciation', 14, 4)->default(0.0000);
            $table->json('tooling_details')->nullable();

            // Total Biaya Akhir Part
            $table->decimal('part_cost', 14, 4)->default(0.0000);

            $table->string('status', 20)->default('Active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'quarter', 'part_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_assembly_parts');
    }
};
