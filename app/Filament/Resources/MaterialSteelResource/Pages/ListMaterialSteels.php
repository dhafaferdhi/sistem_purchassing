<?php

namespace App\Filament\Resources\MaterialSteelResource\Pages;

use App\Filament\Resources\MaterialSteelResource;
use App\Models\MaterialSteel;
use App\Services\ExcelExportService;
use App\Services\ExcelImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Storage;

class ListMaterialSteels extends ListRecords
{
    protected static string $resource = MaterialSteelResource::class;

    protected static string $view = 'filament.resources.material-steels.pages.list-material-steels';

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('+ Penetapan Material Steel Baru')
                ->icon('heroicon-o-plus-circle'),

            Actions\Action::make('import_excel')
                ->label('Import Excel / CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import Data Material Steel')
                ->modalDescription('Unggah file spreadsheet Excel (.xlsx) atau CSV (.csv) untuk memperbarui atau menambahkan data master material steel.')
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
                        ->helperText('Format yang didukung: file Excel (.xlsx), CSV (.csv), atau matriks harga kuartalan'),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('default_year')
                            ->label('Tahun Default (Jika di file tidak tertulis)')
                            ->options(fn () => array_combine(range((int) date('Y') + 2, (int) date('Y') - 3), range((int) date('Y') + 2, (int) date('Y') - 3)))
                            ->default((int) date('Y')),

                        Forms\Components\Select::make('default_quarter')
                            ->label('Kuartal Default (Jika di file tidak tertulis)')
                            ->options([
                                'Q1' => 'Q1',
                                'Q2' => 'Q2',
                                'Q3' => 'Q3',
                                'Q4' => 'Q4',
                            ])
                            ->default('Q2'),
                    ]),

                    Forms\Components\Toggle::make('update_existing')
                        ->label('Perbarui jika data sudah ada (Update/Overwrite)')
                        ->default(true)
                        ->helperText('Jika aktif, baris dengan spesifikasi baja, ketebalan, kuartal, dan tahun yang sama akan diperbarui otomatis.'),
                ])
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['file']);
                    $defaults = [
                        'year' => (int) ($data['default_year'] ?? date('Y')),
                        'quarter' => $data['default_quarter'] ?? 'Q2',
                    ];
                    $result = ExcelImportService::importMaterialSteel($filePath, $defaults, (bool) ($data['update_existing'] ?? true));

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
                            ->title('Impor Excel Berhasil')
                            ->body("Berhasil memproses total {$result['total']} baris data ({$result['imported']} data baru ditambahkan, {$result['updated']} data diperbarui).")
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->modalHeading('Export Data Material Steel')
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
                                $years = MaterialSteel::distinct()->pluck('year', 'year')->filter()->toArray();
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
                    $query = MaterialSteel::query()->with(['supplier', 'currency']);
                    if (($data['year'] ?? 'all') !== 'all') {
                        $query->where('year', (int) $data['year']);
                    }
                    if (($data['quarter'] ?? 'all') !== 'all') {
                        $query->where('quarter', $data['quarter']);
                    }
                    $records = $query->orderBy('year', 'desc')->orderBy('quarter', 'desc')->get();

                    return ExcelExportService::exportMaterialSteel($records, $data['format'] ?? 'xlsx');
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('download_template_xlsx')
                    ->label('Template Excel (.xlsx)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => ExcelExportService::downloadMaterialSteelTemplate('xlsx')),

                Actions\Action::make('download_template_csv')
                    ->label('Template CSV (.csv)')
                    ->icon('heroicon-o-document-text')
                    ->action(fn () => ExcelExportService::downloadMaterialSteelTemplate('csv')),
            ])
                ->label('Unduh Template')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->button(),
        ];
    }
}
