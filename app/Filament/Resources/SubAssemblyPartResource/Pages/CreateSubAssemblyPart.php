<?php

namespace App\Filament\Resources\SubAssemblyPartResource\Pages;

use App\Filament\Resources\SubAssemblyPartResource;
use App\Models\SubAssemblyPart;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class CreateSubAssemblyPart extends CreateRecord
{
    protected static string $resource = SubAssemblyPartResource::class;

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
                ->modalHeading('Tarik Data Acuan Sub-Assembly Part')
                ->modalDescription('Pilih kuartal dan sub-assembly part acuan untuk menyalin konfigurasi komponen anak dan proses.')
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

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('target_year')
                                ->label('Tahun yang Akan Diisi')
                                ->options(function () {
                                    $current = (int) date('Y');
                                    $years = range($current + 2, 2020);

                                    return array_combine($years, $years);
                                })
                                ->default(fn () => (int) ($this->data['year'] ?? date('Y')))
                                ->required()
                                ->live(),

                            Forms\Components\Select::make('target_quarter')
                                ->label('Kuartal yang Akan Diisi')
                                ->options([
                                    'Q1' => 'Q1 (Januari - Maret)',
                                    'Q2' => 'Q2 (April - Juni)',
                                    'Q3' => 'Q3 (Juli - September)',
                                    'Q4' => 'Q4 (Oktober - Desember)',
                                ])
                                ->default(function (Forms\Get $get) {
                                    $sq = $get('source_quarter') ?? $this->data['quarter'] ?? 'Q1';

                                    return match ($sq) {
                                        'Q1' => 'Q2',
                                        'Q2' => 'Q3',
                                        'Q3' => 'Q4',
                                        'Q4' => 'Q1',
                                        default => 'Q2',
                                    };
                                })
                                ->required()
                                ->live(),
                        ]),

                    Forms\Components\Select::make('source_record_id')
                        ->label('Pilih Sub-Assembly Part Acuan')
                        ->placeholder('-- Pilih Sub-Assembly Part Acuan --')
                        ->searchable()
                        ->live()
                        ->options(function (Forms\Get $get) {
                            $y = (int) ($get('source_year') ?: date('Y'));
                            $q = $get('source_quarter') ?: 'Q1';

                            return SubAssemblyPart::where('year', $y)
                                ->where('quarter', $q)
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    $cost = number_format((float) $item->part_cost, 2, ',', '.');
                                    $model = $item->model_name ?: 'Model -';
                                    $label = "{$item->part_number} - {$item->part_name} ({$model}) — {$item->supplier_name} [Total: Rp {$cost}]";

                                    return [$item->id => $label];
                                })
                                ->toArray();
                        }),

                    Forms\Components\Placeholder::make('preview')
                        ->label('Pratinjau Data Terpilih')
                        ->content(function (Forms\Get $get) {
                            $id = $get('source_record_id');
                            if (! $id) {
                                return new HtmlString('<div class="p-3 text-sm text-gray-500 bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">Pilih salah satu sub-assembly di atas untuk melihat rincian komponen anak, proses perakitan, dan total biaya.</div>');
                            }
                            $rec = SubAssemblyPart::find($id);
                            if (! $rec) {
                                return '';
                            }

                            $costStr = number_format((float) $rec->part_cost, 2, ',', '.');
                            $rateStr = number_format((float) $rec->exchange_rate, 0, ',', '.');
                            $matCount = is_array($rec->material_breakdown) ? count($rec->material_breakdown) : 0;
                            $procCount = is_array($rec->process_breakdown) ? count($rec->process_breakdown) : 0;

                            return new HtmlString("
                                    <div class='p-4 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded-lg text-sm space-y-2'>
                                        <div class='font-semibold text-primary-900 dark:text-primary-100 flex items-center justify-between'>
                                            <span class='text-base font-bold'>{$rec->part_number}</span>
                                            <span class='text-xs px-2 py-0.5 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 font-mono'>Rp {$costStr} / pcs</span>
                                        </div>
                                        <div class='text-gray-700 dark:text-gray-300'><strong>Nama Part:</strong> {$rec->part_name} <span class='text-xs text-gray-500'>({$rec->model_name})</span></div>
                                        <div class='text-gray-700 dark:text-gray-300'><strong>Supplier:</strong> {$rec->supplier_name} <span class='text-xs text-gray-500'>({$rec->vendor_code})</span></div>
                                        <div class='grid grid-cols-2 gap-2 pt-2 border-t border-primary-200 dark:border-primary-800/50 text-xs text-gray-700 dark:text-gray-300'>
                                            <div><strong>Komponen Anak:</strong> {$matCount} items</div>
                                            <div><strong>Proses Perakitan:</strong> {$procCount} tahapan</div>
                                            <div><strong>Kurs $:</strong> Rp {$rateStr}</div>
                                            <div><strong>Overhead Supplied:</strong> {$rec->overhead_supplied_percent}%</div>
                                            <div><strong>Biaya Packing:</strong> Rp {$rec->packing_cost}</div>
                                            <div><strong>Depr. Tooling:</strong> Rp {$rec->tooling_depreciation}</div>
                                        </div>
                                    </div>
                                ");
                        }),
                ])
                ->action(function (array $data) {
                    $selectedId = $data['source_record_id'] ?? null;
                    $raw = $this->data ?? [];
                    $targetYear = (int) ($data['target_year'] ?? $data['year'] ?? $raw['year'] ?? date('Y'));
                    $targetQuarter = $data['target_quarter'] ?? $data['quarter'] ?? $raw['quarter'] ?? 'Q1';
                    $raw['year'] = $targetYear;
                    $raw['quarter'] = $targetQuarter;
                    $quarter = $targetQuarter;
                    $year = $targetYear;

                    $record = null;
                    if ($selectedId) {
                        $record = SubAssemblyPart::find($selectedId);
                    }

                    // Fallback jika tidak ada record terpilih (misal dari headless test)
                    if (! $record) {
                        $sourceYear = (int) ($data['source_year'] ?? 0);
                        $sourceQuarter = $data['source_quarter'] ?? null;
                        if ($sourceYear && $sourceQuarter) {
                            $record = SubAssemblyPart::where('year', $sourceYear)->where('quarter', $sourceQuarter)->latest('id')->first();
                        }
                    }

                    if (! $record) {
                        $supplierId = $raw['supplier_id'] ?? null;
                        $partNumber = $raw['part_number'] ?? null;
                        $prevData = SubAssemblyPart::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $partNumber);
                        if ($prevData) {
                            $record = SubAssemblyPart::where('year', $prevData['prev_year'])
                                ->where('quarter', $prevData['prev_quarter'])
                                ->when($prevData['part_number'] ?? null, fn ($q, $p) => $q->where('part_number', $p))
                                ->latest('id')
                                ->first();
                        }
                    }

                    if ($record) {
                        foreach ([
                            'part_number', 'part_name', 'model_name', 'monthly_volume',
                            'drawing_reference', 'eci_number', 'exchange_rate',
                            'material_breakdown', 'process_breakdown',
                            'overhead_supplied_percent', 'profit_percent',
                            'packing_cost', 'tooling_depreciation',
                        ] as $field) {
                            if (isset($record->{$field})) {
                                $raw[$field] = $record->{$field};
                            }
                        }

                        if (! empty($record->supplier_id)) {
                            $raw['supplier_id'] = $record->supplier_id;
                            $raw['vendor_code'] = $record->vendor_code ?? null;
                            $raw['supplier_name'] = $record->supplier_name ?? null;
                        }

                        $this->form->fill($raw);

                        Notification::make()
                            ->title('Data Sub-Assembly Berhasil Diterapkan!')
                            ->body("Part {$record->part_number} ({$record->part_name}) dari periode {$record->quarter} {$record->year} berhasil disalin ke formulir.")
                            ->success()
                            ->send();
                    } else {
                        $info = SubAssemblyPart::getPreviousQuarterInfo($quarter, $year);
                        Notification::make()
                            ->title('Belum Ada Data Acuan')
                            ->body('Tidak ditemukan data sub-assembly pada periode yang dipilih.')
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

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sub-Assembly Berhasil Ditambahkan!')
            ->body("Data {$this->record->part_number} - {$this->record->part_name} ({$this->record->year} - {$this->record->quarter}) berhasil disimpan ke database.");
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['supplier_id']) && ! empty($data['supplier_name'])) {
            $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['supplier_name']), 0, 5));
            $vendorCode = ! empty($data['vendor_code']) ? $data['vendor_code'] : 'VND-'.($clean ?: 'TCF').'-'.rand(10, 99);
            $supplier = Supplier::firstOrCreate(
                ['name' => $data['supplier_name']],
                [
                    'code' => $vendorCode,
                    'category' => 'Component',
                    'is_active' => true,
                ]
            );
            $data['supplier_id'] = $supplier->id;
            $data['vendor_code'] = $supplier->code;
        }

        return $data;
    }
}
