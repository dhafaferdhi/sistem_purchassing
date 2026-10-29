<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StampingPartResource\Pages;
use App\Models\Currency;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\SubAssemblyPart;
use App\Models\Supplier;
use App\Services\ExcelExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class StampingPartResource extends Resource
{
    protected static ?string $model = StampingPart::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Stamping Part';

    protected static ?int $navigationSort = 3;

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

        $cleanSpec = $spec ? StampingPart::cleanSteelSpec($spec) : null;
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

        $thickness = $get('double_thickness');
        $steel = null;
        if (! empty($thickness)) {
            $exactQuery = clone $query;
            $steel = $exactQuery->where('double_thickness', $thickness)->latest('id')->first();
        }

        if (! $steel) {
            $steel = $query->latest('id')->first();
        }

        if (! $steel && $cleanSpec && $supplierId) {
            $fallbackQuery = MaterialSteel::where('year', $year)
                ->where('quarter', $quarter)
                ->where(function ($q) use ($spec, $cleanSpec) {
                    $q->where('steel_spec', $cleanSpec)
                        ->orWhere('steel_spec', $spec)
                        ->orWhere('steel_spec', 'like', $cleanSpec.'%');
                });
            if (! empty($thickness)) {
                $exactFallback = clone $fallbackQuery;
                $steel = $exactFallback->where('double_thickness', $thickness)->latest('id')->first();
            }
            if (! $steel) {
                $steel = $fallbackQuery->latest('id')->first();
            }
        }

        if ($steel) {
            $set('coil_price', (float) $steel->coil_price);
            $set('sheet_price', (float) $steel->sheet_price);

            if ($steel->exchange_rate && (float) $steel->exchange_rate > 0) {
                $set('exchange_rate', (float) $steel->exchange_rate);
            }

            if (empty($get('double_thickness')) && ! empty($steel->double_thickness)) {
                $set('double_thickness', $steel->double_thickness);
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

        $prev = StampingPart::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $steelSpec);

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
            }
            if (! empty($prev['size']) && empty($get('size'))) {
                $set('size', $prev['size']);
            }
            $berat = (float) ($prev['berat'] ?? 0);
            if ($berat <= 0.0) {
                $thk = (float) ($prev['double_thickness'] ?? $get('double_thickness') ?? 0);
                $wide = (float) ($prev['wide'] ?? $get('wide') ?? 0);
                $pitch = (float) ($prev['pitch'] ?? $get('pitch') ?? 0);
                $yr = (float) ($prev['yield_rate'] ?? $get('yield_rate') ?? 1);
                $yr = $yr > 0 ? $yr : 1;
                if ($thk > 0 && $wide > 0 && $pitch > 0) {
                    $berat = round(($thk * $wide * $pitch * 7.85) / (1000000 * $yr), 4);
                }
            }
            if ($berat > 0 && empty($get('berat'))) {
                $set('berat', $berat);
            }
            if (isset($prev['wide']) && empty($get('wide'))) {
                $set('wide', $prev['wide']);
            }
            if (isset($prev['pitch']) && empty($get('pitch'))) {
                $set('pitch', $prev['pitch']);
            }
            if (isset($prev['yield_rate']) && empty($get('yield_rate'))) {
                $set('yield_rate', $prev['yield_rate']);
            }
            if (isset($prev['scrap_weight']) && empty($get('scrap_weight'))) {
                $set('scrap_weight', $prev['scrap_weight']);
            }
            if (isset($prev['scrap_price_per_kg']) && empty($get('scrap_price_per_kg'))) {
                $set('scrap_price_per_kg', $prev['scrap_price_per_kg']);
            }
            if (! empty($prev['process_name']) && empty($get('process_name'))) {
                $set('process_name', $prev['process_name']);
            }
            if (isset($prev['process_man_hour']) && empty($get('process_man_hour'))) {
                $set('process_man_hour', $prev['process_man_hour']);
            }
            if (isset($prev['process_charge']) && empty($get('process_charge'))) {
                $set('process_charge', $prev['process_charge']);
            }
            if (isset($prev['overhead_supplied_percent']) && empty($get('overhead_supplied_percent'))) {
                $set('overhead_supplied_percent', $prev['overhead_supplied_percent']);
            }
            if (isset($prev['overhead_self_percent']) && empty($get('overhead_self_percent'))) {
                $set('overhead_self_percent', $prev['overhead_self_percent']);
            }

            if (! empty($prev['exchange_rate']) && (float) $prev['exchange_rate'] > 0) {
                $set('exchange_rate', (float) $prev['exchange_rate']);
            }

            $spec = $get('steel_spec') ?: ($prev['steel_spec'] ?? null);
            $steelType = StampingPart::determineSteelType($spec);

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

            if ($newAdj != 0.0) {
                $appliedKg = round($newAdj / 1000, 4);
                if ($prevCoil > 0) {
                    $set('coil_price', round($prevCoil + $appliedKg, 4));
                }
                if ($prevSheet > 0) {
                    $set('sheet_price', round($prevSheet + $appliedKg, 4));
                }
                $set('gap_steel_usd', $newAdj);
            } else {
                if ($prevCoil > 0) {
                    $set('coil_price', $prevCoil);
                }
                if ($prevSheet > 0) {
                    $set('sheet_price', $prevSheet);
                }
                $set('gap_steel_usd', 0);
                $set('gap_steel_kg_usd', 0);
            }

            static::recalculateFormPrices($get, $set);
        } else {
            $set('prev_record_found', false);
            $set('prev_coil_price', null);
            $set('prev_sheet_price', null);
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

    public static function updatePricesFromCoil(Get $get, Set $set): void
    {
        $coil = (float) ($get('coil_price') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);

        $set('coil_price_idr', round($coil * $rate));
    }

    public static function updatePricesFromSheet(Get $get, Set $set): void
    {
        $sheet = (float) ($get('sheet_price') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);

        $set('sheet_price_idr', round($sheet * $rate));
    }

    public static function calculateWeightFromDimensions(Get $get, Set $set): void
    {
        $thk = (float) ($get('double_thickness') ?? 0);
        $wide = (float) ($get('wide') ?? 0);
        $pitch = (float) ($get('pitch') ?? 0);
        $yr = (float) ($get('yield_rate') ?: 1);
        $yr = $yr > 0 ? $yr : 1;

        if ($thk > 0 && $wide > 0 && $pitch > 0) {
            // Rumus Excel Posmi / Fuji Seat: (Tebal x Wide x Pitch x 7.85) / (1.000.000 x Yield)
            $weight = round(($thk * $wide * $pitch * 7.85) / (1000000 * $yr), 4);
            $set('berat', $weight);
        }
    }

    public static function updatePricesFromHrCr(Get $get, Set $set, ?string $source = null): void
    {
        $spec = $get('steel_spec');
        $steelType = StampingPart::determineSteelType($spec);

        $hrMt = $get('hr_steel_usd');
        $crMt = $get('cr_steel_usd');

        // Tentukan nilai MT yang diterapkan berdasarkan input spesifik user & tipe baja
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

        $prevData = StampingPart::findPreviousQuarterData($quarter, $year, $supplierId ? (int) $supplierId : null, $spec);

        if ($prevData) {
            if (! empty($prevData['steel_spec'])) {
                $set('steel_spec', $prevData['steel_spec']);
            }
            if (! empty($prevData['double_thickness'])) {
                $set('double_thickness', $prevData['double_thickness']);
            }
            if (! empty($prevData['size'])) {
                $set('size', $prevData['size']);
            }
            $berat = (float) ($prevData['berat'] ?? 0);
            if ($berat <= 0.0) {
                $thk = (float) ($prevData['double_thickness'] ?? 0);
                $wide = (float) ($prevData['wide'] ?? 0);
                $pitch = (float) ($prevData['pitch'] ?? 0);
                $yr = (float) ($prevData['yield_rate'] ?? 1);
                $yr = $yr > 0 ? $yr : 1;
                if ($thk > 0 && $wide > 0 && $pitch > 0) {
                    $berat = round(($thk * $wide * $pitch * 7.85) / (1000000 * $yr), 4);
                }
            }
            if ($berat > 0) {
                $set('berat', $berat);
            }
            $set('gap_steel_usd', 0);
            $set('gap_steel_kg_usd', 0);
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
            if (! empty($prevData['wide'])) {
                $set('wide', $prevData['wide']);
            }
            if (! empty($prevData['pitch'])) {
                $set('pitch', $prevData['pitch']);
            }
            if (! empty($prevData['yield_rate'])) {
                $set('yield_rate', $prevData['yield_rate']);
            }
            if (isset($prevData['scrap_weight']) && (float) $prevData['scrap_weight'] > 0) {
                $set('scrap_weight', $prevData['scrap_weight']);
            }
            if (isset($prevData['scrap_price_per_kg'])) {
                $set('scrap_price_per_kg', $prevData['scrap_price_per_kg']);
            }
            if (! empty($prevData['process_name'])) {
                $set('process_name', $prevData['process_name']);
            }
            if (isset($prevData['process_man_hour'])) {
                $set('process_man_hour', $prevData['process_man_hour']);
            }
            if (isset($prevData['process_charge'])) {
                $set('process_charge', $prevData['process_charge']);
            }
            if (isset($prevData['overhead_supplied_percent'])) {
                $set('overhead_supplied_percent', $prevData['overhead_supplied_percent']);
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
            $info = StampingPart::getPreviousQuarterInfo($quarter, $year);
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
                        // SECTION 1: Spesifikasi Part & Periode
                        Forms\Components\Section::make('1. Spesifikasi Part & Periode')
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
                                        $latestYear = StampingPart::latest('year')->value('year');

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
                                        modifyQueryUsing: function (Builder $query, Get $get, ?StampingPart $record) {
                                            $selectedId = $get('supplier_id') ?? $record?->supplier_id;
                                            $query->where(function ($q) use ($selectedId) {
                                                $q->whereIn('name', ['PT HSSI', 'PT USCI'])
                                                    ->orWhere('name', 'like', '%HSSI%')
                                                    ->orWhere('name', 'like', '%USCI%');
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
                                            ->placeholder('Contoh: PT Krakatau Steel Tbk')
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
                                    ->placeholder('Contoh: 72388-X7V00-A')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('part_name')
                                    ->label('Part Name')
                                    ->placeholder('Contoh: BRKT, SEAT BELT ANCHOR LH')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('part_no_to_compare')
                                    ->label('Part No. to Compare')
                                    ->placeholder('Contoh: 72388-X7V00')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('model_name')
                                    ->label('Model')
                                    ->placeholder('Contoh: D26A')
                                    ->default('D26A')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('monthly_volume')
                                    ->label('Monthly Volume (Pcs)')
                                    ->numeric()
                                    ->placeholder('Contoh: 16500')
                                    ->default(16500)
                                    ->suffix('pcs/bln')
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('quotation_date')
                                    ->label('Tanggal Penawaran / Revisi')
                                    ->placeholder('YYYY-MM-DD')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('drawing_reference')
                                    ->label('Drawing Reference')
                                    ->placeholder('Contoh: Mass Prod. Drwg')
                                    ->default('Mass Prod. Drwg')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('eci_number')
                                    ->label('ECI Number')
                                    ->placeholder('Contoh: F1225')
                                    ->default('F1225')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('steel_spec')
                                    ->label('Spesifikasi Baja (Spec)')
                                    ->placeholder('Contoh: SPH270C-OD, SPC270C, SPH440-OD')
                                    ->datalist(function () {
                                        $standardSpecs = [
                                            'SPH270C-OD', 'SPH440-OD', 'SPH590-OD',
                                            'SPC270C', 'SPC440', 'SPC590',
                                            'SPC440HR', 'SPC590DU', 'NSC590B', 'SPC980',
                                        ];
                                        $dbSpecs = MaterialSteel::distinct()->pluck('steel_spec')->filter()->toArray();

                                        return array_values(array_unique(array_merge($standardSpecs, $dbSpecs)));
                                    })
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if ($state) {
                                            $clean = StampingPart::cleanSteelSpec($state);
                                            if ($clean !== $state) {
                                                $set('steel_spec', $clean);
                                            }
                                        }
                                        $found = static::lookupCurrentQuarterSteel($get, $set, true);
                                        if (! $found) {
                                            static::lookupPreviousQuarterRecord($get, $set);
                                        }
                                    })
                                    ->dehydrateStateUsing(fn (?string $state) => StampingPart::cleanSteelSpec($state))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('double_thickness')
                                    ->label('Thickness (mm)')
                                    ->placeholder('Contoh: 1.2 atau 2.0')
                                    ->datalist(function (Get $get) {
                                        $spec = $get('steel_spec');
                                        $specMap = [
                                            'SPH270C-OD' => ['1.6', '1.8', '2.0', '2.3', '2.6', '3.2', '4.0', '5.0'],
                                            'SPH440-OD' => ['1.6', '1.8', '2.0', '2.3', '2.6', '3.2', '4.0'],
                                            'SPH590-OD' => ['1.6', '1.8', '2.0', '2.3', '2.6', '2.9', '3.2', '4.0'],
                                            'SPC270C' => ['0.5', '0.6', '0.8', '0.9', '1.0', '1.2', '1.4'],
                                            'SPC440' => ['0.6', '0.8', '1.0', '1.2', '1.4'],
                                            'SPC440HR' => ['0.6'],
                                            'SPC590' => ['0.6', '0.8', '0.9', '1.0', '1.2', '1.4', '1.6', '1.8'],
                                            'SPC590DU' => ['1.2'],
                                            'NSC590B' => ['1.4'],
                                            'SPC980' => ['1.4', '1.6', '1.8', '2.0'],
                                        ];

                                        if ($spec && isset($specMap[$spec])) {
                                            return $specMap[$spec];
                                        }

                                        return ['0.5', '0.6', '0.8', '0.9', '1.0', '1.2', '1.4', '1.6', '1.8', '2.0', '2.3', '2.6', '2.9', '3.2', '4.0', '5.0'];
                                    })
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $clean = trim(preg_replace('/[a-zA-Z\s]+/', '', $state));
                                            $set('double_thickness', $clean);

                                            if (empty($get('size'))) {
                                                $spec = (string) ($get('steel_spec') ?? '');
                                                $width = (str_starts_with($spec, 'SPC') || str_starts_with($spec, 'NSC')) ? 1251 : 1219;
                                                $set('size', "{$clean} x {$width} x C");
                                            }
                                        }
                                        static::lookupCurrentQuarterSteel($get, $set);
                                        static::lookupPreviousQuarterRecord($get, $set);
                                        static::calculateWeightFromDimensions($get, $set);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('size')
                                    ->label('Size / Dimensi (Opsional)')
                                    ->placeholder('Contoh: 1.6 x 1219 x C')
                                    ->datalist(function (Get $get) {
                                        $thk = $get('double_thickness');
                                        $spec = (string) ($get('steel_spec') ?? '');
                                        $width = (str_starts_with($spec, 'SPC') || str_starts_with($spec, 'NSC')) ? 1251 : 1219;
                                        if ($thk) {
                                            return ["{$thk} x {$width} x C"];
                                        }

                                        return ['1.6 x 1219 x C', '2.6 x 1219 x C', '0.8 x 1251 x C', '1.2 x 1251 x C'];
                                    })
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state && empty($get('double_thickness'))) {
                                            if (preg_match('/^([0-9]+(?:\.[0-9]+)?)/', trim($state), $matches)) {
                                                $set('double_thickness', $matches[1]);
                                                static::lookupCurrentQuarterSteel($get, $set);
                                                static::lookupPreviousQuarterRecord($get, $set);
                                                static::calculateWeightFromDimensions($get, $set);
                                            }
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('used_in_sub_assembly_info')
                                    ->hiddenLabel()
                                    ->visible(fn (Get $get, ?StampingPart $record) => ! empty($get('part_number')) || ($record && $record->part_number))
                                    ->content(function (Get $get, ?StampingPart $record) {
                                        $partNo = $get('part_number') ?? $record?->part_number;
                                        if (empty($partNo)) {
                                            return null;
                                        }
                                        $clean = trim((string) $partNo);
                                        $year = (int) ($get('year') ?? $record?->year ?? date('Y'));
                                        $quarter = $get('quarter') ?? $record?->quarter ?? 'Q1';

                                        $subAssemblies = SubAssemblyPart::where('year', $year)
                                            ->where('quarter', $quarter)
                                            ->get()
                                            ->filter(function ($sa) use ($clean) {
                                                if (! is_array($sa->material_breakdown)) {
                                                    return false;
                                                }
                                                foreach ($sa->material_breakdown as $item) {
                                                    $ip = trim((string) ($item['part_number'] ?? $item['part_no'] ?? ''));
                                                    if ($ip !== '' && (strcasecmp($ip, $clean) === 0 || str_replace([' ', '-'], '', $ip) === str_replace([' ', '-'], '', $clean))) {
                                                        return true;
                                                    }
                                                }

                                                return false;
                                            });

                                        if ($subAssemblies->isEmpty()) {
                                            return null;
                                        }

                                        $itemsHtml = '';
                                        foreach ($subAssemblies as $sa) {
                                            $itemsHtml .= "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 mr-2 mb-1'>🔗 [{$sa->part_number}] {$sa->part_name} ({$sa->supplier_name})</span>";
                                        }

                                        return new HtmlString("
                                            <div class='rounded-lg bg-purple-50 p-3 border border-purple-200 dark:bg-purple-950/30 dark:border-purple-800'>
                                                <div class='text-xs font-bold text-purple-900 dark:text-purple-200 flex items-center gap-1.5 mb-1'>
                                                    <span>🧩</span> Komponen ini digunakan pada Sub-Assembly ($quarter $year):
                                                </div>
                                                <div class='flex flex-wrap items-center mt-1'>
                                                    {$itemsHtml}
                                                </div>
                                            </div>
                                        ");
                                    })
                                    ->columnSpanFull(),

                                // Hidden fields untuk integritas data
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
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateFormPrices($get, $set))
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('exchange_rate_fluctuation')
                                    ->label('Exchg Fluctuation (Faktor Pengali Kurs)')
                                    ->numeric()
                                    ->step(0.000001)
                                    ->default(1.0)
                                    ->placeholder('1.0')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateFormPrices($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('coil_price')
                                    ->label('Harga Coil ($/Kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: 1.1206')
                                    ->required(fn (Get $get) => empty($get('sheet_price')))
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
                                    ->required(fn (Get $get) => empty($get('coil_price')))
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

                        // SECTION 3: Dimensi Blank, Scrap & Biaya Proses Mesin (Posmi Excel Breakdown)
                        Forms\Components\Section::make('3. Dimensi Blank, Scrap & Biaya Proses Mesin')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema([
                                Forms\Components\TextInput::make('wide')
                                    ->label('Wide / Lebar Blank (mm)')
                                    ->numeric()
                                    ->placeholder('Contoh: 390')
                                    ->suffix('mm')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::calculateWeightFromDimensions($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('pitch')
                                    ->label('Pitch Blank (mm)')
                                    ->numeric()
                                    ->placeholder('Contoh: 148')
                                    ->suffix('mm')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::calculateWeightFromDimensions($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('yield_rate')
                                    ->label('Yield / Stroke (Pcs)')
                                    ->numeric()
                                    ->default(1)
                                    ->placeholder('Contoh: 1 atau 2')
                                    ->suffix('pcs')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::calculateWeightFromDimensions($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('berat')
                                    ->label('Berat Material Gross (Kg)')
                                    ->placeholder('Contoh: 0.5890')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->suffix('kg')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('scrap_weight')
                                    ->label('Berat Scrap (Kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->placeholder('Contoh: 0.2780')
                                    ->default(0)
                                    ->suffix('kg')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('scrap_price_per_kg')
                                    ->label('Harga Scrap (Rp / kg)')
                                    ->numeric()
                                    ->placeholder('Contoh: -1500')
                                    ->default(-1500)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('process_name')
                                    ->label('Nama Proses Mesin')
                                    ->placeholder('Contoh: 1. PROGRESSIVE 250T')
                                    ->default('1. PROGRESSIVE 250T')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('process_man_hour')
                                    ->label('SPH / Man-Hour (Detik / pc)')
                                    ->numeric()
                                    ->default(9)
                                    ->suffix('sec/pc')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('process_charge')
                                    ->label('Process Charge (Rp / detik)')
                                    ->numeric()
                                    ->default(45)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('overhead_supplied_percent')
                                    ->label('O/H Mat\'l Supplied (%)')
                                    ->numeric()
                                    ->default(11)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('overhead_self_percent')
                                    ->label('O/H Mat\'l Self (%)')
                                    ->numeric()
                                    ->default(4)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('transportation_cost')
                                    ->label('(6) Transportation (Rp / pc)')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->placeholder('0')
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('packing_cost')
                                    ->label('(7) Packing (Rp / pc)')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->placeholder('0')
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

                                Forms\Components\Repeater::make('tooling_details')
                                    ->label('Rincian Investasi Tooling & Amortisasi (Die & Jig Breakdown)')
                                    ->schema([
                                        Forms\Components\TextInput::make('contents')
                                            ->label('Contents / Item Die')
                                            ->placeholder('Misal: DIE STAMPING / WELD JIG')
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('total_cost_krp')
                                            ->label('Total Cost (KRp)')
                                            ->numeric()
                                            ->placeholder('0')
                                            ->columnSpan(1),
                                        Forms\Components\TextInput::make('monthly_volume')
                                            ->label('Monthly Volume (pcs)')
                                            ->numeric()
                                            ->placeholder('16500')
                                            ->columnSpan(1),
                                        Forms\Components\TextInput::make('depreciation_per_pc')
                                            ->label('Depr / pc (Rp)')
                                            ->numeric()
                                            ->placeholder('0')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(5)
                                    ->collapsible()
                                    ->collapsed()
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('calculation_summary')
                                    ->hiddenLabel()
                                    ->content(function (Get $get) {
                                        $thk = (float) ($get('double_thickness') ?? 0);
                                        $wide = (float) ($get('wide') ?? 0);
                                        $pitch = (float) ($get('pitch') ?? 0);
                                        $yieldRate = (float) ($get('yield_rate') ?: 1);
                                        $yieldRate = $yieldRate > 0 ? $yieldRate : 1;

                                        $berat = (float) ($get('berat') ?? 0);
                                        if ($berat <= 0.0 && $thk > 0 && $wide > 0 && $pitch > 0) {
                                            $berat = round(($thk * $wide * $pitch * 7.85) / (1000000 * $yieldRate), 4);
                                        }

                                        $rate = (float) ($get('exchange_rate') ?? 16000);
                                        $coilIdr = (float) ($get('coil_price_idr') ?? round((float) ($get('coil_price') ?? 0) * $rate));
                                        $sheetIdr = (float) ($get('sheet_price_idr') ?? round((float) ($get('sheet_price') ?? 0) * $rate));
                                        $steelRate = $sheetIdr > 0 ? $sheetIdr : ($coilIdr > 0 ? $coilIdr : round((float) ($get('sheet_price') ?? $get('coil_price') ?? 0) * $rate));

                                        $grossMatCost = $berat > 0 && $steelRate > 0 ? round($berat * $steelRate, 2) : 0;
                                        $scrapWeight = $berat > 0 ? (float) ($get('scrap_weight') ?? 0) : 0;
                                        $scrapPrice = (float) ($get('scrap_price_per_kg') ?? -1500);
                                        $scrapCost = round($scrapWeight * $scrapPrice, 2);
                                        $matTotal = round($grossMatCost + $scrapCost, 2);

                                        $manHour = (float) ($get('process_man_hour') ?? 9);
                                        $charge = (float) ($get('process_charge') ?? 45);
                                        $procTotal = round(($manHour * $charge) / $yieldRate, 2);
                                        $subtotal = round($matTotal + $procTotal, 2);

                                        $ohSuppliedPct = (float) ($get('overhead_supplied_percent') ?? 11);
                                        $ohSuppliedCost = round(($ohSuppliedPct / 100) * $procTotal, 2);

                                        $ohSelfPct = (float) ($get('overhead_self_percent') ?? 4);
                                        $ohSelfCost = round(($ohSelfPct / 100) * $matTotal, 2);

                                        $transCost = (float) ($get('transportation_cost') ?? 0);
                                        $packCost = (float) ($get('packing_cost') ?? 0);

                                        // (8) Part Cost (excluding tooling cost)
                                        $partCostExclTooling = round($subtotal + $ohSuppliedCost + $ohSelfCost + $transCost + $packCost, 2);

                                        $toolingDepr = (float) ($get('tooling_depreciation') ?? 0);
                                        $finalPartCost = round($partCostExclTooling + $toolingDepr, 2);

                                        return new HtmlString("
                                            <div class='rounded-xl border border-primary-200 bg-white p-5 shadow-sm dark:border-primary-800 dark:bg-gray-900'>
                                                <div class='mb-3 flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800'>
                                                    <h3 class='text-sm font-semibold text-primary-950 dark:text-primary-100 flex items-center gap-2'>
                                                        <span>📊</span> Rincian Komponen Biaya Part (Format Breakdown Excel PT Posmi Steel)
                                                    </h3>
                                                    <span class='text-xs font-mono text-gray-400'>9 Lines Breakdown</span>
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
                                                                <td class='px-3 py-2 text-gray-500'>Gross Material (Rp ".number_format($grossMatCost, 0, ',', '.').') - Scrap Recovery (Rp '.number_format(abs($scrapCost), 0, ',', '.').")</td>
                                                                <td class='px-3 py-2 text-right font-semibold text-blue-600 dark:text-blue-400'>Rp ".number_format($matTotal, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 font-medium text-gray-900 dark:text-gray-100'>(2) Process Total</td>
                                                                <td class='px-3 py-2 text-gray-500'>SPH ({$manHour}) × Charge ({$charge}) / Yield ({$yieldRate})</td>
                                                                <td class='px-3 py-2 text-right font-semibold text-purple-600 dark:text-purple-400'>Rp ".number_format($procTotal, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='bg-gray-50/60 dark:bg-gray-800/40 font-semibold'>
                                                                <td class='px-3 py-2 text-gray-800 dark:text-gray-200'>(3) Sub Total</td>
                                                                <td class='px-3 py-2 text-gray-500'>Komponen (1) Material + (2) Process</td>
                                                                <td class='px-3 py-2 text-right text-gray-900 dark:text-gray-100'>Rp ".number_format($subtotal, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(4) O/H Mat'l Supplied</td>
                                                                <td class='px-3 py-2 text-gray-500'>{$ohSuppliedPct}% × Total Process</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($ohSuppliedCost, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(5) O/H Mat'l Self</td>
                                                                <td class='px-3 py-2 text-gray-500'>{$ohSelfPct}% × Total Material</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($ohSelfCost, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(6) Transportation</td>
                                                                <td class='px-3 py-2 text-gray-500'>Biaya Angkut / Logistik Supplier</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($transCost, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(7) Packing</td>
                                                                <td class='px-3 py-2 text-gray-500'>Biaya Pengemasan Part</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($packCost, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='bg-amber-50/60 dark:bg-amber-950/20 font-bold'>
                                                                <td class='px-3 py-2 text-amber-900 dark:text-amber-200'>(8) Part Cost (Excl. Tooling)</td>
                                                                <td class='px-3 py-2 text-amber-800/80 dark:text-amber-300/80 font-normal'>Total (3) + (4) + (5) + (6) + (7)</td>
                                                                <td class='px-3 py-2 text-right text-amber-900 dark:text-amber-200'>Rp ".number_format($partCostExclTooling, 2, ',', '.')."</td>
                                                            </tr>
                                                            <tr class='hover:bg-gray-50/50'>
                                                                <td class='px-3 py-2 text-gray-700 dark:text-gray-300'>(9) Die & Jig Depreciation</td>
                                                                <td class='px-3 py-2 text-gray-500'>Amortisasi Tooling per pcs</td>
                                                                <td class='px-3 py-2 text-right text-gray-800 dark:text-gray-200'>Rp ".number_format($toolingDepr, 2, ',', '.')."</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <div class='mt-4 flex items-center justify-between rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 px-5 py-3.5 text-white shadow'>
                                                    <div>
                                                        <div class='text-xs font-medium uppercase tracking-wider text-emerald-100'>TOTAL PART COST (Rp / pc)</div>
                                                        <div class='text-xs text-emerald-100/80'>Baris (8) Part Cost Excl. Tooling + (9) Die & Jig Depreciation</div>
                                                    </div>
                                                    <div class='text-2xl font-black tracking-tight'>Rp ".number_format($finalPartCost, 2, ',', '.').'</div>
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
                                    ->placeholder('Catatan riwayat perubahan gambar kerja / alterasi spesifikasi...')
                                    ->rows(2)
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('etc_contents')
                                    ->label('Etc. Contents')
                                    ->placeholder('Catatan konten tambahan lainnya...')
                                    ->rows(2)
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('comments')
                                    ->label('Comments (Catatan Penawaran Supplier)')
                                    ->placeholder('1. Part price based on standard production...')
                                    ->rows(2)
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('remarks')
                                    ->label('Remarks (Catatan Term & Ketentuan)')
                                    ->placeholder('1. Exchange Rate fluctuation term...')
                                    ->rows(2)
                                    ->columnSpan(1),

                                Forms\Components\Select::make('part_id')
                                    ->label('Link Item Part Master (Opsional)')
                                    ->relationship('part', 'part_name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Pilih part terkait jika ada')
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
                                    ->label('Catatan Kontrak / Internal')
                                    ->placeholder('Misal: Berdasarkan surat kesepakatan harga No. SKH-2026/SP-01...')
                                    ->rows(2)
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
            ->toggleColumnsTriggerAction(fn (Tables\Actions\Action $action) => $action->hidden())
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
                ->state(fn (StampingPart $record) => "{$record->year} - {$record->quarter}")
                ->color(fn (StampingPart $record): string => match ($record->quarter) {
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
                ->sortable()
                ->description(fn (StampingPart $record) => $record->part_no_to_compare ? "Comp: {$record->part_no_to_compare}" : null),

            Tables\Columns\TextColumn::make('part_no_to_compare')
                ->label('Part No (Compare)')
                ->placeholder('-')
                ->wrapHeader()
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('quotation_date')
                ->label('Quotation Date')
                ->date('d M Y')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('part_name')
                ->label('Part Name')
                ->placeholder('-')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('steel_spec')
                ->label('Spec')
                ->formatStateUsing(fn ($state) => StampingPart::cleanSteelSpec($state))
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('double_thickness')
                ->label('Thickness (mm)')
                ->wrapHeader()
                ->formatStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return '-';
                    }

                    return trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $state));
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

            Tables\Columns\TextColumn::make('coil_price')
                ->label('Coil ($)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state, StampingPart $record) => static::formatUsd($state, $record->currency_code))
                ->alignRight()
                ->sortable(),

            Tables\Columns\TextColumn::make('coil_price_idr')
                ->label('Coil (IDR)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                ->alignRight()
                ->sortable(),

            Tables\Columns\TextColumn::make('sheet_price')
                ->label('Sheet ($)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state, StampingPart $record) => static::formatUsd($state, $record->currency_code))
                ->alignRight()
                ->sortable(),

            Tables\Columns\TextColumn::make('sheet_price_idr')
                ->label('Sheet (IDR)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                ->alignRight()
                ->sortable(),

            Tables\Columns\TextColumn::make('part_cost')
                ->label('Total Cost (Rp)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->description(fn (StampingPart $record) => $record->part_cost_excluding_tooling > 0 ? 'Excl: Rp '.number_format((float) $record->part_cost_excluding_tooling, 0, ',', '.') : null)
                ->weight('black')
                ->color('success')
                ->alignRight()
                ->sortable(),

            // Kolom rincian Excel opsional (toggleable hidden by default)
            Tables\Columns\TextColumn::make('gross_material_cost')
                ->label('Gross Mat (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('scrap_cost')
                ->label('Scrap (Rp)')
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
                ->label('(6) Transportation (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('packing_cost')
                ->label('(7) Packing (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('part_cost_excluding_tooling')
                ->label('(8) Cost Excl. Tooling (Rp)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('wide')
                ->label('Wide (mm)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? number_format((float) $state, 1, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('pitch')
                ->label('Pitch (mm)')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? number_format((float) $state, 1, ',', '.') : '-')
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('yield_rate')
                ->label('Yield/Stroke')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) $state.' pcs' : '1 pcs')
                ->alignCenter()
                ->toggleable(isToggledHiddenByDefault: true),

            // Kolom tambahan opsional (hidden by default agar tabel fit & rapi seperti MaterialSteel)
            Tables\Columns\TextColumn::make('drawing_reference')
                ->label('Drawing Ref')
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('eci_number')
                ->label('ECI No')
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('tooling_depreciation')
                ->label('Tooling / Jig Depr')
                ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                ->alignRight()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('size')
                ->label('Size')
                ->placeholder('-')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('berat')
                ->label('Berat')
                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? number_format((float) $state, 4, ',', '.').' kg' : '-')
                ->placeholder('-')
                ->alignRight()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('gap_steel_usd')
                ->label('Gap/MT ($)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state, StampingPart $record) => static::formatUsd($state, $record->currency_code))
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('gap_steel_kg_usd')
                ->label('Gap/Kg ($)')
                ->wrapHeader()
                ->formatStateUsing(fn ($state, StampingPart $record) => static::formatUsd($state, $record->currency_code))
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
                ->preload(),

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
                ->modalHeading(fn (StampingPart $record) => "Lembar Perhitungan Part Cost: {$record->part_number} - {$record->part_name}")
                ->modalWidth(MaxWidth::SevenExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(fn (StampingPart $record) => view('filament.resources.stamping-parts.sheet-modal', ['record' => $record])),
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
                    ->action(fn (Collection $records) => ExcelExportService::exportStampingPart($records, 'xlsx')),

                Tables\Actions\BulkAction::make('export_selected_csv')
                    ->label('Export Terpilih (CSV .csv)')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(fn (Collection $records) => ExcelExportService::exportStampingPart($records, 'csv')),

                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'currency', 'part']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStampingParts::route('/'),
            'create' => Pages\CreateStampingPart::route('/create'),
            'edit' => Pages\EditStampingPart::route('/{record}/edit'),
        ];
    }
}
