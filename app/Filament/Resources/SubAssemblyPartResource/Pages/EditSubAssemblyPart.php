<?php

namespace App\Filament\Resources\SubAssemblyPartResource\Pages;

use App\Filament\Resources\SubAssemblyPartResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;

class EditSubAssemblyPart extends EditRecord
{
    protected static string $resource = SubAssemblyPartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_sheet')
                ->label('Lembar Kalkulasi')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->slideOver()
                ->modalHeading(fn () => "Lembar Perhitungan Sub-Assembly: {$this->record->part_number} - {$this->record->part_name}")
                ->modalWidth(MaxWidth::SevenExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(fn () => view('filament.resources.sub-assembly-parts.sheet-modal', ['record' => $this->record])),
            Actions\Action::make('back')
                ->label('Keluar / Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
