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
        Schema::table('currencies', function (Blueprint $table) {
            // Gap AP (Pembelian)
            if (! Schema::hasColumn('currencies', 'gap_ap_usd')) {
                $table->decimal('gap_ap_usd', 16, 4)->default(0)->after('exchange_rate_to_idr');
            }
            if (! Schema::hasColumn('currencies', 'gap_ap_kg_usd')) {
                $table->decimal('gap_ap_kg_usd', 16, 4)->default(0)->after('gap_ap_usd');
            }
            if (! Schema::hasColumn('currencies', 'gap_ap_idr')) {
                $table->decimal('gap_ap_idr', 16, 2)->default(0)->after('gap_ap_kg_usd');
            }

            // Gap AR (Penjualan)
            if (! Schema::hasColumn('currencies', 'gap_ar_usd')) {
                $table->decimal('gap_ar_usd', 16, 4)->default(0)->after('gap_crc_idr');
            }
            if (! Schema::hasColumn('currencies', 'gap_ar_kg_usd')) {
                $table->decimal('gap_ar_kg_usd', 16, 4)->default(0)->after('gap_ar_usd');
            }
            if (! Schema::hasColumn('currencies', 'gap_ar_idr')) {
                $table->decimal('gap_ar_idr', 16, 2)->default(0)->after('gap_ar_kg_usd');
            }

            // Gap HRC & CRC AR (Opsional untuk spec HR/CR pada AR)
            if (! Schema::hasColumn('currencies', 'gap_hrc_ar_usd')) {
                $table->decimal('gap_hrc_ar_usd', 16, 4)->default(0)->after('gap_ar_idr');
            }
            if (! Schema::hasColumn('currencies', 'gap_hrc_ar_kg_usd')) {
                $table->decimal('gap_hrc_ar_kg_usd', 16, 4)->default(0)->after('gap_hrc_ar_usd');
            }
            if (! Schema::hasColumn('currencies', 'gap_hrc_ar_idr')) {
                $table->decimal('gap_hrc_ar_idr', 16, 2)->default(0)->after('gap_hrc_ar_kg_usd');
            }
            if (! Schema::hasColumn('currencies', 'gap_crc_ar_usd')) {
                $table->decimal('gap_crc_ar_usd', 16, 4)->default(0)->after('gap_hrc_ar_idr');
            }
            if (! Schema::hasColumn('currencies', 'gap_crc_ar_kg_usd')) {
                $table->decimal('gap_crc_ar_kg_usd', 16, 4)->default(0)->after('gap_crc_ar_usd');
            }
            if (! Schema::hasColumn('currencies', 'gap_crc_ar_idr')) {
                $table->decimal('gap_crc_ar_idr', 16, 2)->default(0)->after('gap_crc_ar_kg_usd');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $cols = [
                'gap_ap_usd', 'gap_ap_kg_usd', 'gap_ap_idr',
                'gap_ar_usd', 'gap_ar_kg_usd', 'gap_ar_idr',
                'gap_hrc_ar_usd', 'gap_hrc_ar_kg_usd', 'gap_hrc_ar_idr',
                'gap_crc_ar_usd', 'gap_crc_ar_kg_usd', 'gap_crc_ar_idr',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('currencies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
