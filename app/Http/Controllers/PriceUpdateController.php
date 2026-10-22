<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMaterialPriceRequest;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PriceUpdateController extends Controller
{
    /**
     * Tampilkan form penyesuaian harga material baja.
     */
    public function index(): View
    {
        $steelMaterials = Material::where('is_active', true)
            ->where(function ($query) {
                $query->where('main_category', 'Steel')
                    ->orWhereNull('main_category')
                    ->orWhere('category', 'Presspart');
            })
            ->orderBy('spec')
            ->get();

        return view('purchasing.price-update', [
            'materials' => $steelMaterials,
        ]);
    }

    /**
     * Update harga material baja secara massal (Bulk Update) berdasarkan input penyesuaian MT.
     */
    public function update(UpdateMaterialPriceRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $kursIdr = (float) $validated['kurs_idr'];
        $hrAdjMtUsd = (float) $validated['hr_adj_mt_usd'];
        $crAdjMtUsd = (float) $validated['cr_adj_mt_usd'];

        // 1. Konversi Satuan MT ke KG (USD)
        $hrAdjKgUsd = $hrAdjMtUsd / 1000;
        $crAdjKgUsd = $crAdjMtUsd / 1000;

        $updatedDetails = [];
        $hrCount = 0;
        $crCount = 0;

        DB::transaction(function () use (
            $kursIdr,
            $hrAdjKgUsd,
            $crAdjKgUsd,
            &$updatedDetails,
            &$hrCount,
            &$crCount
        ) {
            // Ambil seluruh material aktif kategori Steel atau berawalan SPH/SPC
            $materials = Material::where('is_active', true)
                ->where(function ($query) {
                    $query->where('main_category', 'Steel')
                        ->orWhereNull('main_category')
                        ->orWhere('category', 'Presspart');
                })
                ->lockForUpdate()
                ->get();

            foreach ($materials as $material) {
                $categoryType = $this->resolveSteelCategory($material);

                if ($categoryType === null) {
                    continue;
                }

                $hargaDasarUsd = (float) $material->basic_rate;

                // 2. Kalkulasi Harga Baru (USD) berdasarkan Kategori Material
                if ($categoryType === 'HR') {
                    $hargaBaruUsd = round($hargaDasarUsd + $hrAdjKgUsd, 4);
                    $hrCount++;
                } else {
                    $hargaBaruUsd = round($hargaDasarUsd + $crAdjKgUsd, 4);
                    $crCount++;
                }

                // 3. Konversi Harga Akhir ke IDR
                $hargaBaruIdr = round($hargaBaruUsd * $kursIdr, 2);

                // Update ke database
                $material->update([
                    'basic_rate' => $hargaBaruUsd,
                    'price_idr' => $hargaBaruIdr,
                ]);

                $updatedDetails[] = [
                    'id' => $material->id,
                    'spec' => $material->spec,
                    'category_type' => $categoryType,
                    'harga_dasar_usd' => $hargaDasarUsd,
                    'harga_baru_usd' => $hargaBaruUsd,
                    'harga_baru_idr' => $hargaBaruIdr,
                ];
            }
        });

        $summary = [
            'success' => true,
            'message' => 'Harga material baja berhasil diperbarui secara massal.',
            'parameters' => [
                'kurs_idr' => $kursIdr,
                'hr_adj_mt_usd' => $hrAdjMtUsd,
                'hr_adj_kg_usd' => $hrAdjKgUsd,
                'cr_adj_mt_usd' => $crAdjMtUsd,
                'cr_adj_kg_usd' => $crAdjKgUsd,
            ],
            'total_updated' => count($updatedDetails),
            'hr_updated_count' => $hrCount,
            'cr_updated_count' => $crCount,
            'details' => $updatedDetails,
        ];

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json($summary);
        }

        return redirect()->back()
            ->with('success', "Harga material berhasil diperbarui ({$hrCount} HR, {$crCount} CR).")
            ->with('summary', $summary);
    }

    /**
     * Preview kalkulasi penyesuaian harga sebelum disimpan ke database.
     */
    public function preview(UpdateMaterialPriceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kursIdr = (float) $validated['kurs_idr'];
        $hrAdjMtUsd = (float) $validated['hr_adj_mt_usd'];
        $crAdjMtUsd = (float) $validated['cr_adj_mt_usd'];

        $hrAdjKgUsd = $hrAdjMtUsd / 1000;
        $crAdjKgUsd = $crAdjMtUsd / 1000;

        $materials = Material::where('is_active', true)
            ->where(function ($query) {
                $query->where('main_category', 'Steel')
                    ->orWhereNull('main_category')
                    ->orWhere('category', 'Presspart');
            })
            ->get();

        $previewData = [];
        $hrCount = 0;
        $crCount = 0;

        foreach ($materials as $material) {
            $categoryType = $this->resolveSteelCategory($material);

            if ($categoryType === null) {
                continue;
            }

            $hargaDasarUsd = (float) $material->basic_rate;
            $adjKg = ($categoryType === 'HR') ? $hrAdjKgUsd : $crAdjKgUsd;
            $hargaBaruUsd = round($hargaDasarUsd + $adjKg, 4);
            $hargaBaruIdr = round($hargaBaruUsd * $kursIdr, 2);

            if ($categoryType === 'HR') {
                $hrCount++;
            } else {
                $crCount++;
            }

            $previewData[] = [
                'id' => $material->id,
                'spec' => $material->spec,
                'category_type' => $categoryType,
                'harga_dasar_usd' => $hargaDasarUsd,
                'penyesuaian_kg_usd' => $adjKg,
                'harga_baru_usd' => $hargaBaruUsd,
                'harga_baru_idr' => $hargaBaruIdr,
            ];
        }

        return response()->json([
            'kurs_idr' => $kursIdr,
            'hr_adj_kg_usd' => $hrAdjKgUsd,
            'cr_adj_kg_usd' => $crAdjKgUsd,
            'total_items' => count($previewData),
            'hr_count' => $hrCount,
            'cr_count' => $crCount,
            'preview' => $previewData,
        ]);
    }

    /**
     * Klasifikasikan material ke kategori Hot Rolled (HR) atau Cold Rolled (CR).
     */
    protected function resolveSteelCategory(Material $material): ?string
    {
        $spec = strtoupper(trim((string) $material->spec));
        $subCategory = strtoupper(trim((string) $material->sub_category));
        $category = strtoupper(trim((string) $material->category));

        // Hot Rolled (HR): Seri SPH (SPHC, SPHD, SPHE, dsb.), SAPH, atau tag kategori HR
        if (
            str_starts_with($spec, 'SPH') ||
            str_starts_with($spec, 'SAPH') ||
            $subCategory === 'HR' ||
            $subCategory === 'HOT ROLLED' ||
            $category === 'HR'
        ) {
            return 'HR';
        }

        // Cold Rolled (CR): Seri SPC (SPCC, SPCD, SPCE, dsb.), atau tag kategori CR
        if (
            str_starts_with($spec, 'SPC') ||
            $subCategory === 'CR' ||
            $subCategory === 'COLD ROLLED' ||
            $category === 'CR'
        ) {
            return 'CR';
        }

        return null;
    }
}
