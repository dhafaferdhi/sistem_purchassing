<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQuotations extends ListRecords
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Ekspor Excel / CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn () => route('quotations.export.csv'))
                ->openUrlInNewTab(),
            Actions\CreateAction::make(),
        ];
    }
}
