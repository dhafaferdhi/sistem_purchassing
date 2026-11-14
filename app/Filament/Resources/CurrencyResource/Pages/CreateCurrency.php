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

    protected static ?string $title = 'Tambah Kurs & Gap Kuartalan';

    public function mount(): void
    {
        parent::mount();

        $year = (int) date('Y');
        $quarter = 'Q1';

        $usd = Currency::where('code', 'USD')->where('year', $year)->where('quarter', $quarter)->first()
            ?? Currency::where('code', 'USD')->latest('id')->first();
        $jpy = Currency::where('code', 'JPY')->where('year', $year)->where('quarter', $quarter)->first()
            ?? Currency::where('code', 'JPY')->latest('id')->first();

        $rateUsd = (float) ($usd ? $usd->exchange_rate_to_idr : 16000);
        $gapAp = (float) ($usd ? ($usd->gap_ap_usd ?? $usd->gap_hrc_usd ?? 0) : 0);
        $gapAr = (float) ($usd ? ($usd->gap_ar_usd ?? 0) : 0);
        $gapHrc = (float) ($usd ? ($usd->gap_hrc_usd ?? $gapAp) : 0);
        $gapCrc = (float) ($usd ? ($usd->gap_crc_usd ?? $gapAp) : 0);
        $gapHrcAr = (float) ($usd ? ($usd->gap_hrc_ar_usd ?? $gapAr) : 0);
        $gapCrcAr = (float) ($usd ? ($usd->gap_crc_ar_usd ?? $gapAr) : 0);

        $apKg = round($gapAp / 1000, 4);
        $arKg = round($gapAr / 1000, 4);

        $this->form->fill([
            'year' => $year,
            'quarter' => $quarter,
            'kurs_usd' => $rateUsd,
            'kurs_jpy' => $jpy ? $jpy->exchange_rate_to_idr : 105,
            'gap_ap_usd' => $gapAp,
            'gap_ap_kg_usd' => number_format($apKg, 4, '.', ''),
            'gap_ap_idr' => 'Rp ' . number_format(round($apKg * $rateUsd), 0, ',', '.'),
            'gap_ar_usd' => $gapAr,
            'gap_ar_kg_usd' => number_format($arKg, 4, '.', ''),
            'gap_ar_idr' => 'Rp ' . number_format(round($arKg * $rateUsd), 0, ',', '.'),
            'gap_hrc_usd' => $gapHrc,
            'gap_crc_usd' => $gapCrc,
            'gap_hrc_ar_usd' => $gapHrcAr,
            'gap_crc_ar_usd' => $gapCrcAr,
            'notes' => $usd ? $usd->notes : null,
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $year = (int) $data['year'];
        $quarter = $data['quarter'];
        $rateUsd = (float) $data['kurs_usd'];
        $rateJpy = (float) ($data['kurs_jpy'] ?? 105);
        $gapAp = isset($data['gap_ap_usd']) ? (float) $data['gap_ap_usd'] : 0.0;
        $gapAr = isset($data['gap_ar_usd']) ? (float) $data['gap_ar_usd'] : 0.0;
        $gapHrc = isset($data['gap_hrc_usd']) && (float) $data['gap_hrc_usd'] != 0.0 ? (float) $data['gap_hrc_usd'] : $gapAp;
        $gapCrc = isset($data['gap_crc_usd']) && (float) $data['gap_crc_usd'] != 0.0 ? (float) $data['gap_crc_usd'] : $gapAp;
        $gapHrcAr = isset($data['gap_hrc_ar_usd']) && (float) $data['gap_hrc_ar_usd'] != 0.0 ? (float) $data['gap_hrc_ar_usd'] : $gapAr;
        $gapCrcAr = isset($data['gap_crc_ar_usd']) && (float) $data['gap_crc_ar_usd'] != 0.0 ? (float) $data['gap_crc_ar_usd'] : $gapAr;
        $notes = $data['notes'] ?? null;

        $month = match ($quarter) {
            'Q1' => 1,
            'Q2' => 4,
            'Q3' => 7,
            'Q4' => 10,
            default => 1,
        };

        // 1. Simpan atau Update Kurs USD & Patokan Gap Baja AP & AR
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
                'gap_ap_usd' => $gapAp,
                'gap_ar_usd' => $gapAr,
                'gap_hrc_usd' => $gapHrc,
                'gap_crc_usd' => $gapCrc,
                'gap_hrc_ar_usd' => $gapHrcAr,
                'gap_crc_ar_usd' => $gapCrcAr,
                'notes' => $notes,
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

        // 3. Otomatis sync patokan kurs & gap ke records kuartal yang bersangkutan
        CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($usd, true);

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
            ->title('Kurs & Gap Kuartalan Berhasil Disimpan!')
            ->body('Kurs USD & JPY beserta Gap HRC & CRC berhasil disimpan dan seluruh master data Material Steel, Stamping Part, serta Fasteners periode ini otomatis terhubung dan diperbarui.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-btn-back'])
                ->url(static::getResource()::getUrl('index')),
        ];
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Kembali');
    }
}
