<?php

namespace App\Filament\Resources\FastenerResource\Pages;

use App\Filament\Resources\FastenerResource;
use App\Models\Fastener;
use App\Services\ExcelExportService;
use App\Services\ExcelImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
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

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->when($this->selectedYear, fn (Builder $query) => $query->where('year', (int) $this->selectedYear));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('+ Tambah Fastener Baru')
                ->icon('heroicon-o-plus-circle'),

            Actions\Action::make('import_excel')
                ->label('Import Excel / CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import Data Fasteners')
                ->modalDescription('Unggah file spreadsheet Excel (.xlsx) atau CSV (.csv) untuk memperbarui atau menambahkan komponen Fastener (Bolt, Nut, Screw).')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('File Spreadsheet (Excel / CSV)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                        ])
                        ->disk('local')
                        ->directory('temp-imports')
                        ->required()
                        ->helperText('Format yang didukung: .xlsx, .csv'),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('default_year')
                            ->label('Tahun Default (Jika di file kosong)')
                            ->options(fn () => array_combine(range((int) date('Y') + 2, (int) date('Y') - 3), range((int) date('Y') + 2, (int) date('Y') - 3)))
                            ->default((int) date('Y')),

                        Forms\Components\Select::make('default_quarter')
                            ->label('Kuartal Default (Jika di file kosong)')
                            ->options([
                                'Q1' => 'Q1',
                                'Q2' => 'Q2',
                                'Q3' => 'Q3',
                                'Q4' => 'Q4',
                            ])
                            ->default('Q2'),
                    ]),

                    Forms\Components\Toggle::make('update_existing')
                        ->label('Perbarui jika Part Number sudah ada')
                        ->default(true)
                        ->helperText('Jika aktif, baris dengan Part Number, Kuartal, dan Tahun yang sama akan diperbarui otomatis.'),
                ])
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['file']);
                    $defaults = [
                        'year' => (int) ($data['default_year'] ?? date('Y')),
                        'quarter' => $data['default_quarter'] ?? 'Q2',
                    ];
                    $result = ExcelImportService::importFastener($filePath, $defaults, (bool) ($data['update_existing'] ?? true));

                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }

                    if (! empty($result['errors'])) {
                        Notification::make()
                            ->title('Impor Selesai dengan Catatan')
                            ->body("Berhasil mengimpor {$result['imported']} data baru dan memperbarui {$result['updated']} data. Beberapa baris mengalami kendala:\n".implode("\n", array_slice($result['errors'], 0, 3)))
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Impor Fastener Berhasil')
                            ->body("Berhasil memproses total {$result['total']} baris data ({$result['imported']} data baru, {$result['updated']} diperbarui).")
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->modalHeading('Export Data Fasteners')
                ->form([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('format')
                            ->label('Format File')
                            ->options([
                                'xlsx' => 'Microsoft Excel (.xlsx)',
                                'csv' => 'Comma Separated Values (.csv)',
                            ])
                            ->default('xlsx')
                            ->required(),

                        Forms\Components\Select::make('year')
                            ->label('Filter Tahun')
                            ->options(function () {
                                $years = Fastener::distinct()->pluck('year', 'year')->filter()->toArray();
                                krsort($years);

                                return ['all' => 'Semua Tahun'] + $years;
                            })
                            ->default('all'),

                        Forms\Components\Select::make('quarter')
                            ->label('Filter Kuartal')
                            ->options([
                                'all' => 'Semua Kuartal',
                                'Q1' => 'Q1',
                                'Q2' => 'Q2',
                                'Q3' => 'Q3',
                                'Q4' => 'Q4',
                            ])
                            ->default('all'),
                    ]),
                ])
                ->action(function (array $data) {
                    $query = Fastener::query()->with(['supplier', 'currency']);
                    if (($data['year'] ?? 'all') !== 'all') {
                        $query->where('year', (int) $data['year']);
                    }
                    if (($data['quarter'] ?? 'all') !== 'all') {
                        $query->where('quarter', $data['quarter']);
                    }
                    $records = $query->orderBy('year', 'desc')->orderBy('quarter', 'desc')->get();

                    return ExcelExportService::exportFastener($records, $data['format'] ?? 'xlsx');
                }),

            Actions\Action::make('download_template_xlsx')
                ->label('Unduh Template Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => ExcelExportService::downloadFastenerTemplate('xlsx')),
        ];
    }
}
