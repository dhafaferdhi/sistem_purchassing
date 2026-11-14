<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\MonthlyRolloverLog;
use App\Models\StampingPart;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonthlyDataRolloverService
{
    public const QUEUE_THRESHOLD = 200;

    public const CHUNK_SIZE = 200;

    /**
     * Hitung estimasi jumlah data sumber yang akan diproses saat rollover.
     */
    public static function estimateRolloverCount(
        string $type,
        int $sourceYear,
        string $sourceQuarter = 'Q1',
        ?int $supplierId = null
    ): int {
        return match ($type) {
            'material_steel' => MaterialSteel::where('year', $sourceYear)
                ->where('quarter', $sourceQuarter)
                ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
                ->count(),
            'stamping_part' => StampingPart::where('year', $sourceYear)
                ->where('quarter', $sourceQuarter)
                ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
                ->count(),
            'fastener' => Fastener::where('year', $sourceYear)
                ->where('quarter', $sourceQuarter)
                ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
                ->count(),
            'all' => MaterialSteel::where('year', $sourceYear)->where('quarter', $sourceQuarter)->count()
                + StampingPart::where('year', $sourceYear)->where('quarter', $sourceQuarter)->count()
                + Fastener::where('year', $sourceYear)->where('quarter', $sourceQuarter)->count()
                + Currency::where('year', $sourceYear)->count(),
            default => 0,
        };
    }

    /**
     * Salin & perbarui kurs mata uang dari tahun acuan ke tahun target secara chunked.
     *
     * @return array{copied: int, updated: int, total: int}
     */
    public static function rolloverCurrencies(int $sourceYear, int $targetYear, ?float $usdRate = null): array
    {
        $hasSourceYear = Currency::where('year', $sourceYear)->exists();
        $sourceQuery = $hasSourceYear
            ? Currency::where('year', $sourceYear)
            : Currency::query();

        $copied = 0;
        $updated = 0;

        $sourceQuery->chunkById(100, function ($sourceCurrencies) use ($targetYear, $usdRate, &$copied, &$updated): void {
            foreach ($sourceCurrencies as $curr) {
                $rate = ($curr->code === 'USD' && $usdRate !== null) ? $usdRate : (float) $curr->exchange_rate_to_idr;

                $existingQuery = Currency::where('code', $curr->code)->where('year', $targetYear);
                if ($curr->month !== null) {
                    $existingQuery->where('month', $curr->month);
                } elseif (! empty($curr->quarter)) {
                    $existingQuery->where('quarter', $curr->quarter);
                }
                $existing = $existingQuery->first();

                if ($existing) {
                    if ($usdRate !== null && $curr->code === 'USD') {
                        $existing->exchange_rate_to_idr = $rate;
                        $existing->last_updated_at = now();
                        $existing->save();
                        $updated++;
                    }
                } else {
                    Currency::create([
                        'year' => $targetYear,
                        'quarter' => $curr->quarter ?? 'Q1',
                        'month' => $curr->month ?? 1,
                        'code' => $curr->code,
                        'name' => $curr->name,
                        'symbol' => $curr->symbol,
                        'exchange_rate_to_idr' => $rate,
                        'last_updated_at' => now(),
                    ]);
                    $copied++;
                }
            }
        });

        return [
            'copied' => $copied,
            'updated' => $updated,
            'total' => $copied + $updated,
        ];
    }

    /**
     * Salin & perbarui data Material Steel dari satu periode (tahun & kuartal) ke periode target.
     *
     * @return array{copied: int, updated: int, skipped: int, total_source: int}
     */
    public static function rolloverMaterialSteels(
        int $sourceYear,
        string $sourceQuarter,
        int $targetYear,
        string $targetQuarter,
        ?float $exchangeRate = null,
        ?float $priceAdjustmentPercent = null,
        ?float $newGapSteelUsd = null,
        string $status = 'Active',
        string $duplicateHandling = 'overwrite',
        ?int $supplierId = null,
        ?int $initiatedByUserId = null
    ): array {
        $query = MaterialSteel::where('year', $sourceYear)->where('quarter', $sourceQuarter);
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        $totalSource = (clone $query)->count();

        $targetCurrency = Currency::where('code', 'USD')->where('year', $targetYear)->where('quarter', $targetQuarter)->first()
            ?? Currency::where('code', 'USD')->where('year', $targetYear)->first()
            ?? Currency::where('code', 'USD')->first();
        $rateToUse = $exchangeRate ?? ($targetCurrency ? (float) $targetCurrency->exchange_rate_to_idr : 16000.0);
        $defaultUserId = $initiatedByUserId ?? auth()->id() ?? User::value('id');

        $copied = 0;
        $updated = 0;
        $skipped = 0;

        Currency::withBatchCache(function () use (
            $query,
            $targetYear,
            $targetQuarter,
            $priceAdjustmentPercent,
            $newGapSteelUsd,
            $status,
            $duplicateHandling,
            $targetCurrency,
            $rateToUse,
            $defaultUserId,
            &$copied,
            &$updated,
            &$skipped
        ): void {
            $query->chunkById(self::CHUNK_SIZE, function ($sources) use (
                $targetYear,
                $targetQuarter,
                $priceAdjustmentPercent,
                $newGapSteelUsd,
                $status,
                $duplicateHandling,
                $targetCurrency,
                $rateToUse,
                $defaultUserId,
                &$copied,
                &$updated,
                &$skipped
            ): void {
                foreach ($sources as $source) {
                    $existing = MaterialSteel::where('year', $targetYear)
                        ->where('quarter', $targetQuarter)
                        ->where('supplier_id', $source->supplier_id)
                        ->where('steel_spec', $source->steel_spec)
                        ->where('double_thickness', $source->double_thickness)
                        ->where('price_type', $source->price_type ?? 'AP')
                        ->first();

                    if ($existing && $duplicateHandling === 'skip') {
                        $skipped++;

                        continue;
                    }

                    $coilPrice = (float) ($source->coil_price ?? $source->steel_price ?? 0);
                    $sheetPrice = (float) ($source->sheet_price ?? 0);
                    $gapUsd = $newGapSteelUsd !== null ? $newGapSteelUsd : (float) ($source->gap_steel_usd ?? 0);

                    $apCoil = (float) ($source->ap_coil_price ?: $source->coil_price);
                    $apSheet = (float) ($source->ap_sheet_price ?: $source->sheet_price);

                    if ($newGapSteelUsd !== null && $newGapSteelUsd != 0.0) {
                        $gapKg = round($newGapSteelUsd / 1000, 4);
                        $coilPrice = round($coilPrice + $gapKg, 4);
                        if ($sheetPrice > 0) {
                            $sheetPrice = round($sheetPrice + $gapKg, 4);
                        }
                        $apCoil = round($apCoil + $gapKg, 4);
                        if ($apSheet > 0) {
                            $apSheet = round($apSheet + $gapKg, 4);
                        }
                    } elseif ($priceAdjustmentPercent !== null && $priceAdjustmentPercent != 0.0) {
                        $multiplier = 1 + ($priceAdjustmentPercent / 100);
                        $coilPrice = round($coilPrice * $multiplier, 4);
                        if ($sheetPrice > 0) {
                            $sheetPrice = round($sheetPrice * $multiplier, 4);
                        }
                        $apCoil = round($apCoil * $multiplier, 4);
                        if ($apSheet > 0) {
                            $apSheet = round($apSheet * $multiplier, 4);
                        }
                    }

                    if ($existing && $duplicateHandling === 'overwrite') {
                        $existing->exchange_rate = $rateToUse;
                        $existing->currency_id = $targetCurrency?->id;
                        $existing->currency_code = 'USD';
                        $existing->coil_price = $coilPrice;
                        $existing->sheet_price = $sheetPrice;
                        $existing->ap_coil_price = $apCoil;
                        $existing->ap_sheet_price = $apSheet;
                        $existing->ap_coil_price_idr = round($apCoil * $rateToUse, 2);
                        $existing->ap_sheet_price_idr = round($apSheet * $rateToUse, 2);
                        $existing->gap_steel_usd = $gapUsd;
                        $existing->gap_steel_kg_usd = round($gapUsd / 1000, 4);
                        $existing->status = $status;
                        $existing->save();
                        $updated++;
                    } else {
                        /** @var MaterialSteel $newSteel */
                        $newSteel = $source->replicate(['created_at', 'updated_at']);
                        $newSteel->year = $targetYear;
                        $newSteel->quarter = $targetQuarter;
                        $newSteel->exchange_rate = $rateToUse;
                        $newSteel->currency_id = $targetCurrency?->id;
                        $newSteel->currency_code = 'USD';
                        $newSteel->coil_price = $coilPrice;
                        $newSteel->sheet_price = $sheetPrice;
                        $newSteel->ap_coil_price = $apCoil;
                        $newSteel->ap_sheet_price = $apSheet;
                        $newSteel->ap_coil_price_idr = round($apCoil * $rateToUse, 2);
                        $newSteel->ap_sheet_price_idr = round($apSheet * $rateToUse, 2);
                        $newSteel->gap_steel_usd = $gapUsd;
                        $newSteel->gap_steel_kg_usd = round($gapUsd / 1000, 4);
                        $newSteel->status = $status;
                        $newSteel->created_by = $defaultUserId;
                        $newSteel->save();
                        $copied++;
                    }
                }
            });
        });

        return [
            'copied' => $copied,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_source' => $totalSource,
        ];
    }

    /**
     * Salin & perbarui data Stamping Part dari satu periode ke periode target.
     *
     * @return array{copied: int, updated: int, skipped: int, total_source: int}
     */
    public static function rolloverStampingParts(
        int $sourceYear,
        string $sourceQuarter,
        int $targetYear,
        string $targetQuarter,
        ?float $exchangeRate = null,
        ?float $newGapSteelUsd = null,
        string $status = 'Active',
        string $duplicateHandling = 'overwrite',
        ?int $supplierId = null,
        bool $syncFromMaterialSteel = true,
        ?int $initiatedByUserId = null
    ): array {
        $query = StampingPart::where('year', $sourceYear)->where('quarter', $sourceQuarter);
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        $totalSource = (clone $query)->count();

        $targetCurrency = Currency::where('code', 'USD')->where('year', $targetYear)->where('quarter', $targetQuarter)->first()
            ?? Currency::where('code', 'USD')->where('year', $targetYear)->first()
            ?? Currency::where('code', 'USD')->first();
        $rateToUse = $exchangeRate ?? ($targetCurrency ? (float) $targetCurrency->exchange_rate_to_idr : 16000.0);
        $defaultUserId = $initiatedByUserId ?? auth()->id() ?? User::value('id');

        $copied = 0;
        $updated = 0;
        $skipped = 0;

        Currency::withBatchCache(function () use (
            $query,
            $targetYear,
            $targetQuarter,
            $exchangeRate,
            $newGapSteelUsd,
            $status,
            $duplicateHandling,
            $syncFromMaterialSteel,
            $targetCurrency,
            $rateToUse,
            $defaultUserId,
            &$copied,
            &$updated,
            &$skipped
        ): void {
            $query->chunkById(self::CHUNK_SIZE, function ($sources) use (
                $targetYear,
                $targetQuarter,
                $exchangeRate,
                $newGapSteelUsd,
                $status,
                $duplicateHandling,
                $syncFromMaterialSteel,
                $targetCurrency,
                $rateToUse,
                $defaultUserId,
                &$copied,
                &$updated,
                &$skipped
            ): void {
                $chunkSteels = collect();
                if ($syncFromMaterialSteel) {
                    $specs = $sources->pluck('steel_spec')->filter()->unique()->values()->all();
                    if (! empty($specs)) {
                        $chunkSteels = MaterialSteel::where('year', $targetYear)
                            ->where('quarter', $targetQuarter)
                            ->whereIn('steel_spec', $specs)
                            ->orderByDesc('id')
                            ->get(['id', 'supplier_id', 'steel_spec', 'double_thickness', 'coil_price', 'sheet_price', 'gap_steel_usd'])
                            ->groupBy('steel_spec');
                    }
                }

                foreach ($sources as $source) {
                    $existingQuery = StampingPart::where('year', $targetYear)
                        ->where('quarter', $targetQuarter);

                    if (! empty($source->part_number)) {
                        $existing = $existingQuery->where('part_number', $source->part_number)->first();
                    } else {
                        $existing = $existingQuery
                            ->where('supplier_id', $source->supplier_id)
                            ->where('steel_spec', $source->steel_spec)
                            ->where('double_thickness', $source->double_thickness)
                            ->first();
                    }

                    if ($existing && $duplicateHandling === 'skip') {
                        $skipped++;

                        continue;
                    }

                    $coilPrice = (float) ($source->coil_price ?? 0);
                    $sheetPrice = (float) ($source->sheet_price ?? 0);
                    $gapUsd = $newGapSteelUsd !== null ? $newGapSteelUsd : (float) ($source->gap_steel_usd ?? 0);
                    $isDirectIdr = ($coilPrice == 0.0 && $sheetPrice == 0.0)
                        && ((float) ($source->sheet_price_idr ?? 0) > 0 || (float) ($source->coil_price_idr ?? 0) > 0);

                    // Jika sinkronisasi dari Material Steel target diaktifkan, cari data harga acuan terbaru (lewati jika kontrak memakai Direct IDR)
                    if ($syncFromMaterialSteel && ! $isDirectIdr) {
                        $candidates = $chunkSteels->get($source->steel_spec, collect());
                        if ($source->supplier_id) {
                            $candidates = $candidates->where('supplier_id', $source->supplier_id);
                        }

                        $matchedSteel = ! empty($source->double_thickness)
                            ? ($candidates->firstWhere('double_thickness', $source->double_thickness) ?? $candidates->first())
                            : $candidates->first();

                        if ($matchedSteel) {
                            $coilPrice = (float) ($matchedSteel->coil_price ?? $coilPrice);
                            $sheetPrice = (float) ($matchedSteel->sheet_price ?? $sheetPrice);
                            if ($newGapSteelUsd === null && $matchedSteel->gap_steel_usd !== null) {
                                $gapUsd = (float) $matchedSteel->gap_steel_usd;
                            }
                        }
                    }

                    $sourceRate = (float) ($source->exchange_rate ?? 0);
                    $appliedRate = $isDirectIdr && $exchangeRate === null ? $sourceRate : $rateToUse;
                    $fluctuation = ($sourceRate > 0 && $appliedRate > 0)
                        ? round($appliedRate / $sourceRate, 6)
                        : (float) ($source->exchange_rate_fluctuation ?? 1.0);

                    if ($existing && $duplicateHandling === 'overwrite') {
                        $existing->exchange_rate = $appliedRate;
                        $existing->exchange_rate_fluctuation = $fluctuation;
                        $existing->currency_id = $targetCurrency?->id;
                        $existing->currency_code = 'USD';
                        $existing->coil_price = $coilPrice;
                        $existing->sheet_price = $sheetPrice;
                        if ($isDirectIdr) {
                            $existing->coil_price_idr = (float) ($source->coil_price_idr ?? 0);
                            $existing->sheet_price_idr = (float) ($source->sheet_price_idr ?? 0);
                        }
                        $existing->gap_steel_usd = $gapUsd;
                        $existing->gap_steel_kg_usd = round($gapUsd / 1000, 4);
                        $existing->status = $status;
                        $existing->save();
                        $updated++;
                    } else {
                        /** @var StampingPart $newPart */
                        $newPart = $source->replicate(['created_at', 'updated_at']);
                        $newPart->year = $targetYear;
                        $newPart->quarter = $targetQuarter;
                        $newPart->exchange_rate = $appliedRate;
                        $newPart->exchange_rate_fluctuation = $fluctuation;
                        $newPart->currency_id = $targetCurrency?->id;
                        $newPart->currency_code = 'USD';
                        $newPart->coil_price = $coilPrice;
                        $newPart->sheet_price = $sheetPrice;
                        $newPart->gap_steel_usd = $gapUsd;
                        $newPart->gap_steel_kg_usd = round($gapUsd / 1000, 4);
                        $newPart->status = $status;
                        $newPart->created_by = $defaultUserId;
                        $newPart->save();
                        $copied++;
                    }
                }
            });
        });

        return [
            'copied' => $copied,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_source' => $totalSource,
        ];
    }

    /**
     * Salin & perbarui data Fasteners dari satu periode ke periode target.
     *
     * @return array{copied: int, updated: int, skipped: int, total_source: int}
     */
    public static function rolloverFasteners(
        int $sourceYear,
        string $sourceQuarter,
        int $targetYear,
        string $targetQuarter,
        ?float $exchangeRate = null,
        ?float $newGapSteelUsd = null,
        string $status = 'Active',
        string $duplicateHandling = 'overwrite',
        ?int $supplierId = null,
        bool $syncFromMaterialSteel = false,
        ?int $initiatedByUserId = null
    ): array {
        $query = Fastener::where('year', $sourceYear)->where('quarter', $sourceQuarter);
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        $totalSource = (clone $query)->count();

        $targetCurrency = Currency::where('code', 'USD')->where('year', $targetYear)->where('quarter', $targetQuarter)->first()
            ?? Currency::where('code', 'USD')->where('year', $targetYear)->first()
            ?? Currency::where('code', 'USD')->first();
        $rateToUse = $exchangeRate ?? ($targetCurrency ? (float) $targetCurrency->exchange_rate_to_idr : 16000.0);
        $defaultUserId = $initiatedByUserId ?? auth()->id() ?? User::value('id');

        $copied = 0;
        $updated = 0;
        $skipped = 0;

        Currency::withBatchCache(function () use (
            $query,
            $targetYear,
            $targetQuarter,
            $newGapSteelUsd,
            $status,
            $duplicateHandling,
            $syncFromMaterialSteel,
            $targetCurrency,
            $rateToUse,
            $defaultUserId,
            &$copied,
            &$updated,
            &$skipped
        ): void {
            $query->chunkById(self::CHUNK_SIZE, function ($sources) use (
                $targetYear,
                $targetQuarter,
                $newGapSteelUsd,
                $status,
                $duplicateHandling,
                $syncFromMaterialSteel,
                $targetCurrency,
                $rateToUse,
                $defaultUserId,
                &$copied,
                &$updated,
                &$skipped
            ): void {
                $chunkSteels = collect();
                if ($syncFromMaterialSteel) {
                    $specs = $sources->pluck('steel_spec')->filter()->unique()->values()->all();
                    if (! empty($specs)) {
                        $chunkSteels = MaterialSteel::where('year', $targetYear)
                            ->where('quarter', $targetQuarter)
                            ->whereIn('steel_spec', $specs)
                            ->orderByDesc('id')
                            ->get(['id', 'supplier_id', 'steel_spec', 'double_thickness', 'coil_price', 'sheet_price', 'gap_steel_usd'])
                            ->groupBy('steel_spec');
                    }
                }

                foreach ($sources as $source) {
                    $existingQuery = Fastener::where('year', $targetYear)
                        ->where('quarter', $targetQuarter);

                    if (! empty($source->part_number)) {
                        $existing = $existingQuery->where('part_number', $source->part_number)->first();
                    } else {
                        $existing = $existingQuery
                            ->where('supplier_id', $source->supplier_id)
                            ->where('steel_spec', $source->steel_spec)
                            ->where('double_thickness', $source->double_thickness)
                            ->first();
                    }

                    if ($existing && $duplicateHandling === 'skip') {
                        $skipped++;

                        continue;
                    }

                    $coilPrice = (float) ($source->coil_price ?? 0);
                    $sheetPrice = (float) ($source->sheet_price ?? 0);
                    $wireUsd = (float) ($source->wire_price_usd_kg ?: ($coilPrice > 0 ? $coilPrice : 1.66));
                    $gapUsd = $newGapSteelUsd !== null ? $newGapSteelUsd : (float) ($source->gap_steel_usd ?? 0);

                    if ($syncFromMaterialSteel) {
                        $candidates = $chunkSteels->get($source->steel_spec, collect());
                        if ($source->supplier_id) {
                            $candidates = $candidates->where('supplier_id', $source->supplier_id);
                        }

                        $matchedSteel = ! empty($source->double_thickness)
                            ? ($candidates->firstWhere('double_thickness', $source->double_thickness) ?? $candidates->first())
                            : $candidates->first();

                        if ($matchedSteel) {
                            $coilPrice = (float) ($matchedSteel->coil_price ?? $coilPrice);
                            $sheetPrice = (float) ($matchedSteel->sheet_price ?? $sheetPrice);
                            if ($coilPrice > 0 && abs((float) ($source->wire_price_usd_kg ?? 0) - (float) ($source->coil_price ?? 0)) < 0.0001) {
                                $wireUsd = $coilPrice;
                            }
                            if ($newGapSteelUsd === null && $matchedSteel->gap_steel_usd !== null) {
                                $gapUsd = (float) $matchedSteel->gap_steel_usd;
                            }
                        }
                    } elseif ($newGapSteelUsd !== null && $newGapSteelUsd != 0.0) {
                        $appliedKg = round($newGapSteelUsd / 1000, 4);
                        $wireUsd = round($wireUsd + $appliedKg, 4);
                        if ($coilPrice > 0) {
                            $coilPrice = round($coilPrice + $appliedKg, 4);
                        }
                        if ($sheetPrice > 0) {
                            $sheetPrice = round($sheetPrice + $appliedKg, 4);
                        }
                    }

                    $sourceRate = (float) ($source->exchange_rate ?: 16000);
                    $fluctuation = ($sourceRate > 0 && $rateToUse > 0) ? round($rateToUse / $sourceRate, 6) : 1.0;

                    if ($existing && $duplicateHandling === 'overwrite') {
                        $existing->old_exchange_rate = $sourceRate;
                        $existing->exchange_rate = $rateToUse;
                        $existing->exchange_rate_fluctuation = $fluctuation;
                        $existing->currency_id = $targetCurrency?->id;
                        $existing->currency_code = 'USD';
                        $existing->wire_price_usd_kg = $wireUsd;
                        $existing->coil_price = $coilPrice;
                        $existing->sheet_price = $sheetPrice;
                        $existing->gap_steel_usd = $gapUsd;
                        $existing->gap_steel_kg_usd = round($gapUsd / 1000, 4);
                        $existing->status = $status;
                        $existing->save();
                        $updated++;
                    } else {
                        /** @var Fastener $newFastener */
                        $newFastener = $source->replicate(['created_at', 'updated_at']);
                        $newFastener->year = $targetYear;
                        $newFastener->quarter = $targetQuarter;
                        $newFastener->old_exchange_rate = $sourceRate;
                        $newFastener->exchange_rate = $rateToUse;
                        $newFastener->exchange_rate_fluctuation = $fluctuation;
                        $newFastener->currency_id = $targetCurrency?->id;
                        $newFastener->currency_code = 'USD';
                        $newFastener->wire_price_usd_kg = $wireUsd;
                        $newFastener->coil_price = $coilPrice;
                        $newFastener->sheet_price = $sheetPrice;
                        $newFastener->gap_steel_usd = $gapUsd;
                        $newFastener->gap_steel_kg_usd = round($gapUsd / 1000, 4);
                        $newFastener->status = $status;
                        $newFastener->created_by = $defaultUserId;
                        $newFastener->save();
                        $copied++;
                    }
                }
            });
        });

        return [
            'copied' => $copied,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_source' => $totalSource,
        ];
    }

    /**
     * Jalankan salin & update seluruh data sekaligus (All-in-One Monthly Rollover).
     *
     * @return array{
     *     currencies: array{copied: int, updated: int, total: int},
     *     material_steels: array{copied: int, updated: int, skipped: int, total_source: int},
     *     stamping_parts: array{copied: int, updated: int, skipped: int, total_source: int},
     *     fasteners: array{copied: int, updated: int, skipped: int, total_source: int},
     *     log: MonthlyRolloverLog
     * }
     */
    public static function rolloverAll(
        int $sourceYear,
        string $sourceQuarter,
        int $targetYear,
        string $targetQuarter,
        ?float $usdRate = null,
        ?float $priceAdjustmentPercent = null,
        ?float $newGapSteelUsd = null,
        string $status = 'Active',
        string $duplicateHandling = 'overwrite',
        ?string $notes = null,
        ?int $initiatedByUserId = null
    ): array {
        return DB::transaction(function () use (
            $sourceYear,
            $sourceQuarter,
            $targetYear,
            $targetQuarter,
            $usdRate,
            $priceAdjustmentPercent,
            $newGapSteelUsd,
            $status,
            $duplicateHandling,
            $notes,
            $initiatedByUserId
        ) {
            $defaultUserId = $initiatedByUserId ?? auth()->id() ?? User::value('id');

            // 1. Rollover Kurs Mata Uang (Currency)
            $currStats = self::rolloverCurrencies($sourceYear, $targetYear, $usdRate);

            // Ambil kurs acuan aktif untuk periode target
            $targetRate = $usdRate;
            if ($targetRate === null) {
                $targetRate = Currency::where('code', 'USD')
                    ->where('year', $targetYear)
                    ->where('quarter', $targetQuarter)
                    ->value('exchange_rate_to_idr')
                    ?? Currency::where('code', 'USD')->where('year', $targetYear)->value('exchange_rate_to_idr');
            }

            // 2. Rollover Data Material Steel
            $steelStats = self::rolloverMaterialSteels(
                $sourceYear,
                $sourceQuarter,
                $targetYear,
                $targetQuarter,
                $targetRate ? (float) $targetRate : null,
                $priceAdjustmentPercent,
                $newGapSteelUsd,
                $status,
                $duplicateHandling,
                null,
                $defaultUserId
            );

            // 3. Rollover Data Stamping Part
            $stampingStats = self::rolloverStampingParts(
                $sourceYear,
                $sourceQuarter,
                $targetYear,
                $targetQuarter,
                $targetRate ? (float) $targetRate : null,
                $newGapSteelUsd,
                $status,
                $duplicateHandling,
                null,
                true, // sinkronkan dengan harga Material Steel terbaru
                $defaultUserId
            );

            // 4. Rollover Data Fasteners
            $fastenerStats = self::rolloverFasteners(
                $sourceYear,
                $sourceQuarter,
                $targetYear,
                $targetQuarter,
                $targetRate ? (float) $targetRate : null,
                $newGapSteelUsd,
                $status,
                $duplicateHandling,
                null,
                true,
                $defaultUserId
            );

            // 5. Catat riwayat ke log rollover bulanan
            $log = MonthlyRolloverLog::create([
                'type' => 'all',
                'source_period' => "{$sourceYear}-{$sourceQuarter}",
                'target_period' => "{$targetYear}-{$targetQuarter}",
                'exchange_rate' => $targetRate,
                'currencies_count' => $currStats['total'],
                'material_steels_count' => $steelStats['copied'] + $steelStats['updated'],
                'stamping_parts_count' => $stampingStats['copied'] + $stampingStats['updated'],
                'fasteners_count' => $fastenerStats['copied'] + $fastenerStats['updated'],
                'details' => [
                    'currencies' => $currStats,
                    'material_steels' => $steelStats,
                    'stamping_parts' => $stampingStats,
                    'fasteners' => $fastenerStats,
                    'price_adjustment_percent' => $priceAdjustmentPercent,
                    'new_gap_steel_usd' => $newGapSteelUsd,
                    'duplicate_handling' => $duplicateHandling,
                ],
                'notes' => $notes ?: "Salin & Update Bulanan {$sourceYear} {$sourceQuarter} -> {$targetYear} {$targetQuarter}",
                'user_id' => $defaultUserId,
            ]);

            try {
                Log::info("Monthly Rollover Sukses: {$sourceYear}-{$sourceQuarter} -> {$targetYear}-{$targetQuarter} (Currencies: {$currStats['total']}, Steels: {$steelStats['copied']}/{$steelStats['updated']}, Stamping: {$stampingStats['copied']}/{$stampingStats['updated']}, Fasteners: {$fastenerStats['copied']}/{$fastenerStats['updated']})");
            } catch (\Throwable) {
                // Ignore log stream failure in test/parallel run
            }

            return [
                'currencies' => $currStats,
                'material_steels' => $steelStats,
                'stamping_parts' => $stampingStats,
                'fasteners' => $fastenerStats,
                'log' => $log,
            ];
        });
    }
}
