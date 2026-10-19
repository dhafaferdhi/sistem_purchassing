<?php

namespace App\Filament\Resources\GlobalVariableResource\Pages;

use App\Filament\Resources\GlobalVariableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGlobalVariable extends EditRecord
{
    protected static string $resource = GlobalVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
