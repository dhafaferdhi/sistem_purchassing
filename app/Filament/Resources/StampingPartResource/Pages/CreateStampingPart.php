<?php

namespace App\Filament\Resources\StampingPartResource\Pages;

use App\Filament\Resources\StampingPartResource;
use App\Models\Currency;
use App\Models\StampingPart;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateStampingPart extends CreateRecord
{
    protected static string $resource = StampingPartResource::class;

    public function mount(): void
    {
        parent::mount();

        $raw = $this->form->getRawState();
        if (request()->has('year')) {
            $raw['year'] = (int) request()->get('year');
        }
        if (request()->has('quarter')) {
            $raw['quarter'] = request()->get('quarter');
        }

        $quarter = $raw['quarter'] ?? 'Q1';
        $year = (int) ($raw['year'] ?? date('Y'));
        $supplierId = $raw['supplier_id'] ?? null;
        $spec = $raw['steel_spec'] ?? null;

        $prevData = StampingPart::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $spec);
        if ($prevData) {
            if (empty($raw['steel_spec']) && ! empty($prevData['steel_spec'])) {
                $raw['steel_spec'] = $prevData['steel_spec'];
            }
            if (empty($raw['double_thickness']) && ! empty($prevData['double_thickness'])) {
                $raw['double_thickness'] = $prevData['double_thickness'];
            }
            if (empty($raw['gap_steel_usd']) && isset($prevData['gap_steel_usd'])) {
                $raw['gap_steel_usd'] = $prevData['gap_steel_usd'];
            }
            if (empty($raw['gap_steel_kg_usd']) && isset($prevData['gap_steel_kg_usd'])) {
                $raw['gap_steel_kg_usd'] = $prevData['gap_steel_kg_usd'];
            }
            if (empty($raw['coil_price']) && isset($prevData['coil_price'])) {
                $raw['coil_price'] = $prevData['coil_price'];
            }
            if (empty($raw['sheet_price']) && isset($prevData['sheet_price'])) {
                $raw['sheet_price'] = $prevData['sheet_price'];
            }
            if (empty($raw['supplier_id']) && ! empty($prevData['supplier_id'])) {
                $raw['supplier_id'] = $prevData['supplier_id'];
                $raw['vendor_code'] = $prevData['vendor_code'];
                $raw['supplier_name'] = $prevData['supplier_name'];
            }

            $rate = (float) ($raw['exchange_rate'] ?? $prevData['exchange_rate'] ?? 16000);
            $raw['exchange_rate'] = $rate;
            $raw['coil_price_idr'] = round((float) ($raw['coil_price'] ?? 0) * $rate, 2);
            $raw['sheet_price_idr'] = round((float) ($raw['sheet_price'] ?? 0) * $rate, 2);

            $this->form->fill($raw);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_top')
                ->label('Simpan Data')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action('create'),

            Actions\Action::make('pull_previous')
                ->label('Tarik Data Kuartal Sebelumnya')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->tooltip('Otomatis mengambil data dari kuartal sebelumnya (misal Q1 2027 mengambil dari Q4 2026)')
                ->action(function () {
                    $raw = $this->form->getRawState();
                    $quarter = $raw['quarter'] ?? 'Q1';
                    $year = (int) ($raw['year'] ?? date('Y'));
                    $supplierId = $raw['supplier_id'] ?? null;
                    $spec = $raw['steel_spec'] ?? null;

                    $prevData = StampingPart::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $spec);

                    if ($prevData) {
                        $raw['steel_spec'] = $prevData['steel_spec'] ?? ($raw['steel_spec'] ?? null);
                        $raw['double_thickness'] = $prevData['double_thickness'] ?? ($raw['double_thickness'] ?? null);
                        $raw['gap_steel_usd'] = $prevData['gap_steel_usd'] ?? 0;
                        $raw['gap_steel_kg_usd'] = $prevData['gap_steel_kg_usd'] ?? 0;
                        $raw['coil_price'] = $prevData['coil_price'] ?? 0;
                        $raw['sheet_price'] = $prevData['sheet_price'] ?? 0;

                        if (! empty($prevData['supplier_id']) && empty($raw['supplier_id'])) {
                            $raw['supplier_id'] = $prevData['supplier_id'];
                            $raw['vendor_code'] = $prevData['vendor_code'];
                            $raw['supplier_name'] = $prevData['supplier_name'];
                        }

                        $rate = (float) ($raw['exchange_rate'] ?? $prevData['exchange_rate'] ?? 16000);
                        $raw['exchange_rate'] = $rate;
                        $raw['coil_price_idr'] = round((float) ($prevData['coil_price'] ?? 0) * $rate, 2);
                        $raw['sheet_price_idr'] = round((float) ($prevData['sheet_price'] ?? 0) * $rate, 2);

                        $this->form->fill($raw);

                        Notification::make()
                            ->title('Data Kuartal Sebelumnya Berhasil Diambil!')
                            ->body("Acuan harga dari {$prevData['label']} ({$prevData['source']}) berhasil diisikan.")
                            ->success()
                            ->send();
                    } else {
                        $info = StampingPart::getPreviousQuarterInfo($quarter, $year);
                        Notification::make()
                            ->title('Belum Ada Data di '.$info['label'])
                            ->body('Tidak ditemukan data acuan pada kuartal sebelumnya untuk periode ini.')
                            ->info()
                            ->send();
                    }
                }),

            Actions\Action::make('back')
                ->label('Keluar / Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
        ];
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Keluar');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Stamping Part Berhasil Ditambahkan!')
            ->body("Data {$this->record->supplier_name} ({$this->record->year} - {$this->record->quarter}) berhasil disimpan ke database.");
    }

    protected function getRedirectUrl(): string
    {
        if ($this->record && $this->record->year) {
            return static::getResource()::getUrl('index', ['selectedYear' => (string) $this->record->year]);
        }

        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Pastikan vendor_code dan supplier_name terisi atau buat supplier otomatis jika diinput manual
        if (empty($data['supplier_id']) && ! empty($data['supplier_name'])) {
            $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['supplier_name']), 0, 5));
            $vendorCode = ! empty($data['vendor_code']) ? $data['vendor_code'] : 'VND-'.($clean ?: 'SUP').'-'.rand(10, 99);
            $supplier = Supplier::firstOrCreate(
                ['name' => $data['supplier_name']],
                [
                    'code' => $vendorCode,
                    'category' => 'Steel',
                    'is_active' => true,
                ]
            );
            $data['supplier_id'] = $supplier->id;
            $data['vendor_code'] = $data['vendor_code'] ?? $supplier->code;
        } elseif (! empty($data['supplier_id'])) {
            $supplier = Supplier::find($data['supplier_id']);
            if ($supplier) {
                if (empty($data['vendor_code'])) {
                    $data['vendor_code'] = $supplier->code;
                }
                if (empty($data['supplier_name'])) {
                    $data['supplier_name'] = $supplier->name;
                }
            }
        }

        // Pastikan currency_id terisi
        if (empty($data['currency_id'])) {
            $year = (int) ($data['year'] ?? date('Y'));
            $data['currency_id'] = Currency::where('code', 'USD')->where('year', $year)->first()?->id
                ?? Currency::where('code', 'USD')->first()?->id
                ?? Currency::first()?->id;
            $data['currency_code'] = 'USD';
        }

        return $data;
    }
}
