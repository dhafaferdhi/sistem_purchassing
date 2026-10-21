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
}
