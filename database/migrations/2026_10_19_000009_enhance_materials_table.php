<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('main_category')->default('Steel')->after('category'); // Steel, Non-Steel
            $table->string('sub_category')->nullable()->after('main_category'); // Frame, Bolt, Bracket, Plastik, Busa (Urethane), Cover (Kain)
            $table->foreignId('supplier_id')->nullable()->after('sub_category')->constrained('suppliers')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->after('supplier_id')->constrained('currencies')->nullOnDelete();
            $table->string('unit')->default('kg')->after('basic_rate'); // kg, meter, pcs, liter
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['main_category', 'sub_category', 'supplier_id', 'currency_id', 'unit']);
        });
    }
};

