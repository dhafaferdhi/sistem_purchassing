<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->string('vendor_item_code'); // Kode Part dari Vendor (misal: MBS-BKT-889)
            $table->string('vendor_item_name')->nullable(); // Nama Part dari Vendor
            $table->decimal('unit_price', 16, 2)->default(0); // Harga Penawaran Satuan
            $table->foreignId('currency_id')->constrained('currencies');
            $table->integer('moq')->default(100); // Minimum Order Quantity
            $table->integer('lead_time_days')->default(14); // Lead Time pengiriman (Hari)
            $table->boolean('is_primary_vendor')->default(false); // Apakah Vendor Utama
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_items');
    }
};
