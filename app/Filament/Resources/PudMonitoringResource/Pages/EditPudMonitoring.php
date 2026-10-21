<?php

namespace App\Filament\Resources\PudMonitoringResource\Pages;

use App\Filament\Resources\PudMonitoringResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPudMonitoring extends EditRecord
{
    protected static string $resource = PudMonitoringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $oldStatus = $this->record->status;
        $newStatus = $data['status'] ?? $oldStatus;
        $by = auth()->user()?->name ?: 'PUD Admin';

        $action = ($oldStatus !== $newStatus)
            ? "Status diubah: {$oldStatus} → {$newStatus}"
            : 'PUD memproses update data & supplier quotation';

        $history = $this->record->history ?? [];
        $history[] = [
            'action' => $action,
            'by' => $by,
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
