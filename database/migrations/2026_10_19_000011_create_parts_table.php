<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_number')->unique();
            $table->string('part_name');
            $table->string('main_category')->default('Steel'); // Steel, Non-Steel, Assembly
            $table->string('sub_category')->nullable(); // Frame, Bolt, Bracket, Plastik, Busa, Cover
            $table->string('type')->default('Component'); // Assembly, Component
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->decimal('weight', 12, 4)->default(0);
            $table->string('dimensions')->nullable();
            $table->string('unit')->default('pcs');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
