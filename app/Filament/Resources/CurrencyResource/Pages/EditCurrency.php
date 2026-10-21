<?php

namespace App\Filament\Resources\CurrencyResource\Pages;

use App\Filament\Resources\CurrencyResource;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Services\CurrencyRecalculationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCurrency extends EditRecord
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Kembali');
    }

    protected function afterSave(): void
    {
        CurrencyRecalculationService::recalculateForCurrency($this->record);
    }

    protected function getSavedNotification(): ?Notification
    {
        $record = $this->record;
        $steelCount = MaterialSteel::where('currency_id', $record->id)->orWhere('currency_code', $record->code)->count();
        $stampingCount = StampingPart::where('currency_id', $record->id)->orWhere('currency_code', $record->code)->count();
        $fastenerCount = Fastener::where('currency_id', $record->id)->orWhere('currency_code', $record->code)->count();

        return Notification::make()
            ->success()
            ->title("Kurs {$record->code} ({$record->quarter} {$record->year}) Berhasil Disimpan!")
            ->body('Nilai kurs Rp '.number_format((float) $record->exchange_rate_to_idr, 0, ',', '.')." telah aktif. Sebanyak {$steelCount} data Material Steel, {$stampingCount} data Stamping Part, dan {$fastenerCount} data Pasterner otomatis dihitung ulang ke IDR.");
    }
}
