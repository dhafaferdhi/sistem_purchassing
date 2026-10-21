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
        Schema::create('pud_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->date('request_date');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name');
            $table->string('division');
            $table->string('plant');

            // Detail Part / Barang
            $table->string('part_name');
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->decimal('qty', 12, 2)->default(1);
            $table->string('unit', 30)->default('Pcs');
            $table->text('purpose');
            $table->text('spec');
            $table->string('photo')->nullable();

            // Status & Lifecycle
            $table->string('status', 50)->default('Delivered to PUD');
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            // PUD Quotation & Suppliers (3 Supplier slots)
            $table->string('supplier_1_name')->nullable();
            $table->string('supplier_1_currency_1', 20)->default('Rp');
            $table->decimal('supplier_1_price_1', 15, 2)->nullable();
            $table->string('supplier_1_currency_2', 20)->default('Rp');
            $table->decimal('supplier_1_price_2', 15, 2)->nullable();
            $table->string('supplier_1_quotation_file')->nullable();

            $table->string('supplier_2_name')->nullable();
            $table->string('supplier_2_currency_1', 20)->default('Rp');
            $table->decimal('supplier_2_price_1', 15, 2)->nullable();
            $table->string('supplier_2_currency_2', 20)->default('Rp');
            $table->decimal('supplier_2_price_2', 15, 2)->nullable();
            $table->string('supplier_2_quotation_file')->nullable();

            $table->string('supplier_3_name')->nullable();
            $table->string('supplier_3_currency_1', 20)->default('Rp');
            $table->decimal('supplier_3_price_1', 15, 2)->nullable();
            $table->string('supplier_3_currency_2', 20)->default('Rp');
            $table->decimal('supplier_3_price_2', 15, 2)->nullable();
            $table->string('supplier_3_quotation_file')->nullable();

            // PUD Final Processing
            $table->string('selected_supplier')->nullable();
            $table->string('item_code')->nullable();
            $table->text('feedback')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('history')->nullable();

            // Soft-deletion tracking as requested in HTML spec
            $table->boolean('is_deleted')->default(false);
            $table->dateTime('deleted_at')->nullable();
            $table->string('deleted_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['plant', 'division']);
            $table->index('status');
            $table->index('request_date');
            $table->index('is_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pud_requests');
    }
};
