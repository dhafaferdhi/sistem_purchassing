<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MonthlyRollover;
use App\Filament\Resources\FastenerResource;
use App\Filament\Resources\MaterialSteelResource;
use App\Filament\Resources\StampingPartResource;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class PurchasingHeaderWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'filament.widgets.purchasing-header';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $year = (int) ($this->filters['year'] ?? date('Y'));
        $quarter = $this->filters['quarter'] ?? 'all';

        $currQuery = Currency::where('code', 'USD')->where('year', $year);
        if ($quarter !== 'all') {
            $currQuery->where('quarter', $quarter);
        }
        $latestRate = $currQuery->latest('id')->value('exchange_rate_to_idr')
            ?? Currency::where('code', 'USD')->latest('year')->value('exchange_rate_to_idr')
            ?? 16465;

        $stampingCount = StampingPart::where('year', $year)
            ->when($quarter !== 'all', fn ($q) => $q->where('quarter', $quarter))
            ->count();

        $fastenerCount = Fastener::where('year', $year)
            ->when($quarter !== 'all', fn ($q) => $q->where('quarter', $quarter))
            ->count();

        $steelCount = MaterialSteel::where('year', $year)
            ->when($quarter !== 'all', fn ($q) => $q->where('quarter', $quarter))
            ->count();

        $supplierCount = Supplier::count();

        return [
            'activeYear' => $year,
            'activeQuarter' => $quarter === 'all' ? 'Semua Kuartal' : $quarter,
            'usdRate' => (float) $latestRate,
            'stampingCount' => $stampingCount,
            'fastenerCount' => $fastenerCount,
            'steelCount' => $steelCount,
            'supplierCount' => $supplierCount,
            'stampingCreateUrl' => StampingPartResource::getUrl('create'),
            'fastenerCreateUrl' => FastenerResource::getUrl('create'),
            'stampingListUrl' => StampingPartResource::getUrl('index'),
            'fastenerListUrl' => FastenerResource::getUrl('index'),
            'materialSteelUrl' => MaterialSteelResource::getUrl('index'),
            'rolloverUrl' => MonthlyRollover::getUrl(),
        ];
    }
}

