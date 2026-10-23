<?php

namespace App\Filament\Resources\FastenerResource\Pages;

use App\Filament\Resources\FastenerResource;
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
                ->label('+ Tambah Fastener Baru'),

            Actions\Action::make('import_excel')
                ->label('Import File Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import Data Fasteners dari File Excel (.xlsx / .csv)')
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
                                ->options(function () {
                                    $current = (int) date('Y');
                                    $years = range($current + 2, 2020);

                                    return array_combine($years, $years);
                                })
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
                                ->helperText('Update harga jika spesifikasi fastener sudah terdaftar'),
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
                            ->body("Berhasil mengimpor {$result['imported']} data baru dan memperbarui {$result['updated']} data Fasteners.")
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
        ];
    }
}
