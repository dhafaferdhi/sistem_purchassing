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
        Schema::create('fasteners', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->default(2026)->index();
            $table->string('quarter', 5)->default('Q1')->index();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->string('vendor_code')->nullable();
            $table->string('steel_spec')->index(); // Spesifikasi kawat/baja fastener, misal SWMB, SWRCH 10A
            $table->string('diameter')->nullable(); // Diameter (mm)
            $table->string('double_thickness')->nullable(); // Thickness(mm) / Diameter
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('currency_code', 10)->default('USD');
            $table->decimal('gap_steel_usd', 16, 4)->default(0); // Gap/MT (USD)
            $table->decimal('gap_steel_kg_usd', 16, 4)->default(0); // Gap/Kg (USD)
            $table->decimal('exchange_rate', 16, 4)->default(16000); // Ex Rate (IDR)
            $table->decimal('coil_price', 16, 4)->default(0); // Coil / Wire (USD)
            $table->decimal('coil_price_idr', 16, 2)->default(0); // Coil / Wire (IDR)
            $table->decimal('sheet_price', 16, 4)->default(0); // Sheet / Rod (USD)
            $table->decimal('sheet_price_idr', 16, 2)->default(0); // Sheet / Rod (IDR)
            $table->decimal('fluktuasi_jfe', 16, 4)->default(0);
            $table->string('status', 20)->default('Active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'quarter']);
            $table->index(['year', 'quarter', 'steel_spec']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fasteners');
    }
};
