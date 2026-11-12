<?php

namespace App\Services;

use App\Jobs\RecalculateCurrencyRatesJob;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\PartQuotation;
use App\Models\StampingPart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            + self::buildCurrencyTargetQuery(MaterialSteel::class, $currency)->count()
            + self::buildCurrencyTargetQuery(StampingPart::class, $currency)->count()
            + self::buildCurrencyTargetQuery(Fastener::class, $currency)->count();
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

            Log::info("Kuartalan Currency Recalculate: {$partCount} quotations, {$steelCount} material steels, {$stampingCount} stamping parts & {$fastenerCount} fasteners recalculated for currency {$currency->code} (Tahun {$currency->year}) with new rate {$currency->exchange_rate_to_idr}");

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

        self::buildCurrencyTargetQuery(MaterialSteel::class, $currency)
            ->chunkById(self::CHUNK_SIZE, function ($steels) use ($currency, $rate, &$updatedCount): void {
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

        self::buildCurrencyTargetQuery(StampingPart::class, $currency)
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

                    $part->save();
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

        self::buildCurrencyTargetQuery(Fastener::class, $currency)
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

                    $fastener->save();
                    $updatedCount++;
                }
            });

        return $updatedCount;
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
     * Bangun query target model dengan pengelompokan OR yang aman untuk chunkById().
     *
     * @param  class-string<Model>  $modelClass
     */
    protected static function buildCurrencyTargetQuery(string $modelClass, Currency $currency): Builder
    {
        $codes = [$currency->code];
        if (in_array(strtoupper((string) $currency->code), ['JPY', 'YEN'], true)) {
            $codes = ['JPY', 'YEN'];
        }

        $otherCurrencyYears = Currency::whereIn('code', $codes)
            ->where('id', '!=', $currency->id)
            ->pluck('year')
            ->filter()
            ->all();

        return $modelClass::query()->where(function (Builder $outer) use ($codes, $currency, $otherCurrencyYears): void {
            $outer->where('currency_id', $currency->id)
                ->orWhere(function (Builder $query) use ($codes, $currency, $otherCurrencyYears): void {
                    $query->whereIn('currency_code', $codes);

                    if (! empty($otherCurrencyYears)) {
                        $query->where(function (Builder $sub) use ($currency, $otherCurrencyYears): void {
                            $sub->where('year', $currency->year)
                                ->orWhereNotIn('year', $otherCurrencyYears)
                                ->orWhereNull('year');
                        });
                    }

                    if (! empty($currency->quarter)) {
                        $query->where(function (Builder $sub) use ($currency): void {
                            $sub->where('quarter', $currency->quarter)
                                ->orWhereNull('quarter');
                        });
                    }
                });
        });
    }
}
