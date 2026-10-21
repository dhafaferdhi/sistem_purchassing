<?php

namespace App\Filament\Resources\PudMonitoringResource\Pages;

use App\Filament\Resources\PudMonitoringResource;
use Filament\Resources\Pages\ListRecords;

class ListPudMonitorings extends ListRecords
{
    protected static string $resource = PudMonitoringResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
