<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialSteelResource\Pages;
use App\Models\Currency;
use App\Models\Material;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialSteelResource extends Resource
{
    protected static ?string $model = MaterialSteel::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Material Steel';

    protected static ?int $navigationSort = 2;

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

    public static function updateHistoricalLookback(Get $get, Set $set): void
    {
        $quarter = $get('quarter') ?? 'Q1';
        $year = (int) ($get('year') ?? date('Y'));
        $info = MaterialSteel::getPrevious3MonthsInfo($quarter, $year);

        $set('prev_month_1_label', $info['month_1']);
        $set('prev_month_2_label', $info['month_2']);
        $set('prev_month_3_label', $info['month_3']);

        $supplierId = $get('supplier_id');
        $steelSpec = $get('steel_spec');

        // Jika ada record historis dari kuartal sebelumnya untuk supplier & spec ini, ambil otomatis
        if ($supplierId && ! empty($info['prev_quarter']) && ! empty($info['prev_year'])) {
            $prevRecord = MaterialSteel::where('supplier_id', $supplierId)
                ->when($steelSpec, fn ($q) => $q->where('steel_spec', $steelSpec))
                ->where('quarter', $info['prev_quarter'])
                ->where('year', $info['prev_year'])
                ->first();

            if ($prevRecord) {
                if (! $get('prev_month_1_price')) {
                    $set('prev_month_1_price', (float) $prevRecord->steel_price);
                    $set('prev_month_1_rate', (float) $prevRecord->exchange_rate);
                }
                if (! $get('prev_month_2_price')) {
                    $set('prev_month_2_price', (float) $prevRecord->steel_price);
                    $set('prev_month_2_rate', (float) $prevRecord->exchange_rate);
                }
                if (! $get('prev_month_3_price')) {
                    $set('prev_month_3_price', (float) $prevRecord->steel_price);
                    $set('prev_month_3_rate', (float) $prevRecord->exchange_rate);
                }
            }
        }
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

    public static function lookupPreviousQuarterRecord(Get $get, Set $set): void
    {
        $quarter = $get('quarter') ?? 'Q1';
        $year = (int) ($get('year') ?? date('Y'));
        $supplierId = $get('supplier_id');
        $steelSpec = $get('steel_spec');
        $thickness = $get('double_thickness');

        // Tentukan kuartal dan tahun sebelumnya
        $prevQuarter = match ($quarter) {
            'Q1' => 'Q4',
            'Q2' => 'Q1',
            'Q3' => 'Q2',
            'Q4' => 'Q3',
            default => 'Q1',
        };
        $prevYear = ($quarter === 'Q1') ? ($year - 1) : $year;

        $set('prev_quarter_label', "{$prevQuarter} {$prevYear}");

        if (! $supplierId || ! $steelSpec) {
            return;
        }

        $cleanSpec = MaterialSteel::cleanSteelSpec($steelSpec);
        $cleanThickness = ! empty($thickness) ? trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $thickness)) : null;

        $supplier = Supplier::find($supplierId);
        $prev = MaterialSteel::where(function ($q) use ($supplierId, $supplier) {
            $q->where('supplier_id', $supplierId);
            if ($supplier) {
                $q->orWhere('vendor_code', $supplier->code)
                    ->orWhere('supplier_name', $supplier->name);
            }
        })
            ->where(function ($q) use ($steelSpec, $cleanSpec) {
                $q->where('steel_spec', $cleanSpec)
                    ->orWhere('steel_spec', $steelSpec)
                    ->orWhere('steel_spec', 'like', $cleanSpec.'%');
            })
            ->where('quarter', $prevQuarter)
            ->where('year', $prevYear)
            ->when(! empty($cleanThickness), function ($q) use ($cleanThickness) {
                $q->where(function ($sub) use ($cleanThickness) {
                    $sub->where('double_thickness', $cleanThickness)
                        ->orWhere('double_thickness', 'like', $cleanThickness.'%');
                });
            })
            ->first();

        if ($prev) {
            $set('prev_record_found', true);
            $set('prev_coil_price', (float) $prev->coil_price);
            $set('prev_sheet_price', (float) $prev->sheet_price);
            $set('prev_rate', (float) $prev->exchange_rate);

            if ($prev->exchange_rate && (float) $prev->exchange_rate > 0) {
                $set('exchange_rate', (float) $prev->exchange_rate);
            }

            $spec = $get('steel_spec');
            $steelType = MaterialSteel::determineSteelType($spec);

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

            $prevCoil = (float) $prev->coil_price;
            $prevSheet = (float) $prev->sheet_price;

            if ($newAdj != 0.0) {
                $appliedKg = round($newAdj / 1000, 4);
                $set('coil_price', round($prevCoil + $appliedKg, 4));
                $set('sheet_price', round($prevSheet + $appliedKg, 4));
                $set('gap_steel_usd', $newAdj);
                $set('gap_steel_kg_usd', $appliedKg);
            } else {
                $set('coil_price', $prevCoil);
                $set('sheet_price', $prevSheet);
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

        $steelPrice = $coil > 0 ? $coil : $sheet;
        $set('steel_price', $steelPrice);
        $set('steel_price_idr', round($steelPrice * $rate));
    }

    public static function updatePricesFromCoil(Get $get, Set $set): void
    {
        $coil = (float) ($get('coil_price') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);

        $set('coil_price_idr', round($coil * $rate));

        $steelPrice = $coil > 0 ? $coil : ($get('sheet_price') ?? 0);
        $set('steel_price', $steelPrice);
        $set('steel_price_idr', round($steelPrice * $rate));
    }

    public static function updatePricesFromSheet(Get $get, Set $set): void
    {
        $sheet = (float) ($get('sheet_price') ?? 0);
        $coil = (float) ($get('coil_price') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);

        $set('sheet_price_idr', round($sheet * $rate));

        $steelPrice = $coil > 0 ? $coil : $sheet;
        $set('steel_price', $steelPrice);
        $set('steel_price_idr', round($steelPrice * $rate));
    }

    public static function updatePricesFromHrCr(Get $get, Set $set, ?string $source = null): void
    {
        $spec = $get('steel_spec');
        $steelType = MaterialSteel::determineSteelType($spec);

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
            $newCoil = round((float) $prevCoil + $appliedKg, 4);
            $set('coil_price', $newCoil);
        }

        if ($prevSheet !== null && (float) $prevSheet > 0) {
            $newSheet = round((float) $prevSheet + $appliedKg, 4);
            $set('sheet_price', $newSheet);
        }

        static::recalculateFormPrices($get, $set);
    }

    public static function updatePricesFromGap(Get $get, Set $set): void
    {
        static::updatePricesFromHrCr($get, $set);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        // SECTION 1: Periode & Spesifikasi Material Baja
                        Forms\Components\Section::make('1. Spesifikasi Material & Periode')
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
                                        $latestYear = MaterialSteel::latest('year')->value('year');

                                        return $latestYear ?: (int) date('Y');
                                    })
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        static::syncCurrencyAndPrices($get, $set);
                                        static::updateHistoricalLookback($get, $set);
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
                                        static::updateHistoricalLookback($get, $set);
                                        static::lookupPreviousQuarterRecord($get, $set);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Select::make('supplier_id')
                                    ->label('Pilih Supplier')
                                    ->relationship(
                                        name: 'supplier',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query, Get $get, ?MaterialSteel $record) {
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
                                        static::updateHistoricalLookback($get, $set);
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

                                Forms\Components\TextInput::make('steel_spec')
                                    ->label('Spesifikasi Baja (Spec)')
                                    ->placeholder('Contoh: SPH270C-OD, SPC270C, SPH440-OD')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if ($state) {
                                            $clean = MaterialSteel::cleanSteelSpec($state);
                                            if ($clean !== $state) {
                                                $set('steel_spec', $clean);
                                            }
                                        }
                                        static::updateHistoricalLookback($get, $set);
                                        static::lookupPreviousQuarterRecord($get, $set);
                                    })
                                    ->dehydrateStateUsing(fn (?string $state) => MaterialSteel::cleanSteelSpec($state))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('size')
                                    ->label('Size / Dimensi (Opsional)')
                                    ->placeholder('Contoh: 1.6 x 1219 x C')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state && empty($get('double_thickness'))) {
                                            if (preg_match('/^([0-9]+(?:\.[0-9]+)?)/', trim($state), $matches)) {
                                                $set('double_thickness', $matches[1]);
                                                static::lookupPreviousQuarterRecord($get, $set);
                                            }
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('double_thickness')
                                    ->label('Thickness (mm)')
                                    ->placeholder('Contoh: 1.6 atau 2.0')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $clean = trim(preg_replace('/[a-zA-Z\s]+/', '', $state));
                                            $set('double_thickness', $clean);
                                        }
                                        static::lookupPreviousQuarterRecord($get, $set);
                                    })
                                    ->columnSpan(1),

                                // Hidden fields untuk menjamin integritas data tanpa membingungkan user
                                Forms\Components\Hidden::make('vendor_code'),
                                Forms\Components\Hidden::make('supplier_name'),
                                Forms\Components\Hidden::make('material_id'),
                                Forms\Components\Hidden::make('currency_id'),
                                Forms\Components\Hidden::make('currency_code')->default('USD'),
                                Forms\Components\Hidden::make('gap_steel_kg_usd')->default(0),
                                Forms\Components\Hidden::make('steel_price'),
                                Forms\Components\Hidden::make('steel_price_idr'),
                                Forms\Components\Hidden::make('fluktuasi_jfe')->default(0),

                                // Form state helpers untuk acuan kuartal sebelumnya (tidak disimpan ke DB)
                                Forms\Components\Hidden::make('prev_quarter_label'),
                                Forms\Components\Hidden::make('prev_record_found')->default(false),
                                Forms\Components\Hidden::make('prev_coil_price'),
                                Forms\Components\Hidden::make('prev_sheet_price'),
                                Forms\Components\Hidden::make('prev_rate'),
                            ])->columns(3),

                        // SECTION 2: Penetapan Harga Bahan Baku
                        Forms\Components\Section::make('2. Harga Bahan Baku')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\TextInput::make('hr_steel_usd')
                                    ->label('HR $ / MT')
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
                                    ->label('CR $ / MT')
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
                                    ->columnSpan(2),

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

                        // SECTION 3: Catatan & Status (Collapsible)
                        Forms\Components\Section::make('3. Catatan & Status (Opsional)')
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
                                    ->required(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan Kontrak / Referensi Supplier')
                                    ->placeholder('Misal: Berdasarkan surat kesepakatan harga No. SKH-2027/KP-01 tanggal 15...')
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
                    ->state(fn (MaterialSteel $record) => "{$record->year} - {$record->quarter}")
                    ->color(fn (MaterialSteel $record): string => match ($record->quarter) {
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

                Tables\Columns\TextColumn::make('steel_spec')
                    ->label('Spec')
                    ->formatStateUsing(fn ($state) => MaterialSteel::cleanSteelSpec($state))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('double_thickness')
                    ->label('Thickness(mm)')
                    ->wrapHeader()
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '-';
                        }

                        return trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $state));
                    })
                    ->description(fn (MaterialSteel $record) => $record->size ?: null)
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('hr_steel_usd')
                    ->label('HR $ / MT')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, MaterialSteel $record) => static::formatUsd($state, $record->currency_code))
                    ->weight('bold')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cr_steel_usd')
                    ->label('CR $ / MT')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, MaterialSteel $record) => static::formatUsd($state, $record->currency_code))
                    ->weight('bold')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Perubahan $ Rupiah')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('coil_price')
                    ->label('Coil ($)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, MaterialSteel $record) => static::formatUsd($state, $record->currency_code))
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
                    ->formatStateUsing(fn ($state, MaterialSteel $record) => static::formatUsd($state, $record->currency_code))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sheet_price_idr')
                    ->label('Sheet (IDR)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
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
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('quarter')
                    ->label('Filter Kuartal')
                    ->options(function () {
                        $counts = MaterialSteel::query()
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
                        $counts = MaterialSteel::query()
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
                        $counts = MaterialSteel::query()
                            ->selectRaw('year, count(*) as total')
                            ->groupBy('year')
                            ->pluck('total', 'year');

                        $current = (int) date('Y');
                        $dbYears = MaterialSteel::distinct()->pluck('year')->filter()->toArray();
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

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Filter Supplier Name')
                    ->options(function () {
                        $counts = MaterialSteel::query()
                            ->whereNotNull('supplier_id')
                            ->selectRaw('supplier_id, count(*) as total')
                            ->groupBy('supplier_id')
                            ->pluck('total', 'supplier_id');

                        return Supplier::query()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(function ($s) use ($counts) {
                                $total = $counts[$s->id] ?? 0;

                                return [$s->id => "{$s->name} ({$total} data)"];
                            })
                            ->toArray();
                    })
                    ->searchable(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options(function () {
                        $counts = MaterialSteel::query()
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
                    ->label(fn () => 'Filter Data ('.MaterialSteel::count().' Data)')
            )
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['currency', 'supplier', 'material']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialSteels::route('/'),
            'create' => Pages\CreateMaterialSteel::route('/create'),
            'edit' => Pages\EditMaterialSteel::route('/{record}/edit'),
        ];
    }
}
