<?php

namespace App\Filament\Resources\MaterialSteelResource\Pages;

use App\Filament\Resources\MaterialSteelResource;
use App\Models\Material;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use App\Services\ExcelImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateMaterialSteel extends CreateRecord
{
    protected static string $resource = MaterialSteelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_template')
                ->label('Download Template Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    return response()->streamDownload(function () {
                        echo ExcelImportService::generateMaterialSteelTemplateCsv();
                    }, 'template_import_material_steel.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),

            Actions\Action::make('import_excel')
                ->label('Import File Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import File Excel ke Material Steel')
                ->modalDescription('Unggah file spreadsheet (.xlsx / .csv) sesuai template atau format kenaikan harga purchasing. Data akan otomatis masuk ke tabel Material Steel.')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Pilih File Excel / Spreadsheet')
                        ->helperText('Mendukung format .xlsx dan .csv. Kolom, spesifikasi, dan harga dideteksi otomatis.')
                        ->disk('public')
                        ->directory('temp-excel-imports')
                        ->required(),

                    Forms\Components\Grid::make(3)
                        ->schema([
                            Forms\Components\Select::make('default_year')
                                ->label('Tahun Acuan (Fallback)')
                                ->options(fn () => array_combine(range((int) date('Y') + 1, 2020), range((int) date('Y') + 1, 2020)))
                                ->default((int) date('Y'))
                                ->helperText('Digunakan jika file tidak memuat tahun'),

                            Forms\Components\Select::make('default_quarter')
                                ->label('Kuartal Acuan (Fallback)')
                                ->options([
                                    'Q1' => 'Q1 (Januari - Maret)',
                                    'Q2' => 'Q2 (April - Juni)',
                                    'Q3' => 'Q3 (Juli - September)',
                                    'Q4' => 'Q4 (Oktober - Desember)',
                                ])
                                ->default('Q1')
                                ->helperText('Digunakan jika file tidak memuat kuartal'),

                            Forms\Components\Toggle::make('update_existing')
                                ->label('Perbarui Data yang Ada')
                                ->default(true)
                                ->helperText('Update harga jika spesifikasi dan ketebalan sudah terdaftar'),
                        ]),
                ])
                ->action(function (array $data) {
                    $rawPath = $data['file'];
                    $filePath = Storage::disk('public')->path($rawPath);
                    if (! file_exists($filePath)) {
                        $filePath = Storage::path($rawPath);
                    }

                    $result = ExcelImportService::importMaterialSteel(
                        $filePath,
                        [
                            'year' => (int) ($data['default_year'] ?? date('Y')),
                            'quarter' => $data['default_quarter'] ?? 'Q1',
                        ],
                        (bool) ($data['update_existing'] ?? true)
                    );

                    @unlink($filePath);

                    if ($result['total'] > 0) {
                        Notification::make()
                            ->title('Impor Excel Berhasil!')
                            ->body("Berhasil mengimpor {$result['imported']} data baru dan memperbarui {$result['updated']} data Material Steel.")
                            ->success()
                            ->send();

                        $this->redirect(MaterialSteelResource::getUrl('index'));
                    } else {
                        Notification::make()
                            ->title('Impor Gagal atau Data Kosong')
                            ->body(implode(' ', $result['errors'] ?? ['Tidak ada baris data yang terdeteksi dalam file.']))
                            ->warning()
                            ->send();
                    }
                }),

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
