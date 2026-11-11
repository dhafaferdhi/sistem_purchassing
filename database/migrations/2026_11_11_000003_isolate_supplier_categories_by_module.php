<?php

use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $materialSteelSupplierIds = MaterialSteel::query()
            ->whereNotNull('supplier_id')
            ->distinct()
            ->pluck('supplier_id')
            ->all();

        $stampingOnlyIds = StampingPart::query()
            ->whereNotNull('supplier_id')
            ->whereNotIn('supplier_id', $materialSteelSupplierIds)
            ->distinct()
            ->pluck('supplier_id')
            ->all();

        if (! empty($stampingOnlyIds)) {
            Supplier::whereIn('id', $stampingOnlyIds)
                ->where('category', 'Steel')
                ->update(['category' => 'Stamping & Steel']);
        }

        Supplier::where('name', 'PT. POSMI STEEL INDONESIA')
            ->whereNotIn('id', $materialSteelSupplierIds)
            ->where('category', 'Steel')
            ->update(['category' => 'Stamping & Steel']);

        $fastenerOnlyIds = Fastener::query()
            ->whereNotNull('supplier_id')
            ->whereNotIn('supplier_id', $materialSteelSupplierIds)
            ->distinct()
            ->pluck('supplier_id')
            ->all();

        if (! empty($fastenerOnlyIds)) {
            Supplier::whereIn('id', $fastenerOnlyIds)
                ->whereIn('category', ['Steel', 'General'])
                ->update(['category' => 'Fastener']);
        }
    }

    public function down(): void
    {
        // Non-destructive category isolation
    }
};
