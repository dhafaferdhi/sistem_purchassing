<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubAssemblyPartResource\Pages;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\SubAssemblyPart;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class SubAssemblyPartResource extends Resource
{
    protected static ?string $model = SubAssemblyPart::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Sub-Assembly Parts';

    protected static ?string $modelLabel = 'Sub-Assembly Part';

    protected static ?string $pluralModelLabel = 'Sub-Assembly Parts';

    protected static ?int $navigationSort = 5;

    public static function formatIdr(?float $amount, bool $prefix = true): string
    {
        if ($amount === null) {
            return '-';
        }

        $f = (float) $amount;
        if ($f == 0.0) {
            return $prefix ? 'Rp 0' : '0';
        }

        $formatted = number_format(round($amount), 0, ',', '.');

        return $prefix ? 'Rp '.$formatted : $formatted;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        // SECTION 1: Periode, Identitas Part & Vendor Sub-Assembly
                        Forms\Components\Section::make('1. Identitas Sub-Assembly, Model & Vendor')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('year')
                                            ->label('Tahun Acuan')
                                            ->options(function () {
                                                $current = (int) date('Y');
                                                $years = range($current + 2, 2020);

                                                return array_combine($years, $years);
                                            })
                                            ->default((int) date('Y'))
                                            ->required()
                                            ->live(),

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
                                            ->live(),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('part_number')
                                            ->label('Part Number Sub-Assy')
                                            ->placeholder('Contoh: 73012-BZ010')
                                            ->required()
                                            ->live(onBlur: true),

                                        Forms\Components\TextInput::make('part_name')
                                            ->label('Nama Part Sub-Assy')
                                            ->placeholder('Contoh: BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH')
                                            ->required(),
                                    ]),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('model_name')
                                            ->label('Model Kendaraan')
                                            ->placeholder('Contoh: D26A')
                                            ->default('D26A'),

                                        Forms\Components\TextInput::make('monthly_volume')
                                            ->label('Monthly Volume (Pcs)')
                                            ->numeric()
                                            ->placeholder('16500')
                                            ->default(16500)
                                            ->suffix('pcs/bln'),

                                        Forms\Components\TextInput::make('exchange_rate')
                                            ->label('Kurs $ (IDR)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->default(16000)
                                            ->live(onBlur: true),
                                    ]),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('supplier_id')
                                            ->label('Vendor / Supplier Sub-Assy')
                                            ->relationship('supplier', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Nama Vendor')
                                                    ->required(),
                                                Forms\Components\TextInput::make('code')
                                                    ->label('Kode Vendor')
                                                    ->default(fn () => 'VND-TCF-'.rand(10, 99)),
                                                Forms\Components\Hidden::make('category')->default('Component'),
                                                Forms\Components\Hidden::make('is_active')->default(true),
                                            ])
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, ?int $state) {
                                                if ($state) {
                                                    $supplier = Supplier::find($state);
                                                    if ($supplier) {
                                                        $set('supplier_name', $supplier->name);
                                                        $set('vendor_code', $supplier->code);
                                                    }
                                                }
                                            }),

                                        Forms\Components\TextInput::make('supplier_name')
                                            ->label('Nama Supplier (Fallback)')
                                            ->placeholder('Contoh: PT. TRI CENTRUM FORTUNA')
                                            ->default('PT. TRI CENTRUM FORTUNA'),

                                        Forms\Components\TextInput::make('buyer_name')
                                            ->label('Buyer / Customer')
                                            ->default('PT. FUJI SEAT INDONESIA'),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('drawing_reference')
                                            ->label('Drawing Reference')
                                            ->default('Mass Prod. Drwg (Exchange rate change)'),

                                        Forms\Components\TextInput::make('eci_number')
                                            ->label('ECI Number')
                                            ->default('F1225'),
                                    ]),
                            ]),

                        // SECTION 2: Material Breakdown (Repeater Child Parts)
                        Forms\Components\Section::make('2. Rincian Komponen Anak / Bahan Baku (Material Breakdown)')
                            ->description('Komponen pembentuk sub-assembly (misal: stamping bracket, weld bolt, rivet, nut) persis seperti pada Excel PT. TCF')
                            ->icon('heroicon-o-puzzle-piece')
                            ->schema([
                                Forms\Components\Repeater::make('material_breakdown')
                                    ->label('Daftar Komponen / Child Parts')
                                    ->schema([
                                        Forms\Components\TextInput::make('part_number')
                                            ->label('Part Number Child')
                                            ->placeholder('72388-X7V00-A atau 89911-X7095')
                                            ->datalist(function () {
                                                $stamping = StampingPart::distinct()->pluck('part_number')->filter()->toArray();
                                                $fasteners = Fastener::distinct()->pluck('part_number')->filter()->toArray();
                                                $steels = MaterialSteel::distinct()->pluck('steel_spec')->filter()->toArray();

                                                return array_values(array_unique(array_merge($stamping, $fasteners, $steels)));
                                            })
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                if (! $state) {
                                                    return;
                                                }
                                                $clean = trim((string) $state);
                                                $stamping = StampingPart::where('part_number', $clean)
                                                    ->orWhere('part_number', 'like', "%{$clean}%")
                                                    ->latest('id')
                                                    ->first();
                                                if ($stamping) {
                                                    if (! empty($stamping->part_name)) {
                                                        $set('part_name', $stamping->part_name);
                                                    }
                                                    $unitPrice = (float) ($stamping->part_cost ?: 0);
                                                    if ($unitPrice > 0) {
                                                        $set('unit_price', $unitPrice);
                                                        $qty = (float) ($get('quantity') ?: 1);
                                                        $set('price', round($qty * $unitPrice, 2));
                                                    }
                                                    if (! $get('valuation') || $get('valuation') === 'TCF') {
                                                        $set('valuation', $stamping->supplier_name ?: 'Stamping');
                                                    }

                                                    return;
                                                }

                                                $fastener = Fastener::where('part_number', $clean)
                                                    ->orWhere('part_number', 'like', "%{$clean}%")
                                                    ->latest('id')
                                                    ->first();
                                                if ($fastener) {
                                                    if (! empty($fastener->part_name)) {
                                                        $set('part_name', $fastener->part_name);
                                                    }
                                                    $unitPrice = (float) ($fastener->part_cost ?: ($fastener->total_part_cost ?: 0));
                                                    if ($unitPrice > 0) {
                                                        $set('unit_price', $unitPrice);
                                                        $qty = (float) ($get('quantity') ?: 1);
                                                        $set('price', round($qty * $unitPrice, 2));
                                                    }
                                                    if (! $get('valuation') || $get('valuation') === 'TCF') {
                                                        $set('valuation', $fastener->supplier_name ?: 'Fastener');
                                                    }

                                                    return;
                                                }

                                                $steel = MaterialSteel::where('steel_spec', $clean)
                                                    ->orWhere('steel_spec', 'like', "%{$clean}%")
                                                    ->latest('id')
                                                    ->first();
                                                if ($steel) {
                                                    if (empty($get('part_name'))) {
                                                        $set('part_name', 'RAW STEEL '.$steel->steel_spec);
                                                    }
                                                    $price = (float) ($steel->sheet_price_idr ?: $steel->coil_price_idr ?: 0);
                                                    if ($price > 0) {
                                                        $set('unit_price', $price);
                                                        $qty = (float) ($get('quantity') ?: 1);
                                                        $set('price', round($qty * $price, 2));
                                                    }
                                                    if (! $get('valuation') || $get('valuation') === 'TCF') {
                                                        $set('valuation', $steel->supplier_name ?: 'Steel');
                                                    }
                                                }
                                            })
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('part_name')
                                            ->label('Nama Part')
                                            ->placeholder('BRKT, SEAT BELT ANCHOR LH atau BOLT')
                                            ->required()
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->default(1)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                                $qty = (float) ($state ?: 1);
                                                $set('price', round($qty * $unitPrice, 2));
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Harga Satuan (Rp)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $qty = (float) ($get('quantity') ?: 1);
                                                $unitPrice = (float) ($state ?: 0);
                                                $set('price', round($qty * $unitPrice, 2));
                                            })
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('price')
                                            ->label('Total Biaya (Rp)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->readOnly()
                                            ->dehydrated()
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('valuation')
                                            ->label('Valuation / Sumber')
                                            ->placeholder('Posmi / SUPPLY / Meidoh / TCF')
                                            ->default('TCF')
                                            ->columnSpan(2),
                                    ])
                                    ->columns(12)
                                    ->default([
                                        [
                                            'part_number' => '72388-X7V00-A',
                                            'part_name' => 'BRKT, SEAT BELT ANCHOR LH',
                                            'quantity' => 1,
                                            'unit_price' => 10583.53,
                                            'price' => 10583.53,
                                            'valuation' => 'Posmi',
                                        ],
                                        [
                                            'part_number' => '89911-X7095',
                                            'part_name' => 'BOLT WELD 89911-X7095',
                                            'quantity' => 1,
                                            'unit_price' => 0,
                                            'price' => 0,
                                            'valuation' => 'SUPPLY',
                                        ],
                                        [
                                            'part_number' => '89911-X1F05',
                                            'part_name' => 'BOLT PWR SEAT WELD 89911-X1F05',
                                            'quantity' => 1,
                                            'unit_price' => 0,
                                            'price' => 0,
                                            'valuation' => 'SUPPLY',
                                        ],
                                    ])
                                    ->live(),
                            ]),

                        // SECTION 3: Process Breakdown (Repeater Biaya Proses)
                        Forms\Components\Section::make('3. Rincian Biaya Proses Perakitan / Pengelasan & Cat (Process Breakdown)')
                            ->description('Proses perakitan sub-assembly seperti Las CO (Weld Bolt), Cat CED, Spot Welding')
                            ->icon('heroicon-o-wrench')
                            ->schema([
                                Forms\Components\Repeater::make('process_breakdown')
                                    ->label('Daftar Proses Manufaktur / Perakitan')
                                    ->schema([
                                        Forms\Components\TextInput::make('process_name')
                                            ->label('Nama Proses')
                                            ->placeholder('Contoh: 1. WELD CO BOLT WELD 89911-X7095 atau 3. PAINTING CED')
                                            ->required()
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('man_hour')
                                            ->label('Man-Hour (Detik)')
                                            ->numeric()
                                            ->placeholder('20')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $charge = (float) ($get('charge') ?? 0);
                                                if ($state && $charge > 0) {
                                                    $set('price', round((float) $state * $charge, 2));
                                                }
                                            })
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('charge')
                                            ->label('Charge (Rp/dtk)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->placeholder('27')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $mh = (float) ($get('man_hour') ?? 0);
                                                if ($state && $mh > 0) {
                                                    $set('price', round((float) $state * $mh, 2));
                                                }
                                            })
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('price')
                                            ->label('Biaya Proses (Rp)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->required()
                                            ->columnSpan(4),
                                    ])
                                    ->columns(12)
                                    ->default([
                                        [
                                            'process_name' => '1. WELD CO BOLT WELD 89911-X7095',
                                            'man_hour' => 20,
                                            'charge' => 27,
                                            'price' => 540,
                                        ],
                                        [
                                            'process_name' => '2. WELD C0 BOLT WELD 89911-X1F05',
                                            'man_hour' => 34,
                                            'charge' => 27,
                                            'price' => 918,
                                        ],
                                        [
                                            'process_name' => '3. PAINTING CED',
                                            'man_hour' => 0,
                                            'charge' => 0,
                                            'price' => 2800,
                                        ],
                                    ])
                                    ->live(),
                            ]),

                        // SECTION 4: Overhead, Keuntungan, Packing & Tooling
                        Forms\Components\Section::make('4. Overhead, Profit, Packing & Tooling Depreciation')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('overhead_supplied_percent')
                                            ->label('O/H Supplied Portion (%)')
                                            ->numeric()
                                            ->default(11)
                                            ->suffix('%')
                                            ->helperText('Dihitung dari Total Biaya Proses')
                                            ->live(onBlur: true),

                                        Forms\Components\TextInput::make('profit_percent')
                                            ->label('Profit (%)')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('%')
                                            ->helperText('Dihitung dari Subtotal Biaya')
                                            ->live(onBlur: true),

                                        Forms\Components\TextInput::make('packing_cost')
                                            ->label('Packing Cost (Rp / pc)')
                                            ->numeric()
                                            ->default(120)
                                            ->prefix('Rp')
                                            ->live(onBlur: true),

                                        Forms\Components\TextInput::make('tooling_depreciation')
                                            ->label('Die & Jig Depreciation (Rp / pc)')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('Rp')
                                            ->helperText('Biaya depresiasi alat/jig jika ada')
                                            ->live(onBlur: true),
                                    ]),

                                Forms\Components\Placeholder::make('sub_assembly_calculation_summary')
                                    ->hiddenLabel()
                                    ->content(function (Get $get) {
                                        $materials = $get('material_breakdown') ?: [];
                                        $matTotal = 0.0;
                                        foreach ($materials as $m) {
                                            $qty = (float) ($m['qty'] ?? $m['quantity'] ?? 1);
                                            $up = (float) ($m['unit_price'] ?? 0);
                                            $pr = isset($m['price']) && (float) $m['price'] > 0 ? (float) $m['price'] : round($qty * $up, 2);
                                            $matTotal += $pr;
                                        }

                                        $processes = $get('process_breakdown') ?: [];
                                        $procTotal = 0.0;
                                        foreach ($processes as $p) {
                                            if (isset($p['price']) && (float) $p['price'] > 0) {
                                                $procTotal += (float) $p['price'];
                                            } elseif (isset($p['man_hour']) && isset($p['charge'])) {
                                                $procTotal += round((float) $p['man_hour'] * (float) $p['charge'], 2);
                                            }
                                        }

                                        $subtotal = round($matTotal + $procTotal, 2);
                                        $ohPct = (float) ($get('overhead_supplied_percent') ?? 11);
                                        $ohCost = round(($ohPct / 100) * $procTotal, 2);

                                        $profPct = (float) ($get('profit_percent') ?? 0);
                                        $profCost = round(($profPct / 100) * $subtotal, 2);

                                        $pack = (float) ($get('packing_cost') ?? 120);
                                        $tool = (float) ($get('tooling_depreciation') ?? 0);

                                        $partCost = round($subtotal + $ohCost + $profCost + $pack + $tool, 2);

                                        return new HtmlString("
                                            <div class='rounded-xl border border-primary-200 bg-white p-5 shadow-sm dark:border-primary-800 dark:bg-gray-900'>
                                                <div class='mb-3 flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800'>
                                                    <h3 class='text-sm font-semibold text-primary-950 dark:text-primary-100 flex items-center gap-2'>
                                                        <span>📊</span> Rincian Kalkulasi Harga Sub-Assembly (PT. Tri Centrum Fortuna)
                                                    </h3>
                                                    <span class='text-xs font-medium text-gray-500'>Auto-calculated</span>
                                                </div>
                                                <div class='grid grid-cols-2 md:grid-cols-4 gap-4 text-xs'>
                                                    <div class='rounded-lg bg-blue-50 p-2.5 dark:bg-blue-950/30'>
                                                        <div class='text-blue-800 dark:text-blue-300'>(1) Material Total</div>
                                                        <div class='mt-1 text-sm font-bold text-blue-700 dark:text-blue-400'>Rp ".number_format($matTotal, 2, ',', '.')."</div>
                                                        <div class='text-[10px] text-blue-600'>Total child parts</div>
                                                    </div>
                                                    <div class='rounded-lg bg-purple-50 p-2.5 dark:bg-purple-950/30'>
                                                        <div class='text-purple-800 dark:text-purple-300'>(2) Process Total</div>
                                                        <div class='mt-1 text-sm font-bold text-purple-700 dark:text-purple-400'>Rp ".number_format($procTotal, 2, ',', '.')."</div>
                                                        <div class='text-[10px] text-purple-600'>Las CO + Cat CED</div>
                                                    </div>
                                                    <div class='rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60'>
                                                        <div class='text-gray-500 dark:text-gray-400'>(3) Subtotal = (1) + (2)</div>
                                                        <div class='mt-1 text-sm font-bold text-gray-800 dark:text-gray-200'>Rp ".number_format($subtotal, 2, ',', '.')."</div>
                                                        <div class='text-[10px] text-gray-400'>Material + Proses</div>
                                                    </div>
                                                    <div class='rounded-lg bg-amber-50 p-2.5 dark:bg-amber-950/30'>
                                                        <div class='text-amber-800 dark:text-amber-300'>(4) O/H Supplied ({$ohPct}%)</div>
                                                        <div class='mt-1 text-sm font-bold text-amber-700 dark:text-amber-400'>Rp ".number_format($ohCost, 2, ',', '.')."</div>
                                                        <div class='text-[10px] text-amber-600'>Dari Total Proses</div>
                                                    </div>
                                                </div>
                                                <div class='mt-3 grid grid-cols-3 gap-4 text-xs pt-3 border-t border-gray-100 dark:border-gray-800'>
                                                    <div>
                                                        <span class='text-gray-500'>Profit ({$profPct}%):</span>
                                                        <span class='font-semibold text-gray-800 dark:text-gray-200 ml-1'>Rp ".number_format($profCost, 2, ',', '.')."</span>
                                                    </div>
                                                    <div>
                                                        <span class='text-gray-500'>Packing:</span>
                                                        <span class='font-semibold text-gray-800 dark:text-gray-200 ml-1'>Rp ".number_format($pack, 2, ',', '.')."</span>
                                                    </div>
                                                    <div>
                                                        <span class='text-gray-500'>Die & Jig Depr:</span>
                                                        <span class='font-semibold text-gray-800 dark:text-gray-200 ml-1'>Rp ".number_format($tool, 2, ',', '.')."</span>
                                                    </div>
                                                </div>
                                                <div class='mt-3 flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-3 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800'>
                                                    <div>
                                                        <span class='text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300'>Total Part Cost Sub-Assembly</span>
                                                        <div class='text-[11px] text-emerald-600 dark:text-emerald-400'>Subtotal + Overhead + Profit + Packing + Tooling</div>
                                                    </div>
                                                    <div class='text-2xl font-black text-emerald-700 dark:text-emerald-300'>
                                                        Rp ".number_format($partCost, 2, ',', '.').'
                                                    </div>
                                                </div>
                                            </div>
                                        ');
                                    }),
                            ]),

                        // SECTION 5: Status & Catatan
                        Forms\Components\Section::make('5. Status & Catatan Tambahan (Opsional)')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'Active' => 'Active',
                                                'Draft' => 'Draft',
                                            ])
                                            ->default('Active'),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Catatan'),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('part_number')
                    ->label('Part Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('part_name')
                    ->label('Nama Part Sub-Assy')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('model_name')
                    ->label('Model')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quarter')
                    ->label('Kuartal')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Q1' => 'info',
                        'Q2' => 'success',
                        'Q3' => 'warning',
                        'Q4' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('year')
                    ->label('Tahun')
                    ->sortable(),

                Tables\Columns\TextColumn::make('material_total')
                    ->label('Total Material')
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('process_total')
                    ->label('Total Proses')
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('overhead_supplied_cost')
                    ->label('O/H Cost')
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('profit_cost')
                    ->label('Profit')
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('packing_cost')
                    ->label('Packing')
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tooling_depreciation')
                    ->label('Tooling Depr')
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('part_cost')
                    ->label('Part Cost Akhir')
                    ->formatStateUsing(fn ($state) => static::formatIdr($state, true))
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

                Tables\Columns\TextColumn::make('drawing_reference')
                    ->label('Drawing Ref')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('eci_number')
                    ->label('ECI No')
                    ->toggleable(isToggledHiddenByDefault: true),
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

                Tables\Filters\SelectFilter::make('year')
                    ->label('Filter Tahun')
                    ->options(function () {
                        $current = (int) date('Y');
                        $dbYears = SubAssemblyPart::distinct()->pluck('year')->filter()->toArray();
                        $rangeYears = range($current + 2, $current - 2);
                        $allYears = array_unique(array_merge($dbYears, $rangeYears));
                        rsort($allYears);

                        return array_combine($allYears, array_map(fn ($y) => "Tahun {$y}", $allYears));
                    }),

                Tables\Filters\SelectFilter::make('supplier_name')
                    ->label('Filter Supplier')
                    ->options(fn () => Supplier::query()->pluck('name', 'name')->toArray())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options([
                        'Active' => 'Active',
                        'Draft' => 'Draft',
                    ]),
            ])
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->button()
                    ->label('Filter Data')
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
                    ->modalHeading(fn (SubAssemblyPart $record) => "Lembar Perhitungan Sub-Assembly: {$record->part_number} - {$record->part_name}")
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (SubAssemblyPart $record) => view('filament.resources.sub-assembly-parts.sheet-modal', ['record' => $record])),
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubAssemblyParts::route('/'),
            'create' => Pages\CreateSubAssemblyPart::route('/create'),
            'edit' => Pages\EditSubAssemblyPart::route('/{record}/edit'),
        ];
    }
}
