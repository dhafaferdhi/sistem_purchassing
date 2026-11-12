<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessMonthlyRolloverJob;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\MonthlyRolloverLog;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MonthlyDataRolloverService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MonthlyRollover extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Salin & Update Bulanan';

    protected static ?string $title = 'Salin & Update Data Bulanan (Rollover)';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.pages.monthly-rollover';

    public string $activeTab = 'all'; // all, currency, material_steel, stamping_part, history

    // Form fields untuk Salin Semua (All-in-One)
    public int $sourceYear = 2026;

    public string $sourceQuarter = 'Q4';

    public int $targetYear = 2027;

    public string $targetQuarter = 'Q1';

    public ?float $usdRate = null;

    public ?float $priceAdjustmentPercent = null;

    public ?float $newGapSteelUsd = null;

    public string $status = 'Active';

    public string $duplicateHandling = 'overwrite'; // overwrite, skip, duplicate

    public ?string $notes = null;

    // Form fields khusus tab Currency
    public int $currSourceYear = 2026;

    public int $currTargetYear = 2027;

    public ?float $currUsdRate = null;

    // Form fields khusus tab Material Steel
    public int $steelSourceYear = 2026;

    public string $steelSourceQuarter = 'Q4';

    public int $steelTargetYear = 2027;

    public string $steelTargetQuarter = 'Q1';

    public ?float $steelExchangeRate = null;

    public ?float $steelAdjustmentPercent = null;

    public ?float $steelGapUsd = null;

    public ?int $steelSupplierId = null;

    public string $steelDuplicateHandling = 'overwrite';

    // Form fields khusus tab Stamping Part
    public int $stampSourceYear = 2026;

    public string $stampSourceQuarter = 'Q4';

    public int $stampTargetYear = 2027;

    public string $stampTargetQuarter = 'Q1';

    public ?float $stampExchangeRate = null;

    public ?float $stampGapUsd = null;

    public ?int $stampSupplierId = null;

    public bool $stampSyncFromSteel = true;

    public string $stampDuplicateHandling = 'overwrite';

    // Form fields khusus tab Fasteners
    public int $fastSourceYear = 2026;

    public string $fastSourceQuarter = 'Q4';

    public int $fastTargetYear = 2027;

    public string $fastTargetQuarter = 'Q1';

    public ?float $fastExchangeRate = null;

    public ?float $fastGapUsd = null;

    public ?int $fastSupplierId = null;

    public bool $fastSyncFromSteel = false;

    public string $fastDuplicateHandling = 'overwrite';

    public function mount(): void
    {
        $currentYear = (int) date('Y');
        $this->sourceYear = $currentYear;
        $this->targetYear = $currentYear + 1;

        $this->currSourceYear = $currentYear;
        $this->currTargetYear = $currentYear + 1;

        $this->steelSourceYear = $currentYear;
        $this->steelTargetYear = $currentYear + 1;

        $this->stampSourceYear = $currentYear;
        $this->stampTargetYear = $currentYear + 1;

        $this->fastSourceYear = $currentYear;
        $this->fastTargetYear = $currentYear + 1;

        // Ambil kurs USD aktif saat ini
        $activeUsd = Currency::where('code', 'USD')->latest('year')->first();
        if ($activeUsd) {
            $rate = (float) $activeUsd->exchange_rate_to_idr;
            $this->usdRate = $rate;
            $this->currUsdRate = $rate;
            $this->steelExchangeRate = $rate;
            $this->stampExchangeRate = $rate;
            $this->fastExchangeRate = $rate;
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Jalankan Salin Semua Sekaligus (All-in-One)
     */
    public function executeAllRollover(): void
    {
        try {
            $estimated = MonthlyDataRolloverService::estimateRolloverCount('all', $this->sourceYear, $this->sourceQuarter);

            if ($estimated > MonthlyDataRolloverService::QUEUE_THRESHOLD && config('queue.default') !== 'sync') {
                ProcessMonthlyRolloverJob::dispatch('all', [
                    'sourceYear' => $this->sourceYear,
                    'sourceQuarter' => $this->sourceQuarter,
                    'targetYear' => $this->targetYear,
                    'targetQuarter' => $this->targetQuarter,
                    'usdRate' => $this->usdRate,
                    'priceAdjustmentPercent' => $this->priceAdjustmentPercent,
                    'newGapSteelUsd' => $this->newGapSteelUsd,
                    'status' => $this->status,
                    'duplicateHandling' => $this->duplicateHandling,
                    'notes' => $this->notes,
                ], auth()->id());

                Notification::make()
                    ->title('Rollover Dimasukkan ke Antrean Background!')
                    ->body("Estimasi ~{$estimated} data dari {$this->sourceYear} {$this->sourceQuarter} ke {$this->targetYear} {$this->targetQuarter} sedang diproses di antrean queue dan hasilnya akan tercatat pada tab Riwayat.")
                    ->info()
                    ->duration(8000)
                    ->send();

                $this->activeTab = 'history';

                return;
            }

            $result = MonthlyDataRolloverService::rolloverAll(
                $this->sourceYear,
                $this->sourceQuarter,
                $this->targetYear,
                $this->targetQuarter,
                $this->usdRate,
                $this->priceAdjustmentPercent,
                $this->newGapSteelUsd,
                $this->status,
                $this->duplicateHandling,
                $this->notes
            );

            $steelsCount = $result['material_steels']['copied'] + $result['material_steels']['updated'];
            $stampsCount = $result['stamping_parts']['copied'] + $result['stamping_parts']['updated'];
            $fastsCount = $result['fasteners']['copied'] + $result['fasteners']['updated'];
            $currsCount = $result['currencies']['total'];

            Notification::make()
                ->title('Salin & Update Bulanan Berhasil!')
                ->body("Berhasil memproses: {$currsCount} Kurs Mata Uang, {$steelsCount} Material Steel, {$stampsCount} Stamping Part, dan {$fastsCount} Fastener dari {$this->sourceYear} {$this->sourceQuarter} ke {$this->targetYear} {$this->targetQuarter}.")
                ->success()
                ->duration(8000)
                ->send();

            $this->activeTab = 'history';
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Melakukan Rollover')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Jalankan Salin & Update Khusus Kurs Mata Uang
     */
    public function executeCurrencyRollover(): void
    {
        try {
            $result = MonthlyDataRolloverService::rolloverCurrencies(
                $this->currSourceYear,
                $this->currTargetYear,
                $this->currUsdRate
            );

            MonthlyRolloverLog::create([
                'type' => 'currency',
                'source_period' => "Tahun {$this->currSourceYear}",
                'target_period' => "Tahun {$this->currTargetYear}",
                'exchange_rate' => $this->currUsdRate,
                'currencies_count' => $result['total'],
                'details' => $result,
                'notes' => "Rollover Kurs Mata Uang {$this->currSourceYear} -> {$this->currTargetYear}",
                'user_id' => auth()->id() ?? User::value('id'),
            ]);

            Notification::make()
                ->title('Kurs Mata Uang Berhasil Disalin & Diperbarui!')
                ->body("{$result['copied']} kurs baru dibuat dan {$result['updated']} kurs diperbarui untuk tahun {$this->currTargetYear}.")
                ->success()
                ->send();

            $this->activeTab = 'history';
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menyalin Kurs')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Jalankan Salin & Update Khusus Material Steel
     */
    public function executeMaterialSteelRollover(): void
    {
        try {
            $estimated = MonthlyDataRolloverService::estimateRolloverCount(
                'material_steel',
                $this->steelSourceYear,
                $this->steelSourceQuarter,
                $this->steelSupplierId
            );

            if ($estimated > MonthlyDataRolloverService::QUEUE_THRESHOLD && config('queue.default') !== 'sync') {
                ProcessMonthlyRolloverJob::dispatch('material_steel', [
                    'sourceYear' => $this->steelSourceYear,
                    'sourceQuarter' => $this->steelSourceQuarter,
                    'targetYear' => $this->steelTargetYear,
                    'targetQuarter' => $this->steelTargetQuarter,
                    'exchangeRate' => $this->steelExchangeRate,
                    'priceAdjustmentPercent' => $this->steelAdjustmentPercent,
                    'newGapSteelUsd' => $this->steelGapUsd,
                    'status' => $this->status,
                    'duplicateHandling' => $this->steelDuplicateHandling,
                    'supplierId' => $this->steelSupplierId,
                ], auth()->id());

                Notification::make()
                    ->title('Rollover Material Steel Dimasukkan ke Antrean!')
                    ->body("Estimasi ~{$estimated} baris sedang diproses di background queue.")
                    ->info()
                    ->send();

                $this->activeTab = 'history';

                return;
            }

            $result = MonthlyDataRolloverService::rolloverMaterialSteels(
                $this->steelSourceYear,
                $this->steelSourceQuarter,
                $this->steelTargetYear,
                $this->steelTargetQuarter,
                $this->steelExchangeRate,
                $this->steelAdjustmentPercent,
                $this->steelGapUsd,
                $this->status,
                $this->steelDuplicateHandling,
                $this->steelSupplierId
            );

            MonthlyRolloverLog::create([
                'type' => 'material_steel',
                'source_period' => "{$this->steelSourceYear}-{$this->steelSourceQuarter}",
                'target_period' => "{$this->steelTargetYear}-{$this->steelTargetQuarter}",
                'exchange_rate' => $this->steelExchangeRate,
                'material_steels_count' => $result['copied'] + $result['updated'],
                'details' => $result,
                'notes' => "Rollover Material Steel {$this->steelSourceYear} {$this->steelSourceQuarter} -> {$this->steelTargetYear} {$this->steelTargetQuarter}",
                'user_id' => auth()->id() ?? User::value('id'),
            ]);

            Notification::make()
                ->title('Material Steel Berhasil Disalin & Diperbarui!')
                ->body("{$result['copied']} data baru disalin dan {$result['updated']} data diperbarui ({$result['skipped']} dilewati).")
                ->success()
                ->send();

            $this->activeTab = 'history';
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menyalin Material Steel')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Jalankan Salin & Update Khusus Stamping Part
     */
    public function executeStampingPartRollover(): void
    {
        try {
            $estimated = MonthlyDataRolloverService::estimateRolloverCount(
                'stamping_part',
                $this->stampSourceYear,
                $this->stampSourceQuarter,
                $this->stampSupplierId
            );

            if ($estimated > MonthlyDataRolloverService::QUEUE_THRESHOLD && config('queue.default') !== 'sync') {
                ProcessMonthlyRolloverJob::dispatch('stamping_part', [
                    'sourceYear' => $this->stampSourceYear,
                    'sourceQuarter' => $this->stampSourceQuarter,
                    'targetYear' => $this->stampTargetYear,
                    'targetQuarter' => $this->stampTargetQuarter,
                    'exchangeRate' => $this->stampExchangeRate,
                    'newGapSteelUsd' => $this->stampGapUsd,
                    'status' => $this->status,
                    'duplicateHandling' => $this->stampDuplicateHandling,
                    'supplierId' => $this->stampSupplierId,
                    'syncFromMaterialSteel' => $this->stampSyncFromSteel,
                ], auth()->id());

                Notification::make()
                    ->title('Rollover Stamping Part Dimasukkan ke Antrean!')
                    ->body("Estimasi ~{$estimated} baris sedang diproses di background queue.")
                    ->info()
                    ->send();

                $this->activeTab = 'history';

                return;
            }

            $result = MonthlyDataRolloverService::rolloverStampingParts(
                $this->stampSourceYear,
                $this->stampSourceQuarter,
                $this->stampTargetYear,
                $this->stampTargetQuarter,
                $this->stampExchangeRate,
                $this->stampGapUsd,
                $this->status,
                $this->stampDuplicateHandling,
                $this->stampSupplierId,
                $this->stampSyncFromSteel
            );

            MonthlyRolloverLog::create([
                'type' => 'stamping_part',
                'source_period' => "{$this->stampSourceYear}-{$this->stampSourceQuarter}",
                'target_period' => "{$this->stampTargetYear}-{$this->stampTargetQuarter}",
                'exchange_rate' => $this->stampExchangeRate,
                'stamping_parts_count' => $result['copied'] + $result['updated'],
                'details' => $result,
                'notes' => "Rollover Stamping Part {$this->stampSourceYear} {$this->stampSourceQuarter} -> {$this->stampTargetYear} {$this->stampTargetQuarter}",
                'user_id' => auth()->id() ?? User::value('id'),
            ]);

            Notification::make()
                ->title('Stamping Part Berhasil Disalin & Diperbarui!')
                ->body("{$result['copied']} data baru disalin dan {$result['updated']} data diperbarui ({$result['skipped']} dilewati).")
                ->success()
                ->send();

            $this->activeTab = 'history';
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menyalin Stamping Part')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Jalankan Salin & Update Khusus Fasteners
     */
    public function executeFastenerRollover(): void
    {
        try {
            $estimated = MonthlyDataRolloverService::estimateRolloverCount(
                'fastener',
                $this->fastSourceYear,
                $this->fastSourceQuarter,
                $this->fastSupplierId
            );

            if ($estimated > MonthlyDataRolloverService::QUEUE_THRESHOLD && config('queue.default') !== 'sync') {
                ProcessMonthlyRolloverJob::dispatch('fastener', [
                    'sourceYear' => $this->fastSourceYear,
                    'sourceQuarter' => $this->fastSourceQuarter,
                    'targetYear' => $this->fastTargetYear,
                    'targetQuarter' => $this->fastTargetQuarter,
                    'exchangeRate' => $this->fastExchangeRate,
                    'newGapSteelUsd' => $this->fastGapUsd,
                    'status' => $this->status,
                    'duplicateHandling' => $this->fastDuplicateHandling,
                    'supplierId' => $this->fastSupplierId,
                    'syncFromMaterialSteel' => $this->fastSyncFromSteel,
                ], auth()->id());

                Notification::make()
                    ->title('Rollover Fasteners Dimasukkan ke Antrean!')
                    ->body("Estimasi ~{$estimated} baris sedang diproses di background queue.")
                    ->info()
                    ->send();

                $this->activeTab = 'history';

                return;
            }

            $result = MonthlyDataRolloverService::rolloverFasteners(
                $this->fastSourceYear,
                $this->fastSourceQuarter,
                $this->fastTargetYear,
                $this->fastTargetQuarter,
                $this->fastExchangeRate,
                $this->fastGapUsd,
                $this->status,
                $this->fastDuplicateHandling,
                $this->fastSupplierId,
                $this->fastSyncFromSteel
            );

            MonthlyRolloverLog::create([
                'type' => 'fastener',
                'source_period' => "{$this->fastSourceYear}-{$this->fastSourceQuarter}",
                'target_period' => "{$this->fastTargetYear}-{$this->fastTargetQuarter}",
                'exchange_rate' => $this->fastExchangeRate,
                'fasteners_count' => $result['copied'] + $result['updated'],
                'details' => $result,
                'notes' => "Rollover Fasteners {$this->fastSourceYear} {$this->fastSourceQuarter} -> {$this->fastTargetYear} {$this->fastTargetQuarter}",
                'user_id' => auth()->id() ?? User::value('id'),
            ]);

            Notification::make()
                ->title('Fasteners Berhasil Disalin & Diperbarui!')
                ->body("{$result['copied']} data baru disalin dan {$result['updated']} data diperbarui ({$result['skipped']} dilewati).")
                ->success()
                ->send();

            $this->activeTab = 'history';
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menyalin Fasteners')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getAvailableYears(): array
    {
        $current = (int) date('Y');

        return [$current - 1, $current, $current + 1, $current + 2];
    }

    public function getSuppliersProperty(): Collection
    {
        return Supplier::query()
            ->select(['id', 'code', 'name'])
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    public function getSourceCountsProperty(): array
    {
        return [
            'material_steels' => MaterialSteel::where('year', $this->sourceYear)->where('quarter', $this->sourceQuarter)->count(),
            'stamping_parts' => StampingPart::where('year', $this->sourceYear)->where('quarter', $this->sourceQuarter)->count(),
            'fasteners' => Fastener::where('year', $this->sourceYear)->where('quarter', $this->sourceQuarter)->count(),
            'currencies' => Currency::where('year', $this->sourceYear)->count() ?: Currency::count(),
        ];
    }

    public function getTargetCountsProperty(): array
    {
        return [
            'material_steels' => MaterialSteel::where('year', $this->targetYear)->where('quarter', $this->targetQuarter)->count(),
            'stamping_parts' => StampingPart::where('year', $this->targetYear)->where('quarter', $this->targetQuarter)->count(),
            'fasteners' => Fastener::where('year', $this->targetYear)->where('quarter', $this->targetQuarter)->count(),
            'currencies' => Currency::where('year', $this->targetYear)->count(),
        ];
    }

    public function getRecentLogsProperty(): Collection
    {
        return MonthlyRolloverLog::with('user')->latest('id')->take(15)->get();
    }
}
