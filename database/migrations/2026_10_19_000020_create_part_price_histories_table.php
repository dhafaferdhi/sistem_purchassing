<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('price_date');
            $table->string('source_type')->default('Purchase Order'); // Purchase Order, Quotation, Supplier Catalog, Manual Update
            $table->string('reference_number')->nullable(); // PO-202610-0001, QUO-202610-0001
            $table->decimal('unit_price_idr', 16, 2);
            $table->decimal('previous_price_idr', 16, 2)->nullable();
            $table->decimal('price_change_percentage', 8, 2)->nullable(); // +5.20% or -2.50%
            $table->string('currency_code', 10)->default('IDR');
            $table->decimal('original_price', 16, 4)->nullable();
            $table->text('notes')->nullable(); // Kenaikan kurs, fluktuasi bahan baku, diskon volume
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_price_histories');
    }
};
