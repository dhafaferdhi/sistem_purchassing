<?php

namespace App\Filament\Resources\StampingPartResource\Pages;

use App\Filament\Resources\StampingPartResource;
use App\Models\Currency;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Services\QuarterlyPullHelper;
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
                'part_no_to_compare', 'transportation_cost', 'packing_cost',
                'comments', 'remarks', 'alteration', 'etc_contents', 'tooling_details',
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
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-btn-back'])
                ->url(static::getResource()::getUrl('index')),

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
                ->modalDescription('Pilih kuartal dan part stamping acuan, lalu sesuaikan Kurs USD atau Harga Sheet / Baja ($/Kg).')
                ->modalSubmitActionLabel('Terapkan Data Terpilih')
                ->modalWidth(MaxWidth::TwoExtraLarge)
                ->form(fn () => array_merge(
                    QuarterlyPullHelper::buildPeriodSelectionGrids(fn () => $this->data ?? []),
                    [
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
                                    return MaterialSteel::where('year', $y)
                                        ->where('quarter', $q)
                                        ->get()
                                        ->mapWithKeys(function ($item) {
                                            $coil = number_format((float) $item->coil_price, 4);
                                            $sheet = number_format((float) $item->sheet_price, 4);
                                            $label = "[Material Steel] {$item->steel_spec} — {$item->supplier_name} [Coil: \${$coil} | Sheet: \${$sheet}]";

                                            return ['steel:'.$item->id => $label];
                                        })
                                        ->toArray();
                                }

                                return $stampings;
                            })
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (! $state) {
                                    $set('sheet_price', null);

                                    return;
                                }

                                $rec = str_starts_with($state, 'steel:')
                                    ? MaterialSteel::find((int) str_replace('steel:', '', $state))
                                    : StampingPart::find((int) str_replace('stamping:', '', $state));

                                if ($rec) {
                                    $sheetVal = (float) ($rec->sheet_price > 0 ? $rec->sheet_price : ($rec->coil_price ?? 0));
                                    if ($sheetVal > 0) {
                                        $set('sheet_price', $sheetVal);
                                    }
                                    if (empty($get('exchange_rate')) && $rec->exchange_rate) {
                                        $set('exchange_rate', (int) round((float) $rec->exchange_rate));
                                    }
                                }
                            }),
                    ],
                    QuarterlyPullHelper::buildStampingAdjustmentFields(),
                    [
                        Forms\Components\Placeholder::make('preview')
                            ->label('Pratinjau Data Terpilih & Simulasi Harga')
                            ->content(function (Forms\Get $get) {
                                $selected = $get('source_record_id');
                                if (! $selected) {
                                    return new HtmlString('<div class="p-3 text-sm text-gray-500 bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">Pilih salah satu part di atas untuk melihat rincian spesifikasi, simulasi harga baja, kurs, dan part cost.</div>');
                                }

                                $sheetOverride = $get('sheet_price') !== null && $get('sheet_price') !== '' && (float) $get('sheet_price') > 0
                                    ? (float) $get('sheet_price')
                                    : null;
                                $targetQ = $get('target_quarter') ?? 'Q2';
                                $targetY = (int) ($get('target_year') ?? date('Y'));

                                if (str_starts_with($selected, 'steel:')) {
                                    $rec = MaterialSteel::find((int) str_replace('steel:', '', $selected));
                                    if (! $rec) {
                                        return '';
                                    }

                                    $rate = QuarterlyPullHelper::resolveAppliedRate($get('exchange_rate'), (float) $rec->exchange_rate);
                                    $basePrimary = (float) ($rec->sheet_price > 0 ? $rec->sheet_price : $rec->coil_price);
                                    $appliedKg = $sheetOverride !== null && $basePrimary > 0
                                        ? round($sheetOverride - $basePrimary, 4)
                                        : 0.0;
                                    $sim = QuarterlyPullHelper::computeMaterialSteelSimulation($rec, $rate, $appliedKg);

                                    return new HtmlString(QuarterlyPullHelper::renderMaterialSteelPreviewHtml($rec, $targetQ, $targetY, $sim));
                                }

                                $rec = StampingPart::find((int) str_replace('stamping:', '', $selected));
                                if (! $rec) {
                                    return '';
                                }

                                $rate = QuarterlyPullHelper::resolveAppliedRate($get('exchange_rate'), (float) $rec->exchange_rate);
                                $sim = QuarterlyPullHelper::computeStampingSimulation($rec, $rate, 0.0, $sheetOverride);

                                return new HtmlString(QuarterlyPullHelper::renderStampingPreviewHtml($rec, $targetQ, $targetY, $sim));
                            }),
                    ]
                ))
                ->action(function (array $data) {
                    $selected = $data['source_record_id'] ?? null;
                    $raw = $this->data ?? [];
                    $targetYear = (int) ($data['target_year'] ?? $data['year'] ?? $raw['year'] ?? date('Y'));
                    $targetQuarter = $data['target_quarter'] ?? $data['quarter'] ?? $raw['quarter'] ?? 'Q1';
                    $raw['year'] = $targetYear;
                    $raw['quarter'] = $targetQuarter;
                    $quarter = $targetQuarter;
                    $year = $targetYear;

                    $sheetOverride = isset($data['sheet_price']) && $data['sheet_price'] !== '' && (float) $data['sheet_price'] > 0
                        ? (float) $data['sheet_price']
                        : null;
                    $hrMt = isset($data['hr_steel_usd']) && $data['hr_steel_usd'] !== '' ? (float) $data['hr_steel_usd'] : 0.0;
                    $crMt = isset($data['cr_steel_usd']) && $data['cr_steel_usd'] !== '' ? (float) $data['cr_steel_usd'] : 0.0;

                    // Handle MaterialSteel selection
                    if ($selected && str_starts_with($selected, 'steel:')) {
                        $steel = MaterialSteel::find((int) str_replace('steel:', '', $selected));
                        if ($steel) {
                            $this->applyMaterialSteelFallback($raw, $steel, $data['exchange_rate'] ?? null, $hrMt, $crMt, $sheetOverride);
                            $this->form->fill($raw);

                            Notification::make()
                                ->title('Data Material Steel Berhasil Diterapkan!')
                                ->body("Acuan harga dari {$steel->steel_spec} ({$steel->supplier_name}) berhasil disalin ke {$targetQuarter} {$targetYear}.")
                                ->success()
                                ->send();

                            return;
                        }
                    }

                    $record = null;
                    if ($selected && str_starts_with($selected, 'stamping:')) {
                        $record = StampingPart::find((int) str_replace('stamping:', '', $selected));
                    } elseif ($selected && is_numeric($selected)) {
                        $record = StampingPart::find($selected);
                    }

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
                            'drawing_reference', 'eci_number', 'tooling_depreciation',
                            'part_no_to_compare', 'transportation_cost', 'packing_cost',
                            'comments', 'remarks', 'alteration', 'etc_contents', 'tooling_details',
                        ] as $field) {
                            if (isset($record->{$field})) {
                                $raw[$field] = $record->{$field};
                            }
                        }

                        if (! empty($record->supplier_id)) {
                            $raw['supplier_id'] = $record->supplier_id;
                            $raw['vendor_code'] = $record->vendor_code;
                            $raw['supplier_name'] = $record->supplier_name;
                        }

                        $gap = QuarterlyPullHelper::resolveSteelGap(StampingPart::determineSteelType($record->steel_spec), $hrMt, $crMt);
                        $rate = QuarterlyPullHelper::resolveAppliedRate($data['exchange_rate'] ?? null, (float) $record->exchange_rate, (float) ($raw['exchange_rate'] ?? 0));
                        $sim = QuarterlyPullHelper::computeStampingSimulation($record, $rate, $gap['applied_kg'], $sheetOverride);

                        $raw['prev_coil_price'] = $sim['base_coil'];
                        $raw['prev_sheet_price'] = $sim['base_sheet'];
                        $raw['prev_rate'] = (float) ($record->exchange_rate ?: 16000);
                        $raw['prev_record_found'] = true;
                        $raw['prev_quarter_label'] = "{$record->quarter} {$record->year}";

                        $raw['hr_steel_usd'] = $hrMt;
                        $raw['cr_steel_usd'] = $crMt;
                        $raw['gap_steel_usd'] = $gap['applied_mt'];
                        $raw['gap_steel_kg_usd'] = $gap['applied_kg'];
                        $raw['fluktuasi_jfe'] = $gap['applied_kg'];

                        $raw['coil_price'] = $sim['new_coil'];
                        $raw['sheet_price'] = $sim['new_sheet'];
                        $raw['exchange_rate'] = $rate;
                        $raw['coil_price_idr'] = $sim['coil_price_idr'];
                        $raw['sheet_price_idr'] = $sim['sheet_price_idr'];
                        $raw['berat'] = $sim['berat'];
                        $raw['gross_material_cost'] = $sim['gross_material_cost'];
                        $raw['scrap_cost'] = $sim['scrap_cost'];
                        $raw['material_total'] = $sim['material_total'];
                        $raw['process_total'] = $sim['process_total'];
                        $raw['subtotal'] = $sim['subtotal'];
                        $raw['overhead_supplied_cost'] = $sim['overhead_supplied_cost'];
                        $raw['overhead_self_cost'] = $sim['overhead_self_cost'];
                        $raw['part_cost_excluding_tooling'] = $sim['part_cost_excluding_tooling'];
                        $raw['part_cost'] = $sim['part_cost'];

                        $this->form->fill($raw);

                        $sheetInfo = "Sheet \$ {$sim['new_sheet']}/kg, Kurs Rp ".number_format($rate, 0, ',', '.');
                        Notification::make()
                            ->title('Data Part Stamping Berhasil Diterapkan!')
                            ->body("Part {$record->part_number} ({$record->part_name}) dari periode {$record->quarter} {$record->year} disalin ke {$targetQuarter} {$targetYear} ({$sheetInfo}).")
                            ->success()
                            ->send();
                    } else {
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
                            $this->applyMaterialSteelFallback($raw, $currentSteel, $data['exchange_rate'] ?? null, $hrMt, $crMt, $sheetOverride);
                            $this->form->fill($raw);

                            Notification::make()
                                ->title("Data Material Steel {$quarter} {$year} Berhasil Diterapkan!")
                                ->body("Mengambil acuan harga dari Material Steel ({$currentSteel->steel_spec} - {$currentSteel->supplier_name}).")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Belum Ada Data Acuan')
                                ->body('Tidak ditemukan data acuan pada periode yang dipilih.')
                                ->warning()
                                ->send();
                        }
                    }
                }),
        ];
    }

    /**
     * Terapkan data acuan Material Steel beserta kurs dan harga baja ke state form Stamping Part.
     */
    protected function applyMaterialSteelFallback(array &$raw, MaterialSteel $steel, mixed $modalRate, float $hrMt, float $crMt, ?float $sheetOverride = null): void
    {
        $gap = QuarterlyPullHelper::resolveSteelGap(StampingPart::determineSteelType($steel->steel_spec), $hrMt, $crMt);
        $rate = QuarterlyPullHelper::resolveAppliedRate($modalRate, (float) $steel->exchange_rate, (float) ($raw['exchange_rate'] ?? 0));
        $basePrimary = (float) ($steel->sheet_price > 0 ? $steel->sheet_price : $steel->coil_price);
        $appliedKg = $sheetOverride !== null && $basePrimary > 0
            ? round($sheetOverride - $basePrimary, 4)
            : $gap['applied_kg'];
        $sim = QuarterlyPullHelper::computeMaterialSteelSimulation($steel, $rate, $appliedKg);

        $raw['steel_spec'] = $steel->steel_spec;
        $raw['double_thickness'] = $steel->double_thickness;
        $raw['size'] = $steel->size;
        $raw['supplier_id'] = $steel->supplier_id;
        $raw['vendor_code'] = $steel->vendor_code;
        $raw['supplier_name'] = $steel->supplier_name;

        $raw['prev_coil_price'] = $sim['base_coil'];
        $raw['prev_sheet_price'] = $sim['base_sheet'];
        $raw['prev_rate'] = (float) ($steel->exchange_rate ?: 16000);
        $raw['prev_record_found'] = true;
        $raw['prev_quarter_label'] = "{$steel->quarter} {$steel->year}";

        $raw['hr_steel_usd'] = $hrMt;
        $raw['cr_steel_usd'] = $crMt;
        $raw['gap_steel_usd'] = $gap['applied_mt'];
        $raw['gap_steel_kg_usd'] = $appliedKg;
        $raw['fluktuasi_jfe'] = $appliedKg;

        $raw['coil_price'] = $sim['new_coil'];
        $raw['sheet_price'] = $sheetOverride ?? $sim['new_sheet'];
        $raw['exchange_rate'] = $rate;
        $raw['coil_price_idr'] = round((float) $raw['coil_price'] * $rate, 2);
        $raw['sheet_price_idr'] = round((float) $raw['sheet_price'] * $rate, 2);

        $raw['berat'] = null;
        $raw['scrap_weight'] = 0;
        $raw['scrap_cost'] = 0;
        $raw['gross_material_cost'] = 0;
        $raw['material_total'] = 0;
        $raw['part_cost'] = 0;
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
