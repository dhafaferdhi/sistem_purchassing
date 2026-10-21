<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('component_part_id')->constrained('parts')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->integer('level')->default(1); // 1 = Level 1, 2 = Level 2
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_boms');
    }
};
