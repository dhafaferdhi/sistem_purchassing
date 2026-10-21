<?php

namespace App\Filament\Resources\UserRequestResource\Pages;

use App\Filament\Resources\UserRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserRequest extends CreateRecord
{
    protected static string $resource = UserRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['history'] = [
            [
                'action' => 'Request Created',
                'by' => $data['user_name'] ?: (auth()->user()?->name ?? 'User'),
                'time' => now()->format('d/m/Y H:i'),
            ],
        ];

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
