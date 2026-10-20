<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('gross_weight', 12, 4)->nullable()->after('weight'); // Berat kotor sebelum dipotong
            $table->decimal('net_weight', 12, 4)->nullable()->after('gross_weight'); // Berat bersih barang jadi
            $table->decimal('scrap_weight', 12, 4)->default(0)->after('net_weight'); // Sisa potongan plat
            $table->decimal('scrap_price_per_kg', 16, 2)->default(0)->after('scrap_weight'); // Harga jual besi gram/scrap
            $table->decimal('scrap_recovery_value', 16, 2)->default(0)->after('scrap_price_per_kg'); // Nilai pengurang HPP
            $table->integer('cavity_count')->default(1)->after('scrap_recovery_value'); // Jumlah cavity cetakan
            $table->string('drawing_number')->nullable()->after('cavity_count'); // Nomor gambar teknik
            $table->string('drawing_file')->nullable()->after('drawing_number'); // Path file CAD / PDF drawing
            $table->string('revision_number', 20)->default('Rev 0')->after('drawing_file'); // Rev 0, Rev 1, Rev 2
            $table->date('validity_until')->nullable()->after('revision_number'); // Masa berlaku penawaran
        });

        Schema::table('part_quotations', function (Blueprint $table) {
            $table->integer('cavity_count')->default(1)->after('machine_cost_idr');
            $table->decimal('scrap_recovery_idr', 16, 2)->default(0)->after('cavity_count');
            $table->string('revision_number', 20)->default('Rev 0')->after('status');
            $table->date('validity_until')->nullable()->after('revision_number');
            $table->string('drawing_number')->nullable()->after('validity_until');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'gross_weight',
                'net_weight',
                'scrap_weight',
                'scrap_price_per_kg',
                'scrap_recovery_value',
                'cavity_count',
                'drawing_number',
                'drawing_file',
                'revision_number',
                'validity_until',
            ]);
        });

        Schema::table('part_quotations', function (Blueprint $table) {
            $table->dropColumn([
                'cavity_count',
                'scrap_recovery_idr',
                'revision_number',
                'validity_until',
                'drawing_number',
            ]);
        });
    }
};
