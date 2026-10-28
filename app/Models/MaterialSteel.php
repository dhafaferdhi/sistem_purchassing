<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialSteel extends Model
{
    public static bool $disableQuarterlyCascade = false;

    public int $lastCascadedCount = 0;

    protected $fillable = [
        'year',
        'quarter',
        'supplier_id',
        'vendor_code',
        'supplier_name',
        'material_id',
        'steel_spec',
        'double_thickness',
        'size',
        'currency_id',
        'currency_code',
        'steel_price',
        'exchange_rate',
        'steel_price_idr',
        'coil_price',
        'coil_price_idr',
        'sheet_price',
        'sheet_price_idr',
        'ap_coil_price',
        'ap_coil_price_idr',
        'ap_sheet_price',
        'ap_sheet_price_idr',
        'hr_steel_usd',
        'cr_steel_usd',
        'gap_steel_usd',
        'gap_steel_kg_usd',
        'gap_steel_idr',
        'fluktuasi_jfe',
        'prev_month_1_label',
        'prev_month_1_price',
        'prev_month_1_rate',
        'prev_month_2_label',
        'prev_month_2_price',
        'prev_month_2_rate',
        'prev_month_3_label',
        'prev_month_3_price',
        'prev_month_3_rate',
        'avg_3_months_price',
        'avg_3_months_rate',
        'avg_3_months_price_idr',
        'price_variance_percentage',
        'status',
        'notes',
        'created_by',
    ];

    protected $attributes = [
        'year' => 2026,
        'quarter' => 'Q1',
        'currency_code' => 'USD',
        'steel_price' => 0,
        'exchange_rate' => 16000,
        'steel_price_idr' => 0,
        'coil_price' => 0,
        'coil_price_idr' => 0,
        'sheet_price' => 0,
        'sheet_price_idr' => 0,
        'gap_steel_idr' => 0,
        'fluktuasi_jfe' => 0,
        'status' => 'Active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'steel_price' => 'decimal:4',
            'exchange_rate' => 'decimal:4',
            'steel_price_idr' => 'decimal:2',
            'coil_price' => 'decimal:4',
            'coil_price_idr' => 'decimal:2',
            'sheet_price' => 'decimal:4',
            'sheet_price_idr' => 'decimal:2',
            'ap_coil_price' => 'decimal:4',
            'ap_coil_price_idr' => 'decimal:2',
            'ap_sheet_price' => 'decimal:4',
            'ap_sheet_price_idr' => 'decimal:2',
            'hr_steel_usd' => 'decimal:4',
            'cr_steel_usd' => 'decimal:4',
            'gap_steel_usd' => 'decimal:4',
            'gap_steel_kg_usd' => 'decimal:4',
            'gap_steel_idr' => 'decimal:2',
            'fluktuasi_jfe' => 'decimal:4',
            'prev_month_1_price' => 'decimal:4',
            'prev_month_1_rate' => 'decimal:4',
            'prev_month_2_price' => 'decimal:4',
            'prev_month_2_rate' => 'decimal:4',
            'prev_month_3_price' => 'decimal:4',
            'prev_month_3_rate' => 'decimal:4',
            'avg_3_months_price' => 'decimal:4',
            'avg_3_months_rate' => 'decimal:4',
            'avg_3_months_price_idr' => 'decimal:2',
            'price_variance_percentage' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (MaterialSteel $model) {
            if (static::$disableQuarterlyCascade) {
                return;
            }

            if ($model->wasChanged([
                'coil_price',
                'sheet_price',
                'ap_coil_price',
                'ap_sheet_price',
                'gap_steel_usd',
                'gap_steel_kg_usd',
                'hr_steel_usd',
                'cr_steel_usd',
            ])) {
                $model->cascadeToNextQuarters();
            }
        });

        static::saving(function (MaterialSteel $model) {
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

            // Otomatis sinkronkan vendor_code dan supplier_name dari supplier_id jika belum terisi
            if ($model->supplier_id && (empty($model->vendor_code) || empty($model->supplier_name))) {
                $supp = Supplier::find($model->supplier_id);
                if ($supp) {
                    $model->vendor_code = $model->vendor_code ?: $supp->code;
                    $model->supplier_name = $model->supplier_name ?: $supp->name;
                }
            }

            // Bersihkan satuan teks seperti 'mm' dari double_thickness agar murni angka
            if ($model->double_thickness !== null && $model->double_thickness !== '') {
                $model->double_thickness = trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $model->double_thickness));
            }

            // Bersihkan angka ketebalan dan satuan mm dari steel_spec (misal: "SPCC-SD 1.2mm" -> "SPCC-SD")
            if (! empty($model->steel_spec)) {
                $model->steel_spec = static::cleanSteelSpec($model->steel_spec);
            }

            $sheetPrice = (float) ($model->sheet_price ?? 0);
            $coilPrice = (float) ($model->coil_price ?? 0);
            $rate = (float) ($model->exchange_rate ?? 1);

            // Sinkronisasi nilai HR Dolar/MT & CR Dolar/MT
            $steelType = static::determineSteelType($model->steel_spec);
            $hasHr = $model->hr_steel_usd !== null && (float) $model->hr_steel_usd != 0.0;
            $hasCr = $model->cr_steel_usd !== null && (float) $model->cr_steel_usd != 0.0;

            if ($steelType === 'HR') {
                if ($hasHr) {
                    $model->gap_steel_usd = (float) $model->hr_steel_usd;
                } elseif ($hasCr) {
                    $model->gap_steel_usd = (float) $model->cr_steel_usd;
                } elseif ($model->gap_steel_usd !== null && (float) $model->gap_steel_usd != 0.0) {
                    $model->hr_steel_usd = (float) $model->gap_steel_usd;
                }
            } else {
                if ($hasCr) {
                    $model->gap_steel_usd = (float) $model->cr_steel_usd;
                } elseif ($hasHr) {
                    $model->gap_steel_usd = (float) $model->hr_steel_usd;
                } elseif ($model->gap_steel_usd !== null && (float) $model->gap_steel_usd != 0.0) {
                    $model->cr_steel_usd = (float) $model->gap_steel_usd;
                }
            }

            // Perhitungan Rumus Gap Steel:
            // Gap / MT (USD) dan Gap / Kg (USD) [Rumus Excel: Gap / Kg = Gap / MT / 1000]
            if ($model->gap_steel_usd !== null && (float) $model->gap_steel_usd != 0.0) {
                $model->gap_steel_kg_usd = round((float) $model->gap_steel_usd / 1000, 4);
            } elseif ($model->gap_steel_kg_usd !== null && (float) $model->gap_steel_kg_usd != 0.0) {
                $model->gap_steel_usd = round((float) $model->gap_steel_kg_usd * 1000, 4);
            } else {
                $model->gap_steel_usd = 0;
                $model->gap_steel_kg_usd = 0;
            }

            // Jika harga coil/sheet kosong (misal create dengan Gap saja), tarik dari kuartal sebelumnya:
            // Rumus: Harga Baru ($) = Harga Kuartal Lalu ($) + (Gap ÷ 1.000)
            if (($coilPrice == 0.0 && $sheetPrice == 0.0) && $model->quarter && $model->year) {
                $prev = static::findPreviousQuarterData(
                    $model->quarter,
                    $model->year,
                    $model->supplier_id,
                    $model->steel_spec,
                    $model->double_thickness
                );
                if ($prev) {
                    $appliedKg = (float) $model->gap_steel_kg_usd;
                    if (! empty($prev['coil_price']) && (float) $prev['coil_price'] > 0) {
                        $coilPrice = round((float) $prev['coil_price'] + $appliedKg, 4);
                        $model->coil_price = $coilPrice;
                    }
                    if (! empty($prev['sheet_price']) && (float) $prev['sheet_price'] > 0) {
                        $sheetPrice = round((float) $prev['sheet_price'] + $appliedKg, 4);
                        $model->sheet_price = $sheetPrice;
                    }
                    if (! empty($prev['ap_coil_price']) && (float) $prev['ap_coil_price'] > 0) {
                        $model->ap_coil_price = round((float) $prev['ap_coil_price'] + $appliedKg, 4);
                    }
                    if (! empty($prev['ap_sheet_price']) && (float) $prev['ap_sheet_price'] > 0) {
                        $model->ap_sheet_price = round((float) $prev['ap_sheet_price'] + $appliedKg, 4);
                    }
                }
            }

            $model->gap_steel_idr = round((float) $model->gap_steel_usd * $rate, 2);

            // Sinkronkan fluktuasi_jfe jika kosong
            if ((empty($model->fluktuasi_jfe) || (float) $model->fluktuasi_jfe == 0.0) && $model->gap_steel_kg_usd) {
                $model->fluktuasi_jfe = $model->gap_steel_kg_usd;
            }

            // Kalkulasi harga Coil & Sheet IDR (Rumus: Harga Baru ($) x Kurs IDR)
            $model->coil_price_idr = round($coilPrice * $rate, 2);
            $model->sheet_price_idr = round($sheetPrice * $rate, 2);

            // Kalkulasi harga AP Coil & Sheet (Otomatis terisi samakan dengan rumus Material Steel)
            if (empty($model->ap_coil_price) || (float) $model->ap_coil_price <= 0.0) {
                $model->ap_coil_price = $coilPrice;
            }
            if (empty($model->ap_sheet_price) || (float) $model->ap_sheet_price <= 0.0) {
                $model->ap_sheet_price = $sheetPrice;
            }

            $model->ap_coil_price_idr = round((float) $model->ap_coil_price * $rate, 2);
            $model->ap_sheet_price_idr = round((float) $model->ap_sheet_price * $rate, 2);

            // Fallback steel_price agar kompatibilitas modul lain tetap sinkron
            if (empty($model->steel_price) || (float) $model->steel_price === 0.0) {
                $model->steel_price = $coilPrice ?: $sheetPrice ?: 0;
            }
            $model->steel_price_idr = round((float) $model->steel_price * $rate, 2);

            // Hitung rata-rata 3 bulan ke belakang jika tersedia
            $prices = array_filter([
                $model->prev_month_1_price,
                $model->prev_month_2_price,
                $model->prev_month_3_price,
            ], fn ($v) => $v !== null && $v > 0);

            $rates = array_filter([
                $model->prev_month_1_rate,
                $model->prev_month_2_rate,
                $model->prev_month_3_rate,
            ], fn ($v) => $v !== null && $v > 0);

            if (! empty($prices)) {
                $avgPrice = round(array_sum($prices) / count($prices), 4);
                $model->avg_3_months_price = $avgPrice;

                if (! empty($rates)) {
                    $avgRate = round(array_sum($rates) / count($rates), 4);
                    $model->avg_3_months_rate = $avgRate;
                    $model->avg_3_months_price_idr = round($avgPrice * $avgRate, 2);
                }

                if ($avgPrice > 0) {
                    $benchmark = (float) ($model->coil_price > 0 ? $model->coil_price : $model->steel_price);
                    $model->price_variance_percentage = round((($benchmark - $avgPrice) / $avgPrice) * 100, 2);
                }
            }

            if (empty($model->created_by)) {
                $model->created_by = auth()->id() ?? User::first()?->id;
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Hitung label 3 bulan ke belakang berdasarkan kuartal & tahun yang dipilih
     *
     * @return array{
     *     prev_quarter: string,
     *     prev_year: int,
     *     month_1: string,
     *     month_2: string,
     *     month_3: string
     * }
     */
    public static function getPrevious3MonthsInfo(string $quarter, int $year): array
    {
        return match (strtoupper($quarter)) {
            'Q1' => [
                'prev_quarter' => 'Q4',
                'prev_year' => $year - 1,
                'month_1' => 'Desember '.($year - 1),
                'month_2' => 'November '.($year - 1),
                'month_3' => 'Oktober '.($year - 1),
            ],
            'Q2' => [
                'prev_quarter' => 'Q1',
                'prev_year' => $year,
                'month_1' => 'Maret '.$year,
                'month_2' => 'Februari '.$year,
                'month_3' => 'Januari '.$year,
            ],
            'Q3' => [
                'prev_quarter' => 'Q2',
                'prev_year' => $year,
                'month_1' => 'Juni '.$year,
                'month_2' => 'Mei '.$year,
                'month_3' => 'April '.$year,
            ],
            'Q4' => [
                'prev_quarter' => 'Q3',
                'prev_year' => $year,
                'month_1' => 'September '.$year,
                'month_2' => 'Agustus '.$year,
                'month_3' => 'Juli '.$year,
            ],
            default => [
                'prev_quarter' => 'Q4',
                'prev_year' => $year - 1,
                'month_1' => 'Bulan M-1',
                'month_2' => 'Bulan M-2',
                'month_3' => 'Bulan M-3',
            ],
        };
    }

    /**
     * Bersihkan angka ketebalan dan satuan mm dari steel_spec (misal: "SPCC-SD 1.2mm" -> "SPCC-SD", "SPH270-OD 2.0mm" -> "SPH270-OD")
     */
    public static function cleanSteelSpec(?string $spec): ?string
    {
        if ($spec === null) {
            return null;
        }

        $spec = trim($spec);
        // Hapus ketebalan di dalam tanda kurung di akhir, contoh: "SPCC-SD (1.2mm)" atau "SPCC-SD (1.2)"
        $spec = preg_replace('/\s*\(\s*\d+(?:\.\d+)?\s*(?:mm)?\s*\)\s*$/i', '', $spec);
        // Hapus trailing angka ketebalan dan satuan mm setelah spasi atau tanda hubung, contoh: "SPCC-SD 1.2mm", "SPCC-SD 1.2 mm", "SPCC-SD 1.2", "SPCC-SD - 1.2mm"
        $spec = preg_replace('/(?:\s+|-|\s*–\s*|\s*—\s*)\s*\d+(?:\.\d+)?\s*(?:mm)?\s*$/i', '', $spec);
        // Hapus trailing satuan mm berdiri sendiri setelah spasi, contoh: "SPCC-SD mm"
        $spec = preg_replace('/\s+mm\s*$/i', '', $spec);

        return trim($spec);
    }

    /**
     * Tentukan klasifikasi kategori baja: Hot Rolled (HR) atau Cold Rolled (CR).
     */
    public static function determineSteelType(?string $spec): string
    {
        $clean = strtoupper(trim((string) $spec));

        // Hot Rolled: seri SPH (SPHC, SPHD, SPHE, SPH270, SPH440, SPH590), SAPH
        if (str_starts_with($clean, 'SPH') || str_starts_with($clean, 'SAPH')) {
            return 'HR';
        }

        // Cold Rolled: seri SPC (SPCC, SPCD, SPCE, SPC270, SPC440, SPC590, SPC980), NSC, dsb.
        return 'CR';
    }

    public function isHotRolled(): bool
    {
        return static::determineSteelType($this->steel_spec) === 'HR';
    }

    public function isColdRolled(): bool
    {
        return static::determineSteelType($this->steel_spec) === 'CR';
    }

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

    public static function getNextQuarterInfo(string $quarter, int $year): array
    {
        $q = strtoupper(trim($quarter));

        return match ($q) {
            'Q1' => [
                'next_quarter' => 'Q2',
                'next_year' => $year,
                'label' => 'Q2 '.$year,
            ],
            'Q2' => [
                'next_quarter' => 'Q3',
                'next_year' => $year,
                'label' => 'Q3 '.$year,
            ],
            'Q3' => [
                'next_quarter' => 'Q4',
                'next_year' => $year,
                'label' => 'Q4 '.$year,
            ],
            default => [
                'next_quarter' => 'Q1',
                'next_year' => $year + 1,
                'label' => 'Q1 '.($year + 1),
            ],
        };
    }

    /**
     * Cari record material steel kuartal berikutnya yang spesifikasi, ketebalan, dan supplier-nya cocok.
     *
     * @return Collection<int, MaterialSteel>
     */
    public static function getNextQuarterRecords(MaterialSteel $source): Collection
    {
        if (empty($source->quarter) || empty($source->year)) {
            return new Collection;
        }

        $nextInfo = static::getNextQuarterInfo($source->quarter, (int) $source->year);
        $nextQuarter = $nextInfo['next_quarter'];
        $nextYear = $nextInfo['next_year'];

        $cleanSpec = static::cleanSteelSpec($source->steel_spec);
        $cleanThickness = ! empty($source->double_thickness)
            ? trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $source->double_thickness))
            : null;

        $query = static::where('year', $nextYear)->where('quarter', $nextQuarter);

        if ($source->supplier_id) {
            $query->where(function ($q) use ($source) {
                $q->where('supplier_id', $source->supplier_id);
                if ($source->supplier_name) {
                    $q->orWhere('supplier_name', $source->supplier_name);
                }
                if ($source->vendor_code) {
                    $q->orWhere('vendor_code', $source->vendor_code);
                }
            });
        }

        if ($cleanSpec) {
            $query->where(function ($q) use ($source, $cleanSpec) {
                $q->where('steel_spec', $cleanSpec)
                    ->orWhere('steel_spec', $source->steel_spec)
                    ->orWhere('steel_spec', 'like', $cleanSpec.'%');
            });
        }

        if ($cleanThickness) {
            $query->where(function ($q) use ($cleanThickness) {
                $q->where('double_thickness', $cleanThickness)
                    ->orWhere('double_thickness', 'like', $cleanThickness.'%');
            });
        }

        return $query->get();
    }

    /**
     * Teruskan perubahan harga kuartal ini ke kuartal-kuartal berikutnya yang bergantung (Q2, Q3, dst.)
     *
     * @param  array<int>  $visitedIds
     * @return int Jumlah record kuartal berikutnya yang berhasil diperbarui
     */
    public function cascadeToNextQuarters(array &$visitedIds = []): int
    {
        if (in_array($this->id, $visitedIds, true)) {
            return 0;
        }
        $visitedIds[] = $this->id;

        $updatedCount = 0;
        $nextRecords = static::getNextQuarterRecords($this);

        foreach ($nextRecords as $next) {
            if (in_array($next->id, $visitedIds, true)) {
                continue;
            }

            // Hitung Gap kg USD kuartal berikutnya
            $gapKg = (float) ($next->gap_steel_kg_usd ?? 0);
            if ($gapKg == 0.0 && ! empty($next->gap_steel_usd)) {
                $gapKg = round(((float) $next->gap_steel_usd) / 1000, 4);
            }
            if ($gapKg == 0.0) {
                $steelType = static::determineSteelType($next->steel_spec);
                $gapMt = $steelType === 'HR' ? (float) ($next->hr_steel_usd ?? 0) : (float) ($next->cr_steel_usd ?? 0);
                if ($gapMt != 0.0) {
                    $gapKg = round($gapMt / 1000, 4);
                }
            }

            // Rumus: Harga Baru ($) = Harga Kuartal Lalu ($) + (Gap ÷ 1000)
            $newCoil = (float) $this->coil_price > 0 ? round((float) $this->coil_price + $gapKg, 4) : (float) $next->coil_price;
            $newSheet = (float) $this->sheet_price > 0 ? round((float) $this->sheet_price + $gapKg, 4) : (float) $next->sheet_price;

            $baseApCoil = (float) ($this->ap_coil_price ?: $this->coil_price);
            $newApCoil = $baseApCoil > 0 ? round($baseApCoil + $gapKg, 4) : (float) $next->ap_coil_price;

            $baseApSheet = (float) ($this->ap_sheet_price ?: $this->sheet_price);
            $newApSheet = $baseApSheet > 0 ? round($baseApSheet + $gapKg, 4) : (float) $next->ap_sheet_price;

            $rate = (float) ($next->exchange_rate ?: 1);

            $changed = false;
            if (abs((float) $next->coil_price - $newCoil) > 0.00001) {
                $next->coil_price = $newCoil;
                $next->coil_price_idr = round($newCoil * $rate, 2);
                $changed = true;
            }
            if (abs((float) $next->sheet_price - $newSheet) > 0.00001) {
                $next->sheet_price = $newSheet;
                $next->sheet_price_idr = round($newSheet * $rate, 2);
                $changed = true;
            }
            if (abs((float) $next->ap_coil_price - $newApCoil) > 0.00001) {
                $next->ap_coil_price = $newApCoil;
                $next->ap_coil_price_idr = round($newApCoil * $rate, 2);
                $changed = true;
            }
            if (abs((float) $next->ap_sheet_price - $newApSheet) > 0.00001) {
                $next->ap_sheet_price = $newApSheet;
                $next->ap_sheet_price_idr = round($newApSheet * $rate, 2);
                $changed = true;
            }

            if ($changed) {
                $next->steel_price = $next->coil_price ?: $next->sheet_price ?: 0;
                $next->steel_price_idr = round((float) $next->steel_price * $rate, 2);

                if ($next->material_id) {
                    $mat = Material::find($next->material_id);
                    if ($mat) {
                        $mat->basic_rate = $next->steel_price;
                        $mat->save();
                    }
                }

                $next->saveQuietly();
                $updatedCount++;

                // Rekursif teruskan cascade ke kuartal setelahnya (Q2 -> Q3 -> Q4 ...)
                $updatedCount += $next->cascadeToNextQuarters($visitedIds);
            }
        }

        $this->lastCascadedCount = $updatedCount;

        return $updatedCount;
    }

    public static function findPreviousQuarterData(string $quarter, int $year, ?int $supplierId = null, ?string $spec = null, ?string $thickness = null): ?array
    {
        $info = static::getPreviousQuarterInfo($quarter, $year);
        $prevQuarter = $info['prev_quarter'];
        $prevYear = $info['prev_year'];

        $cleanSpec = $spec ? static::cleanSteelSpec($spec) : null;
        $cleanThickness = ! empty($thickness) ? trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $thickness)) : null;

        $query = static::where('year', $prevYear)->where('quarter', $prevQuarter);
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($cleanSpec) {
            $query->where(function ($q) use ($spec, $cleanSpec) {
                $q->where('steel_spec', $cleanSpec)
                    ->orWhere('steel_spec', $spec)
                    ->orWhere('steel_spec', 'like', $cleanSpec.'%');
            });
        }
        if ($cleanThickness) {
            $query->where(function ($q) use ($cleanThickness) {
                $q->where('double_thickness', $cleanThickness)
                    ->orWhere('double_thickness', 'like', $cleanThickness.'%');
            });
        }

        $record = $query->latest('id')->first();

        if (! $record && $supplierId) {
            $record = static::where('year', $prevYear)
                ->where('quarter', $prevQuarter)
                ->where('supplier_id', $supplierId)
                ->latest('id')
                ->first();
        }

        if (! $record) {
            $record = static::where('year', $prevYear)
                ->where('quarter', $prevQuarter)
                ->latest('id')
                ->first();
        }

        if (! $record) {
            return null;
        }

        return [
            'source' => 'MaterialSteel',
            'prev_year' => $prevYear,
            'prev_quarter' => $prevQuarter,
            'label' => $info['label'],
            'supplier_id' => $record->supplier_id,
            'vendor_code' => $record->vendor_code,
            'supplier_name' => $record->supplier_name,
            'steel_spec' => $record->steel_spec,
            'double_thickness' => $record->double_thickness,
            'size' => $record->size,
            'exchange_rate' => (float) $record->exchange_rate,
            'coil_price' => (float) $record->coil_price,
            'sheet_price' => (float) $record->sheet_price,
            'coil_price_idr' => (float) $record->coil_price_idr,
            'sheet_price_idr' => (float) $record->sheet_price_idr,
            'ap_coil_price' => (float) $record->ap_coil_price,
            'ap_sheet_price' => (float) $record->ap_sheet_price,
            'ap_coil_price_idr' => (float) $record->ap_coil_price_idr,
            'ap_sheet_price_idr' => (float) $record->ap_sheet_price_idr,
            'gap_steel_usd' => (float) $record->gap_steel_usd,
            'gap_steel_kg_usd' => (float) $record->gap_steel_kg_usd,
            'hr_steel_usd' => (float) $record->hr_steel_usd,
            'cr_steel_usd' => (float) $record->cr_steel_usd,
        ];
    }
}
