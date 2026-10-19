<?php

namespace App\Filament\Resources\GlobalVariableResource\Pages;

use App\Filament\Resources\GlobalVariableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGlobalVariables extends ListRecords
{
    protected static string $resource = GlobalVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
