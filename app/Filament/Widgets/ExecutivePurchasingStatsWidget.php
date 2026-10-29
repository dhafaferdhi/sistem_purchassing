<?php

namespace App\Filament\Widgets;

use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExecutivePurchasingStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $year = (int) ($this->filters['year'] ?? date('Y'));
        $quarter = $this->filters['quarter'] ?? 'all';
        $supplierId = $this->filters['supplier_id'] ?? null;

        // 1. Kurs USD
        $currQuery = Currency::where('code', 'USD')->where('year', $year);
        if ($quarter !== 'all') {
            $currQuery->where('quarter', $quarter);
        }
        $curr = $currQuery->latest('id')->first()
            ?? Currency::where('code', 'USD')->latest('year')->first();
        $rate = (float) ($curr?->exchange_rate_to_idr ?? 16465);

        // Previous rate for trend comparison
        $prevRate = (float) (Currency::where('code', 'USD')
            ->where(function ($q) use ($year) {
                $q->where('year', '<', $year)
                    ->orWhere(fn ($sub) => $sub->where('year', $year));
            })
            ->where('id', '!=', $curr?->id ?? 0)
            ->latest('id')
            ->value('exchange_rate_to_idr') ?? 16321);

        $diffRate = $rate - $prevRate;
        $pctRate = $prevRate > 0 ? round(($diffRate / $prevRate) * 100, 2) : 0;
        $rateDesc = $pctRate >= 0 ? "+{$pctRate}% vs periode acuan" : "{$pctRate}% vs periode acuan";

        // 2. Stamping Parts Stats
        $stampingQuery = StampingPart::where('year', $year);
        if ($quarter !== 'all') {
            $stampingQuery->where('quarter', $quarter);
        }
        if ($supplierId) {
            $stampingQuery->where('supplier_id', $supplierId);
        }
        $stampingCount = $stampingQuery->count();
        $stampingAvgCost = (float) ($stampingQuery->avg('total_cost') ?? 0);

        // 3. Fasteners Stats
        $fastenerQuery = Fastener::where('year', $year);
        if ($quarter !== 'all') {
            $fastenerQuery->where('quarter', $quarter);
        }
        if ($supplierId) {
            $fastenerQuery->where('supplier_id', $supplierId);
        }
        $fastenerCount = $fastenerQuery->count();
        $fastenerAvgCost = (float) ($fastenerQuery->avg('part_cost') ?? 0);

        // 4. Material Steel Stats
        $steelQuery = MaterialSteel::where('year', $year);
        if ($quarter !== 'all') {
            $steelQuery->where('quarter', $quarter);
        }
        if ($supplierId) {
            $steelQuery->where('supplier_id', $supplierId);
        }
        $steelCount = $steelQuery->count();
        $avgCoil = (float) ($steelQuery->avg('coil_price') ?? 0);
        $avgSheet = (float) ($steelQuery->avg('sheet_price') ?? 0);

        return [
            Stat::make('Kurs USD Acuan', 'Rp '.number_format($rate, 0, ',', '.'))
                ->description($rateDesc)
                ->descriptionIcon($diffRate >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($diffRate > 0 ? 'warning' : 'success')
                ->chart([16000, 16150, 16321, (int) $rate]),

            Stat::make('Stamping Parts', "{$stampingCount} Komponen")
                ->description('Rata-rata: Rp '.number_format($stampingAvgCost, 2, ',', '.').' / pc')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('success')
                ->chart([8980, 9219, 11994, 12320]),

            Stat::make('Fasteners (Baut & Washer)', "{$fastenerCount} Komponen")
                ->description('Rata-rata: Rp '.number_format($fastenerAvgCost, 2, ',', '.').' / pc')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('primary')
                ->chart([590, 630, 677, 682]),

            Stat::make('Material Steel Specs', "{$steelCount} Spesifikasi")
                ->description('Coil: $ '.number_format($avgCoil, 3).' | Sheet: $ '.number_format($avgSheet, 3))
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('info')
                ->chart([1.15, 1.18, 1.20, 1.23]),
        ];
    }
}

