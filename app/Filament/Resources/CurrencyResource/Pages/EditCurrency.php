<?php

namespace App\Filament\Resources\CurrencyResource\Pages;

use App\Filament\Resources\CurrencyResource;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Services\CurrencyRecalculationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCurrency extends EditRecord
{
    protected static string $resource = CurrencyResource::class;

    protected static ?string $title = 'Ubah Kurs & Gap Kuartalan';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;
        $year = (int) ($record->year ?? date('Y'));
        $quarter = $record->quarter ?? 'Q1';

        $usd = ($record->code === 'USD') ? $record : Currency::where('code', 'USD')->where('year', $year)->where('quarter', $quarter)->first();
        $jpy = ($record->code === 'JPY') ? $record : Currency::where('code', 'JPY')->where('year', $year)->where('quarter', $quarter)->first();

        $rateUsd = (float) ($usd ? $usd->exchange_rate_to_idr : ($record->code === 'USD' ? $record->exchange_rate_to_idr : 16000));
        $rateJpy = (float) ($jpy ? $jpy->exchange_rate_to_idr : 105);

        $gapAp = (float) ($usd ? ($usd->gap_ap_usd ?? $usd->gap_hrc_usd ?? 0) : ($record->gap_ap_usd ?? $record->gap_hrc_usd ?? 0));
        $gapAr = (float) ($usd ? ($usd->gap_ar_usd ?? 0) : ($record->gap_ar_usd ?? 0));

        $gapHrc = (float) ($usd ? ($usd->gap_hrc_usd ?? $gapAp) : ($record->gap_hrc_usd ?? $gapAp));
        $gapCrc = (float) ($usd ? ($usd->gap_crc_usd ?? $gapAp) : ($record->gap_crc_usd ?? $gapAp));
        $gapHrcAr = (float) ($usd ? ($usd->gap_hrc_ar_usd ?? $gapAr) : ($record->gap_hrc_ar_usd ?? $gapAr));
        $gapCrcAr = (float) ($usd ? ($usd->gap_crc_ar_usd ?? $gapAr) : ($record->gap_crc_ar_usd ?? $gapAr));

        $data['kurs_usd'] = $rateUsd;
        $data['kurs_jpy'] = $rateJpy;
        $data['gap_ap_usd'] = $gapAp;
        $data['gap_ar_usd'] = $gapAr;
        $data['gap_hrc_usd'] = $gapHrc;
        $data['gap_crc_usd'] = $gapCrc;
        $data['gap_hrc_ar_usd'] = $gapHrcAr;
        $data['gap_crc_ar_usd'] = $gapCrcAr;
        $data['notes'] = $usd ? $usd->notes : $record->notes;

        $apKg = round($gapAp / 1000, 4);
        $arKg = round($gapAr / 1000, 4);

        $data['gap_ap_kg_usd'] = number_format($apKg, 4, '.', '');
        $data['gap_ap_idr'] = 'Rp ' . number_format(round($apKg * $rateUsd), 0, ',', '.');
        $data['gap_ar_kg_usd'] = number_format($arKg, 4, '.', '');
        $data['gap_ar_idr'] = 'Rp ' . number_format(round($arKg * $rateUsd), 0, ',', '.');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
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
        Currency::updateOrCreate(
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-btn-back'])
                ->url(static::getResource()::getUrl('index')),
            Actions\DeleteAction::make()
                ->before(function (Currency $record, Actions\DeleteAction $action) {
                    if (strtoupper($record->code) === 'IDR') {
                        Notification::make()
                            ->danger()
                            ->title('Gagal Menghapus Kurs!')
                            ->body('Mata uang IDR (Rupiah) merupakan mata uang dasar sistem dan tidak dapat dihapus.')
                            ->send();

                        $action->cancel();
                    }
                })
                ->successNotificationTitle('Data Kurs & Gap Kuartalan Berhasil Dihapus!'),
        ];
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Kembali');
    }

    protected function getSavedNotification(): ?Notification
    {
        $record = $this->record;
        $steelCount = MaterialSteel::where('year', $record->year)->where('quarter', $record->quarter)->count();
        $stampingCount = StampingPart::where('year', $record->year)->where('quarter', $record->quarter)->count();
        $fastenerCount = Fastener::where('year', $record->year)->where('quarter', $record->quarter)->count();

        return Notification::make()
            ->success()
            ->title("Kurs & Gap ({$record->quarter} {$record->year}) Berhasil Disimpan!")
            ->body('Kurs USD Rp '.number_format((float) $record->exchange_rate_to_idr, 0, ',', '.')." dan patokan Gap baja telah aktif. Terhubung otomatis ke {$steelCount} Material Steel (AP/AR), {$stampingCount} Stamping Part, dan {$fastenerCount} Fasteners.");
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
