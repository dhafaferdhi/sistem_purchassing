<?php

namespace App\Filament\Resources\GlobalVariableResource\Pages;

use App\Filament\Resources\GlobalVariableResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGlobalVariable extends CreateRecord
{
    protected static string $resource = GlobalVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
        ];
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Kembali');
    }
}
