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
        Schema::create('material_steels', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->default(2026);
            $table->string('quarter'); // Q1, Q2, Q3, Q4
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('vendor_code'); // e.g. VND-105
            $table->string('supplier_name'); // e.g. PT Maju Baja Stamping
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('steel_spec'); // e.g. SPCC 1.2mm, SPHC Coil
            $table->foreignId('currency_id')->constrained('currencies');
            $table->string('currency_code')->default('USD');
            $table->decimal('steel_price', 16, 4); // Harga dasar per kg / unit
            $table->decimal('exchange_rate', 16, 4)->default(1); // Kurs ke IDR
            $table->decimal('steel_price_idr', 16, 2)->default(0); // steel_price * exchange_rate
            $table->decimal('fluktuasi_jfe', 16, 4)->default(0);

            // 3 Bulan ke belakang (Historical Lookback)
            $table->string('prev_month_1_label')->nullable();
            $table->decimal('prev_month_1_price', 16, 4)->nullable();
            $table->decimal('prev_month_1_rate', 16, 4)->nullable();

            $table->string('prev_month_2_label')->nullable();
            $table->decimal('prev_month_2_price', 16, 4)->nullable();
            $table->decimal('prev_month_2_rate', 16, 4)->nullable();

            $table->string('prev_month_3_label')->nullable();
            $table->decimal('prev_month_3_price', 16, 4)->nullable();
            $table->decimal('prev_month_3_rate', 16, 4)->nullable();

            $table->decimal('avg_3_months_price', 16, 4)->nullable();
            $table->decimal('avg_3_months_rate', 16, 4)->nullable();
            $table->decimal('avg_3_months_price_idr', 16, 2)->nullable();
            $table->decimal('price_variance_percentage', 8, 2)->nullable();

            $table->string('status')->default('Active'); // Active, Draft, Archived
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_steels');
    }
};
