<?php

namespace App\Services;

use App\Jobs\RecalculateCurrencyRatesJob;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\PartQuotation;
use App\Models\StampingPart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class CurrencyRecalculationService
{
    public const QUEUE_THRESHOLD = 200;

    public const CHUNK_SIZE = 200;

    /**
     * Pilih eksekusi sinkron untuk dataset kecil / test, atau background queue job jika jumlah baris besar.
     */
    public static function dispatchOrRunForCurrency(Currency $currency): bool
    {
        if (self::estimateAffectedCount($currency) > self::QUEUE_THRESHOLD && config('queue.default') !== 'sync') {
            RecalculateCurrencyRatesJob::dispatch($currency->id);

            return true;
        }

        self::recalculateForCurrency($currency);

        return false;
    }

    /**
     * Hitung estimasi jumlah baris terdampak oleh perubahan kurs.
     */
    public static function estimateAffectedCount(Currency $currency): int
    {
        return PartQuotation::where('currency_id', $currency->id)->count()
            + self::applyCurrencyTargetFilter(MaterialSteel::query(), $currency)->count()
            + self::applyCurrencyTargetFilter(StampingPart::query(), $currency)->count()
            + self::applyCurrencyTargetFilter(Fastener::query(), $currency)->count();
    }

    /**
     * Hitung ulang seluruh PartQuotation, MaterialSteel, StampingPart, dan Fastener yang menggunakan currency tertentu
     * saat terjadi pembaruan kurs (misal kuartalan atau tahunan).
     */
    public static function recalculateForCurrency(Currency $currency): int
    {
        $stats = self::recalculateForCurrencyWithStats($currency);

        return $stats['total'];
    }

    /**
     * Hitung ulang seluruh data untuk currency ini dan kembalikan rincian statistik per modul.
     *
     * @return array{quotations: int, material_steels: int, stamping_parts: int, fasteners: int, total: int}
     */
    public static function recalculateForCurrencyWithStats(Currency $currency): array
    {
        return Currency::withBatchCache(function () use ($currency): array {
            $partCount = self::recalculatePartQuotationsForCurrency($currency);
            $steelCount = self::recalculateMaterialSteelsForCurrency($currency);
            $stampingCount = self::recalculateStampingPartsForCurrency($currency);
            $fastenerCount = self::recalculateFastenersForCurrency($currency);

            $currency->updateQuietly(['last_updated_at' => now()]);

            try {
                Log::info("Kuartalan Currency Recalculate: {$partCount} quotations, {$steelCount} material steels, {$stampingCount} stamping parts & {$fastenerCount} fasteners recalculated for currency {$currency->code} (Tahun {$currency->year}) with new rate {$currency->exchange_rate_to_idr}");
            } catch (\Throwable) {
                // Ignore log lock in parallel/test environments
            }

            return [
                'quotations' => $partCount,
                'material_steels' => $steelCount,
                'stamping_parts' => $stampingCount,
                'fasteners' => $fastenerCount,
                'total' => $partCount + $steelCount + $stampingCount + $fastenerCount,
            ];
        });
    }

    /**
     * Hitung ulang seluruh PartQuotation untuk currency ini secara bertahap (chunked).
     */
    public static function recalculatePartQuotationsForCurrency(Currency $currency): int
    {
        $updatedCount = 0;

        PartQuotation::where('currency_id', $currency->id)
            ->chunkById(self::CHUNK_SIZE, function ($quotations) use ($currency, &$updatedCount): void {
                foreach ($quotations as $quotation) {
                    $quotation->exchange_rate_to_idr = $currency->exchange_rate_to_idr;
                    $quotation->recalculateTotals();
                    $quotation->save();
                    $updatedCount++;
                }
            });

        return $updatedCount;
    }

    /**
     * Hitung ulang seluruh MaterialSteel yang menggunakan currency ini secara bertahap (chunked).
     */
    public static function recalculateMaterialSteelsForCurrency(Currency $currency): int
    {
        $updatedCount = 0;
        $rate = (float) $currency->exchange_rate_to_idr;

        $gapAp = (float) ($currency->gap_ap_usd ?? 0);
        $gapAr = (float) ($currency->gap_ar_usd ?? 0);
        $hrcAp = (float) ($currency->gap_hrc_usd ?? $gapAp);
        $crcAp = (float) ($currency->gap_crc_usd ?? $gapAp);
        $hrcAr = (float) ($currency->gap_hrc_ar_usd ?? $gapAr);
        $crcAr = (float) ($currency->gap_crc_ar_usd ?? $gapAr);

        self::applyCurrencyTargetFilter(MaterialSteel::query(), $currency)
            ->chunkById(self::CHUNK_SIZE, function ($steels) use ($currency, $rate, $gapAp, $gapAr, $hrcAp, $crcAp, $hrcAr, $crcAr, &$updatedCount): void {
                foreach ($steels as $steel) {
                    $steel->currency_id = $currency->id;
                    $steel->currency_code = $currency->code;
                    $steel->exchange_rate = $rate;

                    $isAr = strtoupper((string) $steel->price_type) === 'AR';
                    $cat = $steel->steel_category ? strtoupper(trim($steel->steel_category)) : null;
                    $steelType = ($cat === 'CRC') ? 'CR' : (($cat === 'HRC') ? 'HR' : MaterialSteel::determineSteelType($steel->steel_spec));
                    $steel->steel_category = ($steelType === 'HR') ? 'HRC' : 'CRC';
                    $oldGapKg = (float) ($steel->gap_steel_kg_usd ?? (($steel->gap_steel_usd ?? 0) / 1000));

                    $isUsd = strtoupper((string) $currency->code) === 'USD';
                    $hasGapBenchmark = $isUsd && ($hrcAr != 0.0 || $crcAr != 0.0 || $gapAr != 0.0 || $hrcAp != 0.0 || $crcAp != 0.0 || $gapAp != 0.0);

                    // Tentukan Gap AP atau Gap AR berdasarkan price_type (rumus sama persis antara AP dan AR)
                    $appliedGapKg = null;
                    if ($hasGapBenchmark) {
                        if ($isAr) {
                            $gapMt = $steelType === 'HR' ? $hrcAr : $crcAr;
                            if ($gapMt == 0 && $gapAr != 0) {
                                $gapMt = $gapAr;
                            }
                            $gapKg = round($gapMt / 1000, 4);
                            $steel->hr_steel_usd = ($steelType === 'HR') ? $gapMt : 0;
                            $steel->cr_steel_usd = ($steelType === 'CR') ? $gapMt : 0;
                            $steel->gap_steel_usd = $gapMt;
                            $steel->gap_steel_kg_usd = $gapKg;
                            $steel->gap_steel_idr = round($gapKg * $rate, 2);
                            $steel->fluktuasi_jfe = $gapKg;
                            $appliedGapKg = $gapKg;
                        } else {
                            $gapMt = $steelType === 'HR' ? $hrcAp : $crcAp;
                            if ($gapMt == 0 && $gapAp != 0) {
                                $gapMt = $gapAp;
                            }
                            $gapKg = round($gapMt / 1000, 4);
                            $steel->hr_steel_usd = ($steelType === 'HR') ? $gapMt : 0;
                            $steel->cr_steel_usd = ($steelType === 'CR') ? $gapMt : 0;
                            $steel->gap_steel_usd = $gapMt;
                            $steel->gap_steel_kg_usd = $gapKg;
                            $steel->gap_steel_idr = round($gapKg * $rate, 2);
                            $steel->fluktuasi_jfe = $gapKg;
                            $appliedGapKg = $gapKg;
                        }
                    } else {
                        if ($steel->gap_steel_usd !== null) {
                            $steel->gap_steel_idr = round((float) $steel->gap_steel_usd * $rate, 2);
                        }
                    }

                    // Sesuaikan coil_price dan sheet_price berdasarkan Gap kuartalan:
                    // Rumus Excel: Harga Baru ($) = Harga Kuartal Lalu ($) + Gap USD/Kg
                    if ($appliedGapKg !== null) {
                        $prev = MaterialSteel::findPreviousQuarterData(
                            $steel->quarter,
                            (int) $steel->year,
                            $steel->supplier_id,
                            $steel->steel_spec,
                            $steel->double_thickness,
                            $steel->price_type
                        );

                        if ($prev) {
                            if (! empty($prev['coil_price']) && (float) $prev['coil_price'] > 0) {
                                $steel->coil_price = round((float) $prev['coil_price'] + $appliedGapKg, 4);
                            }
                            if (! empty($prev['sheet_price']) && (float) $prev['sheet_price'] > 0) {
                                $steel->sheet_price = round((float) $prev['sheet_price'] + $appliedGapKg, 4);
                            }
                            if (! empty($prev['ap_coil_price']) && (float) $prev['ap_coil_price'] > 0) {
                                $steel->ap_coil_price = round((float) $prev['ap_coil_price'] + $appliedGapKg, 4);
                            }
                            if (! empty($prev['ap_sheet_price']) && (float) $prev['ap_sheet_price'] > 0) {
                                $steel->ap_sheet_price = round((float) $prev['ap_sheet_price'] + $appliedGapKg, 4);
                            }
                        } else {
                            // Fallback jika kuartal sebelumnya ($prev) belum ada di sistem (misal data diinput perdana di Q2/Q3/Q4):
                            if ($isAr) {
                                // Khusus AR: Cari pasangan AP di kuartal/tahun yang sama untuk mendapatkan harga dasar murni (Base Price)
                                $apCounterpart = MaterialSteel::where('year', $steel->year)
                                    ->where('quarter', $steel->quarter)
                                    ->where(fn ($q) => $q->where('price_type', 'AP')->orWhereNull('price_type'))
                                    ->where('steel_spec', $steel->steel_spec)
                                    ->when($steel->double_thickness !== null && $steel->double_thickness !== '', function ($q) use ($steel) {
                                        $q->where('double_thickness', $steel->double_thickness);
                                    })
                                    ->when($steel->supplier_id, fn ($q) => $q->where('supplier_id', $steel->supplier_id))
                                    ->first();

                                if ($apCounterpart && ! empty($apCounterpart->coil_price) && (float) $apCounterpart->coil_price > 0) {
                                    $baseCoil = (float) $apCounterpart->coil_price;
                                    $baseSheet = (float) ($apCounterpart->sheet_price ?? $apCounterpart->coil_price);

                                    $steel->coil_price = round($baseCoil + $appliedGapKg, 4);
                                    $steel->sheet_price = round($baseSheet + $appliedGapKg, 4);
                                } else {
                                    $diffGap = round($appliedGapKg - $oldGapKg, 4);
                                    if ($diffGap != 0.0) {
                                        if (! empty($steel->coil_price) && (float) $steel->coil_price > 0) {
                                            $steel->coil_price = round((float) $steel->coil_price + $diffGap, 4);
                                        }
                                        if (! empty($steel->sheet_price) && (float) $steel->sheet_price > 0) {
                                            $steel->sheet_price = round((float) $steel->sheet_price + $diffGap, 4);
                                        }
                                    }
                                }
                            } else {
                                // Khusus AP: Sesuaikan harga berdasarkan selisih Gap AP
                                $diffGap = round($appliedGapKg - $oldGapKg, 4);
                                if ($diffGap != 0.0) {
                                    if (! empty($steel->coil_price) && (float) $steel->coil_price > 0) {
                                        $steel->coil_price = round((float) $steel->coil_price + $diffGap, 4);
                                    }
                                    if (! empty($steel->sheet_price) && (float) $steel->sheet_price > 0) {
                                        $steel->sheet_price = round((float) $steel->sheet_price + $diffGap, 4);
                                    }
                                }
                            }
                        }
                    }

                    $coilPrice = (float) ($steel->coil_price ?? 0);
                    $sheetPrice = (float) ($steel->sheet_price ?? 0);
                    $steelPrice = (float) ($steel->steel_price ?? 0);
                    if ($steelPrice <= 0.0) {
                        $steelPrice = $coilPrice ?: $sheetPrice ?: 0;
                        $steel->steel_price = $steelPrice;
                    }

                    $steel->steel_price_idr = round($steelPrice * $rate, 2);

                    if ($steel->coil_price !== null) {
                        $steel->coil_price_idr = round($coilPrice * $rate, 2);
                    }

                    if ($steel->sheet_price !== null) {
                        $steel->sheet_price_idr = round($sheetPrice * $rate, 2);
                    }

                    if ($appliedGapKg === null && $steel->gap_steel_usd !== null) {
                        $steel->gap_steel_idr = round((float) $steel->gap_steel_usd * $rate, 2);
                    }

                    if ($steel->ap_coil_price !== null) {
                        $steel->ap_coil_price_idr = round((float) $steel->ap_coil_price * $rate, 2);
                    }

                    if ($steel->ap_sheet_price !== null) {
                        $steel->ap_sheet_price_idr = round((float) $steel->ap_sheet_price * $rate, 2);
                    }

                    if ($steel->avg_3_months_price > 0 && $steel->avg_3_months_rate > 0) {
                        $steel->avg_3_months_price_idr = round((float) $steel->avg_3_months_price * (float) $steel->avg_3_months_rate, 2);
                    }

                    $steel->saveQuietly();
                    $updatedCount++;
                }
            });

        return $updatedCount;
    }

    /**
     * Hitung ulang seluruh StampingPart yang menggunakan currency ini secara bertahap (chunked).
     */
    public static function recalculateStampingPartsForCurrency(Currency $currency): int
    {
        $updatedCount = 0;
        $rate = (float) $currency->exchange_rate_to_idr;

        self::applyCurrencyTargetFilter(StampingPart::query(), $currency)
            ->chunkById(self::CHUNK_SIZE, function ($stampingParts) use ($currency, $rate, &$updatedCount): void {
                foreach ($stampingParts as $part) {
                    $part->currency_id = $currency->id;
                    $part->currency_code = $currency->code;
                    $part->exchange_rate = $rate;

                    $coil = (float) ($part->coil_price ?? 0);
                    $sheet = (float) ($part->sheet_price ?? 0);

                    if ($coil > 0) {
                        $part->coil_price_idr = round($coil * $rate, 2);
                    }
                    if ($sheet > 0) {
                        $part->sheet_price_idr = round($sheet * $rate, 2);
                    }

                    $part->saveQuietly();
                    $updatedCount++;
                }
            });

        return $updatedCount;
    }

    /**
     * Hitung ulang seluruh Fastener yang menggunakan currency ini secara bertahap (chunked).
     */
    public static function recalculateFastenersForCurrency(Currency $currency): int
    {
        $updatedCount = 0;
        $rate = (float) $currency->exchange_rate_to_idr;

        self::applyCurrencyTargetFilter(Fastener::query(), $currency)
            ->chunkById(self::CHUNK_SIZE, function ($fasteners) use ($currency, $rate, &$updatedCount): void {
                foreach ($fasteners as $fastener) {
                    $prevRate = (float) ($fastener->exchange_rate ?? 0);
                    if ($prevRate > 0 && abs($prevRate - $rate) > 0.0001) {
                        $fastener->old_exchange_rate = $prevRate;
                    }

                    $fastener->currency_id = $currency->id;
                    $fastener->currency_code = $currency->code;
                    $fastener->exchange_rate = $rate;

                    $coil = (float) ($fastener->coil_price ?? 0);
                    $sheet = (float) ($fastener->sheet_price ?? 0);
                    $wireUsd = (float) ($fastener->wire_price_usd_kg ?? 0);

                    $fastener->coil_price_idr = round($coil * $rate, 2);
                    $fastener->sheet_price_idr = round($sheet * $rate, 2);
                    if ($wireUsd > 0) {
                        $fastener->wire_price_idr_kg = round($wireUsd * $rate, 2);
                    }

                    $fastener->saveQuietly();
                    $updatedCount++;
                }
            });

        return $updatedCount;
    }

    /**
     * Sinkronkan patokan Kurs USD dan Gap AP & Gap AR dari Currency ke seluruh data Material Steel (AP & AR),
     * Stamping Part, dan Fasteners yang berada pada kuartal yang sama (misal 2027-Q1).
     *
     * @return array{material_steels: int, stamping_parts: int, fasteners: int, total: int}
     */
    public static function syncQuarterlyBenchmarkToRecords(Currency $currency, bool $syncGap = true): array
    {
        $rate = (float) ($currency->exchange_rate_to_idr ?? 16000);
        $gapAp = (float) ($currency->gap_ap_usd ?? 0);
        $gapAr = (float) ($currency->gap_ar_usd ?? 0);
        $hrcAp = (float) ($currency->gap_hrc_usd ?? $gapAp);
        $crcAp = (float) ($currency->gap_crc_usd ?? $gapAp);
        $hrcAr = (float) ($currency->gap_hrc_ar_usd ?? $gapAr);
        $crcAr = (float) ($currency->gap_crc_ar_usd ?? $gapAr);

        $steelCount = 0;
        $stampingCount = 0;
        $fastenerCount = 0;

        $isUsd = strtoupper((string) $currency->code) === 'USD';
        if (! $isUsd) {
            $syncGap = false;
        }

        // 1. Material Steel (AP & AR) pada kuartal ini
        $steelQuery = MaterialSteel::where('year', $currency->year);
        if (! empty($currency->quarter)) {
            $steelQuery->where('quarter', $currency->quarter);
        }
        if (! $isUsd) {
            $steelQuery->where(function ($q) use ($currency) {
                $q->where('currency_id', $currency->id)
                    ->orWhere('currency_code', $currency->code);
            });
        }
        $steelQuery->chunkById(self::CHUNK_SIZE, function ($steels) use ($currency, $rate, $syncGap, $isUsd, $gapAp, $gapAr, $hrcAp, $crcAp, $hrcAr, $crcAr, &$steelCount): void {
            foreach ($steels as $steel) {
                $steel->currency_id = $currency->id;
                $steel->currency_code = $currency->code;
                $steel->exchange_rate = $rate;

                $isAr = strtoupper((string) $steel->price_type) === 'AR';
                $cat = $steel->steel_category ? strtoupper(trim($steel->steel_category)) : null;
                $steelType = ($cat === 'CRC') ? 'CR' : (($cat === 'HRC') ? 'HR' : MaterialSteel::determineSteelType($steel->steel_spec));
                $steel->steel_category = ($steelType === 'HR') ? 'HRC' : 'CRC';
                $oldGapKg = (float) ($steel->gap_steel_kg_usd ?? (($steel->gap_steel_usd ?? 0) / 1000));

                $appliedGapKg = null;
                $hasGapBenchmark = $isUsd && $syncGap && ($hrcAr != 0.0 || $crcAr != 0.0 || $gapAr != 0.0 || $hrcAp != 0.0 || $crcAp != 0.0 || $gapAp != 0.0);
                if ($hasGapBenchmark) {
                    if ($isAr) {
                        $gapMt = $steelType === 'HR' ? $hrcAr : $crcAr;
                        if ($gapMt == 0 && $gapAr != 0) {
                            $gapMt = $gapAr;
                        }
                        $gapKg = round($gapMt / 1000, 4);
                        $steel->hr_steel_usd = ($steelType === 'HR') ? $gapMt : 0;
                        $steel->cr_steel_usd = ($steelType === 'CR') ? $gapMt : 0;
                        $steel->gap_steel_usd = $gapMt;
                        $steel->gap_steel_kg_usd = $gapKg;
                        $steel->gap_steel_idr = round($gapKg * $rate, 2);
                        $steel->fluktuasi_jfe = $gapKg;
                        $appliedGapKg = $gapKg;
                    } else {
                        $gapMt = $steelType === 'HR' ? $hrcAp : $crcAp;
                        if ($gapMt == 0 && $gapAp != 0) {
                            $gapMt = $gapAp;
                        }
                        $gapKg = round($gapMt / 1000, 4);
                        $steel->hr_steel_usd = ($steelType === 'HR') ? $gapMt : 0;
                        $steel->cr_steel_usd = ($steelType === 'CR') ? $gapMt : 0;
                        $steel->gap_steel_usd = $gapMt;
                        $steel->gap_steel_kg_usd = $gapKg;
                        $steel->gap_steel_idr = round($gapKg * $rate, 2);
                        $steel->fluktuasi_jfe = $gapKg;
                        $appliedGapKg = $gapKg;
                    }
                } else {
                    if ($steel->gap_steel_usd !== null) {
                        $steel->gap_steel_idr = round((float) $steel->gap_steel_usd * $rate, 2);
                    }
                }

                    // Sesuaikan coil_price dan sheet_price berdasarkan Gap kuartalan:
                    // Rumus Excel: Harga Baru ($) = Harga Kuartal Lalu ($) + Gap USD/Kg
                    if ($appliedGapKg !== null) {
                        $prev = MaterialSteel::findPreviousQuarterData(
                            $steel->quarter,
                            (int) $steel->year,
                            $steel->supplier_id,
                            $steel->steel_spec,
                            $steel->double_thickness,
                            $steel->price_type
                        );

                        if ($prev) {
                            if (! empty($prev['coil_price']) && (float) $prev['coil_price'] > 0) {
                                $steel->coil_price = round((float) $prev['coil_price'] + $appliedGapKg, 4);
                            }
                            if (! empty($prev['sheet_price']) && (float) $prev['sheet_price'] > 0) {
                                $steel->sheet_price = round((float) $prev['sheet_price'] + $appliedGapKg, 4);
                            }
                            if (! empty($prev['ap_coil_price']) && (float) $prev['ap_coil_price'] > 0) {
                                $steel->ap_coil_price = round((float) $prev['ap_coil_price'] + $appliedGapKg, 4);
                            }
                            if (! empty($prev['ap_sheet_price']) && (float) $prev['ap_sheet_price'] > 0) {
                                $steel->ap_sheet_price = round((float) $prev['ap_sheet_price'] + $appliedGapKg, 4);
                            }
                        } else {
                            // Fallback jika kuartal sebelumnya ($prev) belum ada di sistem (misal data diinput perdana di Q2/Q3/Q4):
                            if ($isAr) {
                                // Khusus AR: Cari pasangan AP di kuartal/tahun yang sama untuk mendapatkan harga dasar murni (Base Price)
                                $apCounterpart = MaterialSteel::where('year', $steel->year)
                                    ->where('quarter', $steel->quarter)
                                    ->where(fn ($q) => $q->where('price_type', 'AP')->orWhereNull('price_type'))
                                    ->where('steel_spec', $steel->steel_spec)
                                    ->when($steel->double_thickness !== null && $steel->double_thickness !== '', function ($q) use ($steel) {
                                        $q->where('double_thickness', $steel->double_thickness);
                                    })
                                    ->when($steel->supplier_id, fn ($q) => $q->where('supplier_id', $steel->supplier_id))
                                    ->first();

                                if ($apCounterpart && ! empty($apCounterpart->coil_price) && (float) $apCounterpart->coil_price > 0) {
                                    $baseCoil = (float) $apCounterpart->coil_price;
                                    $baseSheet = (float) ($apCounterpart->sheet_price ?? $apCounterpart->coil_price);

                                    $steel->coil_price = round($baseCoil + $appliedGapKg, 4);
                                    $steel->sheet_price = round($baseSheet + $appliedGapKg, 4);
                                } else {
                                    $diffGap = round($appliedGapKg - $oldGapKg, 4);
                                    if ($diffGap != 0.0) {
                                        if (! empty($steel->coil_price) && (float) $steel->coil_price > 0) {
                                            $steel->coil_price = round((float) $steel->coil_price + $diffGap, 4);
                                        }
                                        if (! empty($steel->sheet_price) && (float) $steel->sheet_price > 0) {
                                            $steel->sheet_price = round((float) $steel->sheet_price + $diffGap, 4);
                                        }
                                    }
                                }
                            } else {
                                // Khusus AP: Sesuaikan harga berdasarkan selisih Gap AP
                                $diffGap = round($appliedGapKg - $oldGapKg, 4);
                                if ($diffGap != 0.0) {
                                    if (! empty($steel->coil_price) && (float) $steel->coil_price > 0) {
                                        $steel->coil_price = round((float) $steel->coil_price + $diffGap, 4);
                                    }
                                    if (! empty($steel->sheet_price) && (float) $steel->sheet_price > 0) {
                                        $steel->sheet_price = round((float) $steel->sheet_price + $diffGap, 4);
                                    }
                                }
                            }
                        }
                    }

                $coil = (float) ($steel->coil_price ?? 0);
                $sheet = (float) ($steel->sheet_price ?? 0);
                if ($coil > 0) {
                    $steel->coil_price_idr = round($coil * $rate, 2);
                }
                if ($sheet > 0) {
                    $steel->sheet_price_idr = round($sheet * $rate, 2);
                }
                $steelPrice = (float) ($steel->steel_price ?? 0);
                if ($steelPrice <= 0.0) {
                    $steelPrice = $coil ?: $sheet ?: 0;
                    $steel->steel_price = $steelPrice;
                }
                $steel->steel_price_idr = round($steelPrice * $rate, 2);

                if ($steel->ap_coil_price !== null) {
                    $steel->ap_coil_price_idr = round((float) $steel->ap_coil_price * $rate, 2);
                }
                if ($steel->ap_sheet_price !== null) {
                    $steel->ap_sheet_price_idr = round((float) $steel->ap_sheet_price * $rate, 2);
                }

                if (! $syncGap && $steel->gap_steel_usd !== null) {
                    $steel->gap_steel_idr = round((float) $steel->gap_steel_usd * $rate, 2);
                }

                $steel->saveQuietly();
                $steelCount++;
            }
        });

        // 2. Stamping Part pada kuartal ini (AP)
        $stampingQuery = StampingPart::where('year', $currency->year);
        if (! empty($currency->quarter)) {
            $stampingQuery->where('quarter', $currency->quarter);
        }
        $stampingQuery->chunkById(self::CHUNK_SIZE, function ($parts) use ($currency, $rate, $syncGap, $gapAp, $hrcAp, $crcAp, &$stampingCount): void {
            foreach ($parts as $part) {
                $part->currency_id = $currency->id;
                $part->currency_code = $currency->code;
                $part->exchange_rate = $rate;

                if ($syncGap) {
                    $steelType = StampingPart::determineSteelType($part->steel_spec);
                    $gapMt = $steelType === 'HR' ? $hrcAp : $crcAp;
                    if ($gapMt == 0 && $gapAp != 0) {
                        $gapMt = $gapAp;
                    }

                    if ($gapMt != 0 || $gapAp != 0) {
                        $gapKg = round($gapMt / 1000, 4);
                        $part->hr_steel_usd = $hrcAp;
                        $part->cr_steel_usd = $crcAp;
                        $part->gap_steel_usd = $gapMt;
                        $part->gap_steel_kg_usd = $gapKg;
                        $part->fluktuasi_jfe = $gapKg;
                    }
                }

                $coil = (float) ($part->coil_price ?? 0);
                $sheet = (float) ($part->sheet_price ?? 0);
                if ($coil > 0) {
                    $part->coil_price_idr = round($coil * $rate, 2);
                }
                if ($sheet > 0) {
                    $part->sheet_price_idr = round($sheet * $rate, 2);
                }

                $part->saveQuietly();
                $stampingCount++;
            }
        });

        // 3. Fasteners pada kuartal ini (AP)
        $fastenerQuery = Fastener::where('year', $currency->year);
        if (! empty($currency->quarter)) {
            $fastenerQuery->where('quarter', $currency->quarter);
        }
        $fastenerQuery->chunkById(self::CHUNK_SIZE, function ($fasteners) use ($currency, $rate, $syncGap, $gapAp, $hrcAp, $crcAp, &$fastenerCount): void {
            foreach ($fasteners as $fastener) {
                $fastener->currency_id = $currency->id;
                $fastener->currency_code = $currency->code;
                $fastener->exchange_rate = $rate;

                if ($syncGap) {
                    $steelType = Fastener::determineSteelType($fastener->steel_spec);
                    $gapMt = $steelType === 'HR' ? $hrcAp : $crcAp;
                    if ($gapMt == 0 && $gapAp != 0) {
                        $gapMt = $gapAp;
                    }

                    if ($gapMt != 0 || $gapAp != 0) {
                        $gapKg = round($gapMt / 1000, 4);
                        $fastener->hr_steel_usd = $hrcAp;
                        $fastener->cr_steel_usd = $crcAp;
                        $fastener->gap_steel_usd = $gapMt;
                        $fastener->gap_steel_kg_usd = $gapKg;
                        $fastener->fluktuasi_jfe = $gapKg;
                    }
                }

                $coil = (float) ($fastener->coil_price ?? 0);
                $sheet = (float) ($fastener->sheet_price ?? 0);
                $wireUsd = (float) ($fastener->wire_price_usd_kg ?? 0);

                if ($coil > 0) {
                    $fastener->coil_price_idr = round($coil * $rate, 2);
                }
                if ($sheet > 0) {
                    $fastener->sheet_price_idr = round($sheet * $rate, 2);
                }
                if ($wireUsd > 0) {
                    $fastener->wire_price_idr_kg = round($wireUsd * $rate, 2);
                }

                $fastener->saveQuietly();
                $fastenerCount++;
            }
        });

        $currency->updateQuietly(['last_updated_at' => now()]);

        return [
            'material_steels' => $steelCount,
            'stamping_parts' => $stampingCount,
            'fasteners' => $fastenerCount,
            'total' => $steelCount + $stampingCount + $fastenerCount,
        ];
    }

    /**
     * Hitung ulang seluruh Quotation, Material Steel, Stamping Part, dan Fastener untuk semua mata uang.
     */
    public static function recalculateAll(): int
    {
        $totalUpdated = 0;

        Currency::query()->chunkById(50, function ($currencies) use (&$totalUpdated): void {
            foreach ($currencies as $currency) {
                $totalUpdated += self::recalculateForCurrency($currency);
            }
        });

        return $totalUpdated;
    }

    /**
     * Terapkan filter target model dengan pengelompokan OR yang aman untuk chunkById().
     * Isolasi ketat: Data kuartal atau tahun lain (misal Q4 2026) tidak akan pernah tersentuh
     * saat memperbarui kuartal ini (misal Q1 2027).
     */
    protected static function applyCurrencyTargetFilter(Builder $baseQuery, Currency $currency): Builder
    {
        $codes = [$currency->code];
        if (in_array(strtoupper((string) $currency->code), ['JPY', 'YEN'], true)) {
            $codes = ['JPY', 'YEN'];
        }

        $otherCurrencyPeriods = Currency::whereIn('code', $codes)
            ->where('id', '!=', $currency->id)
            ->get(['year', 'quarter']);

        $otherCurrencyYears = $otherCurrencyPeriods->pluck('year')->filter()->unique()->all();

        return $baseQuery->where(function (Builder $outer) use ($codes, $currency, $otherCurrencyPeriods, $otherCurrencyYears): void {
            $outer->where('currency_id', $currency->id)
                ->orWhere(function (Builder $query) use ($codes, $currency, $otherCurrencyPeriods, $otherCurrencyYears): void {
                    $query->whereIn('currency_code', $codes);

                    if (! empty($otherCurrencyYears)) {
                        $query->where(function (Builder $sub) use ($currency, $otherCurrencyYears): void {
                            $sub->where('year', $currency->year)
                                ->orWhereNotIn('year', $otherCurrencyYears)
                                ->orWhereNull('year');
                        });
                    }

                    if ($otherCurrencyPeriods->isNotEmpty() && ! empty($currency->quarter)) {
                        $query->where(function (Builder $sub) use ($currency): void {
                            $sub->where('quarter', $currency->quarter)
                                ->orWhereNull('quarter');
                        });
                    }
                });
        });
    }
}
