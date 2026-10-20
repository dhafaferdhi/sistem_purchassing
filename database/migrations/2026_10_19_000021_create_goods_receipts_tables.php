<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah tracking status pengiriman pada purchase_orders
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('delivery_status')->default('Unfulfilled')->after('status'); // Unfulfilled, Partially Received, Fully Received, Over Received
        });

        // 2. Tambah tracking kuantitas datang dan referensi MOQ pada purchase_order_items
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity_received', 12, 2)->default(0)->after('quantity');
            $table->decimal('quantity_rejected', 12, 2)->default(0)->after('quantity_received');
            $table->integer('moq')->nullable()->after('vendor_item_code');
            $table->integer('lead_time_days')->nullable()->after('moq');
        });

        // 3. Buat tabel Surat Penerimaan Barang (Goods Receipt Note / GRN)
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number')->unique(); // GRN-202610-0001
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('delivery_order_number'); // No. Surat Jalan Vendor (SJ-2026-XXXX)
            $table->date('received_date');
            $table->foreignId('received_by')->constrained('users'); // Petugas Gudang / Receiving Staff
            $table->string('driver_name')->nullable();
            $table->string('vehicle_number')->nullable(); // Plat Nomor Truk/Mobil Pengangkut
            $table->string('status')->default('Verified'); // Draft, Verified, Returned
            $table->text('notes')->nullable(); // Catatan kondisi fisik kemasan, segel, dll.
            $table->timestamps();
        });

        // 4. Buat tabel Item Penerimaan Barang
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('item_code');
            $table->string('description');
            $table->decimal('quantity_ordered', 12, 2);
            $table->decimal('quantity_previously_received', 12, 2)->default(0);
            $table->decimal('quantity_received', 12, 2); // Jumlah datang kali ini
            $table->decimal('quantity_rejected', 12, 2)->default(0); // Rusak / cacat QC
            $table->decimal('quantity_remaining', 12, 2)->default(0); // Sisa yang belum datang
            $table->string('unit')->default('pcs');
            $table->text('notes')->nullable(); // Catatan inspeksi QC
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['quantity_received', 'quantity_rejected', 'moq', 'lead_time_days']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });
    }
};
