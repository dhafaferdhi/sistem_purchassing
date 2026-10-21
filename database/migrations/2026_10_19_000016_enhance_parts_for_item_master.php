<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->string('item_code')->nullable()->after('id'); // STP-001, FST-001, MAT-001
            $table->string('description')->nullable()->after('part_name'); // Nama & deskripsi spesifikasi
            $table->string('item_category')->default('Stamping')->after('description'); // Material, Fastener, Stamping, Assembly
        });

        // Sync existing data
        DB::statement('UPDATE parts SET item_code = part_number, description = part_name');
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn(['item_code', 'description', 'item_category']);
        });
    }
};
