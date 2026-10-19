<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->foreignId('machine_id')->nullable()->after('category')->constrained('machines')->nullOnDelete();
            $table->decimal('cycle_time_seconds', 8, 2)->default(0)->after('machine_id'); // Waktu siklus proses per pcs
            $table->string('tooling_type')->default('None')->after('cycle_time_seconds'); // Dies, Mold, Jig, None
        });
    }

    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropColumn(['machine_id', 'cycle_time_seconds', 'tooling_type']);
        });
    }
};

