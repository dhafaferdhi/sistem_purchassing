<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toolings', function (Blueprint $table) {
            $table->id();
            $table->string('tooling_code')->unique(); // DIES-STP-001, MOLD-PL-01
            $table->string('name'); // Dies Progressive Bracket Bumper
            $table->string('type')->default('Dies'); // Dies, Mold, Jig
            $table->string('ownership')->default('Customer Owned'); // Customer Owned (Milik Kita / Consignment), Vendor Owned (Milik Supplier)
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete(); // Lokasi vendor yang memegang cetakan
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete(); // Part yang dicetak
            $table->string('tonnage')->nullable(); // 500 Ton, 800 Ton
            $table->integer('cavity_count')->default(1); // 1-Cavity, 2-Cavity, dll.
            $table->decimal('initial_cost', 16, 2)->default(0); // Biaya pembuatan cetakan
            $table->integer('lifetime_strokes')->default(300000); // Target garansi umur cetakan (Stroke)
            $table->integer('current_strokes')->default(0); // Akumulasi stroke yang sudah terpakai
            $table->string('status')->default('Ready'); // Ready, In Production, Maintenance, Scrap
            $table->string('location_notes')->nullable(); // Misal: PT Maju Baja Plant 1 Line Press 5
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toolings');
    }
};
