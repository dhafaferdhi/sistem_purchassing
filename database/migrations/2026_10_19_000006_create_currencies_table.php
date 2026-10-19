<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // IDR, USD, JPY, EUR
            $table->string('name'); // Indonesian Rupiah, US Dollar, Japanese Yen
            $table->string('symbol', 10)->default('Rp');
            $table->decimal('exchange_rate_to_idr', 16, 4)->default(1.0); // 1 USD = 16000 IDR, 1 JPY = 105 IDR
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};

