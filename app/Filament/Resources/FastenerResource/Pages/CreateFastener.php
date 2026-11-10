<?php

namespace App\Filament\Resources\FastenerResource\Pages;

use App\Filament\Resources\FastenerResource;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use App\Services\QuarterlyPullHelper;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class CreateFastener extends CreateRecord
{
    protected static string $resource = FastenerResource::class;

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

        $prevData = Fastener::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $spec);
        if ($prevData) {
            if (empty($raw['steel_spec']) && ! empty($prevData['steel_spec'])) {
                $raw['steel_spec'] = $prevData['steel_spec'];
            }
            if (empty($raw['double_thickness']) && ! empty($prevData['double_thickness'])) {
                $raw['double_thickness'] = $prevData['double_thickness'];
                $raw['diameter'] = $prevData['diameter'] ?? $prevData['double_thickness'];
            }
            if (empty($raw['gap_steel_usd']) && isset($prevData['gap_steel_usd'])) {
                $raw['gap_steel_usd'] = $prevData['gap_steel_usd'];
            }
            if (empty($raw['gap_steel_kg_usd']) && isset($prevData['gap_steel_kg_usd'])) {
                $raw['gap_steel_kg_usd'] = $prevData['gap_steel_kg_usd'];
            }
            if (empty($raw['coil_price']) && isset($prevData['coil_price'])) {
                $raw['coil_price'] = $prevData['coil_price'];
            }
            if (empty($raw['sheet_price']) && isset($prevData['sheet_price'])) {
                $raw['sheet_price'] = $prevData['sheet_price'];
            }
            if (empty($raw['drawing_reference']) && ! empty($prevData['drawing_reference'])) {
                $raw['drawing_reference'] = $prevData['drawing_reference'];
            }
            if (empty($raw['eci_number']) && ! empty($prevData['eci_number'])) {
                $raw['eci_number'] = $prevData['eci_number'];
            }
            if (empty($raw['tooling_depreciation']) && isset($prevData['tooling_depreciation'])) {
                $raw['tooling_depreciation'] = $prevData['tooling_depreciation'];
            }
            if (empty($raw['old_exchange_rate']) && isset($prevData['old_exchange_rate'])) {
                $raw['old_exchange_rate'] = $prevData['old_exchange_rate'];
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
                ->modalHeading('Tarik Data Acuan Fasteners')
                ->modalDescription('Pilih kuartal dan item fastener acuan, lalu sesuaikan Kurs USD (New Rate) atau Harga Wire ($/Kg) sesuai surat penawaran.')
                ->modalSubmitActionLabel('Terapkan Data Terpilih')
                ->modalWidth(MaxWidth::TwoExtraLarge)
                ->form(fn () => array_merge(
                    QuarterlyPullHelper::buildPeriodSelectionGrids(fn () => $this->data ?? []),
                    [
                        Forms\Components\Select::make('source_record_id')
                            ->label('Pilih Item Fastener Acuan')
                            ->placeholder('-- Pilih Item Fastener Acuan --')
                            ->searchable()
                            ->live()
                            ->options(function (Forms\Get $get) {
                                $y = (int) ($get('source_year') ?: date('Y'));
                                $q = $get('source_quarter') ?: 'Q1';

                                $fasteners = Fastener::where('year', $y)
                                    ->where('quarter', $q)
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        $cost = number_format((float) $item->part_cost, 2, ',', '.');
                                        $spec = $item->steel_spec ?: 'Spec -';
                                        $label = "{$item->part_number} - {$item->part_name} ({$spec}) — {$item->supplier_name} [Cost: Rp {$cost}]";

                                        return ['fastener:'.$item->id => $label];
                                    })
                                    ->toArray();

                                if (empty($fasteners)) {
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

                                return $fasteners;
                            })
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (! $state) {
                                    $set('wire_price_usd_kg', null);

                                    return;
                                }

                                if (str_starts_with($state, 'steel:')) {
                                    $rec = MaterialSteel::find((int) str_replace('steel:', '', $state));
                                    if ($rec) {
                                        if ((float) $rec->coil_price > 0) {
                                            $set('wire_price_usd_kg', (float) $rec->coil_price);
                                        }
                                        if (empty($get('exchange_rate')) && $rec->exchange_rate) {
                                            $set('exchange_rate', (int) round((float) $rec->exchange_rate));
                                        }
                                    }

                                    return;
                                }

                                $rec = Fastener::find((int) str_replace('fastener:', '', $state));
                                if ($rec) {
                                    $wireVal = (float) ($rec->wire_price_usd_kg ?: $rec->coil_price ?: 0);
                                    if ($wireVal > 0) {
                                        $set('wire_price_usd_kg', $wireVal);
                                    }
                                    if (empty($get('exchange_rate')) && $rec->exchange_rate) {
                                        $set('exchange_rate', (int) round((float) $rec->exchange_rate));
                                    }
                                }
                            }),
                    ],
                    QuarterlyPullHelper::buildFastenerAdjustmentFields(),
                    [
                        Forms\Components\Placeholder::make('preview')
                            ->label('Pratinjau Data Terpilih & Simulasi Harga')
                            ->content(function (Forms\Get $get) {
                                $selected = $get('source_record_id');
                                if (! $selected) {
                                    return new HtmlString('<div class="p-3 text-sm text-gray-500 bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">Pilih salah satu item di atas untuk melihat rincian spesifikasi kawat, simulasi harga, kurs, dan part cost.</div>');
                                }

                                $wireOverride = $get('wire_price_usd_kg') !== null && $get('wire_price_usd_kg') !== '' && (float) $get('wire_price_usd_kg') > 0
                                    ? (float) $get('wire_price_usd_kg')
                                    : null;
                                $targetQ = $get('target_quarter') ?? 'Q2';
                                $targetY = (int) ($get('target_year') ?? date('Y'));

                                if (str_starts_with($selected, 'steel:')) {
                                    $rec = MaterialSteel::find((int) str_replace('steel:', '', $selected));
                                    if (! $rec) {
                                        return '';
                                    }

                                    $rate = QuarterlyPullHelper::resolveAppliedRate($get('exchange_rate'), (float) $rec->exchange_rate);
                                    $appliedKg = $wireOverride !== null && (float) $rec->coil_price > 0
                                        ? round($wireOverride - (float) $rec->coil_price, 4)
                                        : 0.0;
                                    $sim = QuarterlyPullHelper::computeMaterialSteelSimulation($rec, $rate, $appliedKg);

                                    return new HtmlString(QuarterlyPullHelper::renderMaterialSteelPreviewHtml($rec, $targetQ, $targetY, $sim));
                                }

                                $rec = Fastener::find((int) str_replace('fastener:', '', $selected));
                                if (! $rec) {
                                    return '';
                                }

                                $rate = QuarterlyPullHelper::resolveAppliedRate($get('exchange_rate'), (float) $rec->exchange_rate);
                                $sim = QuarterlyPullHelper::computeFastenerSimulation($rec, $rate, 0.0, $wireOverride);

                                return new HtmlString(QuarterlyPullHelper::renderFastenerPreviewHtml($rec, $targetQ, $targetY, $sim));
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

                    $wireOverride = isset($data['wire_price_usd_kg']) && $data['wire_price_usd_kg'] !== '' && (float) $data['wire_price_usd_kg'] > 0
                        ? (float) $data['wire_price_usd_kg']
                        : null;
                    $hrMt = isset($data['hr_steel_usd']) && $data['hr_steel_usd'] !== '' ? (float) $data['hr_steel_usd'] : 0.0;
                    $crMt = isset($data['cr_steel_usd']) && $data['cr_steel_usd'] !== '' ? (float) $data['cr_steel_usd'] : 0.0;

                    // Handle MaterialSteel selection
                    if ($selected && str_starts_with($selected, 'steel:')) {
                        $steel = MaterialSteel::find((int) str_replace('steel:', '', $selected));
                        if ($steel) {
                            $this->applyMaterialSteelFallback($raw, $steel, $data['exchange_rate'] ?? null, $hrMt, $crMt, $wireOverride);
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
                    if ($selected && str_starts_with($selected, 'fastener:')) {
                        $record = Fastener::find((int) str_replace('fastener:', '', $selected));
                    } elseif ($selected && is_numeric($selected)) {
                        $record = Fastener::find($selected);
                    }

                    if (! $record) {
                        $sourceYear = (int) ($data['source_year'] ?? 0);
                        $sourceQuarter = $data['source_quarter'] ?? null;
                        if ($sourceYear && $sourceQuarter) {
                            $record = Fastener::where('year', $sourceYear)->where('quarter', $sourceQuarter)->latest('id')->first();
                        }
                    }

                    if (! $record) {
                        $supplierId = $raw['supplier_id'] ?? null;
                        $spec = $raw['steel_spec'] ?? null;
                        $prevData = Fastener::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $spec);
                        if ($prevData && ($prevData['source'] ?? '') === 'Fastener') {
                            $record = Fastener::where('year', $prevData['prev_year'])
                                ->where('quarter', $prevData['prev_quarter'])
                                ->when($prevData['steel_spec'] ?? null, fn ($q, $s) => $q->where('steel_spec', $s))
                                ->latest('id')
                                ->first();
                        }
                    }

                    if ($record) {
                        $raw['steel_spec'] = $record->steel_spec;
                        $raw['double_thickness'] = $record->double_thickness;
                        $raw['diameter'] = $record->diameter ?: $record->double_thickness;
                        $raw['drawing_reference'] = $record->drawing_reference;
                        $raw['eci_number'] = $record->eci_number;
                        $raw['tooling_depreciation'] = (float) ($record->tooling_depreciation ?? 0);

                        foreach ([
                            'part_number', 'old_part_number', 'part_name', 'model_name',
                            'monthly_volume', 'wire_spec', 'gross_weight_gr', 'nett_weight_gr',
                            'material_control_percent', 'washer_price', 'washer_weight_gr',
                            'heading_cost', 'rolling_cost', 'washing_cost_kg', 'plating_cost_kg',
                            'inspection_cost', 'packing_cost', 'overhead_self_percent', 'tooling_details',
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

                        $gap = QuarterlyPullHelper::resolveSteelGap(Fastener::determineSteelType($record->steel_spec), $hrMt, $crMt);
                        $rate = QuarterlyPullHelper::resolveAppliedRate($data['exchange_rate'] ?? null, (float) $record->exchange_rate, (float) ($raw['exchange_rate'] ?? 0));
                        $sim = QuarterlyPullHelper::computeFastenerSimulation($record, $rate, $gap['applied_kg'], $wireOverride);

                        $raw['old_exchange_rate'] = $sim['old_rate'];
                        $raw['exchange_rate_fluctuation'] = $sim['exchange_rate_fluctuation'];

                        $raw['prev_coil_price'] = $sim['base_coil'];
                        $raw['prev_sheet_price'] = $sim['base_sheet'];
                        $raw['prev_wire_price'] = $sim['base_wire'];
                        $raw['prev_rate'] = $sim['old_rate'];
                        $raw['prev_record_found'] = true;
                        $raw['prev_quarter_label'] = "{$record->quarter} {$record->year}";

                        $raw['hr_steel_usd'] = $hrMt;
                        $raw['cr_steel_usd'] = $crMt;
                        $raw['gap_steel_usd'] = $gap['applied_mt'];
                        $raw['gap_steel_kg_usd'] = $gap['applied_kg'];
                        $raw['fluktuasi_jfe'] = $gap['applied_kg'];

                        $raw['coil_price'] = $sim['new_coil'];
                        $raw['sheet_price'] = $sim['new_sheet'];
                        $raw['wire_price_usd_kg'] = $sim['new_wire_usd'];
                        $raw['exchange_rate'] = $rate;
                        $raw['coil_price_idr'] = $sim['coil_price_idr'];
                        $raw['sheet_price_idr'] = $sim['sheet_price_idr'];
                        $raw['wire_price_idr_kg'] = $sim['wire_price_idr_kg'];
                        $raw['wire_price_pc'] = $sim['wire_price_pc'];
                        $raw['material_control_cost'] = $sim['material_control_cost'];
                        $raw['material_total'] = $sim['material_total'];
                        $raw['washing_cost'] = $sim['washing_cost'];
                        $raw['plating_cost'] = $sim['plating_cost'];
                        $raw['process_total'] = $sim['process_total'];
                        $raw['subtotal'] = $sim['subtotal'];
                        $raw['overhead_self_cost'] = $sim['overhead_self_cost'];
                        $raw['part_cost'] = $sim['part_cost'];

                        $this->form->fill($raw);

                        $wireInfo = "Wire \$ {$sim['new_wire_usd']}/kg, Kurs Rp ".number_format($rate, 0, ',', '.');
                        Notification::make()
                            ->title('Data Fastener Berhasil Diterapkan!')
                            ->body("Item {$record->part_number} ({$record->part_name}) dari periode {$record->quarter} {$record->year} disalin ke {$targetQuarter} {$targetYear} ({$wireInfo}).")
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
                            $this->applyMaterialSteelFallback($raw, $currentSteel, $data['exchange_rate'] ?? null, $hrMt, $crMt, $wireOverride);
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
     * Terapkan data acuan Material Steel beserta kurs dan harga wire ke state form Fastener.
     */
    protected function applyMaterialSteelFallback(array &$raw, MaterialSteel $steel, mixed $modalRate, float $hrMt, float $crMt, ?float $wireOverride = null): void
    {
        $gap = QuarterlyPullHelper::resolveSteelGap(Fastener::determineSteelType($steel->steel_spec), $hrMt, $crMt);
        $rate = QuarterlyPullHelper::resolveAppliedRate($modalRate, (float) $steel->exchange_rate, (float) ($raw['exchange_rate'] ?? 0));
        $appliedKg = $wireOverride !== null && (float) $steel->coil_price > 0
            ? round($wireOverride - (float) $steel->coil_price, 4)
            : $gap['applied_kg'];
        $sim = QuarterlyPullHelper::computeMaterialSteelSimulation($steel, $rate, $appliedKg);

        $raw['steel_spec'] = $steel->steel_spec;
        $raw['double_thickness'] = $steel->double_thickness;
        $raw['diameter'] = $steel->double_thickness;
        $raw['supplier_id'] = $steel->supplier_id;
        $raw['vendor_code'] = $steel->vendor_code;
        $raw['supplier_name'] = $steel->supplier_name;

        $raw['prev_coil_price'] = $sim['base_coil'];
        $raw['prev_sheet_price'] = $sim['base_sheet'];
        $raw['prev_wire_price'] = $sim['base_coil'];
        $raw['prev_rate'] = (float) ($steel->exchange_rate ?: 16000);
        $raw['prev_record_found'] = true;
        $raw['prev_quarter_label'] = "{$steel->quarter} {$steel->year}";

        $raw['hr_steel_usd'] = $hrMt;
        $raw['cr_steel_usd'] = $crMt;
        $raw['gap_steel_usd'] = $gap['applied_mt'];
        $raw['gap_steel_kg_usd'] = $appliedKg;
        $raw['fluktuasi_jfe'] = $appliedKg;

        $raw['coil_price'] = $sim['new_coil'];
        $raw['sheet_price'] = $sim['new_sheet'];
        $wireUsd = $wireOverride ?? ($sim['new_coil'] > 0 ? $sim['new_coil'] : 0.0);
        if ($wireUsd > 0) {
            $raw['wire_price_usd_kg'] = $wireUsd;
            $raw['wire_price_idr_kg'] = round($wireUsd * $rate, 2);
        }
        $raw['exchange_rate'] = $rate;
        $raw['coil_price_idr'] = $sim['coil_price_idr'];
        $raw['sheet_price_idr'] = $sim['sheet_price_idr'];
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
            ->title('Fastener Berhasil Ditambahkan!')
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
                    'category' => 'Fastener',
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

        if (empty($data['diameter']) && ! empty($data['double_thickness'])) {
            $data['diameter'] = $data['double_thickness'];
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
