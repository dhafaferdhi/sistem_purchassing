<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->string('stamping_type')->default('General')->after('category'); // Progressive, Tandem, General
        });

        Schema::table('processes', function (Blueprint $table) {
            $table->string('stage_type')->default('General')->after('name'); // Blanking, Piercing, Bending, Drawing, Trimming, General
            $table->string('dies_code')->nullable()->after('tooling_type'); // Kode Dies/Cetakan
            $table->string('dies_name')->nullable()->after('dies_code'); // Nama Cetakan
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('stamping_type');
        });

        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn(['stage_type', 'dies_code', 'dies_name']);
        });
    }
};

