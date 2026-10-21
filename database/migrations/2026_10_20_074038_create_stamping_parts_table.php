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
        Schema::create('stamping_parts', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->default(2026);
            $table->string('quarter'); // Q1, Q2, Q3, Q4
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name');
            $table->string('vendor_code');
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('steel_spec'); // Spec
            $table->string('double_thickness')->nullable(); // Thickness(mm)
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('currency_code')->default('USD');
            $table->decimal('gap_steel_usd', 16, 4)->default(0); // Gap / MT (USD)
            $table->decimal('gap_steel_kg_usd', 16, 4)->default(0); // Gap / Kg (USD) [=Gap/MT / 1000]
            $table->decimal('exchange_rate', 16, 4)->default(16000); // Ex Rate (IDR)
            $table->decimal('coil_price', 16, 4)->default(0); // Coil (USD)
            $table->decimal('coil_price_idr', 16, 2)->default(0); // Coil (IDR) [=Coil * ExRate]
            $table->decimal('sheet_price', 16, 4)->default(0); // Sheet (USD)
            $table->decimal('sheet_price_idr', 16, 2)->default(0); // Sheet (IDR) [=Sheet * ExRate]
            $table->decimal('fluktuasi_jfe', 16, 4)->default(0);
            $table->string('status')->default('Active'); // Active, Draft, Archived
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'quarter']);
            $table->index(['supplier_id', 'year', 'quarter']);
            $table->index('vendor_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamping_parts');
    }
};
