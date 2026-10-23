<?php

namespace App\Filament\Resources\SubAssemblyPartResource\Pages;

use App\Filament\Resources\SubAssemblyPartResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubAssemblyPart extends EditRecord
{
    protected static string $resource = SubAssemblyPartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
