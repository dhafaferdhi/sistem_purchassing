<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fastener extends Model
{
    protected $fillable = [
        'year',
        'quarter',
        'supplier_id',
        'supplier_name',
        'vendor_code',
        'part_number',
        'old_part_number',
        'part_name',
        'model_name',
        'monthly_volume',
        'steel_spec',
        'wire_spec',
        'nett_weight_gr',
        'gross_weight_gr',
        'wire_price_usd_kg',
        'wire_price_idr_kg',
        'wire_price_pc',
        'material_control_percent',
        'material_control_cost',
        'washer_price',
        'washer_weight_gr',
        'material_total',
        'heading_cost',
        'rolling_cost',
        'washing_cost_kg',
        'washing_cost',
        'plating_cost_kg',
        'plating_cost',
        'inspection_cost',
        'packing_cost',
        'process_total',
        'subtotal',
        'overhead_self_percent',
        'overhead_self_cost',
        'tooling_depreciation',
        'tooling_details',
        'drawing_reference',
        'eci_number',
        'old_exchange_rate',
        'exchange_rate_fluctuation',
        'part_cost',
        'diameter',
        'double_thickness',
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
        'created_by',
    ];

    protected $attributes = [
        'year' => 2026,
        'quarter' => 'Q1',
        'model_name' => 'D26A',
        'monthly_volume' => 20000,
        'wire_spec' => 'WIRE OF RIVET [SWCH-45K]',
        'nett_weight_gr' => 4.4,
        'gross_weight_gr' => 4.84,
        'wire_price_usd_kg' => 1.66,
        'material_control_percent' => 5,
        'washer_price' => 198,
        'washer_weight_gr' => 4.5,
        'heading_cost' => 48,
        'rolling_cost' => 76,
        'washing_cost_kg' => 500,
        'washing_cost' => 4.45,
        'plating_cost_kg' => 10000,
        'plating_cost' => 89,
        'inspection_cost' => 5,
        'packing_cost' => 3,
        'process_total' => 225.45,
        'overhead_self_percent' => 14.3,
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
        'status' => 'Active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'monthly_volume' => 'integer',
            'nett_weight_gr' => 'decimal:4',
            'gross_weight_gr' => 'decimal:4',
            'wire_price_usd_kg' => 'decimal:4',
            'wire_price_idr_kg' => 'decimal:2',
            'wire_price_pc' => 'decimal:4',
            'material_control_percent' => 'decimal:2',
            'material_control_cost' => 'decimal:4',
            'washer_price' => 'decimal:2',
            'washer_weight_gr' => 'decimal:4',
            'material_total' => 'decimal:4',
            'heading_cost' => 'decimal:2',
            'rolling_cost' => 'decimal:2',
            'washing_cost_kg' => 'decimal:2',
            'washing_cost' => 'decimal:4',
            'plating_cost_kg' => 'decimal:2',
            'plating_cost' => 'decimal:4',
            'inspection_cost' => 'decimal:2',
            'packing_cost' => 'decimal:2',
            'process_total' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'overhead_self_percent' => 'decimal:2',
            'overhead_self_cost' => 'decimal:4',
            'old_exchange_rate' => 'decimal:2',
            'exchange_rate_fluctuation' => 'decimal:6',
            'tooling_depreciation' => 'decimal:4',
            'tooling_details' => 'array',
            'part_cost' => 'decimal:4',
            'exchange_rate' => 'decimal:4',
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
        static::saving(function (Fastener $model) {
            // Pastikan vendor_code dan supplier_name terisi otomatis jika supplier_id ada
            if (empty($model->vendor_code) && $model->supplier_id) {
                $supplier = $model->supplier ?? Supplier::find($model->supplier_id);
                if ($supplier) {
                    $model->vendor_code = $supplier->code;
                    if (empty($model->supplier_name)) {
                        $model->supplier_name = $supplier->name;
                    }
                }
            }

            // Pastikan currency_id selalu sinkron dengan year, quarter, dan currency_code
            $curr = $model->currency_id ? Currency::find($model->currency_id) : null;
            $needsMatch = ! $curr
                || $curr->year != $model->year
                || ($model->quarter && $curr->quarter != $model->quarter)
                || (strtoupper($curr->code) != strtoupper($model->currency_code ?: 'USD'));

            if ($needsMatch) {
                $matched = Currency::where('code', $model->currency_code ?: 'USD')
                    ->where('year', $model->year)
                    ->when($model->quarter, fn ($q) => $q->where('quarter', $model->quarter))
                    ->first()
                    ?? Currency::where('code', $model->currency_code ?: 'USD')
                        ->where('year', $model->year)
                        ->first()
                    ?? Currency::where('code', $model->currency_code ?: 'USD')->first()
                    ?? Currency::first();

                if ($matched) {
                    $model->currency_id = $matched->id;
                }
            }

            // Bersihkan teks seperti 'mm' dari double_thickness & diameter
            if ($model->double_thickness !== null && $model->double_thickness !== '') {
                $model->double_thickness = trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $model->double_thickness));
            }
            if ($model->diameter !== null && $model->diameter !== '') {
                $model->diameter = trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $model->diameter));
                if (empty($model->double_thickness)) {
                    $model->double_thickness = $model->diameter;
                }
            } elseif (! empty($model->double_thickness) && empty($model->diameter)) {
                $model->diameter = $model->double_thickness;
            }

            // Bersihkan angka ketebalan/diameter dan satuan mm dari steel_spec (misal: "SPCC-SD 1.2mm" -> "SPCC-SD")
            if (! empty($model->steel_spec)) {
                $model->steel_spec = static::cleanSteelSpec($model->steel_spec);
            } elseif (! empty($model->wire_spec)) {
                $model->steel_spec = static::cleanSteelSpec($model->wire_spec);
            } else {
                $model->steel_spec = 'SWCH';
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
            $rate = (float) ($model->exchange_rate ?? 1);

            // Perhitungan Rumus Gap Steel:
            // Gap / MT (USD) dan Gap / Kg (USD) [Rumus Perusahaan: Gap / Kg = Gap / MT / 1000]
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

            // Perhitungan Harga Coil & Sheet dalam Rupiah (IDR)
            $model->coil_price_idr = round($coilPrice * $rate, 2);
            $model->sheet_price_idr = round($sheetPrice * $rate, 2);

            // Perhitungan Biaya Wire, Washer, & Proses Fastener Sesuai Excel
            $wireUsdKg = (float) ($model->wire_price_usd_kg ?: ($coilPrice > 0 ? $coilPrice : 1.66));
            $model->wire_price_usd_kg = $wireUsdKg;
            $wireIdrKg = round($wireUsdKg * $rate, 2);
            $model->wire_price_idr_kg = $wireIdrKg;

            $grossGr = (float) ($model->gross_weight_gr ?? 4.84);
            $wirePc = round(($grossGr / 1000) * $wireIdrKg, 4);
            $model->wire_price_pc = $wirePc;

            $ctrlPct = (float) ($model->material_control_percent ?? 5);
            $model->material_control_cost = round(($ctrlPct / 100) * $wirePc, 4);

            $washerPrice = (float) ($model->washer_price ?? 198);
            $model->material_total = round($wirePc + $model->material_control_cost + $washerPrice, 4);

            $nettGr = (float) ($model->nett_weight_gr ?? 4.4);
            $washerGr = (float) ($model->washer_weight_gr ?? 4.5);
            $totalPartWeightKg = ($nettGr + $washerGr) / 1000;

            $washKg = (float) ($model->washing_cost_kg ?? 500);
            $model->washing_cost = round($totalPartWeightKg * $washKg, 4);

            $plateKg = (float) ($model->plating_cost_kg ?? 10000);
            $model->plating_cost = round($totalPartWeightKg * $plateKg, 4);

            $heading = (float) ($model->heading_cost ?? 48);
            $rolling = (float) ($model->rolling_cost ?? 76);
            $inspect = (float) ($model->inspection_cost ?? 5);
            $packing = (float) ($model->packing_cost ?? 3);

            $model->process_total = round(
                $heading + $rolling + $model->washing_cost + $model->plating_cost + $inspect + $packing,
                4
            );

            $model->subtotal = round((float) $model->material_total + (float) $model->process_total, 4);

            $ohSelfPct = (float) ($model->overhead_self_percent ?? 14.3);
            $model->overhead_self_cost = round(($ohSelfPct / 100) * (float) $model->subtotal, 4);

            if ($model->old_exchange_rate !== null && (float) $model->old_exchange_rate > 0 && $rate > 0) {
                $model->exchange_rate_fluctuation = round($rate / (float) $model->old_exchange_rate, 6);
            }

            $tooling = (float) ($model->tooling_depreciation ?? 0);
            $model->part_cost = round((float) $model->subtotal + (float) $model->overhead_self_cost + $tooling, 4);

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
     * Hitung kuartal dan tahun sebelumnya
     * Contoh: Q1 2027 -> Kuartal sebelumnya: Q4 2026
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
     * Mencari dari Fastener terlebih dahulu, kemudian fallback ke MaterialSteel atau StampingPart
     *
     * @return array<string, mixed>|null
     */
    public static function findPreviousQuarterData(string $quarter, int $year, ?int $supplierId = null, ?string $spec = null): ?array
    {
        $info = static::getPreviousQuarterInfo($quarter, $year);
        $prevQuarter = $info['prev_quarter'];
        $prevYear = $info['prev_year'];

        // 1. Coba cari di Fastener
        $query = static::where('year', $prevYear)->where('quarter', $prevQuarter);
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($spec) {
            $spec = static::cleanSteelSpec($spec);
            $query->where('steel_spec', $spec);
        }
        $record = $query->latest('id')->first();

        if (! $record && $supplierId) {
            $record = static::where('year', $prevYear)
                ->where('quarter', $prevQuarter)
                ->where('supplier_id', $supplierId)
                ->latest('id')
                ->first();
        }

        if ($record) {
            $gapUsd = (float) ($record->gap_steel_usd ?? 0);
            $gapKg = (float) ($record->gap_steel_kg_usd ?? 0);
            if ($gapKg == 0.0 && $gapUsd != 0.0) {
                $gapKg = round($gapUsd / 1000, 4);
            } elseif ($gapUsd == 0.0 && $gapKg != 0.0) {
                $gapUsd = round($gapKg * 1000, 4);
            }

            return [
                'source' => 'Fastener',
                'prev_year' => $prevYear,
                'prev_quarter' => $prevQuarter,
                'label' => $info['label'],
                'part_number' => $record->part_number,
                'old_part_number' => $record->old_part_number,
                'part_name' => $record->part_name,
                'model_name' => $record->model_name,
                'monthly_volume' => $record->monthly_volume,
                'steel_spec' => $record->steel_spec,
                'wire_spec' => $record->wire_spec,
                'double_thickness' => $record->double_thickness,
                'diameter' => $record->diameter ?: $record->double_thickness,
                'gap_steel_usd' => $gapUsd,
                'gap_steel_kg_usd' => $gapKg,
                'hr_steel_usd' => (float) ($record->hr_steel_usd ?? ($gapUsd != 0 && static::determineSteelType($record->steel_spec) === 'HR' ? $gapUsd : 0)),
                'cr_steel_usd' => (float) ($record->cr_steel_usd ?? ($gapUsd != 0 && static::determineSteelType($record->steel_spec) === 'CR' ? $gapUsd : 0)),
                'exchange_rate' => (float) $record->exchange_rate,
                'coil_price' => (float) $record->coil_price,
                'sheet_price' => (float) $record->sheet_price,
                'wire_price_usd_kg' => (float) ($record->wire_price_usd_kg ?? 0),
                'gross_weight_gr' => (float) ($record->gross_weight_gr ?? 4.84),
                'nett_weight_gr' => (float) ($record->nett_weight_gr ?? 4.40),
                'material_control_percent' => (float) ($record->material_control_percent ?? 5),
                'washer_price' => (float) ($record->washer_price ?? 198),
                'washer_weight_gr' => (float) ($record->washer_weight_gr ?? 4.5),
                'heading_cost' => (float) ($record->heading_cost ?? 48),
                'rolling_cost' => (float) ($record->rolling_cost ?? 76),
                'washing_cost_kg' => (float) ($record->washing_cost_kg ?? 500),
                'plating_cost_kg' => (float) ($record->plating_cost_kg ?? 10000),
                'inspection_cost' => (float) ($record->inspection_cost ?? 5),
                'packing_cost' => (float) ($record->packing_cost ?? 3),
                'overhead_self_percent' => (float) ($record->overhead_self_percent ?? 14.3),
                'drawing_reference' => $record->drawing_reference,
                'eci_number' => $record->eci_number,
                'tooling_depreciation' => (float) ($record->tooling_depreciation ?? 0),
                'old_exchange_rate' => (float) ($record->old_exchange_rate ?? $record->exchange_rate),
                'exchange_rate_fluctuation' => (float) ($record->exchange_rate_fluctuation ?? 1.0),
                'supplier_id' => $record->supplier_id,
                'vendor_code' => $record->vendor_code,
                'supplier_name' => $record->supplier_name,
            ];
        }

        // 2. Fallback ke MaterialSteel
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
                'diameter' => $steelRecord->double_thickness,
                'gap_steel_usd' => $gapUsd,
                'gap_steel_kg_usd' => $gapKg,
                'hr_steel_usd' => (float) ($steelRecord->hr_steel_usd ?? 0),
                'cr_steel_usd' => (float) ($steelRecord->cr_steel_usd ?? 0),
                'exchange_rate' => (float) $steelRecord->exchange_rate,
                'coil_price' => (float) $steelRecord->coil_price,
                'sheet_price' => (float) $steelRecord->sheet_price,
                'supplier_id' => $steelRecord->supplier_id,
                'vendor_code' => $steelRecord->vendor_code,
                'supplier_name' => $steelRecord->supplier_name,
            ];
        }

        return null;
    }

    /**
     * Tarik data acuan otomatis dari kuartal sebelumnya
     *
     * @return array<string, mixed>|null
     */
    public static function autoFillFromPreviousQuarter(?int $supplierId, ?string $spec, string $quarter, int $year): ?array
    {
        return static::findPreviousQuarterData($quarter, $year, $supplierId, $spec);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Bersihkan angka ketebalan/diameter dan satuan mm dari steel_spec (misal: "SPCC-SD 1.2mm" -> "SPCC-SD")
     */
    public static function cleanSteelSpec(?string $spec): ?string
    {
        return MaterialSteel::cleanSteelSpec($spec);
    }

    /**
     * Tentukan kategori material baja: Hot Rolled (HR) atau Cold Rolled (CR)
     */
    public static function determineSteelType(?string $spec): string
    {
        return MaterialSteel::determineSteelType($spec);
    }
}
