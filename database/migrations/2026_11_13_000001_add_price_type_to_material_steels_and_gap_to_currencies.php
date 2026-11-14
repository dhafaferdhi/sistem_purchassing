<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambah price_type pada tabel material_steels untuk memisahkan Material Steel AP dan Material Steel AR
        if (! Schema::hasColumn('material_steels', 'price_type')) {
            Schema::table('material_steels', function (Blueprint $table) {
                $table->string('price_type', 10)->default('AP')->after('quarter')->index();
            });

            // Set data lama sebagai 'AP'
            DB::table('material_steels')->whereNull('price_type')->orWhere('price_type', '')->update(['price_type' => 'AP']);
        }

        // 2. Tambah kolom Gap HRC dan Gap CRC pada tabel currencies
        Schema::table('currencies', function (Blueprint $table) {
            if (! Schema::hasColumn('currencies', 'gap_hrc_usd')) {
                $table->decimal('gap_hrc_usd', 16, 4)->default(0)->after('exchange_rate_to_idr');
            }
            if (! Schema::hasColumn('currencies', 'gap_hrc_kg_usd')) {
                $table->decimal('gap_hrc_kg_usd', 16, 4)->default(0)->after('gap_hrc_usd');
            }
            if (! Schema::hasColumn('currencies', 'gap_hrc_idr')) {
                $table->decimal('gap_hrc_idr', 16, 2)->default(0)->after('gap_hrc_kg_usd');
            }
            if (! Schema::hasColumn('currencies', 'gap_crc_usd')) {
                $table->decimal('gap_crc_usd', 16, 4)->default(0)->after('gap_hrc_idr');
            }
            if (! Schema::hasColumn('currencies', 'gap_crc_kg_usd')) {
                $table->decimal('gap_crc_kg_usd', 16, 4)->default(0)->after('gap_crc_usd');
            }
            if (! Schema::hasColumn('currencies', 'gap_crc_idr')) {
                $table->decimal('gap_crc_idr', 16, 2)->default(0)->after('gap_crc_kg_usd');
            }
            if (! Schema::hasColumn('currencies', 'notes')) {
                $table->text('notes')->nullable()->after('gap_crc_idr');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('material_steels', 'price_type')) {
            Schema::table('material_steels', function (Blueprint $table) {
                $table->dropColumn('price_type');
            });
        }

        Schema::table('currencies', function (Blueprint $table) {
            $cols = [];
            foreach (['gap_hrc_usd', 'gap_hrc_kg_usd', 'gap_hrc_idr', 'gap_crc_usd', 'gap_crc_kg_usd', 'gap_crc_idr', 'notes'] as $c) {
                if (Schema::hasColumn('currencies', $c)) {
                    $cols[] = $c;
                }
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
