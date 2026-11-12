<?php

namespace App\Filament\Pages;

use App\Models\Part;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PartPriceTrend extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationGroup = 'Kalkulator & Purchasing';

    protected static ?string $navigationLabel = 'Tren Harga Part (Price Trend)';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.part-price-trend';

    public ?int $selectedPartId = null;

    public string $partSearch = '';

    public function mount(): void
    {
        $firstPart = Part::whereHas('priceHistories')->first() ?? Part::first();
        if ($firstPart) {
            $this->selectedPartId = $firstPart->id;
        }
    }

    public function getAvailablePartsProperty(): Collection
    {
        $search = trim($this->partSearch);
        $columns = ['id', 'item_code', 'part_number', 'description', 'part_name', 'main_category', 'sub_category'];

        $parts = Part::query()
            ->select($columns)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('item_code', 'like', "%{$search}%")
                        ->orWhere('part_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('part_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('part_number')
            ->limit(100)
            ->get();

        if ($this->selectedPartId && ! $parts->contains('id', $this->selectedPartId)) {
            $selected = Part::query()->select($columns)->find($this->selectedPartId);
            if ($selected) {
                $parts->prepend($selected);
            }
        }

        return $parts;
    }

    public function getSelectedPartProperty(): ?Part
    {
        if (! $this->selectedPartId) {
            return null;
        }

        return Part::with(['material', 'priceHistories.supplier'])->find($this->selectedPartId);
    }

    public function getPriceHistoriesProperty(): Collection
    {
        if (! $this->selectedPart) {
            return collect();
        }

        return $this->selectedPart->priceHistories()->with('supplier')->orderBy('price_date', 'asc')->get();
    }

    public function getMetricsProperty(): array
    {
        $histories = $this->priceHistories;
        if ($histories->isEmpty()) {
            return [
                'latest_price' => 0,
                'min_price' => 0,
                'max_price' => 0,
                'overall_change' => 0,
                'has_data' => false,
            ];
        }

        $latest = (float) $histories->last()->unit_price_idr;
        $first = (float) $histories->first()->unit_price_idr;
        $min = (float) $histories->min('unit_price_idr');
        $max = (float) $histories->max('unit_price_idr');
        $overallChange = $first > 0 ? round((($latest - $first) / $first) * 100, 2) : 0;

        return [
            'latest_price' => $latest,
            'min_price' => $min,
            'max_price' => $max,
            'overall_change' => $overallChange,
            'has_data' => true,
        ];
    }
}
