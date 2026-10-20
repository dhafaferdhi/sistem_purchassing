<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique(); // PO-202610-0001
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('user_id')->constrained('users'); // Staff Purchasing
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->string('payment_terms')->default('Net 30 Days'); // Net 30, Net 60, COD
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('exchange_rate', 16, 4)->default(1);
            $table->decimal('subtotal_idr', 16, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(11.0); // PPN 11%
            $table->decimal('tax_amount_idr', 16, 2)->default(0);
            $table->decimal('total_amount_idr', 16, 2)->default(0);
            $table->string('status')->default('Draft'); // Draft, Approved, Sent to Vendor, Completed, Cancelled
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('item_code');
            $table->string('description');
            $table->string('vendor_item_code')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit')->default('pcs');
            $table->decimal('unit_price_idr', 16, 2)->default(0);
            $table->decimal('total_price_idr', 16, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
