<?php

namespace App\Models;

use App\Services\QuarterlyPullHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class StampingPart extends Model
{
    protected $fillable = [
        'year',
        'quarter',
        'supplier_id',
        'supplier_name',
        'vendor_code',
        'part_id',
        'part_number',
        'part_name',
        'model_name',
        'monthly_volume',
        'steel_spec',
        'double_thickness',
        'wide',
        'pitch',
        'yield_rate',
        'size',
        'berat',
        'gross_material_cost',
        'scrap_weight',
        'scrap_price_per_kg',
        'scrap_cost',
        'material_total',
        'process_name',
        'process_man_hour',
        'process_charge',
        'process_total',
        'subtotal',
        'overhead_supplied_percent',
        'overhead_supplied_cost',
        'overhead_self_percent',
        'overhead_self_cost',
        'transportation_cost',
        'packing_cost',
        'part_cost_excluding_tooling',
        'tooling_depreciation',
        'tooling_details',
        'drawing_reference',
        'eci_number',
        'part_no_to_compare',
        'quotation_date',
        'exchange_rate_fluctuation',
        'part_cost',
        'currency_id',
        'currency_code',
        'gap_steel_usd',
        'hr_steel_usd',
        'cr_steel_usd',
        'gap_steel_kg_usd',
        'exchange_rate',
        'coil_price',
        'coil_price_idr',
        'sheet_price',
        'sheet_price_idr',
        'fluktuasi_jfe',
        'status',
        'notes',
        'comments',
        'remarks',
        'alteration',
        'etc_contents',
        'created_by',
    ];

    protected $attributes = [
        'year' => 2026,
        'quarter' => 'Q1',
        'model_name' => 'D26A',
        'monthly_volume' => 16500,
        'yield_rate' => 1,
        'scrap_price_per_kg' => -1500,
        'process_name' => '1. PROGRESSIVE 250T',
        'process_man_hour' => 9,
        'process_charge' => 45,
        'process_total' => 202.50,
        'overhead_supplied_percent' => 11,
        'overhead_self_percent' => 4,
        'transportation_cost' => 0,
        'packing_cost' => 0,
        'part_cost_excluding_tooling' => 0,
        'exchange_rate_fluctuation' => 1.0,
        'currency_code' => 'USD',
        'exchange_rate' => 16000,
        'gap_steel_usd' => 0,
        'hr_steel_usd' => 0,
        'cr_steel_usd' => 0,
        'gap_steel_kg_usd' => 0,
        'coil_price' => 0,
        'coil_price_idr' => 0,
        'sheet_price' => 0,
        'sheet_price_idr' => 0,
        'fluktuasi_jfe' => 0,
        'berat' => 0,
        'status' => 'Active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'monthly_volume' => 'integer',
            'quotation_date' => 'date',
            'wide' => 'decimal:2',
            'pitch' => 'decimal:2',
            'yield_rate' => 'decimal:2',
            'berat' => 'decimal:7',
            'gross_material_cost' => 'decimal:2',
            'scrap_weight' => 'decimal:4',
            'scrap_price_per_kg' => 'decimal:2',
            'scrap_cost' => 'decimal:2',
            'material_total' => 'decimal:2',
            'process_man_hour' => 'decimal:2',
            'process_charge' => 'decimal:2',
            'process_total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'overhead_supplied_percent' => 'decimal:2',
            'overhead_supplied_cost' => 'decimal:2',
            'overhead_self_percent' => 'decimal:2',
            'overhead_self_cost' => 'decimal:2',
            'transportation_cost' => 'decimal:2',
            'packing_cost' => 'decimal:2',
            'part_cost_excluding_tooling' => 'decimal:4',
            'tooling_depreciation' => 'decimal:4',
            'tooling_details' => 'array',
            'part_cost' => 'decimal:4',
            'exchange_rate' => 'decimal:4',
            'exchange_rate_fluctuation' => 'decimal:6',
            'gap_steel_usd' => 'decimal:4',
            'hr_steel_usd' => 'decimal:4',
            'cr_steel_usd' => 'decimal:4',
            'gap_steel_kg_usd' => 'decimal:4',
            'coil_price' => 'decimal:4',
            'coil_price_idr' => 'decimal:2',
            'sheet_price' => 'decimal:4',
            'sheet_price_idr' => 'decimal:2',
            'fluktuasi_jfe' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (StampingPart $model) {
            // Pastikan vendor_code dan supplier_name terisi otomatis jika supplier_id ada
            if (empty($model->vendor_code) && $model->supplier_id) {
                $supplier = $model->relationLoaded('supplier')
                    ? $model->getRelation('supplier')
                    : Supplier::find($model->supplier_id);
                if ($supplier) {
                    $model->vendor_code = $supplier->code;
                    if (empty($model->supplier_name)) {
                        $model->supplier_name = $supplier->name;
                    }
                }
            }

            // Pastikan currency_id selalu sinkron dengan year, quarter, dan currency_code
            $matchedCurrency = Currency::resolvePeriodMatch(
                $model->currency_id,
                $model->currency_code ?: 'USD',
                $model->year,
                $model->quarter
            );
            if ($matchedCurrency) {
                $model->currency_id = $matchedCurrency->id;
            }

            // Bersihkan teks seperti 'mm' dari double_thickness
            if ($model->double_thickness !== null && $model->double_thickness !== '') {
                $model->double_thickness = trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $model->double_thickness));
            }

            // Bersihkan angka ketebalan dan satuan mm dari steel_spec (misal: "SPCC-SD 1.2mm" -> "SPCC-SD")
            if (! empty($model->steel_spec)) {
                $model->steel_spec = static::cleanSteelSpec($model->steel_spec);
            }

            // Sinkronisasi HR / CR Dolar MT dengan gap_steel_usd
            $steelType = static::determineSteelType($model->steel_spec);
            $hasHr = $model->hr_steel_usd !== null && (float) $model->hr_steel_usd != 0.0;
            $hasCr = $model->cr_steel_usd !== null && (float) $model->cr_steel_usd != 0.0;

            if ($steelType === 'HR') {
                if ($hasHr) {
                    $model->gap_steel_usd = (float) $model->hr_steel_usd;
                } elseif ($hasCr) {
                    $model->gap_steel_usd = (float) $model->cr_steel_usd;
                } elseif ($model->gap_steel_usd !== null && (float) $model->gap_steel_usd != 0.0 && empty($model->hr_steel_usd)) {
                    $model->hr_steel_usd = (float) $model->gap_steel_usd;
                }
            } else {
                if ($hasCr) {
                    $model->gap_steel_usd = (float) $model->cr_steel_usd;
                } elseif ($hasHr) {
                    $model->gap_steel_usd = (float) $model->hr_steel_usd;
                } elseif ($model->gap_steel_usd !== null && (float) $model->gap_steel_usd != 0.0 && empty($model->cr_steel_usd)) {
                    $model->cr_steel_usd = (float) $model->gap_steel_usd;
                }
            }

            $sheetPrice = (float) ($model->sheet_price ?? 0);
            $coilPrice = (float) ($model->coil_price ?? 0);
            $rate = (float) ($model->exchange_rate ?? 0);

            // Perhitungan Rumus Gap Steel:
            // Gap / MT (USD) dan Gap / Kg (USD) [Rumus Excel: Gap / Kg = Gap / MT / 1000]
            if ($model->gap_steel_usd !== null && (float) $model->gap_steel_usd != 0.0) {
                if ($model->gap_steel_kg_usd === null || (float) $model->gap_steel_kg_usd == 0.0) {
                    $model->gap_steel_kg_usd = round((float) $model->gap_steel_usd / 1000, 4);
                }
            } elseif ($model->gap_steel_kg_usd !== null && (float) $model->gap_steel_kg_usd != 0.0) {
                $model->gap_steel_usd = round((float) $model->gap_steel_kg_usd * 1000, 4);
            } else {
                $model->gap_steel_usd = 0;
                $model->gap_steel_kg_usd = 0;
            }

            // Perhitungan Harga Coil & Sheet dalam Rupiah (IDR):
            // Hitung dari USD x Kurs jika harga USD & Kurs > 0; jika USD = 0 atau user input langsung IDR/kg, pertahankan nilai IDR tersebut.
            $dirtyDirectCoilIdr = $model->isDirty('coil_price_idr') && ! $model->isDirty(['coil_price', 'exchange_rate']);
            $dirtyDirectSheetIdr = $model->isDirty('sheet_price_idr') && ! $model->isDirty(['sheet_price', 'exchange_rate']);

            if ($coilPrice > 0 && $rate > 0 && ! $dirtyDirectCoilIdr) {
                $model->coil_price_idr = round($coilPrice * $rate, 2);
            } elseif ($coilPrice > 0 && $rate <= 0 && ($model->coil_price_idr === null || (float) $model->coil_price_idr == 0.0)) {
                $model->coil_price_idr = 0;
            }

            if ($sheetPrice > 0 && $rate > 0 && ! $dirtyDirectSheetIdr) {
                $model->sheet_price_idr = round($sheetPrice * $rate, 2);
            } elseif ($sheetPrice > 0 && $rate <= 0 && ($model->sheet_price_idr === null || (float) $model->sheet_price_idr == 0.0)) {
                $model->sheet_price_idr = 0;
            }

            // Perhitungan Biaya Material & Proses Stamping Part Sesuai Excel
            $berat = (float) ($model->berat ?? 0);

            // Auto calculate berat jika kosong atau dimensi/yield berubah pada update tanpa mengubah berat manual
            $shouldRecalcBerat = $berat <= 0.0
                || ($model->exists && $model->isDirty(['double_thickness', 'wide', 'pitch', 'yield_rate']) && ! $model->isDirty('berat'));

            if ($shouldRecalcBerat) {
                $thk = (float) ($model->double_thickness ?? 0);
                $wide = (float) ($model->wide ?? 0);
                $pitch = (float) ($model->pitch ?? 0);
                $yr = (float) ($model->yield_rate ?: 1);
                $yr = $yr > 0 ? $yr : 1;
                if ($thk > 0 && $wide > 0 && $pitch > 0) {
                    $berat = round(($thk * $wide * $pitch * 7.85) / (1000000 * $yr), 7);
                    $model->berat = $berat;
                }
            }

            $sheetIdrVal = (float) ($model->sheet_price_idr ?? 0);
            $coilIdrVal = (float) ($model->coil_price_idr ?? 0);
            $steelRate = $sheetIdrVal > 0
                ? $sheetIdrVal
                : ($coilIdrVal > 0
                    ? $coilIdrVal
                    : round(($sheetPrice > 0 ? $sheetPrice : $coilPrice) * $rate, 2));

            if ($berat > 0 && $steelRate > 0) {
                $model->gross_material_cost = round($berat * $steelRate, 2);
                $scrapWeight = (float) ($model->scrap_weight ?? 0);
                $scrapPrice = (float) ($model->scrap_price_per_kg ?? -1500);
                $model->scrap_cost = round($scrapWeight * $scrapPrice, 2);
                $model->material_total = round((float) $model->gross_material_cost + (float) $model->scrap_cost, 2);
            } else {
                $model->gross_material_cost = 0;
                $model->scrap_cost = 0;
                $model->material_total = 0;
            }

            $manHour = (float) ($model->process_man_hour ?? 9);
            $charge = (float) ($model->process_charge ?? 45);
            $yieldRate = (float) ($model->yield_rate ?: 1);
            $yieldRate = $yieldRate > 0 ? $yieldRate : 1;
            $model->process_total = round(($manHour * $charge) / $yieldRate, 2);

            $model->subtotal = round((float) $model->material_total + (float) $model->process_total, 2);

            $ohSuppliedPct = (float) ($model->overhead_supplied_percent ?? 11);
            $model->overhead_supplied_cost = round(($ohSuppliedPct / 100) * (float) $model->process_total, 2);

            $ohSelfPct = (float) ($model->overhead_self_percent ?? 4);
            $model->overhead_self_cost = round(($ohSelfPct / 100) * (float) $model->material_total, 2);

            $transCost = (float) ($model->transportation_cost ?? 0);
            $packCost = (float) ($model->packing_cost ?? 0);

            // (8) Part Cost (excluding tooling cost) = Subtotal + OH Supplied + OH Self + Transportation + Packing
            $model->part_cost_excluding_tooling = round(
                (float) $model->subtotal
                + (float) $model->overhead_supplied_cost
                + (float) $model->overhead_self_cost
                + $transCost
                + $packCost,
                4
            );

            // Jika ada rincian tooling_details, selalu sinkronkan total depresiasi tooling (termasuk update/reset)
            if (! empty($model->tooling_details) && is_array($model->tooling_details)) {
                $model->tooling_depreciation = QuarterlyPullHelper::sumToolingDepreciation($model->tooling_details);
            } elseif ($model->exists && $model->isDirty('tooling_details') && ! $model->isDirty('tooling_depreciation')) {
                $model->tooling_depreciation = 0;
            }

            $tooling = (float) ($model->tooling_depreciation ?? 0);

            // Total Final Part Cost = Part Cost (excluding tooling) + Die & Jig Depreciation
            $model->part_cost = round(
                (float) $model->part_cost_excluding_tooling + $tooling,
                4
            );

            // Auto-update exchange_rate_fluctuation jika kurs berubah tanpa ubah manual fluctuation
            if ($model->exists && $model->isDirty('exchange_rate') && ! $model->isDirty('exchange_rate_fluctuation')) {
                $origRate = (float) ($model->getOriginal('exchange_rate') ?? 0);
                if ($origRate > 0 && $rate > 0 && abs($origRate - $rate) > 0.0001) {
                    $model->exchange_rate_fluctuation = round($rate / $origRate, 6);
                }
            }

            // Sinkronisasi fluktuasi_jfe jika kosong
            if ((empty($model->fluktuasi_jfe) || (float) $model->fluktuasi_jfe == 0.0) && $model->gap_steel_kg_usd) {
                $model->fluktuasi_jfe = $model->gap_steel_kg_usd;
            }

            if (empty($model->created_by)) {
                $model->created_by = auth()->id() ?? User::first()?->id;
            }
        });
    }

    /**
     * Hitung kuartal dan tahun sebelumnya berdasarkan kuartal & tahun yang dipilih
     * Contoh: Q1 2027 -> Kuartal sebelumnya: Q4 2026
     *         Q2 2027 -> Kuartal sebelumnya: Q1 2027
     *
     * @return array{prev_quarter: string, prev_year: int, label: string}
     */
    public static function getPreviousQuarterInfo(string $quarter, int $year): array
    {
        $q = strtoupper(trim($quarter));

        return match ($q) {
            'Q1' => [
                'prev_quarter' => 'Q4',
                'prev_year' => $year - 1,
                'label' => 'Q4 '.($year - 1),
            ],
            'Q2' => [
                'prev_quarter' => 'Q1',
                'prev_year' => $year,
                'label' => 'Q1 '.$year,
            ],
            'Q3' => [
                'prev_quarter' => 'Q2',
                'prev_year' => $year,
                'label' => 'Q2 '.$year,
            ],
            'Q4' => [
                'prev_quarter' => 'Q3',
                'prev_year' => $year,
                'label' => 'Q3 '.$year,
            ],
            default => [
                'prev_quarter' => 'Q4',
                'prev_year' => $year - 1,
                'label' => 'Q4 '.($year - 1),
            ],
        };
    }

    /**
     * Cari data acuan dari kuartal sebelumnya (misal Q1 2027 mengambil dari Q4 2026)
     * Mencari dari StampingPart terlebih dahulu (utamakan part_number jika tersedia), kemudian fallback ke MaterialSteel
     */
    public static function findPreviousQuarterData(string $quarter, int $year, ?int $supplierId = null, ?string $spec = null, ?string $partNumber = null): ?array
    {
        $info = static::getPreviousQuarterInfo($quarter, $year);
        $prevQuarter = $info['prev_quarter'];
        $prevYear = $info['prev_year'];

        // 1. Coba cari di StampingPart
        $query = static::where('year', $prevYear)->where('quarter', $prevQuarter);
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($spec) {
            $spec = static::cleanSteelSpec($spec);
            $query->where('steel_spec', $spec);
        }

        if (! empty($partNumber)) {
            $cleanPart = trim($partNumber);
            $record = (clone $query)->where('part_number', $cleanPart)->latest('id')->first()
                ?? static::where('year', $prevYear)
                    ->where('quarter', $prevQuarter)
                    ->where('part_number', $cleanPart)
                    ->latest('id')
                    ->first();
        } else {
            $record = $query->latest('id')->first();

            // Jika tidak spesifik spec, cari berdasarkan supplier saja di kuartal sebelumnya
            if (! $record && $supplierId) {
                $record = static::where('year', $prevYear)
                    ->where('quarter', $prevQuarter)
                    ->where('supplier_id', $supplierId)
                    ->latest('id')
                    ->first();
            }
        }

        // Jika ditemukan di StampingPart
        if ($record) {
            $gapUsd = (float) ($record->gap_steel_usd ?? 0);
            $gapKg = (float) ($record->gap_steel_kg_usd ?? 0);
            if ($gapKg == 0.0 && $gapUsd != 0.0) {
                $gapKg = round($gapUsd / 1000, 4);
            } elseif ($gapUsd == 0.0 && $gapKg != 0.0) {
                $gapUsd = round($gapKg * 1000, 4);
            }

            return [
                'source' => 'StampingPart',
                'prev_year' => $prevYear,
                'prev_quarter' => $prevQuarter,
                'label' => $info['label'],
                'part_id' => $record->part_id,
                'part_number' => $record->part_number,
                'part_no_to_compare' => $record->part_no_to_compare,
                'part_name' => $record->part_name,
                'model_name' => $record->model_name,
                'monthly_volume' => $record->monthly_volume,
                'quotation_date' => $record->quotation_date?->format('Y-m-d'),
                'steel_spec' => $record->steel_spec,
                'double_thickness' => $record->double_thickness,
                'wide' => $record->wide,
                'pitch' => $record->pitch,
                'yield_rate' => $record->yield_rate,
                'size' => $record->size,
                'berat' => $record->berat,
                'gross_material_cost' => (float) $record->gross_material_cost,
                'scrap_weight' => (float) $record->scrap_weight,
                'scrap_price_per_kg' => (float) ($record->scrap_price_per_kg ?? -1500),
                'scrap_cost' => (float) $record->scrap_cost,
                'material_total' => (float) $record->material_total,
                'process_name' => $record->process_name,
                'process_man_hour' => (float) ($record->process_man_hour ?? 9),
                'process_charge' => (float) ($record->process_charge ?? 45),
                'process_total' => (float) $record->process_total,
                'subtotal' => (float) $record->subtotal,
                'overhead_supplied_percent' => (float) ($record->overhead_supplied_percent ?? 11),
                'overhead_supplied_cost' => (float) $record->overhead_supplied_cost,
                'overhead_self_percent' => (float) ($record->overhead_self_percent ?? 4),
                'overhead_self_cost' => (float) $record->overhead_self_cost,
                'transportation_cost' => (float) ($record->transportation_cost ?? 0),
                'packing_cost' => (float) ($record->packing_cost ?? 0),
                'part_cost_excluding_tooling' => (float) ($record->part_cost_excluding_tooling ?? 0),
                'tooling_depreciation' => (float) ($record->tooling_depreciation ?? 0),
                'tooling_details' => $record->tooling_details,
                'drawing_reference' => $record->drawing_reference,
                'eci_number' => $record->eci_number,
                'part_cost' => (float) $record->part_cost,
                'gap_steel_usd' => $gapUsd,
                'gap_steel_kg_usd' => $gapKg,
                'hr_steel_usd' => (float) ($record->hr_steel_usd ?? ($gapUsd != 0 && static::determineSteelType($record->steel_spec) === 'HR' ? $gapUsd : 0)),
                'cr_steel_usd' => (float) ($record->cr_steel_usd ?? ($gapUsd != 0 && static::determineSteelType($record->steel_spec) === 'CR' ? $gapUsd : 0)),
                'exchange_rate' => (float) $record->exchange_rate,
                'exchange_rate_fluctuation' => (float) ($record->exchange_rate_fluctuation ?? 1.0),
                'coil_price' => (float) $record->coil_price,
                'coil_price_idr' => (float) $record->coil_price_idr,
                'sheet_price' => (float) $record->sheet_price,
                'sheet_price_idr' => (float) $record->sheet_price_idr,
                'supplier_id' => $record->supplier_id,
                'vendor_code' => $record->vendor_code,
                'supplier_name' => $record->supplier_name,
                'comments' => $record->comments,
                'remarks' => $record->remarks,
                'alteration' => $record->alteration,
                'etc_contents' => $record->etc_contents,
                'notes' => $record->notes,
                'status' => $record->status,
            ];
        }

        // 2. Fallback: Coba cari di MaterialSteel untuk kuartal sebelumnya tersebut
        $steelQuery = MaterialSteel::where('year', $prevYear)->where('quarter', $prevQuarter);
        if ($supplierId) {
            $steelQuery->where('supplier_id', $supplierId);
        }
        if ($spec) {
            $steelQuery->where('steel_spec', $spec);
        }
        $steelRecord = $steelQuery->latest('id')->first();

        if (! $steelRecord && $supplierId) {
            $steelRecord = MaterialSteel::where('year', $prevYear)
                ->where('quarter', $prevQuarter)
                ->where('supplier_id', $supplierId)
                ->latest('id')
                ->first();
        }

        if (! $steelRecord) {
            // Coba ambil record kuartal sebelumnya secara umum jika ada
            $steelRecord = MaterialSteel::where('year', $prevYear)
                ->where('quarter', $prevQuarter)
                ->latest('id')
                ->first();
        }

        if ($steelRecord) {
            $gapUsd = (float) ($steelRecord->gap_steel_usd ?? 0);
            $gapKg = (float) ($steelRecord->gap_steel_kg_usd ?? 0);
            if ($gapKg == 0.0 && $gapUsd != 0.0) {
                $gapKg = round($gapUsd / 1000, 4);
            } elseif ($gapUsd == 0.0 && $gapKg != 0.0) {
                $gapUsd = round($gapKg * 1000, 4);
            }

            return [
                'source' => 'MaterialSteel',
                'prev_year' => $prevYear,
                'prev_quarter' => $prevQuarter,
                'label' => $info['label'],
                'steel_spec' => $steelRecord->steel_spec,
                'double_thickness' => $steelRecord->double_thickness,
                'size' => $steelRecord->size,
                'berat' => 0,
                'gap_steel_usd' => $gapUsd,
                'gap_steel_kg_usd' => $gapKg,
                'hr_steel_usd' => (float) ($steelRecord->hr_steel_usd ?? 0),
                'cr_steel_usd' => (float) ($steelRecord->cr_steel_usd ?? 0),
                'exchange_rate' => (float) $steelRecord->exchange_rate,
                'coil_price' => (float) $steelRecord->coil_price,
                'sheet_price' => (float) $steelRecord->sheet_price,
                'supplier_id' => null,
                'vendor_code' => null,
                'supplier_name' => null,
            ];
        }

        return null;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getWeightAttribute(): ?float
    {
        return $this->berat !== null ? (float) $this->berat : null;
    }

    public function setWeightAttribute($value): void
    {
        $this->attributes['berat'] = $value;
    }

    /**
     * Bersihkan angka ketebalan dan satuan mm dari steel_spec (misal: "SPCC-SD 1.2mm" -> "SPCC-SD")
     */
    public static function cleanSteelSpec(?string $spec): ?string
    {
        return MaterialSteel::cleanSteelSpec($spec);
    }

    /**
     * Dapatkan Sub-Assembly yang menggunakan part ini sebagai child component (BOM)
     *
     * @return Collection<int, SubAssemblyPart>
     */
    public function getUsedInSubAssembliesAttribute(): Collection
    {
        if (empty($this->part_number)) {
            return collect();
        }

        $cleanPartNo = trim((string) $this->part_number);
        $normalizedTarget = str_replace([' ', '-'], '', $cleanPartNo);
        $matches = collect();

        SubAssemblyPart::query()
            ->where('year', $this->year)
            ->where('quarter', $this->quarter)
            ->chunkById(200, function (Collection $subAssemblies) use ($cleanPartNo, $normalizedTarget, &$matches): void {
                foreach ($subAssemblies as $sa) {
                    if (! is_array($sa->material_breakdown)) {
                        continue;
                    }
                    foreach ($sa->material_breakdown as $item) {
                        $itemPart = trim((string) ($item['part_number'] ?? $item['part_no'] ?? ''));
                        if ($itemPart !== '' && (strcasecmp($itemPart, $cleanPartNo) === 0 || str_replace([' ', '-'], '', $itemPart) === $normalizedTarget)) {
                            $matches->push($sa);

                            break;
                        }
                    }
                }
            });

        return $matches->values();
    }

    /**
     * Tentukan kategori material baja: Hot Rolled (HR) atau Cold Rolled (CR)
     */
    public static function determineSteelType(?string $spec): string
    {
        return MaterialSteel::determineSteelType($spec);
    }
}
