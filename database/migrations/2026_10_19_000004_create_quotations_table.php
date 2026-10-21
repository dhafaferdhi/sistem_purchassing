<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials');
            $table->string('category'); // Fastener, Presspart
            $table->string('diameter')->nullable();
            $table->string('thickness')->nullable();
            $table->string('size_material')->nullable();
            $table->decimal('weight', 12, 4);
            $table->decimal('snapshot_basic_rate', 16, 4);
            $table->decimal('snapshot_exchange_rate', 16, 4);
            $table->decimal('snapshot_fluktuasi', 16, 4)->default(0);
            $table->decimal('price_idr', 16, 2);
            $table->decimal('total_process_cost', 16, 2)->default(0);
            $table->decimal('profit_percentage', 8, 2)->default(10);
            $table->decimal('profit', 16, 2)->default(0);
            $table->decimal('transport', 16, 2)->default(100);
            $table->decimal('packing', 16, 2)->default(50);
            $table->decimal('total_cost', 16, 2);
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
