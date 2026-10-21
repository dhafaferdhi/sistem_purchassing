<?php

namespace App\Filament\Resources\PudMonitoringResource\Pages;

use App\Filament\Resources\PudMonitoringResource;
use App\Filament\Resources\UserRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPudMonitorings extends ListRecords
{
    protected static string $resource = PudMonitoringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_request')
                ->label('+ Create Request')
                ->icon('heroicon-o-plus')
                ->url(UserRequestResource::getUrl('create')),
        ];
    }
}
