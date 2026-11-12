<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FastenerResource\Pages;
use App\Filament\Support\SupplierSelectField;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Services\ExcelExportService;
use App\Services\QuarterlyPullHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class FastenerResource extends Resource
{
    protected static ?string $model = Fastener::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Fasteners';

    protected static ?int $navigationSort = 4;

    /**
     * Format angka mata uang USD tanpa trailing zero yang tidak perlu (contoh: 1.1 alih-alih 1.1000, -20 alih-alih -20.00).
     */
    public static function formatUsd(mixed $value, ?string $currency = '$'): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($currency === 'USD') {
            $currency = '$';
        }

        $f = (float) $value;
        if ($f == 0.0) {
            return $currency ? "{$currency} 0" : '0';
        }

        $formatted = rtrim(rtrim(number_format($f, 4, '.', ''), '0'), '.');

        return $currency ? "{$currency} {$formatted}" : $formatted;
    }

    /**
     * Format angka mata uang IDR bulat tanpa pecahan desimal sen (contoh: Rp 16.000).
     */
    public static function formatIdr(mixed $value, bool $withPrefix = true): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $f = (float) $value;
        if ($f == 0.0) {
            return $withPrefix ? 'Rp 0' : '0';
        }

        $formatted = number_format(round($f), 0, ',', '.');

        return $withPrefix ? "Rp {$formatted}" : $formatted;
    }

    public static function syncCurrencyAndPrices(Get $get, Set $set): void
    {
        $year = (int) ($get('year') ?? date('Y'));
        $quarter = $get('quarter') ?? 'Q1';

        $curr = Currency::where('code', 'USD')->where('year', $year)->where('quarter', $quarter)->first()
            ?? Currency::where('code', 'USD')->where('year', $year)->first()
            ?? Currency::where('code', 'USD')->latest('year')->first();

        if ($curr) {
            $set('currency_id', $curr->id);
            $set('currency_code', $curr->code);
            $set('exchange_rate', (float) $curr->exchange_rate_to_idr);
        }

        static::recalculateFormPrices($get, $set);
    }

    public static function lookupCurrentQuarterSteel(Get $get, Set $set, bool $notify = false): bool
    {
        $year = (int) ($get('year') ?? date('Y'));
        $quarter = $get('quarter') ?? 'Q1';
        $spec = $get('steel_spec');
        $supplierId = $get('supplier_id');

        if (! $spec && ! $supplierId) {
            return false;
        }

        $cleanSpec = $spec ? Fastener::cleanSteelSpec($spec) : null;
        $query = MaterialSteel::where('year', $year)->where('quarter', $quarter);
        if ($cleanSpec) {
            $query->where(function ($q) use ($spec, $cleanSpec) {
                $q->where('steel_spec', $cleanSpec)
                    ->orWhere('steel_spec', $spec)
                    ->orWhere('steel_spec', 'like', $cleanSpec.'%');
            });
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $steel = $query->latest('id')->first();

        if (! $steel && $cleanSpec && $supplierId) {
            $steel = MaterialSteel::where('year', $year)
                ->where('quarter', $quarter)
                ->where(function ($q) use ($spec, $cleanSpec) {
                    $q->where('steel_spec', $cleanSpec)
                        ->orWhere('steel_spec', $spec)
                        ->orWhere('steel_spec', 'like', $cleanSpec.'%');
                })
                ->latest('id')
                ->first();
        }

        if ($steel) {
            $set('coil_price', (float) $steel->coil_price);
            $set('sheet_price', (float) $steel->sheet_price);

            if ($steel->exchange_rate && (float) $steel->exchange_rate > 0) {
                $set('exchange_rate', (float) $steel->exchange_rate);
            }

            if (empty($get('double_thickness')) && ! empty($steel->double_thickness)) {
                $set('double_thickness', $steel->double_thickness);
                $set('diameter', $steel->double_thickness);
            }

            static::recalculateFormPrices($get, $set);

            if ($notify) {
                Notification::make()
                    ->title("Data Material Steel {$quarter} {$year} Ditemukan!")
                    ->body("Harga Coil $ {$steel->coil_price}, Sheet $ {$steel->sheet_price}, Kurs Rp ".number_format($steel->exchange_rate, 0, ',', '.').' berhasil diisi otomatis.')
                    ->success()
                    ->send();
            }

            return true;
        }

        return false;
    }

    public static function lookupPreviousQuarterRecord(Get $get, Set $set): void
    {
        $quarter = $get('quarter') ?? 'Q1';
        $year = (int) ($get('year') ?? date('Y'));
        $supplierId = $get('supplier_id');
        $steelSpec = $get('steel_spec');
        $partNumber = $get('part_number');

        $prevQuarter = match ($quarter) {
            'Q1' => 'Q4',
            'Q2' => 'Q1',
            'Q3' => 'Q2',
            'Q4' => 'Q3',
            default => 'Q1',
        };
        $prevYear = ($quarter === 'Q1') ? ($year - 1) : $year;

        $set('prev_quarter_label', "{$prevQuarter} {$prevYear}");

        if (! $supplierId && ! $steelSpec && ! $partNumber) {
            return;
        }

        $prev = Fastener::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $steelSpec, $partNumber);

        if ($prev) {
            $set('prev_record_found', true);
            $set('prev_coil_price', (float) ($prev['coil_price'] ?? 0));
            $set('prev_sheet_price', (float) ($prev['sheet_price'] ?? 0));
            $set('prev_rate', (float) ($prev['exchange_rate'] ?? 16000));

            // Isi nilai default jika masih kosong pada form
            if (! empty($prev['part_number']) && empty($get('part_number'))) {
                $set('part_number', $prev['part_number']);
            }
            if (! empty($prev['part_name']) && empty($get('part_name'))) {
                $set('part_name', $prev['part_name']);
            }
            if (! empty($prev['double_thickness']) && empty($get('double_thickness'))) {
                $set('double_thickness', $prev['double_thickness']);
                $set('diameter', $prev['double_thickness']);
            }
            if (! empty($prev['wire_spec']) && empty($get('wire_spec'))) {
                $set('wire_spec', $prev['wire_spec']);
            }
            if (isset($prev['wire_price_usd_kg']) && (float) $prev['wire_price_usd_kg'] > 0 && empty($get('wire_price_usd_kg'))) {
                $set('wire_price_usd_kg', $prev['wire_price_usd_kg']);
            }
            if (isset($prev['gross_weight_gr']) && empty($get('gross_weight_gr'))) {
                $set('gross_weight_gr', $prev['gross_weight_gr']);
            }
            if (isset($prev['nett_weight_gr']) && empty($get('nett_weight_gr'))) {
                $set('nett_weight_gr', $prev['nett_weight_gr']);
            }
            if (isset($prev['material_control_percent']) && empty($get('material_control_percent'))) {
                $set('material_control_percent', $prev['material_control_percent']);
            }
            if (isset($prev['washer_price']) && empty($get('washer_price'))) {
                $set('washer_price', $prev['washer_price']);
            }
            if (isset($prev['washer_weight_gr']) && empty($get('washer_weight_gr'))) {
                $set('washer_weight_gr', $prev['washer_weight_gr']);
            }
            if (isset($prev['heading_cost']) && empty($get('heading_cost'))) {
                $set('heading_cost', $prev['heading_cost']);
            }
            if (isset($prev['rolling_cost']) && empty($get('rolling_cost'))) {
                $set('rolling_cost', $prev['rolling_cost']);
            }
            if (isset($prev['washing_cost_kg']) && empty($get('washing_cost_kg'))) {
                $set('washing_cost_kg', $prev['washing_cost_kg']);
            }
            if (isset($prev['plating_cost_kg']) && empty($get('plating_cost_kg'))) {
                $set('plating_cost_kg', $prev['plating_cost_kg']);
            }
            if (isset($prev['inspection_cost']) && empty($get('inspection_cost'))) {
                $set('inspection_cost', $prev['inspection_cost']);
            }
            if (isset($prev['packing_cost']) && empty($get('packing_cost'))) {
                $set('packing_cost', $prev['packing_cost']);
            }
            if (isset($prev['overhead_supplied_percent']) && empty($get('overhead_supplied_percent'))) {
                $set('overhead_supplied_percent', $prev['overhead_supplied_percent']);
            }
            if (isset($prev['overhead_self_percent']) && empty($get('overhead_self_percent'))) {
                $set('overhead_self_percent', $prev['overhead_self_percent']);
            }
            if (isset($prev['transportation_cost']) && empty($get('transportation_cost'))) {
                $set('transportation_cost', $prev['transportation_cost']);
            }
            if (isset($prev['cost_down']) && empty($get('cost_down'))) {
                $set('cost_down', $prev['cost_down']);
            }
            if (array_key_exists('old_part_number', $prev) && empty($get('old_part_number'))) {
                $set('old_part_number', $prev['old_part_number']);
            }
            if (array_key_exists('drawing_reference', $prev) && empty($get('drawing_reference'))) {
                $set('drawing_reference', $prev['drawing_reference']);
            }
            if (array_key_exists('eci_number', $prev) && empty($get('eci_number'))) {
                $set('eci_number', $prev['eci_number']);
            }
            if (array_key_exists('quotation_date', $prev) && empty($get('quotation_date'))) {
                $set('quotation_date', $prev['quotation_date']);
            }
            if (isset($prev['tooling_depreciation']) && empty($get('tooling_depreciation'))) {
                $set('tooling_depreciation', $prev['tooling_depreciation']);
            }
            if (array_key_exists('tooling_details', $prev) && empty($get('tooling_details')) && ! empty($prev['tooling_details'])) {
                $set('tooling_details', $prev['tooling_details']);
            }
            foreach (['comments', 'remarks', 'alteration', 'etc_contents'] as $txtKey) {
                if (! empty($prev[$txtKey]) && empty($get($txtKey))) {
                    $set($txtKey, $prev[$txtKey]);
                }
            }

            if (! empty($prev['exchange_rate']) && (float) $prev['exchange_rate'] > 0) {
                $set('exchange_rate', (float) $prev['exchange_rate']);
            }

            $spec = $get('steel_spec') ?: ($prev['steel_spec'] ?? null);
            $steelType = Fastener::determineSteelType($spec);

            $hrMt = $get('hr_steel_usd');
            $crMt = $get('cr_steel_usd');

            $newAdj = 0.0;
            if ($steelType === 'HR') {
                if ($hrMt !== null && $hrMt !== '' && (float) $hrMt != 0.0) {
                    $newAdj = (float) $hrMt;
                }
            } else {
                if ($crMt !== null && $crMt !== '' && (float) $crMt != 0.0) {
                    $newAdj = (float) $crMt;
                }
            }

            $prevCoil = (float) ($prev['coil_price'] ?? 0);
            $prevSheet = (float) ($prev['sheet_price'] ?? 0);
            $prevWire = (float) ($prev['wire_price_usd_kg'] ?? 0);

            if ($newAdj != 0.0) {
                $appliedKg = round($newAdj / 1000, 4);
                if ($prevCoil > 0) {
                    $set('coil_price', round($prevCoil + $appliedKg, 4));
                }
                if ($prevSheet > 0) {
                    $set('sheet_price', round($prevSheet + $appliedKg, 4));
                }
                if ($prevWire > 0) {
                    $set('wire_price_usd_kg', round($prevWire + $appliedKg, 4));
                } elseif ($prevCoil > 0) {
                    $set('wire_price_usd_kg', round($prevCoil + $appliedKg, 4));
                }
                $set('gap_steel_usd', $newAdj);
                $set('gap_steel_kg_usd', $appliedKg);
            } else {
                if ($prevCoil > 0) {
                    $set('coil_price', $prevCoil);
                }
                if ($prevSheet > 0) {
                    $set('sheet_price', $prevSheet);
                }
                if ($prevWire > 0) {
                    $set('wire_price_usd_kg', $prevWire);
                } elseif ($prevCoil > 0) {
                    $set('wire_price_usd_kg', $prevCoil);
                }
                $set('gap_steel_usd', 0);
                $set('gap_steel_kg_usd', 0);
            }

            $set('prev_wire_price', $prevWire > 0 ? $prevWire : $prevCoil);

            static::recalculateFormPrices($get, $set);
        } else {
            $set('prev_record_found', false);
            $set('prev_coil_price', null);
            $set('prev_sheet_price', null);
            $set('prev_wire_price', null);
            $set('prev_rate', null);
        }
    }

    public static function recalculateFormPrices(Get $get, Set $set): void
    {
        $coil = (float) ($get('coil_price') ?? 0);
        $sheet = (float) ($get('sheet_price') ?? 0);
        $wire = (float) ($get('wire_price_usd_kg') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 0);

        if ($rate > 0) {
            if ($coil > 0) {
                $set('coil_price_idr', round($coil * $rate));
            }
            if ($sheet > 0) {
                $set('sheet_price_idr', round($sheet * $rate));
            }
            if ($wire > 0) {
                $set('wire_price_idr_kg', round($wire * $rate, 2));
            }
        }
    }

    public static function updatePricesFromWire(Get $get, Set $set): void
    {
        $wire = (float) ($get('wire_price_usd_kg') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 0);

        if ($wire > 0) {
            $set('coil_price', $wire);
            if ($rate > 0) {
                $set('coil_price_idr', round($wire * $rate));
                $set('wire_price_idr_kg', round($wire * $rate, 2));
            }
        }
    }

    public static function updatePricesFromCoil(Get $get, Set $set): void
    {
        $coil = (float) ($get('coil_price') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);
        $sheet = (float) ($get('sheet_price') ?? 0);

        $set('coil_price_idr', round($coil * $rate));

        if (empty($get('wire_price_usd_kg')) && $coil > 0) {
            $set('wire_price_usd_kg', $coil);
            $set('wire_price_idr_kg', round($coil * $rate, 2));
        }

        if ($sheet > 0 && $coil > 0) {
            $gapKg = round($sheet - $coil, 4);
            $gapUsd = round(($sheet - $coil) * 1000, 4);
            $set('gap_steel_usd', $gapUsd);
            $set('gap_steel_kg_usd', $gapKg);
        }
    }

    public static function updatePricesFromSheet(Get $get, Set $set): void
    {
        $sheet = (float) ($get('sheet_price') ?? 0);
        $coil = (float) ($get('coil_price') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);

        $set('sheet_price_idr', round($sheet * $rate));

        if ($sheet > 0 && $coil > 0) {
            $gapKg = round($sheet - $coil, 4);
            $gapUsd = round(($sheet - $coil) * 1000, 4);
            $set('gap_steel_usd', $gapUsd);
            $set('gap_steel_kg_usd', $gapKg);
        }
    }

    public static function updatePricesFromHrCr(Get $get, Set $set, ?string $source = null): void
    {
        $spec = $get('steel_spec');
        $steelType = Fastener::determineSteelType($spec);

        $hrMt = $get('hr_steel_usd');
        $crMt = $get('cr_steel_usd');

        // Tentukan nilai MT yang diterapkan berdasarkan input spesifik user & tipe kawat/baja
        if ($source === 'HR' && $hrMt !== null && $hrMt !== '') {
            $appliedMt = (float) $hrMt;
        } elseif ($source === 'CR' && $crMt !== null && $crMt !== '') {
            $appliedMt = (float) $crMt;
        } else {
            $hasHr = $hrMt !== null && $hrMt !== '';
            $hasCr = $crMt !== null && $crMt !== '';

            if ($steelType === 'HR') {
                if ($hasHr && (float) $hrMt != 0.0) {
                    $appliedMt = (float) $hrMt;
                } elseif ($hasCr && (float) $crMt != 0.0) {
                    $appliedMt = (float) $crMt;
                } else {
                    $appliedMt = $hasHr ? (float) $hrMt : ($hasCr ? (float) $crMt : 0.0);
                }
            } else {
                if ($hasCr && (float) $crMt != 0.0) {
                    $appliedMt = (float) $crMt;
                } elseif ($hasHr && (float) $hrMt != 0.0) {
                    $appliedMt = (float) $hrMt;
                } else {
                    $appliedMt = $hasCr ? (float) $crMt : ($hasHr ? (float) $hrMt : 0.0);
                }
            }
        }

        $set('gap_steel_usd', $appliedMt);
        $appliedKg = round($appliedMt / 1000, 4);
        $set('gap_steel_kg_usd', $appliedKg);
        $set('fluktuasi_jfe', $appliedKg);

        $prevCoil = $get('prev_coil_price');
        $prevSheet = $get('prev_sheet_price');

        if ($prevCoil === null && $prevSheet === null) {
            static::lookupPreviousQuarterRecord($get, $set);
            $prevCoil = $get('prev_coil_price');
            $prevSheet = $get('prev_sheet_price');
        }

        if ($prevCoil !== null && (float) $prevCoil > 0) {
            $set('coil_price', round((float) $prevCoil + $appliedKg, 4));
        }

        if ($prevSheet !== null && (float) $prevSheet > 0) {
            $set('sheet_price', round((float) $prevSheet + $appliedKg, 4));
        }

        $prevWire = (float) ($get('prev_wire_price') ?? $prevCoil ?? $get('wire_price_usd_kg') ?? 0);
        if ($prevWire > 0) {
            $set('wire_price_usd_kg', round($prevWire + $appliedKg, 4));
        }

        static::recalculateFormPrices($get, $set);
    }

    /**
     * Logika otomatis menarik data dari kuartal sebelumnya
     */
    public static function pullPreviousQuarterData(Get $get, Set $set, bool $notify = true): void
    {
        $quarter = $get('quarter') ?? 'Q1';
        $year = (int) ($get('year') ?? date('Y'));
        $supplierId = $get('supplier_id');
        $spec = $get('steel_spec');
        $partNumber = $get('part_number');

        $prevData = Fastener::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $spec, $partNumber);

        if ($prevData) {
            if (! empty($prevData['steel_spec'])) {
                $set('steel_spec', $prevData['steel_spec']);
            }
            if (! empty($prevData['diameter'])) {
                $set('diameter', $prevData['diameter']);
            }
            if (! empty($prevData['double_thickness'])) {
                $set('double_thickness', $prevData['double_thickness']);
            }
            if (isset($prevData['gap_steel_usd'])) {
                $set('gap_steel_usd', $prevData['gap_steel_usd']);
            }
            if (isset($prevData['gap_steel_kg_usd'])) {
                $set('gap_steel_kg_usd', $prevData['gap_steel_kg_usd']);
            }
            if (isset($prevData['coil_price'])) {
                $set('coil_price', $prevData['coil_price']);
            }
            if (isset($prevData['sheet_price'])) {
                $set('sheet_price', $prevData['sheet_price']);
            }
            if (empty($get('supplier_id')) && ($prevData['source'] ?? '') !== 'MaterialSteel' && ! empty($prevData['supplier_id'])) {
                $set('supplier_id', $prevData['supplier_id']);
                $set('vendor_code', $prevData['vendor_code']);
                $set('supplier_name', $prevData['supplier_name']);
            }

            foreach ([
                'part_number', 'old_part_number', 'part_name', 'model_name', 'monthly_volume',
                'drawing_reference', 'eci_number', 'quotation_date', 'wire_spec',
                'nett_weight_gr', 'gross_weight_gr', 'wire_price_usd_kg', 'material_control_percent',
                'washer_price', 'washer_weight_gr', 'heading_cost', 'rolling_cost',
                'washing_cost_kg', 'plating_cost_kg', 'inspection_cost', 'packing_cost',
                'overhead_supplied_percent', 'overhead_self_percent', 'transportation_cost',
                'cost_down', 'old_exchange_rate', 'exchange_rate_fluctuation',
                'tooling_depreciation', 'tooling_details', 'notes', 'comments', 'remarks', 'alteration', 'etc_contents', 'status',
            ] as $field) {
                if (array_key_exists($field, $prevData)) {
                    $set($field, $prevData[$field]);
                }
            }

            $rate = (float) (! empty($prevData['exchange_rate']) ? $prevData['exchange_rate'] : ($get('exchange_rate') ?? 16000));
            $set('exchange_rate', QuarterlyPullHelper::normalizeRate($rate));
            $set('coil_price_idr', round((float) ($prevData['coil_price'] ?? 0) * $rate));
            $set('sheet_price_idr', round((float) ($prevData['sheet_price'] ?? 0) * $rate));
            $wireUsdVal = (float) ($prevData['wire_price_usd_kg'] ?? $prevData['coil_price'] ?? 0);
            $wireIdrVal = ! empty($prevData['wire_price_idr_kg']) && $wireUsdVal <= 0
                ? (float) $prevData['wire_price_idr_kg']
                : round($wireUsdVal * $rate, 2);
            $set('wire_price_idr_kg', $wireIdrVal);

            $set('prev_record_found', true);
            $set('prev_quarter_label', $prevData['label'] ?? null);
            $set('prev_coil_price', (float) ($prevData['coil_price'] ?? 0));
            $set('prev_sheet_price', (float) ($prevData['sheet_price'] ?? 0));
            $set('prev_rate', (float) ($prevData['exchange_rate'] ?? 16000));

            if ($notify) {
                Notification::make()
                    ->title('Data Kuartal Sebelumnya Berhasil Diambil!')
                    ->body("Acuan harga dari {$prevData['label']} ({$prevData['source']}) berhasil diisikan.")
                    ->success()
                    ->send();
            }
        } elseif ($notify) {
            $info = Fastener::getPreviousQuarterInfo($quarter, $year);
            Notification::make()
                ->title('Belum Ada Data di '.$info['label'])
                ->body('Tidak ditemukan data acuan pada kuartal sebelumnya untuk periode ini.')
                ->info()
                ->send();
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        // SECTION 1: Spesifikasi Fastener & Periode
                        Forms\Components\Section::make('1. Spesifikasi Fastener & Periode')
                            ->icon('heroicon-o-scale')
                            ->schema([
                                Forms\Components\Select::make('year')
                                    ->label('Tahun Kontrak')
                                    ->options(function () {
                                        $current = (int) date('Y');
                                        $years = [];
                                        for ($y = $current - 2; $y <= $current + 2; $y++) {
                                            $years[$y] = (string) $y;
                                        }

                                        return $years;
                                    })
                                    ->default(function () {
                                        $latestYear = Fastener::latest('year')->value('year');

                                        return $latestYear ?: (int) date('Y');
                                    })
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $livewire) {
                                        static::syncCurrencyAndPrices($get, $set);
                                        if (! $livewire instanceof EditRecord) {
                                            static::pullPreviousQuarterData($get, $set, false);
                                            static::lookupPreviousQuarterRecord($get, $set);
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Select::make('quarter')
                                    ->label('Kuartal (Quarter)')
                                    ->options([
                                        'Q1' => 'Q1 (Januari - Maret)',
                                        'Q2' => 'Q2 (April - Juni)',
                                        'Q3' => 'Q3 (Juli - September)',
                                        'Q4' => 'Q4 (Oktober - Desember)',
                                    ])
                                    ->default('Q1')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $livewire) {
                                        static::syncCurrencyAndPrices($get, $set);
                                        if (! $livewire instanceof EditRecord) {
                                            static::pullPreviousQuarterData($get, $set, false);
                                            static::lookupPreviousQuarterRecord($get, $set);
                                        }
                                    })
                                    ->columnSpan(1),

                                SupplierSelectField::make(
                                    fieldName: 'supplier_id',
                                    label: 'Pilih Supplier',
                                    defaultCategory: 'Fastener',
                                    allowedCategories: ['Fastener'],
                                    syncTextFields: true,
                                    required: fn (Get $get) => empty($get('supplier_name')),
                                    helperText: 'Vendor pembuat fastener (mis. PT. NITTO ALAM INDONESIA, PT HSSI, PT USCI)',
                                    onUpdated: fn (Get $get, Set $set) => static::lookupPreviousQuarterRecord($get, $set),
                                ),

                                Forms\Components\TextInput::make('part_number')
                                    ->label('Part Number')
                                    ->placeholder('Contoh: 90041-19960')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('old_part_number')
                                    ->label('OLD Part No.')
                                    ->placeholder('Contoh: 90041-19960')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('part_name')
                                    ->label('Part Name')
                                    ->placeholder('Contoh: BOLT, W/WASHER')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('model_name')
                                    ->label('Model')
                                    ->placeholder('Contoh: D26A')
                                    ->default('D26A')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('monthly_volume')
                                    ->label('Monthly Volume (Pcs)')
                                    ->numeric()
                                    ->placeholder('Contoh: 20000')
                                    ->default(20000)
                                    ->suffix('pcs/bln')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('drawing_reference')
                                    ->label('Drawing Reference')
                                    ->placeholder('Contoh: Official Dwg (supplier selection)')
                                    ->default('Official Dwg (supplier selection)')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('eci_number')
                                    ->label('ECI Number')
                                    ->placeholder('Contoh: F1225')
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('quotation_date')
                                    ->label('Tanggal Penawaran / Revisi')
                                    ->placeholder('YYYY-MM-DD')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('steel_spec')
                                    ->label('Spesifikasi Baja (Spec)')
                                    ->placeholder('Contoh: SWCH-45K, SPH270C, SPC270C')
                                    ->datalist(fn (): array => MaterialSteel::query()
                                        ->whereNotNull('steel_spec')
                                        ->distinct()
                                        ->orderBy('steel_spec')
                                        ->limit(200)
                                        ->pluck('steel_spec')
                                        ->toArray())
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if ($state) {
                                            $clean = Fastener::cleanSteelSpec($state);
                                            if ($clean !== $state) {
                                                $set('steel_spec', $clean);
                                            }
                                        }
                                        $found = static::lookupCurrentQuarterSteel($get, $set, true);
                                        if (! $found) {
                                            static::lookupPreviousQuarterRecord($get, $set);
                                        }
                                    })
                                    ->dehydrateStateUsing(fn (?string $state) => Fastener::cleanSteelSpec($state))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('double_thickness')
                                    ->label('Thickness / Diameter (mm)')
                                    ->placeholder('Contoh: 4.4 atau 5.0')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $clean = trim(preg_replace('/[a-zA-Z\s]+/', '', $state));
                                            $set('double_thickness', $clean);
                                            $set('diameter', $clean);
                                        }
                                        static::lookupPreviousQuarterRecord($get, $set);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('wire_spec')
                                    ->label('Wire Spec (Spesifikasi Kawat)')
                                    ->placeholder('Contoh: WIRE OF RIVET [SWCH-45K]')
                                    ->default('WIRE OF RIVET [SWCH-45K]')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                // Hidden fields untuk integritas data
                                Forms\Components\Hidden::make('diameter'),
                                Forms\Components\Hidden::make('vendor_code'),
                                Forms\Components\Hidden::make('supplier_name'),
                                Forms\Components\Hidden::make('currency_id'),
                                Forms\Components\Hidden::make('currency_code')->default('USD'),
                                Forms\Components\Hidden::make('gap_steel_kg_usd')->default(0),
                                Forms\Components\Hidden::make('fluktuasi_jfe')->default(0),

                                // Form state helpers untuk acuan kuartal sebelumnya
                                Forms\Components\Hidden::make('prev_quarter_label')->dehydrated(false),
                                Forms\Components\Hidden::make('prev_record_found')->default(false)->dehydrated(false),
                                Forms\Components\Hidden::make('prev_coil_price')->dehydrated(false),
                                Forms\Components\Hidden::make('prev_sheet_price')->dehydrated(false),
                                Forms\Components\Hidden::make('prev_wire_price')->dehydrated(false),
                                Forms\Components\Hidden::make('prev_rate')->dehydrated(false),
                            ])->columns(3),

                        // SECTION 2: Penetapan Harga Bahan Baku & Kurs
                        Forms\Components\Section::make('2. Harga Bahan Baku & Kurs')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\Hidden::make('hr_steel_usd')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromHrCr($get, $set, 'HR')),

                                Forms\Components\Hidden::make('cr_steel_usd')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromHrCr($get, $set, 'CR')),
                                Forms\Components\Hidden::make('gap_steel_usd')->default(0),

                                Forms\Components\TextInput::make('exchange_rate')
                                    ->label('Perubahan $ Rupiah (Kurs IDR)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('Rp')
                                    ->placeholder('Contoh: 16465 (atau 0 jika input harga IDR langsung)')
                                    ->default(function (Get $get) {
                                        $prevRate = $get('prev_rate');
                                        if ($prevRate !== null && (float) $prevRate > 0) {
                                            return QuarterlyPullHelper::normalizeRate($prevRate);
                                        }

                                        $year = (int) ($get('year') ?? date('Y'));
                                        $quarter = $get('quarter') ?? 'Q1';

                                        $rate = Currency::where('code', 'USD')->where('year', $year)->where('quarter', $quarter)->value('exchange_rate_to_idr')
                                            ?? Currency::where('code', 'USD')->where('year', $year)->value('exchange_rate_to_idr')
                                            ?? Currency::where('code', 'USD')->latest('year')->value('exchange_rate_to_idr')
                                            ?? 16000;

                                        return QuarterlyPullHelper::normalizeRate($rate);
                                    })
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? ((float) $state == 0.0 ? 0 : QuarterlyPullHelper::normalizeRate($state)) : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        static::recalculateFormPrices($get, $set);
                                        $oldRate = (float) ($get('old_exchange_rate') ?? 0);
                                        if ($oldRate > 0 && $state && (float) $state > 0) {
                                            $set('exchange_rate_fluctuation', round((float) $state / $oldRate, 6));
                                        }
                                    })
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('old_exchange_rate')
                                    ->label('Kurs Lama (Old Rate Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('Contoh: 16321')
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? QuarterlyPullHelper::normalizeRate($state) : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        $newRate = (float) ($get('exchange_rate') ?? 0);
                                        if ($state && (float) $state > 0 && $newRate > 0) {
                                            $set('exchange_rate_fluctuation', round($newRate / (float) $state, 6));
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('exchange_rate_fluctuation')
                                    ->label('Faktor Fluktuasi Kurs')
                                    ->numeric()
                                    ->step(0.000001)
                                    ->placeholder('Contoh: 1.008823')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('wire_price_usd_kg')
                                    ->label('Harga Wire / Kawat ($/Kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: 2.0200')
                                    ->default(1.66)
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (float) $state : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromWire($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('wire_price_idr_kg')
                                    ->label('Harga Wire / Kawat (IDR/Kg)')
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix('Rp')
                                    ->placeholder('Otomatis USD × Kurs atau input Rp langsung')
                                    ->helperText('Otomatis dari USD × Kurs, atau isi langsung IDR/kg (RM Price)')
                                    ->live(onBlur: true)
                                    ->dehydrated()
                                    ->default(fn (Get $get) => round(((float) ($get('wire_price_usd_kg') ?? 1.66)) * ((float) ($get('exchange_rate') ?? 16000)), 2))
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? round((float) $state, 2) : null)
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('coil_price')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromCoil($get, $set)),

                                Forms\Components\Hidden::make('sheet_price')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromSheet($get, $set)),

                                Forms\Components\Hidden::make('coil_price_idr')
                                    ->default(0),

                                Forms\Components\Hidden::make('sheet_price_idr')
                                    ->default(0),
                            ])->columns(2),

                        // SECTION 3: Rincian Wire, Washer & Biaya Proses (Nitto Alam Breakdown)
                        Forms\Components\Section::make('3. Rincian Wire, Washer & Biaya Proses Fastener')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\TextInput::make('gross_weight_gr')
                                    ->label('Gross Weight (gr / pc)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->default(4.84)
                                    ->helperText('Otomatis = Nett Weight × 1,1 (bisa diedit manual)')
                                    ->suffix('gr/pc')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('nett_weight_gr')
                                    ->label('Nett Weight (gr / pc)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->default(4.40)
                                    ->suffix('gr/pc')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state !== null && $state !== '' && (float) $state > 0) {
                                            $set('gross_weight_gr', round((float) $state * 1.1, 4));
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('material_control_percent')
                                    ->label('Material Control (%)')
                                    ->numeric()
                                    ->default(5)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('washer_price')
                                    ->label('Harga Washer (Rp / pc)')
                                    ->numeric()
                                    ->default(198)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('washer_weight_gr')
                                    ->label('Berat Washer (gr / pc)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->default(4.50)
                                    ->suffix('gr/pc')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('heading_cost')
                                    ->label('Heading Cost (Rp / pc)')
                                    ->numeric()
                                    ->default(48)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('rolling_cost')
                                    ->label('Rolling Cost (Rp / pc)')
                                    ->numeric()
                                    ->default(76)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('washing_cost_kg')
                                    ->label('Washing Cost (Rp / kg)')
                                    ->numeric()
                                    ->default(500)
                                    ->helperText('Tarif per kg (di Excel 0,5 Rp/gr = Rp 500/kg)')
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('plating_cost_kg')
                                    ->label('Plating Cost (Rp / kg)')
                                    ->numeric()
                                    ->default(10000)
                                    ->helperText('Tarif per kg (di Excel 10 Rp/gr = Rp 10.000/kg)')
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('inspection_cost')
                                    ->label('Inspection Cost (Rp / pc)')
                                    ->numeric()
                                    ->default(5)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('packing_cost')
                                    ->label('Packing Cost (Rp / pc)')
                                    ->numeric()
                                    ->default(3)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('overhead_supplied_percent')
                                    ->label('(4) O/H Supplied (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('overhead_self_percent')
                                    ->label('(5) Overhead Self (%)')
                                    ->numeric()
                                    ->default(14.3)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('transportation_cost')
                                    ->label('(6) Transportation / Others (Rp / pc)')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->placeholder('0')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('cost_down')
                                    ->label('(7) Cost Down (Rp / pc)')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->placeholder('0')
                                    ->helperText('Isi negatif jika potongan harga (mis. -5), atau 0')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('tooling_depreciation')
                                    ->label('(9) Die & Jig Depreciation (Rp / pc)')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->placeholder('0')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                QuarterlyPullHelper::buildToolingDetailsRepeater('Misal: HEADING DIE / ROLLING DIE', '20000'),

                                Forms\Components\Placeholder::make('calculation_summary')
                                    ->hiddenLabel()
                                    ->content(function (Get $get) {
                                        $rate = (float) ($get('exchange_rate') ?? 0);
                                        $wireUsdKg = (float) ($get('wire_price_usd_kg') ?? 0);
                                        $wireIdrRaw = (float) ($get('wire_price_idr_kg') ?? 0);
                                        if ($wireUsdKg > 0 && $rate > 0) {
                                            $wireIdrKg = round($wireUsdKg * $rate, 2);
                                        } elseif ($wireIdrRaw > 0) {
                                            $wireIdrKg = round($wireIdrRaw, 2);
                                        } else {
                                            $fallbackUsd = (float) ($get('coil_price') ?: 1.66);
                                            $wireIdrKg = round($fallbackUsd * ($rate > 0 ? $rate : 16000), 2);
                                        }

                                        $nettGr = (float) ($get('nett_weight_gr') ?? 4.4);
                                        $grossGr = (float) ($get('gross_weight_gr') ?? 0);
                                        if ($grossGr <= 0 && $nettGr > 0) {
                                            $grossGr = round($nettGr * 1.1, 4);
                                        }
                                        $wirePc = round(($grossGr / 1000) * $wireIdrKg, 4);

                                        $ctrlPct = (float) ($get('material_control_percent') ?? 5);
                                        $ctrlCost = round(($ctrlPct / 100) * $wirePc, 4);

                                        $washerPrice = (float) ($get('washer_price') ?? 198);
                                        $matTotal = round($wirePc + $ctrlCost + $washerPrice, 4);

                                        $washerGr = (float) ($get('washer_weight_gr') ?? 4.5);
                                        $totalPartWeightKg = ($nettGr + $washerGr) / 1000;

                                        $washKg = (float) ($get('washing_cost_kg') ?? 500);
                                        $washCost = round($totalPartWeightKg * $washKg, 4);

                                        $plateKg = (float) ($get('plating_cost_kg') ?? 10000);
                                        $plateCost = round($totalPartWeightKg * $plateKg, 4);

                                        $heading = (float) ($get('heading_cost') ?? 48);
                                        $rolling = (float) ($get('rolling_cost') ?? 76);
                                        $inspect = (float) ($get('inspection_cost') ?? 5);
                                        $packing = (float) ($get('packing_cost') ?? 3);

                                        $procTotal = round($heading + $rolling + $washCost + $plateCost + $inspect + $packing, 4);
                                        $subtotal = round($matTotal + $procTotal, 4);

                                        $ohSuppliedPct = (float) ($get('overhead_supplied_percent') ?? 0);
                                        $ohSuppliedCost = round(($ohSuppliedPct / 100) * $subtotal, 4);

                                        $ohSelfPct = (float) ($get('overhead_self_percent') ?? 14.3);
                                        $ohCost = round(($ohSelfPct / 100) * $subtotal, 4);

                                        $transCost = (float) ($get('transportation_cost') ?? 0);
                                        $costDown = (float) ($get('cost_down') ?? 0);
                                        $partCostExcl = round($subtotal + $ohSuppliedCost + $ohCost + $transCost + $costDown, 4);

                                        $tooling = (float) ($get('tooling_depreciation') ?? 0);
                                        $partCost = round($partCostExcl + $tooling, 4);

                                        return new HtmlString("
                                            <div class='rounded-xl border border-primary-200 bg-white p-5 shadow-sm dark:border-primary-800 dark:bg-gray-900'>
                                                <div class='mb-3 flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800'>
                                                    <h3 class='text-sm font-semibold text-primary-950 dark:text-primary-100 flex items-center gap-2'>
                                                        <span>📊</span> Rincian Komponen Biaya Fastener (Format Excel PT Nitto Alam)
                                                    </h3>
                                                    <span class='text-xs font-mono text-gray-400'>9 Lines Breakdown</span>
                                                </div>

                                                <div class='mb-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs'>
                                                    <div class='rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60'>
                                                        <div class='text-gray-500 dark:text-gray-400'>Wire / pc ({$grossGr} gr)</div>
                                                        <div class='mt-1 text-sm font-bold text-gray-800 dark:text-gray-200'>Rp ".number_format($wirePc, 2, ',', '.')."</div>
                                                    </div>
                                                    <div class='rounded-lg bg-amber-50 p-2.5 dark:bg-amber-950/30'>
                                                        <div class='text-amber-800 dark:text-amber-300'>Mat'l Ctrl ({$ctrlPct}%) + Washer</div>
                                                        <div class='mt-1 text-sm font-bold text-amber-700 dark:text-amber-400'>Rp ".number_format($ctrlCost + $washerPrice, 2, ',', '.')."</div>
                                                    </div>
                                                    <div class='rounded-lg bg-blue-50 p-2.5 dark:bg-blue-950/30'>
                                                        <div class='text-blue-800 dark:text-blue-300'>Washing + Plating (".($nettGr + $washerGr)." gr)</div>
                                                        <div class='mt-1 text-sm font-bold text-blue-700 dark:text-blue-400'>Rp ".number_format($washCost + $plateCost, 2, ',', '.')."</div>
                                                    </div>
                                                    <div class='rounded-lg bg-purple-50 p-2.5 dark:bg-purple-950/30'>
                                                        <div class='text-purple-800 dark:text-purple-300'>Head + Roll + Insp + Pack</div>
                                                        <div class='mt-1 text-sm font-bold text-purple-700 dark:text-purple-400'>Rp ".number_format($heading + $rolling + $inspect + $packing, 2, ',', '.')."</div>
                                                    </div>
                                                </div>

                                                <div class='overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700'>
                                                    <table class='min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs'>
                                                        <thead class='bg-gray-50 dark:bg-gray-800 font-semibold text-gray-700 dark:text-gray-300'>
                                                            <tr>
                                                                <th class='px-3 py-2 text-left'>No / Komponen Biaya</th>
                                                                <th class='px-3 py-2 text-left'>Keterangan / Rumus Acuan</th>
                                                                <th class='px-3 py-2 text-right'>Nilai (Rp / pc)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class='divide-y divide-gray-100 dark:divide-gray-800'>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 font-medium text-gray-900 dark:text-gray-100'>(1) Material Total</td>
                                                                <td class='px-3 py-2 text-gray-500'>Wire/pc (Rp ".number_format($wirePc, 2, ',', '.').') + Mat Ctrl (Rp '.number_format($ctrlCost, 2, ',', '.').') + Washer (Rp '.number_format($washerPrice, 0, ',', '.').")</td>
                                                                <td class='px-3 py-2 text-right font-semibold text-blue-600 dark:text-blue-400'>Rp ".number_format($matTotal, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 font-medium text-gray-900 dark:text-gray-100'>(2) Process Total</td>
                                                                <td class='px-3 py-2 text-gray-500'>Heading ({$heading}) + Rolling ({$rolling}) + Wash (".number_format($washCost, 2, ',', '.').') + Plate ('.number_format($plateCost, 2, ',', '.').") + Insp ({$inspect}) + Pack ({$packing})</td>
                                                                <td class='px-3 py-2 text-right font-semibold text-purple-600 dark:text-purple-400'>Rp ".number_format($procTotal, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='bg-gray-50/60 dark:bg-gray-800/40 font-semibold'>
                                                                <td class='px-3 py-2 text-gray-800 dark:text-gray-200'>(3) Sub Total</td>
                                                                <td class='px-3 py-2 text-gray-500'>Komponen (1) Material + (2) Process</td>
                                                                <td class='px-3 py-2 text-right text-gray-900 dark:text-gray-100'>Rp ".number_format($subtotal, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(4) O/H & Profit Supplied</td>
                                                                <td class='px-3 py-2 text-gray-500'>{$ohSuppliedPct}% × Subtotal</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($ohSuppliedCost, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(5) O/H & Profit Self</td>
                                                                <td class='px-3 py-2 text-gray-500'>{$ohSelfPct}% × Subtotal</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($ohCost, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(6) Transportation / Others</td>
                                                                <td class='px-3 py-2 text-gray-500'>Biaya Pengiriman / Lain-lain</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($transCost, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(7) Cost Down</td>
                                                                <td class='px-3 py-2 text-gray-500'>Penyesuaian / Pengurangan Biaya</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($costDown, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='bg-amber-50/60 dark:bg-amber-950/20 font-bold'>
                                                                <td class='px-3 py-2 text-amber-900 dark:text-amber-200'>(8) Part Cost (Excl. Tooling)</td>
                                                                <td class='px-3 py-2 text-amber-800/80 dark:text-amber-300/80 font-normal'>Total (3) + (4) + (5) + (6) + (7)</td>
                                                                <td class='px-3 py-2 text-right text-amber-900 dark:text-amber-200'>Rp ".number_format($partCostExcl, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(9) Die & Jig Depreciation</td>
                                                                <td class='px-3 py-2 text-gray-500'>Amortisasi Tooling per pcs</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($tooling, 2, ',', '.')."</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <div class='mt-4 flex items-center justify-between rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 px-5 py-3.5 text-white shadow'>
                                                    <div>
                                                        <div class='text-xs font-medium uppercase tracking-wider text-emerald-100'>FINAL FASTENER COST (Rp / pc)</div>
                                                        <div class='text-xs text-emerald-100/80'>Baris (8) Part Cost + (9) Die & Jig Depreciation</div>
                                                    </div>
                                                    <div class='text-2xl font-black tracking-tight'>Rp ".number_format($partCost, 2, ',', '.').'</div>
                                                </div>
                                            </div>
                                        ');
                                    })
                                    ->columnSpanFull(),
                            ])->columns(3),

                        // SECTION 4: Catatan, Alterasi & Referensi Excel (Collapsible)
                        Forms\Components\Section::make('4. Catatan, Alterasi & Referensi Excel (Opsional)')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\Textarea::make('alteration')
                                    ->label('Alteration / ECI Changes')
                                    ->placeholder('Catatan perubahan spesifikasi atau revisi drawing...')
                                    ->rows(2)
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('etc_contents')
                                    ->label('ETC Contents')
                                    ->placeholder('Keterangan konten tambahan...')
                                    ->rows(2)
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('comments')
                                    ->label('Comments (Catatan Penawaran Supplier)')
                                    ->placeholder('Catatan resmi penawaran...')
                                    ->rows(2)
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('remarks')
                                    ->label('Remarks (Ketentuan & Term)')
                                    ->placeholder('Syarat fluktuasi kurs / pengiriman...')
                                    ->rows(2)
                                    ->columnSpan(1),

                                Forms\Components\Select::make('status')
                                    ->label('Status Kontrak')
                                    ->options([
                                        'Active' => 'Active (Sedang Berlaku)',
                                        'Draft' => 'Draft (Dalam Pengajuan)',
                                        'Archived' => 'Archived (Arsip)',
                                    ])
                                    ->default('Active')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan Kontrak / Referensi Supplier')
                                    ->placeholder('Misal: Berdasarkan surat penawaran harga Fasteners No...')
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::getTableColumns())
            ->defaultSort('year', 'desc')
            ->filters(static::getTableFilters())
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->button()
                    ->label('Filter Data')
            )
            ->filtersFormColumns(2)
            ->actions(static::getTableActions(), position: ActionsPosition::BeforeColumns)
            ->bulkActions(static::getTableBulkActions());
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    public static function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('period')
                ->label('Kuartal')
                ->badge()
                ->state(fn (Fastener $record) => "{$record->year} - {$record->quarter}")
                ->color(fn (Fastener $record): string => match ($record->quarter) {
                    'Q1' => 'info',
                    'Q2' => 'success',
                    'Q3' => 'warning',
                    'Q4' => 'primary',
                    default => 'gray',
                })
                ->sortable(['year', 'quarter']),

            Tables\Columns\TextColumn::make('supplier_name')
                ->label('Supplier')
                ->weight('bold')
                ->wrap()
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('part_number')
                ->label('Part Number')
                ->weight('bold')
                ->placeholder('-')
                ->wrapHeader()
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('part_name')
                ->label('Part Name')
                ->placeholder('-')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('steel_spec')
                ->label('Spec')
                ->formatStateUsing(fn ($state) => Fastener::cleanSteelSpec($state))
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('double_thickness')
                ->label('Dia / Thick (mm)')
                ->wrapHeader()
                ->formatStateUsing(function ($state, Fastener $record) {
                    $val = ($state !== null && $state !== '') ? $state : $record->diameter;
                    if ($val === null || $val === '') {
                        return '-';
                    }

                    return trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $val));
                })
                ->placeholder('-')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('exchange_rate')
                ->label('Kurs USD')
                ->wrapHeader()
                ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                ->alignRight()
                ->sortable(),

            Tables\Columns\TextColumn::make('wire_price_usd_kg')
                ->label('Wire ($/Kg)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state, Fastener $record) => static::formatUsd($state, $record->currency_code))
                ->alignRight()
                ->sortable(),

            Tables\Columns\TextColumn::make('part_cost')
                ->label('Total Cost (Rp)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->weight('black')
                ->color('success')
                ->alignRight()
                ->sortable(),

            // Kolom rincian Excel opsional (toggleable hidden by default)
            Tables\Columns\TextColumn::make('old_part_number')
                ->label('Old Part No')
                ->placeholder('-')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('wire_spec')
                ->label('Wire Spec')
                ->placeholder('-')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('gross_weight_gr')
                ->label('Gross Wt (gr)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? number_format((float) $state, 2, ',', '.').' gr' : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('nett_weight_gr')
                ->label('Nett Wt (gr)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? number_format((float) $state, 2, ',', '.').' gr' : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('wire_price_idr_kg')
                ->label('Wire IDR/Kg')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('wire_price_pc')
                ->label('Wire Cost/pc')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('washer_price')
                ->label('Washer (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('material_total')
                ->label('(1) Material Total (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('process_total')
                ->label('(2) Process Total (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('subtotal')
                ->label('(3) Subtotal (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('overhead_supplied_cost')
                ->label('(4) O/H Supplied (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('overhead_self_cost')
                ->label('(5) O/H Self (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('transportation_cost')
                ->label('(6) Transport (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('cost_down')
                ->label('(7) Cost Down (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('part_cost_excluding_tooling')
                ->label('(8) Cost Excl. Tooling')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            // Kolom gap untuk kompatibilitas test (toggleable hidden by default)
            Tables\Columns\TextColumn::make('gap_steel_usd')
                ->label('Gap/MT ($)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state, Fastener $record) => static::formatUsd($state, $record->currency_code))
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('gap_steel_kg_usd')
                ->label('Gap/Kg ($)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state, Fastener $record) => static::formatUsd($state, $record->currency_code))
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('drawing_reference')
                ->label('Drawing Ref')
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('eci_number')
                ->label('ECI No')
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('quotation_date')
                ->label('Tgl Quotation')
                ->date('d M Y')
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('old_exchange_rate')
                ->label('Old Rate (Rp)')
                ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('exchange_rate_fluctuation')
                ->label('Fluktuasi Kurs')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? number_format((float) $state, 4) : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('tooling_depreciation')
                ->label('Tooling / Jig Depr')
                ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, Tables\Filters\BaseFilter>
     */
    public static function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('quarter')
                ->label('Filter Kuartal')
                ->options([
                    'Q1' => 'Q1 (Januari - Maret)',
                    'Q2' => 'Q2 (April - Juni)',
                    'Q3' => 'Q3 (Juli - September)',
                    'Q4' => 'Q4 (Oktober - Desember)',
                ])
                ->multiple(),

            Tables\Filters\SelectFilter::make('supplier_id')
                ->label('Filter Supplier')
                ->relationship('supplier', 'name')
                ->searchable()
                ->optionsLimit(50),

            Tables\Filters\SelectFilter::make('year')
                ->label('Filter Tahun')
                ->options(function () {
                    $current = (int) date('Y');
                    $years = range($current + 2, $current - 2);

                    return array_combine($years, array_map(fn ($y) => "Tahun {$y}", $years));
                }),

            Tables\Filters\SelectFilter::make('status')
                ->label('Filter Status')
                ->options([
                    'Active' => 'Active',
                    'Draft' => 'Draft',
                    'Archived' => 'Archived',
                ]),
        ];
    }

    /**
     * @return array<int, Tables\Actions\Action|Tables\Actions\ActionGroup>
     */
    public static function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('view_sheet')
                ->label('Lembar Kalkulasi')
                ->tooltip('Tampilkan lembar rincian kalkulasi format resmi Excel')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->iconButton()
                ->slideOver()
                ->modalHeading(fn (Fastener $record) => "Lembar Perhitungan Fastener Cost: {$record->part_number} - {$record->part_name}")
                ->modalWidth(MaxWidth::SevenExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(fn (Fastener $record) => view('filament.resources.fasteners.sheet-modal', ['record' => $record])),
            Tables\Actions\EditAction::make()->iconButton(),
            Tables\Actions\DeleteAction::make()->iconButton(),
        ];
    }

    /**
     * @return array<int, Tables\Actions\BulkAction|Tables\Actions\BulkActionGroup>
     */
    public static function getTableBulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('export_selected_xlsx')
                    ->label('Export Terpilih (Excel .xlsx)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => ExcelExportService::exportFastener($records, 'xlsx')),

                Tables\Actions\BulkAction::make('export_selected_csv')
                    ->label('Export Terpilih (CSV .csv)')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => ExcelExportService::exportFastener($records, 'csv')),

                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'currency']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFasteners::route('/'),
            'create' => Pages\CreateFastener::route('/create'),
            'edit' => Pages\EditFastener::route('/{record}/edit'),
        ];
    }
}
