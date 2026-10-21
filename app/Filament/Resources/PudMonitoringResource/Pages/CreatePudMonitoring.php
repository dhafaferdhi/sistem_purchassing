<?php

namespace App\Filament\Resources\PudMonitoringResource\Pages;

use App\Filament\Resources\PudMonitoringResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePudMonitoring extends CreateRecord
{
    protected static string $resource = PudMonitoringResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['history'] = [
            [
                'action' => 'Request Created via Monitoring',
                'by' => $data['user_name'] ?: (auth()->user()?->name ?? 'PUD Admin'),
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
