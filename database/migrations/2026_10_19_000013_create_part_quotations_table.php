<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('exchange_rate_to_idr', 16, 4)->default(1);
            
            // Komponen Biaya Asal & IDR
            $table->decimal('material_cost_original', 16, 4)->default(0);
            $table->decimal('material_cost_idr', 16, 2)->default(0);

            $table->decimal('machine_cost_original', 16, 4)->default(0);
            $table->decimal('machine_cost_idr', 16, 2)->default(0);

            $table->decimal('manpower_cost_idr', 16, 2)->default(0);
            $table->decimal('packaging_cost_idr', 16, 2)->default(0);
            $table->decimal('transport_cost_idr', 16, 2)->default(0);

            // Tooling (Mold / Dies)
            $table->string('tooling_type')->default('None'); // Dies, Mold, None
            $table->string('tooling_payment_type')->default('Lumpsum'); // Lumpsum, Amortisasi
            $table->decimal('tooling_cost_total', 16, 2)->default(0);
            $table->integer('tooling_amortization_qty')->default(0);
            $table->decimal('tooling_cost_per_piece', 16, 2)->default(0);

            // Grand Total Biaya per Pcs (IDR)
            $table->decimal('total_cost_idr', 16, 2)->default(0);

            $table->string('status')->default('active'); // active, draft, archived
            $table->boolean('is_selected')->default(false); // Penanda supplier pemenang / terpilih
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_quotations');
    }
};

