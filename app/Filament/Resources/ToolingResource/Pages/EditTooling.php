<?php

namespace App\Filament\Resources\ToolingResource\Pages;

use App\Filament\Resources\ToolingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTooling extends EditRecord
{
    protected static string $resource = ToolingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
