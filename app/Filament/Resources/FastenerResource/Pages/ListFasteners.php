<?php

namespace App\Filament\Resources\FastenerResource\Pages;

use App\Filament\Pages\MonthlyRollover;
use App\Filament\Resources\FastenerResource;
use App\Models\Currency;
use App\Models\Fastener;
use App\Services\ExcelImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;

class ListFasteners extends ListRecords
{
    protected static string $resource = FastenerResource::class;

    protected static string $view = 'filament.resources.fasteners.pages.list-fasteners';

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
                ->label('+ Tambah Pasterner Baru'),

            Actions\Action::make('import_excel')
                ->label('Import File Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import Data Pasterner dari File Excel (.xlsx / .csv)')
                ->modalDescription('Unggah file spreadsheet Excel Anda. Sistem otomatis mendeteksi kolom kuartal, spesifikasi material/wire, diameter, vendor, dan harga.')
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
                                ->helperText('Update harga jika spesifikasi pasterner sudah terdaftar'),
                        ]),
                ])
                ->action(function (array $data) {
                    $rawPath = $data['file'];
                    $filePath = Storage::disk('public')->path($rawPath);
                    if (! file_exists($filePath)) {
                        $filePath = Storage::path($rawPath);
                    }

                    $result = ExcelImportService::importFastener(
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
                            ->body("Berhasil mengimpor {$result['imported']} data baru dan memperbarui {$result['updated']} data Pasterner.")
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
                    $csv = ExcelImportService::generateSampleCsv('fastener');

                    return response()->streamDownload(function () use ($csv) {
                        echo $csv;
                    }, 'template-import-pasterner.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),

            Actions\Action::make('copy_period_data')
                ->label('Salin Data dari Periode Lalu')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->modalHeading('Salin Data Pasterner Antar Periode')
                ->modalDescription('Duplikasi seluruh harga pasterner dari kuartal/tahun acuan ke kuartal/tahun target dengan kalkulasi rumus otomatis.')
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
                    $sourceYear = (int) $data['source_year'];
                    $sourceQuarter = (string) $data['source_quarter'];
                    $targetYear = (int) $data['target_year'];
                    $targetQuarter = (string) $data['target_quarter'];
                    $rateToUse = ! empty($data['exchange_rate']) ? (float) $data['exchange_rate'] : null;

                    if ($rateToUse === null) {
                        $rateToUse = (float) (Currency::where('code', 'USD')->where('year', $targetYear)->value('exchange_rate_to_idr') ?? 16000);
                    }

                    $sources = Fastener::where('year', $sourceYear)->where('quarter', $sourceQuarter)->get();
                    $copied = 0;
                    $updated = 0;

                    foreach ($sources as $source) {
                        $existing = Fastener::where('year', $targetYear)
                            ->where('quarter', $targetQuarter)
                            ->where('steel_spec', $source->steel_spec)
                            ->when($source->supplier_id, fn ($q) => $q->where('supplier_id', $source->supplier_id))
                            ->first();

                        if ($existing && ($data['duplicate_handling'] ?? 'overwrite') === 'skip') {
                            continue;
                        }

                        $coil = (float) ($source->coil_price ?? 0);
                        $sheet = (float) ($source->sheet_price ?? 0);
                        $gapUsd = (float) ($source->gap_steel_usd ?? 0);
                        $gapKg = (float) ($source->gap_steel_kg_usd ?? 0);

                        if ($existing && ($data['duplicate_handling'] ?? 'overwrite') === 'overwrite') {
                            $existing->update([
                                'exchange_rate' => $rateToUse,
                                'coil_price' => $coil,
                                'coil_price_idr' => round($coil * $rateToUse, 2),
                                'sheet_price' => $sheet,
                                'sheet_price_idr' => round($sheet * $rateToUse, 2),
                                'gap_steel_usd' => $gapUsd,
                                'gap_steel_kg_usd' => $gapKg,
                            ]);
                            $updated++;
                        } else {
                            $newRecord = $source->replicate(['created_at', 'updated_at']);
                            $newRecord->year = $targetYear;
                            $newRecord->quarter = $targetQuarter;
                            $newRecord->exchange_rate = $rateToUse;
                            $newRecord->coil_price = $coil;
                            $newRecord->coil_price_idr = round($coil * $rateToUse, 2);
                            $newRecord->sheet_price = $sheet;
                            $newRecord->sheet_price_idr = round($sheet * $rateToUse, 2);
                            $newRecord->gap_steel_usd = $gapUsd;
                            $newRecord->gap_steel_kg_usd = $gapKg;
                            $newRecord->save();
                            $copied++;
                        }
                    }

                    Notification::make()
                        ->title('Salin Data Pasterner Berhasil!')
                        ->body("{$copied} data baru disalin dan {$updated} diperbarui dari {$sourceYear} {$sourceQuarter} ke {$targetYear} {$targetQuarter}.")
                        ->success()
                        ->send();

                    $this->selectedYear = (string) $targetYear;
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
        $dbYears = Fastener::distinct()->pluck('year')->filter()->toArray();
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
                ->badge(fn () => Fastener::when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->when($year, fn ($q) => $q->where('year', $year))),

            'Q1' => Tab::make('Kuartal 1 (Q1)')
                ->badge(fn () => Fastener::where('quarter', 'Q1')->when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q1')->when($year, fn ($q) => $q->where('year', $year))),

            'Q2' => Tab::make('Kuartal 2 (Q2)')
                ->badge(fn () => Fastener::where('quarter', 'Q2')->when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q2')->when($year, fn ($q) => $q->where('year', $year))),

            'Q3' => Tab::make('Kuartal 3 (Q3)')
                ->badge(fn () => Fastener::where('quarter', 'Q3')->when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q3')->when($year, fn ($q) => $q->where('year', $year))),

            'Q4' => Tab::make('Kuartal 4 (Q4)')
                ->badge(fn () => Fastener::where('quarter', 'Q4')->when($year, fn ($q) => $q->where('year', $year))->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quarter', 'Q4')->when($year, fn ($q) => $q->where('year', $year))),
        ];
    }
}
