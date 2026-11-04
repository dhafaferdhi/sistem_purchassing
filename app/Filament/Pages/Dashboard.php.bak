<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CostBreakdownDoughnutChart;
use App\Filament\Widgets\ExecutivePurchasingStatsWidget;
use App\Filament\Widgets\LatestPartsPriceMatrixWidget;
use App\Filament\Widgets\PudProgressSlaWidget;
use App\Filament\Widgets\PurchasingHeaderWidget;
use App\Filament\Widgets\SteelPriceTrendChart;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Support\Enums\MaxWidth;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Purchasing & Cost Planning Executive Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 3,
                ])->schema([
                    Select::make('year')
                        ->label('Tahun Fiskal')
                        ->options(function () {
                            $years = array_unique(array_merge(
                                StampingPart::distinct()->pluck('year')->filter()->toArray(),
                                Fastener::distinct()->pluck('year')->filter()->toArray(),
                                MaterialSteel::distinct()->pluck('year')->filter()->toArray(),
                                [(int) date('Y'), (int) date('Y') + 1, (int) date('Y') - 1]
                            ));
                            rsort($years);

                            return array_combine($years, array_map(fn ($y) => "Tahun {$y}", $years));
                        })
                        ->default((int) date('Y')),

                    Select::make('quarter')
                        ->label('Kuartal (Quarter)')
                        ->options([
                            'all' => 'Semua Kuartal',
                            'Q1' => 'Q1 (Januari - Maret)',
                            'Q2' => 'Q2 (April - Juni)',
                            'Q3' => 'Q3 (Juli - September)',
                            'Q4' => 'Q4 (Oktober - Desember)',
                        ])
                        ->default('all'),

                    Select::make('supplier_id')
                        ->label('Filter Supplier')
                        ->options(fn () => Supplier::pluck('name', 'id')->toArray())
                        ->searchable()
                        ->placeholder('Semua Supplier'),
                ]),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            PurchasingHeaderWidget::class,
            ExecutivePurchasingStatsWidget::class,
            SteelPriceTrendChart::class,
            CostBreakdownDoughnutChart::class,
            LatestPartsPriceMatrixWidget::class,
            PudProgressSlaWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}

