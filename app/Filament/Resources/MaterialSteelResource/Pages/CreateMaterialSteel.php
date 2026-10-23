<?php

namespace App\Filament\Resources\MaterialSteelResource\Pages;

use App\Filament\Resources\MaterialSteelResource;
use App\Models\Material;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class CreateMaterialSteel extends CreateRecord
{
    protected static string $resource = MaterialSteelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_top')
                ->label('Simpan Data')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action('create'),

            Actions\Action::make('pull_previous')
                ->label('Tarik Data Kuartal')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->tooltip('Pilih dan tarik data acuan dari kuartal sebelumnya')
                ->modalHeading('Tarik Data Acuan Material Steel')
                ->modalDescription('Pilih kuartal dan spesifikasi baja yang ingin Anda jadikan acuan harga.')
                ->modalSubmitActionLabel('Terapkan Data Terpilih')
                ->modalWidth(MaxWidth::TwoExtraLarge)
                ->form([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('source_year')
                                ->label('Tahun Acuan')
                                ->options(function () {
                                    $current = (int) date('Y');
                                    $years = range($current + 2, 2020);

                                    return array_combine($years, $years);
                                })
                                ->default(function () {
                                    $q = $this->data['quarter'] ?? 'Q1';
                                    $y = (int) ($this->data['year'] ?? date('Y'));

                                    return $q === 'Q1' ? $y - 1 : $y;
                                })
                                ->required()
                                ->live(),

                            Forms\Components\Select::make('source_quarter')
                                ->label('Kuartal Acuan')
                                ->options([
                                    'Q1' => 'Q1 (Januari - Maret)',
                                    'Q2' => 'Q2 (April - Juni)',
                                    'Q3' => 'Q3 (Juli - September)',
                                    'Q4' => 'Q4 (Oktober - Desember)',
                                ])
                                ->default(function () {
                                    $q = $this->data['quarter'] ?? 'Q1';

                                    return match ($q) {
                                        'Q1' => 'Q4',
                                        'Q2' => 'Q1',
                                        'Q3' => 'Q2',
                                        'Q4' => 'Q3',
                                        default => 'Q4',
                                    };
                                })
                                ->required()
                                ->live(),
                        ]),

                    Forms\Components\Select::make('source_record_id')
                        ->label('Pilih Spesifikasi Baja & Supplier')
                        ->placeholder('-- Pilih Spesifikasi Baja Acuan --')
                        ->searchable()
                        ->live()
                        ->options(function (Forms\Get $get) {
                            $y = (int) ($get('source_year') ?: date('Y'));
                            $q = $get('source_quarter') ?: 'Q1';

                            return MaterialSteel::where('year', $y)
                                ->where('quarter', $q)
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    $coil = number_format((float) $item->coil_price, 4);
                                    $sheet = number_format((float) $item->sheet_price, 4);
                                    $thick = $item->double_thickness ? " ({$item->double_thickness}mm)" : '';
                                    $supp = $item->supplier_name ?: ($item->vendor_code ?: 'Vendor');
                                    $label = "{$item->steel_spec}{$thick} — {$supp} [Coil: \${$coil} | Sheet: \${$sheet}]";

                                    return [$item->id => $label];
                                })
                                ->toArray();
                        })
                        ->helperText('Pilih salah satu spesifikasi baja dari daftar kuartal tersebut'),

                    Forms\Components\Placeholder::make('preview')
                        ->label('Pratinjau Data Terpilih')
                        ->content(function (Forms\Get $get) {
                            $id = $get('source_record_id');
                            if (! $id) {
                                return new HtmlString('<div class="p-3 text-sm text-gray-500 bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">Pilih salah satu data spesifikasi baja di atas untuk melihat rincian harga coil, sheet, kurs, dan spesifikasinya.</div>');
                            }
                            $rec = MaterialSteel::find($id);
                            if (! $rec) {
                                return '';
                            }
                            $rateStr = number_format((float) $rec->exchange_rate, 0, ',', '.');
                            $coilIdr = number_format(round((float) $rec->coil_price * (float) $rec->exchange_rate), 0, ',', '.');
                            $sheetIdr = number_format(round((float) $rec->sheet_price * (float) $rec->exchange_rate), 0, ',', '.');

                            return new HtmlString("
                                <div class='p-4 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded-lg text-sm space-y-2'>
                                    <div class='font-semibold text-primary-900 dark:text-primary-100 flex items-center justify-between'>
                                        <span class='text-base font-bold'>{$rec->steel_spec}</span>
                                        <span class='text-xs px-2 py-0.5 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 font-mono'>{$rec->quarter} {$rec->year}</span>
                                    </div>
                                    <div class='text-gray-700 dark:text-gray-300'><strong>Supplier:</strong> {$rec->supplier_name} <span class='text-xs text-gray-500'>({$rec->vendor_code})</span></div>
                                    <div class='grid grid-cols-2 gap-2 pt-2 border-t border-primary-200 dark:border-primary-800/50 text-xs text-gray-700 dark:text-gray-300'>
                                        <div><strong>Ketebalan:</strong> ".($rec->double_thickness ? "{$rec->double_thickness} mm" : '-').'</div>
                                        <div><strong>Dimensi / Size:</strong> '.($rec->size ?: '-')."</div>
                                        <div><strong>Coil Price:</strong> USD {$rec->coil_price} <span class='text-gray-500'>(Rp {$coilIdr})</span></div>
                                        <div><strong>Sheet Price:</strong> USD {$rec->sheet_price} <span class='text-gray-500'>(Rp {$sheetIdr})</span></div>
                                        <div><strong>Kurs Acuan:</strong> Rp {$rateStr}</div>
                                    </div>
                                </div>
                            ");
                        }),
                ])
                ->action(function (array $data) {
                    $selectedId = $data['source_record_id'] ?? null;
                    $raw = $this->data ?? [];
                    $quarter = $raw['quarter'] ?? 'Q1';
                    $year = (int) ($raw['year'] ?? date('Y'));

                    $record = null;
                    if ($selectedId) {
                        $record = MaterialSteel::find($selectedId);
                    }

                    // Fallback jika dipanggil tanpa ID spesifik (misal dari headless test)
                    if (! $record) {
                        $sourceYear = (int) ($data['source_year'] ?? 0);
                        $sourceQuarter = $data['source_quarter'] ?? null;
                        if ($sourceYear && $sourceQuarter) {
                            $record = MaterialSteel::where('year', $sourceYear)->where('quarter', $sourceQuarter)->latest('id')->first();
                        }
                    }

                    if (! $record) {
                        $supplierId = $raw['supplier_id'] ?? null;
                        $spec = $raw['steel_spec'] ?? null;
                        $thickness = $raw['double_thickness'] ?? null;
                        $prevData = MaterialSteel::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $spec, $thickness);
                        if ($prevData) {
                            $record = MaterialSteel::where('year', $prevData['prev_year'])
                                ->where('quarter', $prevData['prev_quarter'])
                                ->when($prevData['steel_spec'] ?? null, fn ($q, $s) => $q->where('steel_spec', $s))
                                ->latest('id')
                                ->first();
                        }
                    }

                    if ($record) {
                        $raw['steel_spec'] = $record->steel_spec;
                        $raw['double_thickness'] = $record->double_thickness;
                        $raw['size'] = $record->size;
                        $raw['coil_price'] = (float) $record->coil_price;
                        $raw['sheet_price'] = (float) $record->sheet_price;
                        $raw['ap_coil_price'] = $record->ap_coil_price ? (float) $record->ap_coil_price : null;
                        $raw['ap_sheet_price'] = $record->ap_sheet_price ? (float) $record->ap_sheet_price : null;

                        if (! empty($record->supplier_id)) {
                            $raw['supplier_id'] = $record->supplier_id;
                            $raw['vendor_code'] = $record->vendor_code;
                            $raw['supplier_name'] = $record->supplier_name;
                        }

                        $rate = (float) ($record->exchange_rate ?: ($raw['exchange_rate'] ?? 16000));
                        $raw['exchange_rate'] = $rate;
                        $raw['coil_price_idr'] = round((float) $record->coil_price * $rate, 2);
                        $raw['sheet_price_idr'] = round((float) $record->sheet_price * $rate, 2);

                        $this->form->fill($raw);

                        Notification::make()
                            ->title('Data Acuan Berhasil Diterapkan!')
                            ->body("Spesifikasi {$record->steel_spec} ({$record->supplier_name}) dari periode {$record->quarter} {$record->year} berhasil disalin ke formulir.")
                            ->success()
                            ->send();
                    } else {
                        $info = MaterialSteel::getPreviousQuarterInfo($quarter, $year);
                        Notification::make()
                            ->title('Belum Ada Data Acuan')
                            ->body('Tidak ditemukan data Material Steel pada periode yang dipilih.')
                            ->warning()
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
            ->label('Kembali');
    }

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Simpan');
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Pastikan vendor_code dan supplier_name terisi atau buat supplier otomatis jika diinput manual
        if (empty($data['supplier_id']) && ! empty($data['supplier_name'])) {
            $vendorCode = ! empty($data['vendor_code']) ? $data['vendor_code'] : 'VND-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['supplier_name']), 0, 5)).'-'.rand(10, 99);
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
                $data['vendor_code'] = $data['vendor_code'] ?? $supplier->code;
                $data['supplier_name'] = $data['supplier_name'] ?? $supplier->name;
            }
        }

        // Pastikan label 3 bulan ke belakang terisi
        $quarter = $data['quarter'] ?? 'Q1';
        $year = (int) ($data['year'] ?? date('Y'));
        $info = MaterialSteel::getPrevious3MonthsInfo($quarter, $year);

        $data['prev_month_1_label'] = $data['prev_month_1_label'] ?? $info['month_1'];
        $data['prev_month_2_label'] = $data['prev_month_2_label'] ?? $info['month_2'];
        $data['prev_month_3_label'] = $data['prev_month_3_label'] ?? $info['month_3'];

        // Bersihkan satuan mm dari ketebalan
        if (! empty($data['double_thickness'])) {
            $data['double_thickness'] = trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $data['double_thickness']));
        }

        // Hitung total Rupiah (IDR) & Gap Steel / MT
        $exchangeRate = (float) ($data['exchange_rate'] ?? 1);
        $coilPrice = (float) ($data['coil_price'] ?? 0);
        $sheetPrice = (float) ($data['sheet_price'] ?? 0);
        $prevCoil = $data['prev_coil_price'] ?? null;
        $prevSheet = $data['prev_sheet_price'] ?? null;
        $prevRate = $data['prev_rate'] ?? null;
        $priceMode = $data['price_mode'] ?? null;

        unset(
            $data['price_mode'],
            $data['prev_quarter_label'],
            $data['prev_record_found'],
            $data['prev_coil_price'],
            $data['prev_sheet_price'],
            $data['prev_rate'],
            $data['prev_quarter_info']
        );

        $spec = $data['steel_spec'] ?? '';
        $steelType = MaterialSteel::determineSteelType($spec);

        $hrVal = isset($data['hr_steel_usd']) && $data['hr_steel_usd'] !== '' ? (float) $data['hr_steel_usd'] : null;
        $crVal = isset($data['cr_steel_usd']) && $data['cr_steel_usd'] !== '' ? (float) $data['cr_steel_usd'] : null;
        $gapVal = isset($data['gap_steel_usd']) && $data['gap_steel_usd'] !== '' ? (float) $data['gap_steel_usd'] : null;

        if ($priceMode === 'baseline' || $quarter === 'Q1') {
            $data['hr_steel_usd'] = 0;
            $data['cr_steel_usd'] = 0;
            $data['gap_steel_usd'] = 0;
            $data['gap_steel_kg_usd'] = 0;
            $data['fluktuasi_jfe'] = 0;
        } else {
            if ($steelType === 'HR') {
                if ($hrVal !== null && $hrVal != 0.0) {
                    $data['gap_steel_usd'] = $hrVal;
                } elseif ($gapVal !== null && $gapVal != 0.0) {
                    $data['hr_steel_usd'] = $gapVal;
                    $data['gap_steel_usd'] = $gapVal;
                }
            } else {
                if ($crVal !== null && $crVal != 0.0) {
                    $data['gap_steel_usd'] = $crVal;
                } elseif ($gapVal !== null && $gapVal != 0.0) {
                    $data['cr_steel_usd'] = $gapVal;
                    $data['gap_steel_usd'] = $gapVal;
                }
            }

            if (! empty($data['gap_steel_usd']) && (float) $data['gap_steel_usd'] != 0.0) {
                $data['gap_steel_usd'] = (float) $data['gap_steel_usd'];
                $data['gap_steel_kg_usd'] = round($data['gap_steel_usd'] / 1000, 4);
            } else {
                $data['gap_steel_usd'] = round(($sheetPrice - $coilPrice) * 1000, 4);
                $data['gap_steel_kg_usd'] = round($data['gap_steel_usd'] / 1000, 4);
            }
        }

        // Jika mode adjustment, terapkan penyesuaian terhadap harga acuan kuartal sebelumnya
        if ($priceMode === 'adjustment') {
            if ($prevCoil === null && $prevSheet === null && ! empty($data['supplier_id']) && ! empty($spec)) {
                $prevQ = match ($quarter) {
                    'Q1' => 'Q4',
                    'Q2' => 'Q1',
                    'Q3' => 'Q2',
                    'Q4' => 'Q3',
                    default => 'Q1',
                };
                $prevY = ($quarter === 'Q1') ? ($year - 1) : $year;
                $cleanSpec = MaterialSteel::cleanSteelSpec($spec);
                $prev = MaterialSteel::where('supplier_id', $data['supplier_id'])
                    ->where(function ($q) use ($spec, $cleanSpec) {
                        $q->where('steel_spec', $cleanSpec)
                            ->orWhere('steel_spec', $spec);
                    })
                    ->where('quarter', $prevQ)
                    ->where('year', $prevY)
                    ->first();
                if ($prev) {
                    $prevCoil = $prev->coil_price;
                    $prevSheet = $prev->sheet_price;
                    $prevRate = $prev->exchange_rate;
                }
            }

            if ($prevRate !== null && (float) $prevRate > 0) {
                if (empty($data['exchange_rate']) || (float) $data['exchange_rate'] <= 0) {
                    $exchangeRate = (float) $prevRate;
                    $data['exchange_rate'] = $exchangeRate;
                }
            }

            if ($prevCoil !== null && (float) $prevCoil > 0 && ! empty($data['gap_steel_kg_usd'])) {
                $coilPrice = round((float) $prevCoil + (float) $data['gap_steel_kg_usd'], 4);
                $sheetPrice = round((float) $prevSheet + (float) $data['gap_steel_kg_usd'], 4);
                $data['coil_price'] = $coilPrice;
                $data['sheet_price'] = $sheetPrice;
            }
        }
        $data['gap_steel_idr'] = round((float) $data['gap_steel_usd'] * $exchangeRate, 2);

        $data['coil_price_idr'] = round($coilPrice * $exchangeRate, 2);
        $data['sheet_price_idr'] = round($sheetPrice * $exchangeRate, 2);

        $steelPrice = (float) ($data['steel_price'] ?? ($coilPrice ?: $sheetPrice ?: 0));
        $data['steel_price'] = $steelPrice;
        $data['steel_price_idr'] = round($steelPrice * $exchangeRate, 2);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var MaterialSteel $record */
        $record = $this->record;

        // Jika dikaitkan dengan Master Material tertentu, perbarui harga dasar (basic_rate) material tersebut
        if ($record->material_id) {
            $material = Material::find($record->material_id);
            if ($material) {
                $material->basic_rate = $record->steel_price;
                $material->currency_id = $record->currency_id;
                $material->save();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
