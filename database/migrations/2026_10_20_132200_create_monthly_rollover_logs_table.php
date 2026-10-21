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
        Schema::create('monthly_rollover_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->default('all'); // all, currency, material_steel, stamping_part
            $table->string('source_period', 50);
            $table->string('target_period', 50);
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->unsignedInteger('currencies_count')->default(0);
            $table->unsignedInteger('material_steels_count')->default(0);
            $table->unsignedInteger('stamping_parts_count')->default(0);
            $table->json('details')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_rollover_logs');
    }
};
