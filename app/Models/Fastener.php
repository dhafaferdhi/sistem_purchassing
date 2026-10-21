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
        'steel_spec',
        'diameter',
        'double_thickness',
        'currency_id',
        'currency_code',
        'gap_steel_usd',
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
        'currency_code' => 'USD',
        'exchange_rate' => 16000,
        'gap_steel_usd' => 0,
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
            'exchange_rate' => 'decimal:4',
            'gap_steel_usd' => 'decimal:4',
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
            } elseif ($sheetPrice > 0 || $coilPrice > 0) {
                $model->gap_steel_kg_usd = round($sheetPrice - $coilPrice, 4);
                $model->gap_steel_usd = round(($sheetPrice - $coilPrice) * 1000, 4);
            }

            // Perhitungan Harga Coil & Sheet dalam Rupiah (IDR)
            $model->coil_price_idr = round($coilPrice * $rate, 2);
            $model->sheet_price_idr = round($sheetPrice * $rate, 2);

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
            } elseif ($gapKg == 0.0 && $gapUsd == 0.0 && ((float) ($record->sheet_price ?? 0) > 0 || (float) ($record->coil_price ?? 0) > 0)) {
                $gapKg = round((float) ($record->sheet_price ?? 0) - (float) ($record->coil_price ?? 0), 4);
                $gapUsd = round($gapKg * 1000, 4);
            }

            return [
                'source' => 'Pasterner',
                'prev_year' => $prevYear,
                'prev_quarter' => $prevQuarter,
                'label' => $info['label'],
                'steel_spec' => $record->steel_spec,
                'double_thickness' => $record->double_thickness,
                'diameter' => $record->diameter ?: $record->double_thickness,
                'gap_steel_usd' => $gapUsd,
                'gap_steel_kg_usd' => $gapKg,
                'exchange_rate' => (float) $record->exchange_rate,
                'coil_price' => (float) $record->coil_price,
                'sheet_price' => (float) $record->sheet_price,
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
}
