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
