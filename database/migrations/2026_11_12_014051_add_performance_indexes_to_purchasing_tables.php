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
        Schema::table('material_steels', function (Blueprint $table) {
            $table->index(['year', 'quarter', 'status'], 'idx_ms_year_quarter_status');
            $table->index(['year', 'quarter', 'currency_code'], 'idx_ms_year_quarter_currency');
            $table->index('steel_spec', 'idx_ms_steel_spec');
            $table->index('supplier_name', 'idx_ms_supplier_name');
            $table->index('currency_id', 'idx_ms_currency_id');
        });

        Schema::table('stamping_parts', function (Blueprint $table) {
            $table->index(['year', 'quarter', 'status'], 'idx_sp_year_quarter_status');
            $table->index(['year', 'quarter', 'currency_code'], 'idx_sp_year_quarter_currency');
            $table->index(['year', 'quarter', 'part_number'], 'idx_sp_year_quarter_part');
            $table->index('supplier_name', 'idx_sp_supplier_name');
            $table->index('steel_spec', 'idx_sp_steel_spec');
            $table->index('part_name', 'idx_sp_part_name');
            $table->index('currency_id', 'idx_sp_currency_id');
        });

        Schema::table('fasteners', function (Blueprint $table) {
            $table->index(['year', 'quarter', 'status'], 'idx_fst_year_quarter_status');
            $table->index(['year', 'quarter', 'currency_code'], 'idx_fst_year_quarter_currency');
            $table->index(['year', 'quarter', 'part_number'], 'idx_fst_year_quarter_part');
            $table->index('supplier_name', 'idx_fst_supplier_name');
            $table->index('vendor_code', 'idx_fst_vendor_code');
            $table->index('part_name', 'idx_fst_part_name');
            $table->index('currency_id', 'idx_fst_currency_id');
        });

        Schema::table('sub_assembly_parts', function (Blueprint $table) {
            $table->index(['year', 'quarter', 'status'], 'idx_sap_year_quarter_status');
            $table->index(['year', 'quarter', 'currency_code'], 'idx_sap_year_quarter_currency');
            $table->index('currency_id', 'idx_sap_currency_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['status', 'delivery_status'], 'idx_po_status_delivery');
            $table->index('order_date', 'idx_po_order_date');
            $table->index(['supplier_id', 'order_date'], 'idx_po_supplier_order_date');
        });

        Schema::table('pud_requests', function (Blueprint $table) {
            $table->index(['is_deleted', 'request_date', 'plant', 'status'], 'idx_pud_del_date_plant_status');
            $table->index(['is_deleted', 'status', 'created_at'], 'idx_pud_del_status_created');
            $table->index(['is_deleted', 'plant', 'division'], 'idx_pud_del_plant_div');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->index(['code', 'year', 'quarter'], 'idx_cur_code_year_quarter');
            $table->index(['year', 'quarter', 'month'], 'idx_cur_year_quarter_month');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index(['is_active', 'name'], 'idx_sup_active_name');
            $table->index('name', 'idx_sup_name');
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->index('item_code', 'idx_parts_item_code');
            $table->index('part_name', 'idx_parts_part_name');
            $table->index(['is_active', 'part_number'], 'idx_parts_active_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_steels', function (Blueprint $table) {
            $table->dropIndex('idx_ms_year_quarter_status');
            $table->dropIndex('idx_ms_year_quarter_currency');
            $table->dropIndex('idx_ms_steel_spec');
            $table->dropIndex('idx_ms_supplier_name');
            $table->dropIndex('idx_ms_currency_id');
        });

        Schema::table('stamping_parts', function (Blueprint $table) {
            $table->dropIndex('idx_sp_year_quarter_status');
            $table->dropIndex('idx_sp_year_quarter_currency');
            $table->dropIndex('idx_sp_year_quarter_part');
            $table->dropIndex('idx_sp_supplier_name');
            $table->dropIndex('idx_sp_steel_spec');
            $table->dropIndex('idx_sp_part_name');
            $table->dropIndex('idx_sp_currency_id');
        });

        Schema::table('fasteners', function (Blueprint $table) {
            $table->dropIndex('idx_fst_year_quarter_status');
            $table->dropIndex('idx_fst_year_quarter_currency');
            $table->dropIndex('idx_fst_year_quarter_part');
            $table->dropIndex('idx_fst_supplier_name');
            $table->dropIndex('idx_fst_vendor_code');
            $table->dropIndex('idx_fst_part_name');
            $table->dropIndex('idx_fst_currency_id');
        });

        Schema::table('sub_assembly_parts', function (Blueprint $table) {
            $table->dropIndex('idx_sap_year_quarter_status');
            $table->dropIndex('idx_sap_year_quarter_currency');
            $table->dropIndex('idx_sap_currency_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_po_status_delivery');
            $table->dropIndex('idx_po_order_date');
            $table->dropIndex('idx_po_supplier_order_date');
        });

        Schema::table('pud_requests', function (Blueprint $table) {
            $table->dropIndex('idx_pud_del_date_plant_status');
            $table->dropIndex('idx_pud_del_status_created');
            $table->dropIndex('idx_pud_del_plant_div');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropIndex('idx_cur_code_year_quarter');
            $table->dropIndex('idx_cur_year_quarter_month');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('idx_sup_active_name');
            $table->dropIndex('idx_sup_name');
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->dropIndex('idx_parts_item_code');
            $table->dropIndex('idx_parts_part_name');
            $table->dropIndex('idx_parts_active_number');
        });
    }
};
