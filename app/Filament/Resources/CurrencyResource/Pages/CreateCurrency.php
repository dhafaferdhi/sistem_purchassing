<?php

namespace App\Filament\Resources\CurrencyResource\Pages;

use App\Filament\Resources\CurrencyResource;
use App\Models\Currency;
use App\Services\CurrencyRecalculationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCurrency extends CreateRecord
{
    protected static string $resource = CurrencyResource::class;

    protected static ?string $title = 'Tambah Kurs Mata Uang';

    public function mount(): void
    {
        parent::mount();

        $year = (int) date('Y');
        $quarter = 'Q1';

        $usd = Currency::where('code', 'USD')->where('year', $year)->where('quarter', $quarter)->first()
            ?? Currency::where('code', 'USD')->latest('id')->first();
        $jpy = Currency::where('code', 'JPY')->where('year', $year)->where('quarter', $quarter)->first()
            ?? Currency::where('code', 'JPY')->latest('id')->first();

        $this->form->fill([
            'year' => $year,
            'quarter' => $quarter,
            'kurs_usd' => $usd ? $usd->exchange_rate_to_idr : 16000,
            'kurs_jpy' => $jpy ? $jpy->exchange_rate_to_idr : 105,
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $year = (int) $data['year'];
        $quarter = $data['quarter'];
        $rateUsd = (float) $data['kurs_usd'];
        $rateJpy = (float) $data['kurs_jpy'];

        $month = match ($quarter) {
            'Q1' => 1,
            'Q2' => 4,
            'Q3' => 7,
            'Q4' => 10,
            default => 1,
        };

        // 1. Simpan atau Update Kurs USD
        $usd = Currency::updateOrCreate(
            [
                'code' => 'USD',
                'year' => $year,
                'quarter' => $quarter,
            ],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate_to_idr' => $rateUsd,
                'month' => $month,
            ]
        );

        // 2. Simpan atau Update Kurs JPY
        $jpy = Currency::updateOrCreate(
            [
                'code' => 'JPY',
                'year' => $year,
                'quarter' => $quarter,
            ],
            [
                'name' => 'Japanese Yen',
                'symbol' => '¥',
                'exchange_rate_to_idr' => $rateJpy,
                'month' => $month,
            ]
        );

        // 3. Otomatis hitung ulang ke Material Steel, Stamping Part, dan Fastener (pasterner)
        CurrencyRecalculationService::recalculateForCurrency($usd);
        CurrencyRecalculationService::recalculateForCurrency($jpy);

        return $usd;
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Kurs Berhasil Disimpan!')
            ->body('Kurs USD & JPY berhasil disimpan dan harga pada Material Steel, Stamping Part, serta Pasterner telah otomatis diperbarui.');
    }

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
