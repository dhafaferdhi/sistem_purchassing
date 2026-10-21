<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\PartQuotation;
use App\Models\StampingPart;
use Illuminate\Support\Facades\Log;

class CurrencyRecalculationService
{
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
        $partCount = self::recalculatePartQuotationsForCurrency($currency);
        $steelCount = self::recalculateMaterialSteelsForCurrency($currency);
        $stampingCount = self::recalculateStampingPartsForCurrency($currency);
        $fastenerCount = self::recalculateFastenersForCurrency($currency);

        $currency->updateQuietly(['last_updated_at' => now()]);

        Log::info("Kuartalan Currency Recalculate: {$partCount} quotations, {$steelCount} material steels, {$stampingCount} stamping parts & {$fastenerCount} fasteners recalculated for currency {$currency->code} (Tahun {$currency->year}) with new rate {$currency->exchange_rate_to_idr}");

        return [
            'quotations' => $partCount,
            'material_steels' => $steelCount,
            'stamping_parts' => $stampingCount,
            'fasteners' => $fastenerCount,
            'total' => $partCount + $steelCount + $stampingCount + $fastenerCount,
        ];
    }

    /**
     * Hitung ulang seluruh PartQuotation untuk currency ini.
     */
    public static function recalculatePartQuotationsForCurrency(Currency $currency): int
    {
        $quotations = PartQuotation::where('currency_id', $currency->id)->get();
        $updatedCount = 0;

        foreach ($quotations as $quotation) {
            $quotation->exchange_rate_to_idr = $currency->exchange_rate_to_idr;
            $quotation->recalculateTotals();
            $quotation->save();
            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Hitung ulang seluruh MaterialSteel yang menggunakan currency ini (atau sesuai code dan year).
     */
    public static function recalculateMaterialSteelsForCurrency(Currency $currency): int
    {
        $codes = [$currency->code];
        if (in_array(strtoupper($currency->code), ['JPY', 'YEN'])) {
            $codes = ['JPY', 'YEN'];
        }

        $otherCurrencyYears = Currency::whereIn('code', $codes)
            ->where('id', '!=', $currency->id)
            ->pluck('year')
            ->filter()
            ->toArray();

        $steels = MaterialSteel::where('currency_id', $currency->id)
            ->orWhere(function ($query) use ($codes, $currency, $otherCurrencyYears) {
                $query->whereIn('currency_code', $codes);
                if (! empty($otherCurrencyYears)) {
                    $query->where(function ($sub) use ($currency, $otherCurrencyYears) {
                        $sub->where('year', $currency->year)
                            ->orWhereNotIn('year', $otherCurrencyYears)
                            ->orWhereNull('year');
                    });
                }
                if (! empty($currency->quarter)) {
                    $query->where(function ($sub) use ($currency) {
                        $sub->where('quarter', $currency->quarter)
                            ->orWhereNull('quarter');
                    });
                }
            })
            ->get();

        $updatedCount = 0;
        $rate = (float) $currency->exchange_rate_to_idr;

        foreach ($steels as $steel) {
            $steel->currency_id = $currency->id;
            $steel->currency_code = $currency->code;
            $steel->exchange_rate = $rate;

            $steelPrice = (float) ($steel->steel_price ?? 0);
            $coilPrice = (float) ($steel->coil_price ?? 0);
            $sheetPrice = (float) ($steel->sheet_price ?? 0);
            $gapUsd = (float) ($steel->gap_steel_usd ?? 0);

            $steel->steel_price_idr = round($steelPrice * $rate, 2);

            if ($steel->coil_price !== null) {
                $steel->coil_price_idr = round($coilPrice * $rate, 2);
            }

            if ($steel->sheet_price !== null) {
                $steel->sheet_price_idr = round($sheetPrice * $rate, 2);
            }

            if ($steel->gap_steel_usd !== null) {
                $steel->gap_steel_idr = round($gapUsd * $rate, 2);
            }

            if ($steel->avg_3_months_price > 0 && $steel->avg_3_months_rate > 0) {
                $steel->avg_3_months_price_idr = round((float) $steel->avg_3_months_price * (float) $steel->avg_3_months_rate, 2);
            }

            $steel->save();
            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Hitung ulang seluruh StampingPart yang menggunakan currency ini (atau sesuai code dan year).
     */
    public static function recalculateStampingPartsForCurrency(Currency $currency): int
    {
        $codes = [$currency->code];
        if (in_array(strtoupper($currency->code), ['JPY', 'YEN'])) {
            $codes = ['JPY', 'YEN'];
        }

        $otherCurrencyYears = Currency::whereIn('code', $codes)
            ->where('id', '!=', $currency->id)
            ->pluck('year')
            ->filter()
            ->toArray();

        $stampingParts = StampingPart::where('currency_id', $currency->id)
            ->orWhere(function ($query) use ($codes, $currency, $otherCurrencyYears) {
                $query->whereIn('currency_code', $codes);
                if (! empty($otherCurrencyYears)) {
                    $query->where(function ($sub) use ($currency, $otherCurrencyYears) {
                        $sub->where('year', $currency->year)
                            ->orWhereNotIn('year', $otherCurrencyYears)
                            ->orWhereNull('year');
                    });
                }
                if (! empty($currency->quarter)) {
                    $query->where(function ($sub) use ($currency) {
                        $sub->where('quarter', $currency->quarter)
                            ->orWhereNull('quarter');
                    });
                }
            })
            ->get();

        $updatedCount = 0;
        $rate = (float) $currency->exchange_rate_to_idr;

        foreach ($stampingParts as $part) {
            $part->currency_id = $currency->id;
            $part->currency_code = $currency->code;
            $part->exchange_rate = $rate;

            $coil = (float) ($part->coil_price ?? 0);
            $sheet = (float) ($part->sheet_price ?? 0);

            $part->coil_price_idr = round($coil * $rate, 2);
            $part->sheet_price_idr = round($sheet * $rate, 2);

            $part->save();
            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Hitung ulang seluruh Fastener yang menggunakan currency ini (atau sesuai code dan year).
     */
    public static function recalculateFastenersForCurrency(Currency $currency): int
    {
        $codes = [$currency->code];
        if (in_array(strtoupper($currency->code), ['JPY', 'YEN'])) {
            $codes = ['JPY', 'YEN'];
        }

        $otherCurrencyYears = Currency::whereIn('code', $codes)
            ->where('id', '!=', $currency->id)
            ->pluck('year')
            ->filter()
            ->toArray();

        $fasteners = Fastener::where('currency_id', $currency->id)
            ->orWhere(function ($query) use ($codes, $currency, $otherCurrencyYears) {
                $query->whereIn('currency_code', $codes);
                if (! empty($otherCurrencyYears)) {
                    $query->where(function ($sub) use ($currency, $otherCurrencyYears) {
                        $sub->where('year', $currency->year)
                            ->orWhereNotIn('year', $otherCurrencyYears)
                            ->orWhereNull('year');
                    });
                }
                if (! empty($currency->quarter)) {
                    $query->where(function ($sub) use ($currency) {
                        $sub->where('quarter', $currency->quarter)
                            ->orWhereNull('quarter');
                    });
                }
            })
            ->get();

        $updatedCount = 0;
        $rate = (float) $currency->exchange_rate_to_idr;

        foreach ($fasteners as $fastener) {
            $fastener->currency_id = $currency->id;
            $fastener->currency_code = $currency->code;
            $fastener->exchange_rate = $rate;

            $coil = (float) ($fastener->coil_price ?? 0);
            $sheet = (float) ($fastener->sheet_price ?? 0);

            $fastener->coil_price_idr = round($coil * $rate, 2);
            $fastener->sheet_price_idr = round($sheet * $rate, 2);

            $fastener->save();
            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Hitung ulang seluruh Quotation, Material Steel, Stamping Part, dan Fastener untuk semua mata uang
     */
    public static function recalculateAll(): int
    {
        $currencies = Currency::all();
        $totalUpdated = 0;

        foreach ($currencies as $currency) {
            $totalUpdated += self::recalculateForCurrency($currency);
        }

        return $totalUpdated;
    }
}
