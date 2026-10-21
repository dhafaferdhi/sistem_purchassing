<?php

namespace App\Filament\Resources\PudMonitoringResource\Pages;

use App\Filament\Resources\PudMonitoringResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPudMonitoring extends ViewRecord
{
    protected static string $resource = PudMonitoringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
            Actions\EditAction::make(),
        ];
    }
}
