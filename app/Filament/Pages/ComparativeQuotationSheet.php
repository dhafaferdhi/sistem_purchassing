<?php

namespace App\Filament\Pages;

use App\Models\ComparativeQuotation;
use App\Models\Part;
use App\Services\ComparativeQuotationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComparativeQuotationSheet extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Kalkulator & Purchasing';

    protected static ?string $navigationLabel = 'Calculation Sheet (Comparative Q\'tn)';

    protected static ?string $title = 'Calculation Sheet for Comparative Q\'tn';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.comparative-quotation-sheet';

    public string $selectedPartNumber = '73012-BZ010';

    public function mount(): void
    {
        $firstPart = ComparativeQuotation::distinct()->pluck('part_number')->first();
        if ($firstPart) {
            $this->selectedPartNumber = $firstPart;
        }
    }

    /**
     * Ambil data matriks lengkap untuk part yang dipilih
     */
    public function getMatrixProperty(): array
    {
        return ComparativeQuotationService::getComparativeMatrix($this->selectedPartNumber);
    }

    /**
     * Daftar pilihan Part Number yang tersedia
     */
    public function getAvailablePartsProperty(): array
    {
        return ComparativeQuotation::select('part_number', 'part_name', 'part_type', 'supplier_name')
            ->distinct()
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    $item->part_number => "{$item->part_number} — {$item->part_name} ({$item->part_type} | {$item->supplier_name})",
                ];
            })
            ->toArray();
    }

    /**
     * Metrik ringkasan harga
     */
    public function getSummaryMetricsProperty(): array
    {
        $periods = $this->matrix['periods'];
        if ($periods->isEmpty()) {
            return [
                'initial_price' => 0,
                'latest_price' => 0,
                'min_price' => 0,
                'max_price' => 0,
                'total_change' => 0,
                'total_change_percent' => 0,
            ];
        }

        $firstPrice = (float) $periods->first()->total_cost;
        $latestPrice = (float) $periods->last()->total_cost;
        $minPrice = (float) $periods->min('total_cost');
        $maxPrice = (float) $periods->max('total_cost');
        $change = $latestPrice - $firstPrice;
        $changePct = $firstPrice > 0 ? round(($change / $firstPrice) * 100, 2) : 0;

        return [
            'initial_price' => $firstPrice,
            'latest_price' => $latestPrice,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'total_change' => $change,
            'total_change_percent' => $changePct,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Download Sheet Excel (.csv)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): StreamedResponse {
                    $part = $this->selectedPartNumber;
                    $csv = ComparativeQuotationService::exportToCsv($part);

                    return response()->streamDownload(function () use ($csv) {
                        echo "\xEF\xBB\xBF"; // UTF-8 BOM
                        echo $csv;
                    }, "comparative_quotation_{$part}.csv", [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),

            Action::make('add_period')
                ->label('Simulasi / Tambah Kuartal Baru')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->modalHeading('Tambah Kolom Kuartal Penawaran Baru')
                ->modalDescription('Simulasikan atau tetapkan harga baru untuk kuartal mendatang berdasarkan perubahan kurs atau penyesuaian pasar.')
                ->form([
                    TextInput::make('period_label')
                        ->label('Nama Periode (Header Kolom)')
                        ->placeholder('Contoh: Periode Q1 Januari-Maret \'27')
                        ->required()
                        ->default('Periode Q1 2027'),

                    Select::make('quarter')
                        ->label('Kuartal')
                        ->options([
                            'Q1' => 'Q1',
                            'Q2' => 'Q2',
                            'Q3' => 'Q3',
                            'Q4' => 'Q4',
                        ])
                        ->default('Q1')
                        ->required(),

                    TextInput::make('year')
                        ->label('Tahun')
                        ->numeric()
                        ->default((int) date('Y') + 1)
                        ->required(),

                    TextInput::make('new_exchange_rate')
                        ->label('Kurs IDR (Exchange Rate)')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(function () {
                            $latest = ComparativeQuotation::where('part_number', $this->selectedPartNumber)->latest('sequence')->first();

                            return $latest ? (float) $latest->new_exchange_rate : 16000;
                        })
                        ->required(),

                    TextInput::make('cost_adjustment_percent')
                        ->label('Penyesuaian Biaya Material (%)')
                        ->numeric()
                        ->placeholder('Contoh: -2 atau 5')
                        ->helperText('Persentase kenaikan/penurunan harga material dibanding kuartal sebelumnya (opsional)')
                        ->default(0),
                ])
                ->action(function (array $data) {
                    $latest = ComparativeQuotation::where('part_number', $this->selectedPartNumber)->latest('sequence')->first();
                    if (! $latest) {
                        return;
                    }

                    $adjPercent = (float) ($data['cost_adjustment_percent'] ?? 0);
                    $factor = 1 + ($adjPercent / 100);

                    // Gandakan breakdown material dari periode terakhir dengan penyesuaian jika ada
                    $newMaterials = [];
                    $newMatTotal = 0.0;
                    if (is_array($latest->material_breakdown)) {
                        foreach ($latest->material_breakdown as $item) {
                            $newPrice = round((float) ($item['price'] ?? 0) * $factor, 4);
                            $newUnit = round((float) ($item['unit_price'] ?? 0) * $factor, 4);
                            $newMaterials[] = array_merge($item, [
                                'unit_price' => $newUnit,
                                'price' => $newPrice,
                            ]);
                            $newMatTotal += $newPrice;
                        }
                    }

                    $newSeq = (int) $latest->sequence + 1;
                    $rate = (float) $data['new_exchange_rate'];
                    $oldRate = (float) $latest->new_exchange_rate;

                    $newRecord = ComparativeQuotation::create([
                        'part_id' => $latest->part_id,
                        'part_number' => $latest->part_number,
                        'part_name' => $latest->part_name,
                        'part_type' => $latest->part_type,
                        'supplier_id' => $latest->supplier_id,
                        'supplier_name' => $latest->supplier_name,
                        'buyer_name' => $latest->buyer_name,
                        'model_name' => $latest->model_name,
                        'monthly_volume' => $latest->monthly_volume,
                        'drawing_reference' => $latest->drawing_reference,
                        'eci_number' => $latest->eci_number,
                        'period_label' => $data['period_label'],
                        'year' => (int) $data['year'],
                        'quarter' => $data['quarter'],
                        'sequence' => $newSeq,
                        'old_exchange_rate' => $oldRate,
                        'new_exchange_rate' => $rate,
                        'exchange_fluctuation' => $oldRate > 0 ? round($rate / $oldRate, 6) : 1.0,
                        'material_breakdown' => $newMaterials,
                        'material_total' => $newMatTotal,
                        'process_breakdown' => $latest->process_breakdown,
                        'process_total' => $latest->process_total,
                        'subtotal' => round($newMatTotal + (float) $latest->process_total, 4),
                        'overhead_supplied_percent' => $latest->overhead_supplied_percent,
                        'overhead_supplied_cost' => $latest->overhead_supplied_cost,
                        'overhead_self_percent' => $latest->overhead_self_percent,
                        'overhead_self_cost' => round(((float) $latest->overhead_self_percent / 100) * ($newMatTotal + (float) $latest->process_total), 4),
                        'profit_percent' => $latest->profit_percent,
                        'profit_cost' => $latest->profit_cost,
                        'transport_cost' => $latest->transport_cost,
                        'packing_cost' => $latest->packing_cost,
                        'tooling_cost' => $latest->tooling_cost,
                        'tooling_details' => $latest->tooling_details,
                        'created_by' => auth()->id(),
                    ]);

                    ComparativeQuotationService::recalculateAllDifferences($this->selectedPartNumber);

                    Notification::make()
                        ->title('Periode Kuartal Baru Berhasil Ditambahkan!')
                        ->body("Kuartal '{$data['period_label']}' telah dikalkulasi dan masuk ke matriks perbandingan.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
