<?php

namespace App\Filament\Resources\StampingPartResource\Pages;

use App\Filament\Pages\MonthlyRollover;
use App\Filament\Resources\StampingPartResource;
use App\Models\Currency;
use App\Models\StampingPart;
use App\Services\ExcelImportService;
use App\Services\MonthlyDataRolloverService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;

class ListStampingParts extends ListRecords
{
    protected static string $resource = StampingPartResource::class;

    protected static string $view = 'filament.resources.stamping-parts.pages.list-stamping-parts';

    #[Url]
    public ?string $selectedYear = '';

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('+ Tambah Stamping Part Baru'),

            Actions\Action::make('import_excel')
                ->label('Import File Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import Data Stamping Part dari File Excel (.xlsx / .csv)')
                ->modalDescription('Unggah file spreadsheet Excel Anda. Sistem otomatis mendeteksi kolom kuartal, spesifikasi part, vendor, dan harga.')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Pilih File Excel / Spreadsheet')
                        ->helperText('Mendukung format .xlsx dan .csv. Kolom dan data akan dideteksi secara otomatis.')
                        ->disk('public')
                        ->directory('temp-excel-imports')
                        ->required(),

                    Forms\Components\Grid::make(3)
                        ->schema([
                            Forms\Components\Select::make('default_year')
                                ->label('Tahun Acuan (Fallback)')
                                ->options(fn () => array_combine($this->getAvailableYears(), $this->getAvailableYears()))
                                ->default((int) ($this->selectedYear ?: date('Y')))
                                ->helperText('Digunakan jika baris tidak memuat tahun'),

                            Forms\Components\Select::make('default_quarter')
                                ->label('Kuartal Acuan (Fallback)')
                                ->options([
                                    'Q1' => 'Q1 (Januari - Maret)',
                                    'Q2' => 'Q2 (April - Juni)',
                                    'Q3' => 'Q3 (Juli - September)',
                                    'Q4' => 'Q4 (Oktober - Desember)',
                                ])
                                ->default('Q1')
                                ->helperText('Digunakan jika baris tidak memuat kuartal'),

                            Forms\Components\Toggle::make('update_existing')
                                ->label('Perbarui Data yang Ada')
                                ->default(true)
                                ->helperText('Update harga jika spesifikasi stamping part sudah terdaftar'),
                        ]),
                ])
                ->action(function (array $data) {
                    $rawPath = $data['file'];
                    $filePath = Storage::disk('public')->path($rawPath);
                    if (! file_exists($filePath)) {
                        $filePath = Storage::path($rawPath);
                    }

                    $result = ExcelImportService::importStampingPart(
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
                            ->body("Berhasil mengimpor {$result['imported']} data baru dan memperbarui {$result['updated']} data Stamping Part.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Impor Gagal atau Data Kosong')
                            ->body(implode(' ', $result['errors'] ?? ['Tidak ada baris data yang terdeteksi dalam file.']))
                            ->warning()
                            ->send();
                    }
                }),

            Actions\Action::make('download_template')
                ->label('Unduh Template Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $csv = ExcelImportService::generateSampleCsv('stamping_part');

                    return response()->streamDownload(function () use ($csv) {
                        echo $csv;
                    }, 'template-import-stamping-part.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),

            Actions\Action::make('copy_period_data')
                ->label('Salin Data dari Periode Lalu')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->modalHeading('Salin Data Stamping Part Antar Periode')
                ->modalDescription('Duplikasi seluruh harga stamping part dari kuartal/tahun acuan ke kuartal/tahun target dengan kalkulasi rumus otomatis.')
                ->form([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('source_year')
                                ->label('Tahun Sumber')
                                ->options(fn () => array_combine($this->getAvailableYears(), $this->getAvailableYears()))
                                ->default((int) date('Y'))
                                ->required(),

                            Forms\Components\Select::make('source_quarter')
                                ->label('Kuartal Sumber')
                                ->options([
                                    'Q1' => 'Q1',
                                    'Q2' => 'Q2',
                                    'Q3' => 'Q3',
                                    'Q4' => 'Q4',
                                ])
                                ->default('Q4')
                                ->required(),

                            Forms\Components\Select::make('target_year')
                                ->label('Tahun Target')
                                ->options(fn () => array_combine($this->getAvailableYears(), $this->getAvailableYears()))
                                ->default((int) date('Y') + 1)
                                ->required(),

                            Forms\Components\Select::make('target_quarter')
                                ->label('Kuartal Target')
                                ->options([
                                    'Q1' => 'Q1',
                                    'Q2' => 'Q2',
                                    'Q3' => 'Q3',
                                    'Q4' => 'Q4',
                                ])
                                ->default('Q1')
                                ->required(),
                        ]),

                    Forms\Components\TextInput::make('exchange_rate')
                        ->label('Kurs Dolar Baru (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(fn () => Currency::where('code', 'USD')->latest('year')->value('exchange_rate_to_idr'))
                        ->helperText('Kurs IDR yang diterapkan ke data hasil salinan'),

                    Forms\Components\Checkbox::make('sync_from_steel')
                        ->label('Sinkronkan harga acuan Coil & Sheet dari Material Steel periode target jika ada')
                        ->default(true),

                    Forms\Components\Select::make('duplicate_handling')
                        ->label('Jika Data di Target Sudah Ada')
                        ->options([
                            'overwrite' => 'Timpa / Perbarui Data Lama',
                            'skip' => 'Lewati (Jangan duplikasi)',
                            'duplicate' => 'Tetap Tambah sebagai Rekaman Baru',
                        ])
                        ->default('overwrite'),
                ])
                ->action(function (array $data) {
                    $result = MonthlyDataRolloverService::rolloverStampingParts(
                        (int) $data['source_year'],
                        (string) $data['source_quarter'],
                        (int) $data['target_year'],
                        (string) $data['target_quarter'],
                        ! empty($data['exchange_rate']) ? (float) $data['exchange_rate'] : null,
                        null,
                        'Active',
                        (string) ($data['duplicate_handling'] ?? 'overwrite'),
                        null,
                        (bool) ($data['sync_from_steel'] ?? true)
                    );

                    Notification::make()
                        ->title('Salin Data Stamping Part Berhasil!')
                        ->body("{$result['copied']} data baru disalin dan {$result['updated']} diperbarui dari {$data['source_year']} {$data['source_quarter']} ke {$data['target_year']} {$data['target_quarter']}.")
                        ->success()
                        ->send();

                    $this->selectedYear = (string) $data['target_year'];
                    $this->resetPage();
                }),

            Actions\Action::make('all_rollover_page')
                ->label('Menu Rollover Bulanan')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->url(MonthlyRollover::getUrl()),
        ];
    }

    public function updatedSelectedYear(): void
    {
        unset($this->cachedTabs);
        $this->resetPage();
    }

    public function getAvailableYears(): array
    {
        $dbYears = StampingPart::distinct()->pluck('year')->filter()->toArray();
        $current = (int) date('Y');
        $standardYears = [$current - 1, $current, $current + 1, $current + 2];
        $merged = array_unique(array_merge($dbYears, $standardYears));
        sort($merged);

        return $merged;
    }

    public function getTabs(): array
    {
        $year = $this->selectedYear ? (int) $this->selectedYear : null;
        $yearSuffix = $year ? " ({$year})" : '';

        return [
            'all' => Tab::make('Semua Kuartal'.$yearSuffix)
                ->badge(fn () => StampingPart::when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->when($year, fn ($q) => $q->where('year', $year))),

            'Q1' => Tab::make('Kuartal 1 (Q1)')
                ->badge(fn () => StampingPart::where('quarter', 'Q1')->when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q1')->when($year, fn ($q) => $q->where('year', $year))),

            'Q2' => Tab::make('Kuartal 2 (Q2)')
                ->badge(fn () => StampingPart::where('quarter', 'Q2')->when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q2')->when($year, fn ($q) => $q->where('year', $year))),

            'Q3' => Tab::make('Kuartal 3 (Q3)')
                ->badge(fn () => StampingPart::where('quarter', 'Q3')->when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q3')->when($year, fn ($q) => $q->where('year', $year))),

            'Q4' => Tab::make('Kuartal 4 (Q4)')
                ->badge(fn () => StampingPart::where('quarter', 'Q4')->when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q4')->when($year, fn ($q) => $q->where('year', $year))),
        ];
    }
}
