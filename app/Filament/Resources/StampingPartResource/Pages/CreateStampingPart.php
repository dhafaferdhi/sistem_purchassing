<?php

namespace App\Filament\Resources\StampingPartResource\Pages;

use App\Filament\Resources\StampingPartResource;
use App\Models\Currency;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class CreateStampingPart extends CreateRecord
{
    protected static string $resource = StampingPartResource::class;

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

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
            foreach ([
                'part_number', 'part_name', 'model_name', 'monthly_volume',
                'wide', 'pitch', 'yield_rate', 'steel_spec', 'double_thickness',
                'size', 'berat', 'scrap_weight', 'scrap_price_per_kg',
                'process_name', 'process_man_hour', 'process_charge',
                'overhead_supplied_percent', 'overhead_self_percent',
                'gap_steel_usd', 'gap_steel_kg_usd', 'coil_price', 'sheet_price',
                'drawing_reference', 'eci_number', 'tooling_depreciation',
            ] as $field) {
                if ((! isset($raw[$field]) || $raw[$field] === '' || $raw[$field] === null) && isset($prevData[$field])) {
                    $raw[$field] = $prevData[$field];
                }
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

            $steelRate = (float) ($raw['sheet_price_idr'] ?: $raw['coil_price_idr']);
            $thk = (float) ($raw['double_thickness'] ?? $prevData['double_thickness'] ?? 0);
            $wide = (float) ($raw['wide'] ?? $prevData['wide'] ?? 0);
            $pitch = (float) ($raw['pitch'] ?? $prevData['pitch'] ?? 0);
            $yieldRate = (float) ($raw['yield_rate'] ?? $prevData['yield_rate'] ?? 1);
            $yieldRate = $yieldRate > 0 ? $yieldRate : 1;

            $berat = (float) ($raw['berat'] ?? $prevData['berat'] ?? 0);
            if ($berat <= 0.0 && $thk > 0 && $wide > 0 && $pitch > 0) {
                $berat = round(($thk * $wide * $pitch * 7.85) / (1000000 * $yieldRate), 4);
                $raw['berat'] = $berat;
            }

            $grossMat = $berat > 0 && $steelRate > 0 ? round($berat * $steelRate, 2) : 0;
            $raw['gross_material_cost'] = $grossMat;

            $scrapWeight = $berat > 0 ? (float) ($raw['scrap_weight'] ?? $prevData['scrap_weight'] ?? 0) : 0;
            $scrapPrice = (float) ($raw['scrap_price_per_kg'] ?? $prevData['scrap_price_per_kg'] ?? -1500);
            $scrapCost = round($scrapWeight * $scrapPrice, 2);
            $raw['scrap_cost'] = $scrapCost;

            $matTotal = round($grossMat + $scrapCost, 2);
            $raw['material_total'] = $matTotal;

            $procMan = (float) ($raw['process_man_hour'] ?? 9);
            $procChg = (float) ($raw['process_charge'] ?? 45);
            $procTotal = round(($procMan * $procChg) / $yieldRate, 2);
            $raw['process_total'] = $procTotal;

            $subtotal = round($matTotal + $procTotal, 2);
            $raw['subtotal'] = $subtotal;

            $ohSupp = round(((float) ($raw['overhead_supplied_percent'] ?? 11) / 100) * $procTotal, 2);
            $raw['overhead_supplied_cost'] = $ohSupp;

            $ohSelf = round(((float) ($raw['overhead_self_percent'] ?? 4) / 100) * $matTotal, 2);
            $raw['overhead_self_cost'] = $ohSelf;

            $raw['part_cost'] = round($subtotal + $ohSupp + $ohSelf, 4);

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
                ->label('Tarik Data Kuartal')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->tooltip('Pilih dan tarik data acuan dari kuartal sebelumnya')
                ->modalHeading('Tarik Data Acuan Stamping Part')
                ->modalDescription('Pilih kuartal dan part stamping acuan untuk disalin ke formulir.')
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
                        ->label('Pilih Part Stamping Acuan')
                        ->placeholder('-- Pilih Part Stamping Acuan --')
                        ->searchable()
                        ->live()
                        ->options(function (Forms\Get $get) {
                            $y = (int) ($get('source_year') ?: date('Y'));
                            $q = $get('source_quarter') ?: 'Q1';

                            $stampings = StampingPart::where('year', $y)
                                ->where('quarter', $q)
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    $cost = number_format((float) $item->part_cost, 2, ',', '.');
                                    $spec = $item->steel_spec ?: 'Spec -';
                                    $label = "{$item->part_number} - {$item->part_name} ({$spec}) — {$item->supplier_name} [Cost: Rp {$cost}]";

                                    return ['stamping:'.$item->id => $label];
                                })
                                ->toArray();

                            if (empty($stampings)) {
                                $steels = MaterialSteel::where('year', $y)
                                    ->where('quarter', $q)
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        $coil = number_format((float) $item->coil_price, 4);
                                        $sheet = number_format((float) $item->sheet_price, 4);
                                        $label = "[Material Steel] {$item->steel_spec} — {$item->supplier_name} [Coil: \${$coil} | Sheet: \${$sheet}]";

                                        return ['steel:'.$item->id => $label];
                                    })
                                    ->toArray();

                                return $steels;
                            }

                            return $stampings;
                        }),

                    Forms\Components\Placeholder::make('preview')
                        ->label('Pratinjau Data Terpilih')
                        ->content(function (Forms\Get $get) {
                            $selected = $get('source_record_id');
                            if (! $selected) {
                                return new HtmlString('<div class="p-3 text-sm text-gray-500 bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">Pilih salah satu part di atas untuk melihat rincian spesifikasi, harga baja, proses, dan part cost.</div>');
                            }

                            if (str_starts_with($selected, 'steel:')) {
                                $steelId = (int) str_replace('steel:', '', $selected);
                                $rec = MaterialSteel::find($steelId);
                                if (! $rec) {
                                    return '';
                                }
                                $rateStr = number_format((float) $rec->exchange_rate, 0, ',', '.');

                                return new HtmlString("
                                        <div class='p-4 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded-lg text-sm space-y-1.5'>
                                            <div class='font-bold text-primary-900 dark:text-primary-100'>[Material Steel] {$rec->steel_spec} ({$rec->double_thickness} mm)</div>
                                            <div class='text-gray-600 dark:text-gray-300'><strong>Vendor:</strong> {$rec->supplier_name}</div>
                                            <div class='grid grid-cols-2 gap-2 text-xs pt-2 border-t border-primary-200'>
                                                <div><strong>Coil Price:</strong> $ {$rec->coil_price}</div>
                                                <div><strong>Sheet Price:</strong> $ {$rec->sheet_price}</div>
                                                <div><strong>Kurs $:</strong> Rp {$rateStr}</div>
                                            </div>
                                        </div>
                                    ");
                            }

                            $stampingId = (int) str_replace('stamping:', '', $selected);
                            $rec = StampingPart::find($stampingId);
                            if (! $rec) {
                                return '';
                            }

                            $costStr = number_format((float) $rec->part_cost, 2, ',', '.');
                            $rateStr = number_format((float) $rec->exchange_rate, 0, ',', '.');

                            return new HtmlString("
                                    <div class='p-4 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded-lg text-sm space-y-2'>
                                        <div class='font-semibold text-primary-900 dark:text-primary-100 flex items-center justify-between'>
                                            <span class='text-base font-bold'>{$rec->part_number}</span>
                                            <span class='text-xs px-2 py-0.5 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 font-mono'>Rp {$costStr} / pcs</span>
                                        </div>
                                        <div class='text-gray-700 dark:text-gray-300'><strong>Nama Part:</strong> {$rec->part_name} <span class='text-xs text-gray-500'>({$rec->model_name})</span></div>
                                        <div class='text-gray-700 dark:text-gray-300'><strong>Supplier:</strong> {$rec->supplier_name} <span class='text-xs text-gray-500'>({$rec->vendor_code})</span></div>
                                        <div class='grid grid-cols-2 gap-2 pt-2 border-t border-primary-200 dark:border-primary-800/50 text-xs text-gray-700 dark:text-gray-300'>
                                            <div><strong>Spec Baja:</strong> {$rec->steel_spec} ({$rec->double_thickness} mm)</div>
                                            <div><strong>Berat Bersih:</strong> {$rec->berat} kg</div>
                                            <div><strong>Proses:</strong> {$rec->process_name}</div>
                                            <div><strong>Harga Coil:</strong> $ {$rec->coil_price}</div>
                                            <div><strong>Kurs $:</strong> Rp {$rateStr}</div>
                                            <div><strong>Depr. Tooling:</strong> Rp {$rec->tooling_depreciation}</div>
                                        </div>
                                    </div>
                                ");
                        }),
                ])
                ->action(function (array $data) {
                    $selected = $data['source_record_id'] ?? null;
                    $raw = $this->data ?? [];
                    $targetYear = (int) ($data['target_year'] ?? $data['year'] ?? $raw['year'] ?? date('Y'));
                    $targetQuarter = $data['target_quarter'] ?? $data['quarter'] ?? $raw['quarter'] ?? 'Q1';
                    $raw['year'] = $targetYear;
                    $raw['quarter'] = $targetQuarter;
                    $quarter = $targetQuarter;
                    $year = $targetYear;

                    // Handle MaterialSteel selection
                    if ($selected && str_starts_with($selected, 'steel:')) {
                        $steelId = (int) str_replace('steel:', '', $selected);
                        $steel = MaterialSteel::find($steelId);
                        if ($steel) {
                            $raw['steel_spec'] = $steel->steel_spec;
                            $raw['double_thickness'] = $steel->double_thickness;
                            $raw['size'] = $steel->size;
                            $raw['coil_price'] = (float) $steel->coil_price;
                            $raw['sheet_price'] = (float) $steel->sheet_price;
                            $raw['supplier_id'] = $steel->supplier_id;
                            $raw['vendor_code'] = $steel->vendor_code;
                            $raw['supplier_name'] = $steel->supplier_name;
                            $rate = (float) ($steel->exchange_rate ?: 16000);
                            $raw['exchange_rate'] = $rate;
                            $raw['coil_price_idr'] = round((float) $steel->coil_price * $rate, 2);
                            $raw['sheet_price_idr'] = round((float) $steel->sheet_price * $rate, 2);
                            $raw['gap_steel_usd'] = 0;
                            $raw['gap_steel_kg_usd'] = 0;
                            $raw['hr_steel_usd'] = 0;
                            $raw['cr_steel_usd'] = 0;
                            $raw['berat'] = null;
                            $raw['scrap_weight'] = 0;
                            $raw['scrap_cost'] = 0;
                            $raw['gross_material_cost'] = 0;
                            $raw['material_total'] = 0;
                            $raw['part_cost'] = 0;
                            $this->form->fill($raw);

                            Notification::make()
                                ->title('Data Material Steel Berhasil Diterapkan!')
                                ->body("Acuan harga dari {$steel->steel_spec} ({$steel->supplier_name}) berhasil disalin.")
                                ->success()
                                ->send();

                            return;
                        }
                    }

                    $record = null;
                    if ($selected && str_starts_with($selected, 'stamping:')) {
                        $stampingId = (int) str_replace('stamping:', '', $selected);
                        $record = StampingPart::find($stampingId);
                    } elseif ($selected && is_numeric($selected)) {
                        $record = StampingPart::find($selected);
                    }

                    // Fallback jika tidak ada record terpilih (misal dipanggil tanpa input modal dari headless test)
                    if (! $record) {
                        $sourceYear = (int) ($data['source_year'] ?? 0);
                        $sourceQuarter = $data['source_quarter'] ?? null;
                        if ($sourceYear && $sourceQuarter) {
                            $record = StampingPart::where('year', $sourceYear)->where('quarter', $sourceQuarter)->latest('id')->first();
                        }
                    }

                    if (! $record) {
                        $supplierId = $raw['supplier_id'] ?? null;
                        $spec = $raw['steel_spec'] ?? null;
                        $prevData = StampingPart::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $spec);
                        if ($prevData && ($prevData['source'] ?? '') === 'StampingPart') {
                            $record = StampingPart::where('year', $prevData['prev_year'])
                                ->where('quarter', $prevData['prev_quarter'])
                                ->when($prevData['part_number'] ?? null, fn ($q, $p) => $q->where('part_number', $p))
                                ->latest('id')
                                ->first();
                        }
                    }

                    if ($record) {
                        foreach ([
                            'part_number', 'part_name', 'model_name', 'monthly_volume',
                            'wide', 'pitch', 'yield_rate', 'steel_spec', 'double_thickness',
                            'size', 'berat', 'scrap_weight', 'scrap_price_per_kg',
                            'process_name', 'process_man_hour', 'process_charge',
                            'overhead_supplied_percent', 'overhead_self_percent',
                            'gap_steel_usd', 'gap_steel_kg_usd', 'coil_price', 'sheet_price',
                            'drawing_reference', 'eci_number', 'tooling_depreciation',
                        ] as $field) {
                            if (isset($record->{$field})) {
                                $raw[$field] = $record->{$field};
                            }
                        }

                        $raw['prev_coil_price'] = (float) $record->coil_price;
                        $raw['prev_sheet_price'] = (float) $record->sheet_price;
                        $raw['prev_record_found'] = true;
                        $raw['prev_quarter_label'] = "{$record->quarter} {$record->year}";

                        if (! empty($record->supplier_id)) {
                            $raw['supplier_id'] = $record->supplier_id;
                            $raw['vendor_code'] = $record->vendor_code;
                            $raw['supplier_name'] = $record->supplier_name;
                        }

                        $rate = (float) ($record->exchange_rate ?: ($raw['exchange_rate'] ?? 16000));
                        $raw['exchange_rate'] = $rate;
                        $raw['coil_price_idr'] = round((float) ($raw['coil_price'] ?? 0) * $rate, 2);
                        $raw['sheet_price_idr'] = round((float) ($raw['sheet_price'] ?? 0) * $rate, 2);

                        $steelRate = (float) ($raw['sheet_price_idr'] ?: $raw['coil_price_idr']);
                        $thk = (float) ($raw['double_thickness'] ?? $record->double_thickness ?? 0);
                        $wide = (float) ($raw['wide'] ?? $record->wide ?? 0);
                        $pitch = (float) ($raw['pitch'] ?? $record->pitch ?? 0);
                        $yieldRate = (float) ($raw['yield_rate'] ?? $record->yield_rate ?? 1);
                        $yieldRate = $yieldRate > 0 ? $yieldRate : 1;

                        $berat = (float) ($raw['berat'] ?? $record->berat ?? 0);
                        if ($berat <= 0.0 && $thk > 0 && $wide > 0 && $pitch > 0) {
                            $berat = round(($thk * $wide * $pitch * 7.85) / (1000000 * $yieldRate), 4);
                            $raw['berat'] = $berat;
                        }

                        $grossMat = $berat > 0 && $steelRate > 0 ? round($berat * $steelRate, 2) : 0;
                        $raw['gross_material_cost'] = $grossMat;

                        $scrapWeight = $berat > 0 ? (float) ($raw['scrap_weight'] ?? $record->scrap_weight ?? 0) : 0;
                        $scrapPrice = (float) ($raw['scrap_price_per_kg'] ?? $record->scrap_price_per_kg ?? -1500);
                        $scrapCost = round($scrapWeight * $scrapPrice, 2);
                        $raw['scrap_cost'] = $scrapCost;

                        $matTotal = round($grossMat + $scrapCost, 2);
                        $raw['material_total'] = $matTotal;

                        $procMan = (float) ($raw['process_man_hour'] ?? 9);
                        $procChg = (float) ($raw['process_charge'] ?? 45);
                        $procTotal = round(($procMan * $procChg) / $yieldRate, 2);
                        $raw['process_total'] = $procTotal;

                        $subtotal = round($matTotal + $procTotal, 2);
                        $raw['subtotal'] = $subtotal;

                        $ohSupp = round(((float) ($raw['overhead_supplied_percent'] ?? 11) / 100) * $procTotal, 2);
                        $raw['overhead_supplied_cost'] = $ohSupp;

                        $ohSelf = round(((float) ($raw['overhead_self_percent'] ?? 4) / 100) * $matTotal, 2);
                        $raw['overhead_self_cost'] = $ohSelf;

                        $raw['part_cost'] = round($subtotal + $ohSupp + $ohSelf + (float) ($raw['tooling_depreciation'] ?? 0), 4);

                        $this->form->fill($raw);

                        Notification::make()
                            ->title('Data Part Stamping Berhasil Diterapkan!')
                            ->body("Part {$record->part_number} ({$record->part_name}) dari periode {$record->quarter} {$record->year} berhasil disalin ke formulir.")
                            ->success()
                            ->send();
                    } else {
                        // Fallback ke MaterialSteel jika ada
                        $supplierId = $raw['supplier_id'] ?? null;
                        $spec = $raw['steel_spec'] ?? null;
                        $currentSteel = MaterialSteel::where('year', $year)
                            ->where('quarter', $quarter)
                            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
                            ->when($spec, fn ($q) => $q->where('steel_spec', $spec))
                            ->latest('id')
                            ->first()
                            ?? MaterialSteel::where('year', $year)->where('quarter', $quarter)->latest('id')->first();

                        if ($currentSteel) {
                            $raw['steel_spec'] = $currentSteel->steel_spec;
                            $raw['double_thickness'] = $currentSteel->double_thickness;
                            $raw['coil_price'] = (float) $currentSteel->coil_price;
                            $raw['sheet_price'] = (float) $currentSteel->sheet_price;
                            $raw['supplier_id'] = $currentSteel->supplier_id;
                            $raw['vendor_code'] = $currentSteel->vendor_code;
                            $raw['supplier_name'] = $currentSteel->supplier_name;
                            $rate = (float) ($currentSteel->exchange_rate ?: 16000);
                            $raw['exchange_rate'] = $rate;
                            $raw['coil_price_idr'] = round((float) $currentSteel->coil_price * $rate, 2);
                            $raw['sheet_price_idr'] = round((float) $currentSteel->sheet_price * $rate, 2);
                            $raw['gap_steel_usd'] = 0;
                            $raw['gap_steel_kg_usd'] = 0;
                            $raw['hr_steel_usd'] = 0;
                            $raw['cr_steel_usd'] = 0;
                            $raw['berat'] = null;
                            $raw['scrap_weight'] = 0;
                            $raw['scrap_cost'] = 0;
                            $raw['gross_material_cost'] = 0;
                            $raw['material_total'] = 0;
                            $raw['part_cost'] = 0;
                            $this->form->fill($raw);

                            Notification::make()
                                ->title("Data Material Steel {$quarter} {$year} Berhasil Diterapkan!")
                                ->body("Mengambil acuan harga dari Material Steel ({$currentSteel->steel_spec} - {$currentSteel->supplier_name}).")
                                ->success()
                                ->send();
                        } else {
                            $info = StampingPart::getPreviousQuarterInfo($quarter, $year);
                            Notification::make()
                                ->title('Belum Ada Data Acuan')
                                ->body('Tidak ditemukan data acuan pada periode yang dipilih.')
                                ->warning()
                                ->send();
                        }
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

        unset(
            $data['excel_auto_fill'],
            $data['price_mode'],
            $data['prev_quarter_label'],
            $data['prev_record_found'],
            $data['prev_coil_price'],
            $data['prev_sheet_price'],
            $data['prev_rate'],
            $data['prev_quarter_info'],
            $data['calculation_summary']
        );

        return $data;
    }
}
