<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Part;
use App\Models\PartQuotation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaterialMassUpdateService
{
    /**
     * Dapatkan semua Part yang menggunakan material tertentu (Where-Used)
     */
    public static function getWhereUsedParts(Material $material): Collection
    {
        return Part::with(['material', 'partQuotations.supplier', 'parentBoms.parentPart'])
            ->where('material_id', $material->id)
            ->get();
    }

    /**
     * Lakukan Mass Update kenaikan harga material dan sinkronisasi ke seluruh Part terdampak
     *
     * @param  Material  $material  Material yang mengalami kenaikan harga
     * @param  float  $percentageChange  Persentase kenaikan/penurunan (misal: 10 untuk +10%)
     * @param  array  $selectedPartIds  Daftar ID part yang dipilih (atau kosong untuk semua)
     * @return array Ringkasan hasil update
     */
    public static function massUpdatePrice(
        Material $material,
        float $percentageChange,
        array $selectedPartIds = []
    ): array {
        return DB::transaction(function () use ($material, $percentageChange, $selectedPartIds) {
            $oldRate = (float) $material->basic_rate;
            $multiplier = 1 + ($percentageChange / 100);
            $newRate = round($oldRate * $multiplier, 4);

            // 1. Update basic rate material
            $material->update(['basic_rate' => $newRate]);

            // 2. Cari part yang terdampak (Where-Used)
            $query = Part::where('material_id', $material->id);
            if (! empty($selectedPartIds)) {
                $query->whereIn('id', $selectedPartIds);
            }
            $affectedParts = $query->get();

            $updatedQuotationsCount = 0;

            // 3. Update setiap Part Quotation yang terdampak
            foreach ($affectedParts as $part) {
                $quotations = PartQuotation::where('part_id', $part->id)
                    ->where('status', 'active')
                    ->get();

                foreach ($quotations as $quotation) {
                    // Update biaya material dasar sesuai persentase perubahan
                    $oldMatCost = (float) $quotation->material_cost_original;
                    $newMatCost = round($oldMatCost * $multiplier, 4);

                    $quotation->material_cost_original = $newMatCost;
                    $quotation->recalculateTotals();
                    $quotation->save();

                    $updatedQuotationsCount++;
                }
            }

            return [
                'material_id' => $material->id,
                'material_spec' => $material->spec,
                'old_basic_rate' => $oldRate,
                'new_basic_rate' => $newRate,
                'percentage_change' => $percentageChange,
                'affected_parts_count' => $affectedParts->count(),
                'updated_quotations_count' => $updatedQuotationsCount,
            ];
        });
    }
}
