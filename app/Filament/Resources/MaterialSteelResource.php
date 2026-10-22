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

        $prev = MaterialSteel::where('supplier_id', $supplierId)
            ->where('steel_spec', $cleanSpec)
            ->where('quarter', $prevQuarter)
            ->where('year', $prevYear)
            ->when(! empty($thickness), fn ($q) => $q->where('double_thickness', $thickness))
            ->first();

        if ($prev) {
            $set('prev_record_found', true);
            $set('prev_coil_price', (float) $prev->coil_price);
            $set('prev_sheet_price', (float) $prev->sheet_price);
            $set('prev_rate', (float) $prev->exchange_rate);

            // Jika dalam mode adjustment, otomatis terapkan harga baru dari gap_steel_usd
            $mode = $get('price_mode');
            if ($mode === 'adjustment') {
                $gapMt = (float) ($get('gap_steel_usd') ?? 0);
                $gapKg = round($gapMt / 1000, 4);

                $newCoil = round((float) $prev->coil_price + $gapKg, 4);
                $newSheet = round((float) $prev->sheet_price + $gapKg, 4);

                $set('coil_price', $newCoil);
                $set('sheet_price', $newSheet);

                static::recalculateFormPrices($get, $set);
            }
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

        $coilIdr = round($coil * $rate, 2);
        $sheetIdr = round($sheet * $rate, 2);

        $set('coil_price_idr', $coilIdr);
        $set('sheet_price_idr', $sheetIdr);

        $steelPrice = $coil > 0 ? $coil : $sheet;
        $set('steel_price', $steelPrice);
        $set('steel_price_idr', round($steelPrice * $rate, 2));
    }

    public static function updatePricesFromCoil(Get $get, Set $set): void
    {
        $coil = (float) ($get('coil_price') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);

        $set('coil_price_idr', round($coil * $rate, 2));

        $steelPrice = $coil > 0 ? $coil : ($get('sheet_price') ?? 0);
        $set('steel_price', $steelPrice);
        $set('steel_price_idr', round($steelPrice * $rate, 2));
    }

    public static function updatePricesFromSheet(Get $get, Set $set): void
    {
        $sheet = (float) ($get('sheet_price') ?? 0);
        $coil = (float) ($get('coil_price') ?? 0);
        $rate = (float) ($get('exchange_rate') ?? 1);

        $set('sheet_price_idr', round($sheet * $rate, 2));

        $steelPrice = $coil > 0 ? $coil : $sheet;
        $set('steel_price', $steelPrice);
        $set('steel_price_idr', round($steelPrice * $rate, 2));
    }

    public static function updatePricesFromGap(Get $get, Set $set): void
    {
        $gapMt = (float) ($get('gap_steel_usd') ?? 0);
        $gapKg = round($gapMt / 1000, 4);
        $set('gap_steel_kg_usd', $gapKg);
        $set('fluktuasi_jfe', $gapKg);

        $prevCoil = $get('prev_coil_price');
        $prevSheet = $get('prev_sheet_price');

        if ($prevCoil !== null && (float) $prevCoil > 0) {
            $newCoil = round((float) $prevCoil + $gapKg, 4);
            $set('coil_price', $newCoil);
        }

        if ($prevSheet !== null && (float) $prevSheet > 0) {
            $newSheet = round((float) $prevSheet + $gapKg, 4);
            $set('sheet_price', $newSheet);
        }

        static::recalculateFormPrices($get, $set);
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
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if ($state === 'Q1') {
                                            $set('price_mode', 'baseline');
                                            $set('gap_steel_usd', 0);
                                            $set('gap_steel_kg_usd', 0);
                                        } else {
                                            $set('price_mode', 'adjustment');
                                        }
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

                                        Forms\Components\TextInput::make('code')
                                            ->label('Vendor Code (Kode Supplier)')
                                            ->placeholder('Contoh: VND-KRAK-01')
                                            ->required()
                                            ->unique('suppliers', 'code')
                                            ->maxLength(50),
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
                                Forms\Components\Hidden::make('exchange_rate')
                                    ->default(function (Get $get) {
                                        $year = (int) ($get('year') ?? date('Y'));
                                        $quarter = $get('quarter') ?? 'Q1';

                                        return Currency::where('code', 'USD')->where('year', $year)->where('quarter', $quarter)->value('exchange_rate_to_idr')
                                            ?? Currency::where('code', 'USD')->where('year', $year)->value('exchange_rate_to_idr')
                                            ?? Currency::where('code', 'USD')->latest('year')->value('exchange_rate_to_idr')
                                            ?? 19000;
                                    })
                                    ->dehydrated(),
                                Forms\Components\Hidden::make('gap_steel_kg_usd')->default(0),
                                Forms\Components\Hidden::make('steel_price'),
                                Forms\Components\Hidden::make('steel_price_idr'),
                                Forms\Components\Hidden::make('fluktuasi_jfe')->default(0),

                                // Form state helpers untuk acuan kuartal sebelumnya (tidak disimpan ke DB)
                                Forms\Components\Hidden::make('prev_quarter_label')->dehydrated(false),
                                Forms\Components\Hidden::make('prev_record_found')->default(false)->dehydrated(false),
                                Forms\Components\Hidden::make('prev_coil_price')->dehydrated(false),
                                Forms\Components\Hidden::make('prev_sheet_price')->dehydrated(false),
                                Forms\Components\Hidden::make('prev_rate')->dehydrated(false),
                            ])->columns(3),

                        // SECTION 2: Penetapan Harga Bahan Baku
                        Forms\Components\Section::make('2. Harga Bahan Baku')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\Radio::make('price_mode')
                                    ->label('Mode Penetapan Harga')
                                    ->options([
                                        'baseline' => 'Data Awal / Baseline (Tanpa Perbandingan — Langsung Isi Harga Coil & Sheet)',
                                        'adjustment' => 'Pembaruan Kuartalan (Ada Kenaikan / Penurunan Gap MT)',
                                    ])
                                    ->default(fn (Get $get) => ($get('quarter') ?? 'Q1') === 'Q1' ? 'baseline' : 'adjustment')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state === 'baseline') {
                                            $set('gap_steel_usd', 0);
                                            $set('gap_steel_kg_usd', 0);
                                            $set('fluktuasi_jfe', 0);
                                            static::recalculateFormPrices($get, $set);
                                        } else {
                                            static::lookupPreviousQuarterRecord($get, $set);
                                        }
                                    })
                                    ->dehydrated()
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('prev_quarter_info')
                                    ->label('Status Data Acuan')
                                    ->content(function (Get $get) {
                                        $found = $get('prev_record_found');
                                        $prevLabel = $get('prev_quarter_label') ?? 'Kuartal Sebelumnya';
                                        $prevCoil = $get('prev_coil_price');
                                        $prevSheet = $get('prev_sheet_price');

                                        if ($found && $prevCoil !== null) {
                                            return "📌 Ditemukan data acuan {$prevLabel}: Harga Coil \${$prevCoil} | Sheet \${$prevSheet}. Masukkan Kenaikan/Penurunan Gap MT di bawah untuk menghitung harga baru secara otomatis.";
                                        }

                                        return "ℹ️ Belum ada data di {$prevLabel} untuk spesifikasi ini (Spesifikasi Baru). Silakan masukkan Harga Coil dan Sheet langsung di bawah.";
                                    })
                                    ->visible(fn (Get $get) => $get('price_mode') === 'adjustment')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('gap_steel_usd')
                                    ->label('Kenaikan / Penurunan Gap MT ($/MT)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: 0.5 atau -20 (0 jika tidak ada perubahan)')
                                    ->default(0)
                                    ->visible(fn (Get $get) => $get('price_mode') === 'adjustment')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromGap($get, $set))
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('coil_price')
                                    ->label('Harga Coil ($/Kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: 1.1206')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromCoil($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('sheet_price')
                                    ->label('Harga Sheet ($/Kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: 1.1456')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePricesFromSheet($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('coil_price_idr')
                                    ->label('Total Coil (IDR/Kg)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('sheet_price_idr')
                                    ->label('Total Sheet (IDR/Kg)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated()
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

                Tables\Columns\TextColumn::make('vendor_code')
                    ->label('Vendor Code')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Supplier Name')
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

                Tables\Columns\TextColumn::make('gap_steel_usd')
                    ->label('Gap/MT (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, MaterialSteel $record) => $state !== null && (float) $state != 0 ? "{$record->currency_code} ".number_format((float) $state, 2) : '-')
                    ->weight('bold')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Ex Rate (IDR)')
                    ->wrapHeader()
                    ->description(fn (MaterialSteel $record) => $record->currency ? "Kurs {$record->currency->code} (Thn {$record->currency->year})" : null)
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('coil_price')
                    ->label('Coil (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, MaterialSteel $record) => $state && (float) $state > 0 ? "{$record->currency_code} ".number_format((float) $state, 4) : '-')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('coil_price_idr')
                    ->label('Coil (IDR)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state) => $state && (float) $state > 0 ? 'Rp '.number_format((float) $state, 0, ',', '.') : '-')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sheet_price')
                    ->label('Sheet (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, MaterialSteel $record) => $state && (float) $state > 0 ? "{$record->currency_code} ".number_format((float) $state, 4) : '-')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sheet_price_idr')
                    ->label('Sheet (IDR)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state) => $state && (float) $state > 0 ? 'Rp '.number_format((float) $state, 0, ',', '.') : '-')
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
                    ->options([
                        'Q1' => 'Q1 (Januari - Maret)',
                        'Q2' => 'Q2 (April - Juni)',
                        'Q3' => 'Q3 (Juli - September)',
                        'Q4' => 'Q4 (Oktober - Desember)',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('vendor_code')
                    ->label('Filter Vendor Code')
                    ->options(function () {
                        return Supplier::query()
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn ($s) => [$s->code => "{$s->code} — {$s->name}"])
                            ->toArray();
                    })
                    ->searchable()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Filter Tahun')
                    ->options(function () {
                        $current = (int) date('Y');
                        $years = [];
                        for ($y = $current - 2; $y <= $current + 2; $y++) {
                            $years[$y] = (string) $y;
                        }

                        return $years;
                    }),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Filter Supplier Name')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
            ])
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
