<?php

namespace App\Filament\Resources\CurrencyResource\Pages;

use App\Filament\Resources\CurrencyResource;
use App\Models\Currency;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('+ Tambah Kurs Baru'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Kuartal')
                ->badge(fn () => Currency::count())
                ->badgeColor('gray'),

            'Q1' => Tab::make('Kuartal 1 (Q1)')
                ->badge(fn () => Currency::where('quarter', 'Q1')->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q1')),

            'Q2' => Tab::make('Kuartal 2 (Q2)')
                ->badge(fn () => Currency::where('quarter', 'Q2')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q2')),

            'Q3' => Tab::make('Kuartal 3 (Q3)')
                ->badge(fn () => Currency::where('quarter', 'Q3')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q3')),

            'Q4' => Tab::make('Kuartal 4 (Q4)')
                ->badge(fn () => Currency::where('quarter', 'Q4')->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q4')),
        ];
    }
}
