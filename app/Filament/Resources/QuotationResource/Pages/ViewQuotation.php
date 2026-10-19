<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label('Cetak PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn () => route('quotations.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('approve')
                ->label('Setujui (Approve)')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'submitted' && (Auth::user()?->isAdmin() || Auth::user()?->isApprover()))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => 'approved',
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);
                    Notification::make()
                        ->title("Dokumen {$this->record->document_number} telah disetujui.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reject')
                ->label('Tolak (Reject)')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'submitted' && (Auth::user()?->isAdmin() || Auth::user()?->isApprover()))
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_note')
                        ->label('Alasan Penolakan')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'rejected',
                        'notes' => ($this->record->notes ? $this->record->notes . "\n" : '') . "[Ditolak]: " . $data['rejection_note'],
                    ]);
                    Notification::make()
                        ->title("Dokumen {$this->record->document_number} telah ditolak.")
                        ->warning()
                        ->send();
                }),

            Actions\EditAction::make(),
        ];
    }
}

