<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Mesin Stamping 500 Ton, Mesin Injection 800 Ton
            $table->string('category'); // Steel (Stamping), Non-Steel (Injection/Foaming/Sewing)
            $table->string('tonnage')->nullable(); // 500 Ton, 800 Ton, 1200 Ton, etc.
            $table->decimal('hourly_rate', 16, 2)->default(0); // Biaya operasional mesin per jam (IDR)
            $table->decimal('operator_hourly_rate', 16, 2)->default(0); // Biaya operator per jam (IDR)
            $table->integer('default_capacity_per_hour')->default(60); // Estimasi pcs per jam
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
