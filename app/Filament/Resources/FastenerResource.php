<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FastenerResource\Pages;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
    public static function formatUsd(mixed $value, ?string $currency = 'USD'): string
    {
        if ($value === null || $value === '') {
            return '-';
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

            if (empty($get('supplier_id')) && ! empty($steel->supplier_id)) {
                $set('supplier_id', $steel->supplier_id);
                $set('vendor_code', $steel->vendor_code);
                $set('supplier_name', $steel->supplier_name);
            }

            static::recalculateFormPrices($get, $set);

            if ($notify) {
                Notification::make()
                    ->title("Data Material Steel {$quarter} {$year} Ditemukan!")
                    ->body("Harga Coil USD {$steel->coil_price}, Sheet USD {$steel->sheet_price}, Kurs Rp ".number_format($steel->exchange_rate, 0, ',', '.').' berhasil diisi otomatis.')
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

        $prevQuarter = match ($quarter) {
            'Q1' => 'Q4',
            'Q2' => 'Q1',
            'Q3' => 'Q2',
            'Q4' => 'Q3',
            default => 'Q1',
        };
        $prevYear = ($quarter === 'Q1') ? ($year - 1) : $year;

        $set('prev_quarter_label', "{$prevQuarter} {$prevYear}");

        if (! $supplierId && ! $steelSpec) {
            return;
        }

        $prev = Fastener::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $steelSpec);

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
            if (isset($prev['overhead_self_percent']) && empty($get('overhead_self_percent'))) {
                $set('overhead_self_percent', $prev['overhead_self_percent']);
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
        $rate = (float) ($get('exchange_rate') ?? 1);

        $coilIdr = round($coil * $rate);
        $sheetIdr = round($sheet * $rate);

        $set('coil_price_idr', $coilIdr);
        $set('sheet_price_idr', $sheetIdr);
    }

    public static function updatePricesFromWire(Get $get, Set $set): void
    {
        $wire = (float) ($get('wire_price_usd_kg') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);
        $coil = (float) ($get('coil_price') ?? 0);

        if ($coil == 0.0 && $wire > 0) {
            $set('coil_price', $wire);
            $set('coil_price_idr', round($wire * $rate));
        }

        static::recalculateFormPrices($get, $set);
    }

    public static function updatePricesFromCoil(Get $get, Set $set): void
    {
        $coil = (float) ($get('coil_price') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);
        $sheet = (float) ($get('sheet_price') ?? 0);

        $set('coil_price_idr', round($coil * $rate));

        if (empty($get('wire_price_usd_kg')) && $coil > 0) {
            $set('wire_price_usd_kg', $coil);
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

        $prevData = Fastener::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $spec);

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
            if (empty($get('supplier_id')) && ! empty($prevData['supplier_id'])) {
                $set('supplier_id', $prevData['supplier_id']);
                $set('vendor_code', $prevData['vendor_code']);
                $set('supplier_name', $prevData['supplier_name']);
            }

            if (! empty($prevData['part_number'])) {
                $set('part_number', $prevData['part_number']);
            }
            if (! empty($prevData['part_name'])) {
                $set('part_name', $prevData['part_name']);
            }
            if (! empty($prevData['model_name'])) {
                $set('model_name', $prevData['model_name']);
            }
            if (! empty($prevData['monthly_volume'])) {
                $set('monthly_volume', $prevData['monthly_volume']);
            }
            if (! empty($prevData['wire_spec'])) {
                $set('wire_spec', $prevData['wire_spec']);
            }
            if (isset($prevData['nett_weight_gr'])) {
                $set('nett_weight_gr', $prevData['nett_weight_gr']);
            }
            if (isset($prevData['gross_weight_gr'])) {
                $set('gross_weight_gr', $prevData['gross_weight_gr']);
            }
            if (isset($prevData['wire_price_usd_kg'])) {
                $set('wire_price_usd_kg', $prevData['wire_price_usd_kg']);
            }
            if (isset($prevData['material_control_percent'])) {
                $set('material_control_percent', $prevData['material_control_percent']);
            }
            if (isset($prevData['washer_price'])) {
                $set('washer_price', $prevData['washer_price']);
            }
            if (isset($prevData['washer_weight_gr'])) {
                $set('washer_weight_gr', $prevData['washer_weight_gr']);
            }
            if (isset($prevData['heading_cost'])) {
                $set('heading_cost', $prevData['heading_cost']);
            }
            if (isset($prevData['rolling_cost'])) {
                $set('rolling_cost', $prevData['rolling_cost']);
            }
            if (isset($prevData['washing_cost_kg'])) {
                $set('washing_cost_kg', $prevData['washing_cost_kg']);
            }
            if (isset($prevData['plating_cost_kg'])) {
                $set('plating_cost_kg', $prevData['plating_cost_kg']);
            }
            if (isset($prevData['inspection_cost'])) {
                $set('inspection_cost', $prevData['inspection_cost']);
            }
            if (isset($prevData['packing_cost'])) {
                $set('packing_cost', $prevData['packing_cost']);
            }
            if (isset($prevData['overhead_self_percent'])) {
                $set('overhead_self_percent', $prevData['overhead_self_percent']);
            }

            $rate = (float) ($get('exchange_rate') ?? $prevData['exchange_rate'] ?? 16000);
            $set('exchange_rate', $rate);
            $set('coil_price_idr', round((float) ($prevData['coil_price'] ?? 0) * $rate));
            $set('sheet_price_idr', round((float) ($prevData['sheet_price'] ?? 0) * $rate));

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
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        static::syncCurrencyAndPrices($get, $set);
                                        static::pullPreviousQuarterData($get, $set, false);
                                        static::lookupPreviousQuarterRecord($get, $set);
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
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        static::syncCurrencyAndPrices($get, $set);
                                        static::pullPreviousQuarterData($get, $set, false);
                                        static::lookupPreviousQuarterRecord($get, $set);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Select::make('supplier_id')
                                    ->label('Pilih Supplier')
                                    ->relationship(
                                        name: 'supplier',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query, Get $get, ?Fastener $record) {
                                            $selectedId = $get('supplier_id') ?? $record?->supplier_id;
                                            $query->where(function ($q) use ($selectedId) {
                                                $q->whereIn('name', ['PT HSSI', 'PT USCI'])
                                                    ->orWhere('name', 'like', '%HSSI%')
                                                    ->orWhere('name', 'like', '%USCI%')
                                                    ->orWhere('category', 'Fastener');
                                                if ($selectedId) {
                                                    $q->orWhere('id', $selectedId);
                                                }
                                            });
                                        }
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (Get $get) => empty($get('supplier_name')))
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $supplier = Supplier::find($state);
                                            if ($supplier) {
                                                $set('vendor_code', $supplier->code);
                                                $set('supplier_name', $supplier->name);
                                            }
                                        }
                                        static::lookupPreviousQuarterRecord($get, $set);
                                    })
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nama Supplier Baru')
                                            ->placeholder('Contoh: PT Toho Fastener Tbk')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if ($state) {
                                                    $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $state), 0, 5));
                                                    $set('code', 'VND-'.($clean ?: 'SUP').'-'.rand(10, 99));
                                                }
                                            }),

                                        Forms\Components\Hidden::make('code')
                                            ->default(fn () => 'VND-SUP-'.rand(100, 999)),
                                    ])
                                    ->columnSpan(1),

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

                                Forms\Components\TextInput::make('steel_spec')
                                    ->label('Spesifikasi Baja (Spec)')
                                    ->placeholder('Contoh: SWCH-45K, SPH270C, SPC270C')
                                    ->datalist(fn () => MaterialSteel::distinct()->pluck('steel_spec')->filter()->toArray())
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
                                Forms\Components\TextInput::make('hr_steel_usd')
                                    ->label('HR Dolar / MT')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: -20 atau 50 (Hot Rolled: SPH, SAPH)')
                                    ->default(0)
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (float) $state : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromHrCr($get, $set, 'HR'))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('cr_steel_usd')
                                    ->label('CR Dolar / MT')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: -20 atau 100 (Cold Rolled: SPC, NSC)')
                                    ->default(0)
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (float) $state : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromHrCr($get, $set, 'CR'))
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('gap_steel_usd')->default(0),

                                Forms\Components\TextInput::make('exchange_rate')
                                    ->label('Perubahan Dolar Rupiah (Kurs IDR)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('Contoh: 16000')
                                    ->default(function (Get $get) {
                                        $prevRate = $get('prev_rate');
                                        if ($prevRate !== null && (float) $prevRate > 0) {
                                            return (int) round((float) $prevRate);
                                        }

                                        $year = (int) ($get('year') ?? date('Y'));
                                        $quarter = $get('quarter') ?? 'Q1';

                                        $rate = Currency::where('code', 'USD')->where('year', $year)->where('quarter', $quarter)->value('exchange_rate_to_idr')
                                            ?? Currency::where('code', 'USD')->where('year', $year)->value('exchange_rate_to_idr')
                                            ?? Currency::where('code', 'USD')->latest('year')->value('exchange_rate_to_idr')
                                            ?? 16000;

                                        return (int) round((float) $rate);
                                    })
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round((float) $state) : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        static::recalculateFormPrices($get, $set);
                                        $oldRate = (float) ($get('old_exchange_rate') ?? 0);
                                        if ($oldRate > 0 && $state) {
                                            $set('exchange_rate_fluctuation', round((float) $state / $oldRate, 6));
                                        }
                                    })
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('old_exchange_rate')
                                    ->label('Kurs Lama (Old Rate Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('Contoh: 14555')
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
                                    ->placeholder('Contoh: 1.013535')
                                    ->helperText('Perbandingan kurs baru / kurs lama (Exchg fluctuation)')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('wire_price_usd_kg')
                                    ->label('Harga Wire / Kawat ($/Kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: 1.6600')
                                    ->default(1.66)
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (float) $state : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromWire($get, $set))
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('coil_price')
                                    ->label('Harga Coil ($/Kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: 1.1206')
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (float) $state : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromCoil($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('sheet_price')
                                    ->label('Harga Sheet ($/Kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: 1.1456')
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (float) $state : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromSheet($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('coil_price_idr')
                                    ->label('Total Coil (IDR/Kg)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round((float) $state) : null)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('sheet_price_idr')
                                    ->label('Total Sheet (IDR/Kg)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round((float) $state) : null)
                                    ->columnSpan(1),
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
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('plating_cost_kg')
                                    ->label('Plating Cost (Rp / kg)')
                                    ->numeric()
                                    ->default(10000)
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

                                Forms\Components\TextInput::make('overhead_self_percent')
                                    ->label('Overhead Self (%)')
                                    ->numeric()
                                    ->default(14.3)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('tooling_depreciation')
                                    ->label('Die & Jig Depreciation (Rp / pc)')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->placeholder('0')
                                    ->helperText('Biaya depresiasi cetakan/jig per pcs jika ada')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('calculation_summary')
                                    ->hiddenLabel()
                                    ->content(function (Get $get) {
                                        $rate = (float) ($get('exchange_rate') ?? 16000);
                                        $wireUsdKg = (float) ($get('wire_price_usd_kg') ?: ($get('coil_price') ?: 1.66));
                                        $wireIdrKg = round($wireUsdKg * $rate, 2);

                                        $grossGr = (float) ($get('gross_weight_gr') ?? 4.84);
                                        $wirePc = round(($grossGr / 1000) * $wireIdrKg, 4);

                                        $ctrlPct = (float) ($get('material_control_percent') ?? 5);
                                        $ctrlCost = round(($ctrlPct / 100) * $wirePc, 4);

                                        $washerPrice = (float) ($get('washer_price') ?? 198);
                                        $matTotal = round($wirePc + $ctrlCost + $washerPrice, 4);

                                        $nettGr = (float) ($get('nett_weight_gr') ?? 4.4);
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

                                        $ohSelfPct = (float) ($get('overhead_self_percent') ?? 14.3);
                                        $ohCost = round(($ohSelfPct / 100) * $subtotal, 4);
                                        $tooling = (float) ($get('tooling_depreciation') ?? 0);

                                        $partCost = round($subtotal + $ohCost + $tooling, 4);

                                        return new HtmlString("
                                            <div class='rounded-xl border border-primary-200 bg-white p-5 shadow-sm dark:border-primary-800 dark:bg-gray-900'>
                                                <div class='mb-3 flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800'>
                                                    <h3 class='text-sm font-semibold text-primary-950 dark:text-primary-100 flex items-center gap-2'>
                                                        <span>📊</span> Rincian Komponen Biaya Fastener (PT Nitto Alam)
                                                    </h3>
                                                    <span class='text-xs font-medium text-gray-500'>Auto-calculated</span>
                                                </div>
                                                <div class='grid grid-cols-2 md:grid-cols-4 gap-4 text-xs'>
                                                    <div class='rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60'>
                                                        <div class='text-gray-500 dark:text-gray-400'>Wire Cost / pc</div>
                                                        <div class='mt-1 text-sm font-bold text-gray-800 dark:text-gray-200'>Rp ".number_format($wirePc, 2, ',', '.')."</div>
                                                        <div class='text-[10px] text-gray-400'>{$grossGr} gr × Rp ".number_format($wireIdrKg, 0, ',', '.')."/kg</div>
                                                    </div>
                                                    <div class='rounded-lg bg-amber-50 p-2.5 dark:bg-amber-950/30'>
                                                        <div class='text-amber-800 dark:text-amber-300'>Washer & Material Control</div>
                                                        <div class='mt-1 text-sm font-bold text-amber-700 dark:text-amber-400'>Rp ".number_format($ctrlCost + $washerPrice, 2, ',', '.')."</div>
                                                        <div class='text-[10px] text-amber-600'>Washer: Rp {$washerPrice} + Ctrl: Rp ".number_format($ctrlCost, 2)."</div>
                                                    </div>
                                                    <div class='rounded-lg bg-blue-50 p-2.5 dark:bg-blue-950/30'>
                                                        <div class='text-blue-800 dark:text-blue-300'>Total Biaya Material</div>
                                                        <div class='mt-1 text-sm font-bold text-blue-700 dark:text-blue-400'>Rp ".number_format($matTotal, 2, ',', '.')."</div>
                                                        <div class='text-[10px] text-blue-600'>Wire + Washer + Ctrl</div>
                                                    </div>
                                                    <div class='rounded-lg bg-purple-50 p-2.5 dark:bg-purple-950/30'>
                                                        <div class='text-purple-800 dark:text-purple-300'>Total Biaya Proses</div>
                                                        <div class='mt-1 text-sm font-bold text-purple-700 dark:text-purple-400'>Rp ".number_format($procTotal, 2, ',', '.')."</div>
                                                        <div class='text-[10px] text-purple-600'>Heading + Roll + Wash + Plate</div>
                                                    </div>
                                                </div>
                                                <div class='mt-3 grid grid-cols-2 md:grid-cols-2 gap-4 text-xs pt-3 border-t border-gray-100 dark:border-gray-800'>
                                                    <div>
                                                        <span class='text-gray-500'>Subtotal (Mat'l + Proc):</span>
                                                        <span class='font-semibold text-gray-800 dark:text-gray-200 ml-1'>Rp ".number_format($subtotal, 2, ',', '.')."</span>
                                                    </div>
                                                    <div>
                                                        <span class='text-gray-500'>Overhead Self ({$ohSelfPct}%):</span>
                                                        <span class='font-semibold text-gray-800 dark:text-gray-200 ml-1'>Rp ".number_format($ohCost, 2, ',', '.')."</span>
                                                    </div>
                                                </div>
                                                <div class='mt-3 flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-3 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800'>
                                                    <div class='text-sm font-bold text-emerald-900 dark:text-emerald-100'>FINAL FASTENER COST:</div>
                                                    <div class='text-lg font-black text-emerald-700 dark:text-emerald-300'>Rp ".number_format($partCost, 2, ',', '.').'</div>
                                                </div>
                                            </div>
                                        ');
                                    })
                                    ->columnSpanFull(),
                            ])->columns(3),

                        // SECTION 4: Catatan & Status (Collapsible)
                        Forms\Components\Section::make('4. Catatan & Status (Opsional)')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
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
                            ])->columns(1),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('part_number')
                    ->label('Part Number')
                    ->weight('bold')
                    ->placeholder('-')
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
                    ->label('Thickness / Dia (mm)')
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

                Tables\Columns\TextColumn::make('hr_steel_usd')
                    ->label('HR Dolar / MT')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, Fastener $record) => static::formatUsd($state, $record->currency_code))
                    ->weight('bold')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cr_steel_usd')
                    ->label('CR Dolar / MT')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, Fastener $record) => static::formatUsd($state, $record->currency_code))
                    ->weight('bold')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Perubahan Dolar Rupiah')
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

                Tables\Columns\TextColumn::make('coil_price')
                    ->label('Coil (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, Fastener $record) => static::formatUsd($state, $record->currency_code))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('coil_price_idr')
                    ->label('Coil (IDR)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sheet_price')
                    ->label('Sheet (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, Fastener $record) => static::formatUsd($state, $record->currency_code))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sheet_price_idr')
                    ->label('Sheet (IDR)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('part_cost')
                    ->label('Total Part Cost')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                    ->weight('black')
                    ->color('success')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Draft' => 'warning',
                        default => 'gray',
                    }),

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

                Tables\Columns\TextColumn::make('overhead_self_cost')
                    ->label('(5) O/H Self (Rp)')
                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Kolom gap untuk kompatibilitas test (toggleable hidden by default)
                Tables\Columns\TextColumn::make('gap_steel_usd')
                    ->label('Gap/MT (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, Fastener $record) => static::formatUsd($state, $record->currency_code))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('gap_steel_kg_usd')
                    ->label('Gap/Kg (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, Fastener $record) => static::formatUsd($state, $record->currency_code))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('drawing_reference')
                    ->label('Drawing Ref')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('eci_number')
                    ->label('ECI No')
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
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('quarter')
                    ->label('Filter Kuartal')
                    ->options(function () {
                        $counts = Fastener::query()
                            ->selectRaw('quarter, count(*) as total')
                            ->groupBy('quarter')
                            ->pluck('total', 'quarter');

                        return [
                            'Q1' => 'Q1 (Januari - Maret) ('.($counts['Q1'] ?? 0).' data)',
                            'Q2' => 'Q2 (April - Juni) ('.($counts['Q2'] ?? 0).' data)',
                            'Q3' => 'Q3 (Juli - September) ('.($counts['Q3'] ?? 0).' data)',
                            'Q4' => 'Q4 (Oktober - Desember) ('.($counts['Q4'] ?? 0).' data)',
                        ];
                    })
                    ->multiple(),

                Tables\Filters\SelectFilter::make('supplier_name')
                    ->label('Filter Supplier')
                    ->options(function () {
                        $counts = Fastener::query()
                            ->whereNotNull('supplier_name')
                            ->selectRaw('supplier_name, count(*) as total')
                            ->groupBy('supplier_name')
                            ->pluck('total', 'supplier_name');

                        $supplierMap = Supplier::query()
                            ->orderBy('name')
                            ->pluck('name', 'name')
                            ->toArray();

                        $options = [];
                        foreach ($counts as $name => $total) {
                            $options[$name] = "{$name} ({$total} data)";
                        }

                        foreach ($supplierMap as $name) {
                            if (! isset($options[$name])) {
                                $options[$name] = "{$name} (0 data)";
                            }
                        }

                        return $options;
                    })
                    ->searchable()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Filter Tahun')
                    ->options(function () {
                        $counts = Fastener::query()
                            ->selectRaw('year, count(*) as total')
                            ->groupBy('year')
                            ->pluck('total', 'year');

                        $current = (int) date('Y');
                        $dbYears = Fastener::distinct()->pluck('year')->filter()->toArray();
                        $rangeYears = range($current + 2, $current - 2);
                        $allYears = array_unique(array_merge($dbYears, $rangeYears));
                        rsort($allYears);

                        $options = [];
                        foreach ($allYears as $y) {
                            $total = $counts[$y] ?? 0;
                            $options[$y] = "Tahun {$y} ({$total} data)";
                        }

                        return $options;
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options(function () {
                        $counts = Fastener::query()
                            ->selectRaw('status, count(*) as total')
                            ->groupBy('status')
                            ->pluck('total', 'status');

                        return [
                            'Active' => 'Active ('.($counts['Active'] ?? 0).' data)',
                            'Draft' => 'Draft ('.($counts['Draft'] ?? 0).' data)',
                        ];
                    }),
            ])
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->button()
                    ->label(fn () => 'Filter Data ('.Fastener::count().' Data)')
            )
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\Action::make('view_sheet')
                    ->label('Lembar Kalkulasi')
                    ->tooltip('Tampilkan lembar rincian kalkulasi format resmi Excel')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->button()
                    ->size(ActionSize::Small)
                    ->slideOver()
                    ->modalHeading(fn (Fastener $record) => "Lembar Perhitungan Fastener Cost: {$record->part_number} - {$record->part_name}")
                    ->modalWidth(MaxWidth::FiveExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Fastener $record) => view('filament.resources.fasteners.sheet-modal', ['record' => $record])),
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
