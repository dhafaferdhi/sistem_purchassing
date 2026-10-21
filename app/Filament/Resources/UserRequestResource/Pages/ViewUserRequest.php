<?php

namespace App\Filament\Resources\UserRequestResource\Pages;

use App\Filament\Resources\UserRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserRequest extends ViewRecord
{
    protected static string $resource = UserRequestResource::class;

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
