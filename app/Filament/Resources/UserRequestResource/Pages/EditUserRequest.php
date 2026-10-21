<?php

namespace App\Filament\Resources\UserRequestResource\Pages;

use App\Filament\Resources\UserRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserRequest extends EditRecord
{
    protected static string $resource = UserRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $history = $this->record->history ?? [];
        $history[] = [
            'action' => 'Request Information Edited',
            'by' => auth()->user()?->name ?: ($data['user_name'] ?? 'User'),
            'time' => now()->format('d/m/Y H:i'),
        ];
        $data['history'] = $history;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
