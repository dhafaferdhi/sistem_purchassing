<?php

namespace App\Jobs;

use App\Models\MonthlyRolloverLog;
use App\Models\User;
use App\Services\MonthlyDataRolloverService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMonthlyRolloverJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public string $type,
        public array $params,
        public ?int $userId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $resolvedUserId = $this->userId ?? User::value('id');

        match ($this->type) {
            'all' => MonthlyDataRolloverService::rolloverAll(
                (int) $this->params['sourceYear'],
                (string) $this->params['sourceQuarter'],
                (int) $this->params['targetYear'],
                (string) $this->params['targetQuarter'],
                $this->params['usdRate'] ?? null,
                $this->params['priceAdjustmentPercent'] ?? null,
                $this->params['newGapSteelUsd'] ?? null,
                (string) ($this->params['status'] ?? 'Active'),
                (string) ($this->params['duplicateHandling'] ?? 'overwrite'),
                $this->params['notes'] ?? null,
                $resolvedUserId,
            ),
            'currency' => $this->handleCurrency($resolvedUserId),
            'material_steel' => $this->handleMaterialSteel($resolvedUserId),
            'stamping_part' => $this->handleStampingPart($resolvedUserId),
            'fastener' => $this->handleFastener($resolvedUserId),
            default => null,
        };
    }

    protected function handleCurrency(?int $resolvedUserId): void
    {
        $sourceYear = (int) $this->params['sourceYear'];
        $targetYear = (int) $this->params['targetYear'];
        $usdRate = $this->params['usdRate'] ?? null;

        $result = MonthlyDataRolloverService::rolloverCurrencies($sourceYear, $targetYear, $usdRate);

        MonthlyRolloverLog::create([
            'type' => 'currency',
            'source_period' => "Tahun {$sourceYear}",
            'target_period' => "Tahun {$targetYear}",
            'exchange_rate' => $usdRate,
            'currencies_count' => $result['total'],
            'details' => $result,
            'notes' => "Rollover Kurs Mata Uang {$sourceYear} -> {$targetYear}",
            'user_id' => $resolvedUserId,
        ]);
    }

    protected function handleMaterialSteel(?int $resolvedUserId): void
    {
        $sourceYear = (int) $this->params['sourceYear'];
        $sourceQuarter = (string) $this->params['sourceQuarter'];
        $targetYear = (int) $this->params['targetYear'];
        $targetQuarter = (string) $this->params['targetQuarter'];
        $exchangeRate = $this->params['exchangeRate'] ?? null;

        $result = MonthlyDataRolloverService::rolloverMaterialSteels(
            $sourceYear,
            $sourceQuarter,
            $targetYear,
            $targetQuarter,
            $exchangeRate,
            $this->params['priceAdjustmentPercent'] ?? null,
            $this->params['newGapSteelUsd'] ?? null,
            (string) ($this->params['status'] ?? 'Active'),
            (string) ($this->params['duplicateHandling'] ?? 'overwrite'),
            $this->params['supplierId'] ?? null,
            $resolvedUserId,
        );

        MonthlyRolloverLog::create([
            'type' => 'material_steel',
            'source_period' => "{$sourceYear}-{$sourceQuarter}",
            'target_period' => "{$targetYear}-{$targetQuarter}",
            'exchange_rate' => $exchangeRate,
            'material_steels_count' => $result['copied'] + $result['updated'],
            'details' => $result,
            'notes' => "Rollover Material Steel {$sourceYear} {$sourceQuarter} -> {$targetYear} {$targetQuarter}",
            'user_id' => $resolvedUserId,
        ]);
    }

    protected function handleStampingPart(?int $resolvedUserId): void
    {
        $sourceYear = (int) $this->params['sourceYear'];
        $sourceQuarter = (string) $this->params['sourceQuarter'];
        $targetYear = (int) $this->params['targetYear'];
        $targetQuarter = (string) $this->params['targetQuarter'];
        $exchangeRate = $this->params['exchangeRate'] ?? null;

        $result = MonthlyDataRolloverService::rolloverStampingParts(
            $sourceYear,
            $sourceQuarter,
            $targetYear,
            $targetQuarter,
            $exchangeRate,
            $this->params['newGapSteelUsd'] ?? null,
            (string) ($this->params['status'] ?? 'Active'),
            (string) ($this->params['duplicateHandling'] ?? 'overwrite'),
            $this->params['supplierId'] ?? null,
            (bool) ($this->params['syncFromMaterialSteel'] ?? true),
            $resolvedUserId,
        );

        MonthlyRolloverLog::create([
            'type' => 'stamping_part',
            'source_period' => "{$sourceYear}-{$sourceQuarter}",
            'target_period' => "{$targetYear}-{$targetQuarter}",
            'exchange_rate' => $exchangeRate,
            'stamping_parts_count' => $result['copied'] + $result['updated'],
            'details' => $result,
            'notes' => "Rollover Stamping Part {$sourceYear} {$sourceQuarter} -> {$targetYear} {$targetQuarter}",
            'user_id' => $resolvedUserId,
        ]);
    }

    protected function handleFastener(?int $resolvedUserId): void
    {
        $sourceYear = (int) $this->params['sourceYear'];
        $sourceQuarter = (string) $this->params['sourceQuarter'];
        $targetYear = (int) $this->params['targetYear'];
        $targetQuarter = (string) $this->params['targetQuarter'];
        $exchangeRate = $this->params['exchangeRate'] ?? null;

        $result = MonthlyDataRolloverService::rolloverFasteners(
            $sourceYear,
            $sourceQuarter,
            $targetYear,
            $targetQuarter,
            $exchangeRate,
            $this->params['newGapSteelUsd'] ?? null,
            (string) ($this->params['status'] ?? 'Active'),
            (string) ($this->params['duplicateHandling'] ?? 'overwrite'),
            $this->params['supplierId'] ?? null,
            (bool) ($this->params['syncFromMaterialSteel'] ?? false),
            $resolvedUserId,
        );

        MonthlyRolloverLog::create([
            'type' => 'fastener',
            'source_period' => "{$sourceYear}-{$sourceQuarter}",
            'target_period' => "{$targetYear}-{$targetQuarter}",
            'exchange_rate' => $exchangeRate,
            'fasteners_count' => $result['copied'] + $result['updated'],
            'details' => $result,
            'notes' => "Rollover Fasteners {$sourceYear} {$sourceQuarter} -> {$targetYear} {$targetQuarter}",
            'user_id' => $resolvedUserId,
        ]);
    }
}
