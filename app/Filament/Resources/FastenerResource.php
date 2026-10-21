<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FastenerResource\Pages;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\Supplier;
use App\Services\ExcelImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class FastenerResource extends Resource
{
    protected static ?string $model = Fastener::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Pasterner';

    protected static ?int $navigationSort = 4;

    /**
     * Logika otomatis menarik data dari kuartal sebelumnya
     * Contoh: Q1 2027 mengambil data dari Q4 2026
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

            // Hitung konversi ke IDR dengan kurs tahun terpilih
            $rate = (float) ($get('exchange_rate') ?? $prevData['exchange_rate'] ?? 16000);
            $set('exchange_rate', $rate);
            $set('coil_price_idr', round((float) ($prevData['coil_price'] ?? 0) * $rate, 2));
            $set('sheet_price_idr', round((float) ($prevData['sheet_price'] ?? 0) * $rate, 2));

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
                        // Auto-Isi dari File Excel
                        // Auto-Isi dari File Excel (Opsional - Terlipat)
                        Forms\Components\Section::make('Auto-Deteksi & Isi dari File Excel (.xlsx / .csv)')
                            ->description('Unggah file Excel untuk membaca kolom dan mengisi formulir di bawah secara instan dan otomatis')
                            ->icon('heroicon-o-arrow-up-tray')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\FileUpload::make('excel_auto_fill')
                                    ->label('Pilih File Excel (.xlsx / .csv)')
                                    ->helperText('Setelah file diunggah, data baris pertama akan otomatis dideteksi dan mengisikan spesifikasi part/fastener, ketebalan/diameter, dan harga ke formulir.')
                                    ->disk('public')
                                    ->directory('temp-excel-detect')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (! $state) {
                                            return;
                                        }

                                        $filePath = is_string($state) ? Storage::disk('public')->path($state) : (method_exists($state, 'getRealPath') ? $state->getRealPath() : null);
                                        if (! $filePath || ! file_exists($filePath)) {
                                            $filePath = Storage::disk('public')->path((string) $state);
                                        }

                                        if ($filePath && file_exists($filePath)) {
                                            $detected = ExcelImportService::detectFirstRowData($filePath, [
                                                'year' => (int) ($get('year') ?? date('Y')),
                                                'quarter' => $get('quarter') ?? 'Q1',
                                            ]);

                                            if ($detected) {
                                                if (! empty($detected['year'])) {
                                                    $set('year', (int) $detected['year']);
                                                }
                                                if (! empty($detected['quarter'])) {
                                                    $set('quarter', $detected['quarter']);
                                                }
                                                if (! empty($detected['supplier_name'])) {
                                                    $set('supplier_name', $detected['supplier_name']);
                                                }
                                                if (! empty($detected['vendor_code'])) {
                                                    $set('vendor_code', $detected['vendor_code']);
                                                }
                                                if (! empty($detected['steel_spec'])) {
                                                    $set('steel_spec', $detected['steel_spec']);
                                                }
                                                if (! empty($detected['double_thickness'])) {
                                                    $set('double_thickness', $detected['double_thickness']);
                                                    $set('diameter', $detected['double_thickness']);
                                                }
                                                if (! empty($detected['exchange_rate'])) {
                                                    $set('exchange_rate', (float) $detected['exchange_rate']);
                                                }
                                                if (! empty($detected['coil_price'])) {
                                                    $set('coil_price', (float) $detected['coil_price']);
                                                }
                                                if (! empty($detected['coil_price_idr'])) {
                                                    $set('coil_price_idr', (float) $detected['coil_price_idr']);
                                                }
                                                if (! empty($detected['sheet_price'])) {
                                                    $set('sheet_price', (float) $detected['sheet_price']);
                                                }
                                                if (! empty($detected['sheet_price_idr'])) {
                                                    $set('sheet_price_idr', (float) $detected['sheet_price_idr']);
                                                }
                                                if (! empty($detected['gap_steel_usd'])) {
                                                    $set('gap_steel_usd', (float) $detected['gap_steel_usd']);
                                                }
                                                if (! empty($detected['gap_steel_kg_usd'])) {
                                                    $set('gap_steel_kg_usd', (float) $detected['gap_steel_kg_usd']);
                                                }
                                                if (! empty($detected['fluktuasi_jfe'])) {
                                                    $set('fluktuasi_jfe', (float) $detected['fluktuasi_jfe']);
                                                }

                                                Notification::make()
                                                    ->title('Data Excel Berhasil Dideteksi!')
                                                    ->body("Spesifikasi '{$detected['steel_spec']}' dan parameter harga telah berhasil terisi otomatis.")
                                                    ->success()
                                                    ->send();
                                            }
                                        }
                                    })
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ]),

                        // SECTION 1: Periode Kontrak Kuartalan (Q1 - Q4)
                        Forms\Components\Section::make('1. Periode Kontrak Pasterner')
                            ->description('Pilih tahun dan kuartal (Q1 - Q4). Data dapat otomatis ditarik dari kuartal sebelumnya (misal Q1 2027 dari Q4 2026)')
                            ->icon('heroicon-o-calendar')
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
                                    ->default((int) date('Y'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        if ($state) {
                                            $match = Currency::where('code', 'USD')->where('year', (int) $state)->first()
                                                ?? Currency::where('code', 'USD')->first();
                                            if ($match) {
                                                $set('currency_id', $match->id);
                                                $set('currency_code', $match->code);
                                                $set('exchange_rate', (float) $match->exchange_rate_to_idr);

                                                $coil = (float) ($get('coil_price') ?? 0);
                                                $sheet = (float) ($get('sheet_price') ?? 0);
                                                $rate = (float) $match->exchange_rate_to_idr;
                                                $set('coil_price_idr', round($coil * $rate, 2));
                                                $set('sheet_price_idr', round($sheet * $rate, 2));
                                            }
                                        }

                                        static::pullPreviousQuarterData($get, $set, false);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('currency_id')
                                    ->default(function (Get $get) {
                                        $year = (int) ($get('year') ?? date('Y'));

                                        return Currency::where('code', 'USD')->where('year', $year)->first()?->id
                                            ?? Currency::where('code', 'USD')->first()?->id
                                            ?? Currency::first()?->id;
                                    }),

                                Forms\Components\Hidden::make('currency_code')
                                    ->default('USD'),

                                Forms\Components\Select::make('quarter')
                                    ->label('Kuartal (Q1 - Q4)')
                                    ->options([
                                        'Q1' => 'Kuartal 1 (Q1)',
                                        'Q2' => 'Kuartal 2 (Q2)',
                                        'Q3' => 'Kuartal 3 (Q3)',
                                        'Q4' => 'Kuartal 4 (Q4)',
                                    ])
                                    ->default('Q1')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::pullPreviousQuarterData($get, $set, false))
                                    ->helperText('Pilih kuartal penetapan harga')
                                    ->columnSpan(1),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('pull_previous_data')
                                        ->label('Tarik Data Kuartal Sebelumnya')
                                        ->icon('heroicon-o-arrow-path')
                                        ->color('primary')
                                        ->tooltip('Otomatis mengambil data dari kuartal sebelumnya (misal Q1 2027 mengambil dari Q4 2026)')
                                        ->action(fn (Get $get, Set $set) => static::pullPreviousQuarterData($get, $set, true)),
                                ])
                                    ->columnSpan(1)
                                    ->alignEnd(),

                                Forms\Components\Placeholder::make('previous_quarter_info')
                                    ->hiddenLabel()
                                    ->content(function (Get $get) {
                                        $quarter = $get('quarter') ?? 'Q1';
                                        $year = (int) ($get('year') ?? date('Y'));
                                        $info = Fastener::getPreviousQuarterInfo($quarter, $year);
                                        $prevData = Fastener::findPreviousQuarterData($quarter, $year, $get('supplier_id') ? (int) $get('supplier_id') : null, $get('steel_spec'));

                                        if ($prevData) {
                                            return new HtmlString("<div class='rounded-lg bg-primary-50 p-3 text-xs text-primary-800 dark:bg-primary-950/40 dark:text-primary-300 border border-primary-200 dark:border-primary-800 flex items-center gap-2'><span>📌</span> <span><strong>Acuan Aktif:</strong> Mengambil data dari <strong>{$prevData['label']}</strong> ({$prevData['source']}) — Spec: <strong>{$prevData['steel_spec']}</strong>, Coil/Wire: <strong>\${$prevData['coil_price']}</strong>, Sheet/Rod: <strong>\${$prevData['sheet_price']}</strong>, Gap/MT: <strong>\${$prevData['gap_steel_usd']}</strong>.</span></div>");
                                        }

                                        return new HtmlString("<div class='rounded-lg bg-gray-50 p-2.5 text-xs text-gray-600 dark:bg-gray-800/40 dark:text-gray-400 border border-gray-200 dark:border-gray-700 flex items-center gap-2'><span>ℹ️</span> <span>Belum ada data acuan di <strong>{$info['label']}</strong>. Anda dapat mengisi form secara manual.</span></div>");
                                    })
                                    ->columnSpanFull(),
                            ])->columns(3),

                        // SECTION 2: Identitas Supplier & Spesifikasi
                        Forms\Components\Section::make('2. Supplier & Spesifikasi Pasterner')
                            ->description('Pilih supplier (PT HSSI, PT USCI, PT TOHO, dll.), ketik nama supplier sendiri, dan spesifikasi baja / wire')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\Select::make('supplier_id')
                                    ->label('Pilih Supplier / Tambah Baru (+)')
                                    ->relationship(
                                        name: 'supplier',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query, Get $get, ?Fastener $record) {
                                            $selectedId = $get('supplier_id') ?? $record?->supplier_id;
                                            $query->where(function ($q) use ($selectedId) {
                                                $q->whereIn('name', ['PT HSSI', 'PT USCI'])
                                                    ->orWhere('name', 'like', '%HSSI%')
                                                    ->orWhere('name', 'like', '%USCI%')
                                                    ->orWhere('name', 'like', '%FASTENER%')
                                                    ->orWhere('name', 'like', '%TOHO%')
                                                    ->orWhere('name', 'like', '%MOON%');
                                                if ($selectedId) {
                                                    $q->orWhere('id', $selectedId);
                                                }
                                            });
                                        }
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nama Supplier Baru')
                                            ->placeholder('Contoh: PT Toho Fastener Indonesia')
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
                                            ->placeholder('Contoh: VND-TOHO-01')
                                            ->required()
                                            ->unique('suppliers', 'code')
                                            ->maxLength(50),

                                        Forms\Components\Select::make('category')
                                            ->label('Kategori')
                                            ->options([
                                                'Fastener' => 'Fastener (Baut, Mur, Paku, Wire)',
                                                'Steel' => 'Steel (Baja / Raw Material)',
                                                'Component' => 'Component (Part Komponen)',
                                            ])
                                            ->default('Fastener')
                                            ->required(),
                                    ])
                                    ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                        return $action
                                            ->modalHeading('Bikin Supplier Baru')
                                            ->modalButton('Simpan & Gunakan')
                                            ->modalWidth('md');
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $supplier = Supplier::find($state);
                                            if ($supplier) {
                                                $set('vendor_code', $supplier->code);
                                                $set('supplier_name', $supplier->name);
                                            }
                                        }
                                        static::pullPreviousQuarterData($get, $set, false);
                                    })
                                    ->helperText('Klik (+) untuk menambah supplier baru')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('supplier_name')
                                    ->label('Supplier Name')
                                    ->placeholder('Ketik sendiri nama supplier atau pilih dari dropdown')
                                    ->required(fn (Get $get) => empty($get('supplier_id')))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state && empty($get('vendor_code'))) {
                                            $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $state), 0, 5));
                                            $set('vendor_code', 'VND-'.($clean ?: 'SUP').'-'.rand(10, 99));
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('vendor_code')
                                    ->label('Vendor Code')
                                    ->placeholder('Contoh: VND-TOHO atau VND-MOON')
                                    ->required(fn (Get $get) => empty($get('supplier_id')))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('steel_spec')
                                    ->label('Spec (Spesifikasi Material / Wire)')
                                    ->placeholder('Contoh: SWMB, SWRCH10A, SWRCH18A, SPHC')
                                    ->helperText('Hanya kode spesifikasi grade material/wire tanpa angka diameter/ketebalan dan mm. Ukuran diameter diisi pada kolom Diameter.')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if ($state) {
                                            $clean = Fastener::cleanSteelSpec($state);
                                            if ($clean !== $state) {
                                                $set('steel_spec', $clean);
                                            }
                                        }
                                        static::pullPreviousQuarterData($get, $set, false);
                                    })
                                    ->dehydrateStateUsing(fn (?string $state) => Fastener::cleanSteelSpec($state))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('double_thickness')
                                    ->label('Diameter / Thickness(mm)')
                                    ->placeholder('Contoh: 3.0 atau 4.5')
                                    ->helperText('Isi ukuran diameter / ketebalan dalam mm (tanpa teks mm)')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $clean = trim(preg_replace('/[a-zA-Z\s]+/', '', $state));
                                            $set('double_thickness', $clean);
                                            $set('diameter', $clean);
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('diameter'),
                            ])->columns(3),

                        // SECTION 3: Penetapan Harga & Kurs Pasterner
                        Forms\Components\Section::make('3. Penetapan Harga & Kurs Pasterner')
                            ->description('Cukup masukkan Coil/Wire ($) dan Gap/MT ($), harga lainnya otomatis dihitung')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\TextInput::make('coil_price')
                                    ->label('Coil / Wire (USD / kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('0.0000')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $coil = (float) ($state ?? 0);
                                        $rate = (float) ($get('exchange_rate') ?? 1);
                                        $sheet = (float) ($get('sheet_price') ?? 0);

                                        $set('coil_price_idr', round($coil * $rate, 2));

                                        if ($sheet > 0 && $coil > 0) {
                                            $gapKg = round($sheet - $coil, 4);
                                            $gapUsd = round(($sheet - $coil) * 1000, 4);
                                            $set('gap_steel_usd', $gapUsd);
                                            $set('gap_steel_kg_usd', $gapKg);
                                        } elseif ($coil > 0 && empty($sheet)) {
                                            $gapKg = (float) ($get('gap_steel_kg_usd') ?? 0);
                                            if ($gapKg != 0) {
                                                $calcSheet = round($coil + $gapKg, 4);
                                                $set('sheet_price', $calcSheet);
                                                $set('sheet_price_idr', round($calcSheet * $rate, 2));
                                            }
                                        }
                                    })
                                    ->helperText('Harga material bentuk Coil / Wire ($/kg)')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('gap_steel_usd')
                                    ->label('Gap / MT ( USD )')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Contoh: -20 atau 50')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $val = (float) ($state ?? 0);
                                        $kg = round($val / 1000, 4);
                                        $set('gap_steel_kg_usd', $kg);

                                        $coil = (float) ($get('coil_price') ?? 0);
                                        if ($coil > 0) {
                                            $sheet = round($coil + $kg, 4);
                                            $set('sheet_price', $sheet);
                                            $rate = (float) ($get('exchange_rate') ?? 1);
                                            $set('sheet_price_idr', round($sheet * $rate, 2));
                                        }
                                    })
                                    ->helperText('Gap per MT (USD/MT)')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('exchange_rate')
                                    ->label('Ex Rate ( IDR )')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->required()
                                    ->prefix('Rp')
                                    ->default(function (Get $get) {
                                        $year = (int) ($get('year') ?? date('Y'));

                                        return Currency::where('code', 'USD')->where('year', $year)->first()?->exchange_rate_to_idr
                                            ?? Currency::where('code', 'USD')->first()?->exchange_rate_to_idr
                                            ?? 16000;
                                    })
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $rate = (float) ($state ?? 1);
                                        $coilPrice = (float) ($get('coil_price') ?? 0);
                                        $sheetPrice = (float) ($get('sheet_price') ?? 0);

                                        $set('coil_price_idr', round($coilPrice * $rate, 2));
                                        $set('sheet_price_idr', round($sheetPrice * $rate, 2));
                                    })
                                    ->helperText('Kurs nilai tukar ke Rupiah')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('sheet_price')
                                    ->label('Sheet / Rod (USD / kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Otomatis: Coil + Gap')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $sheet = (float) ($state ?? 0);
                                        $rate = (float) ($get('exchange_rate') ?? 1);
                                        $coil = (float) ($get('coil_price') ?? 0);

                                        $set('sheet_price_idr', round($sheet * $rate, 2));

                                        if ($sheet > 0 && $coil > 0) {
                                            $gapKg = round($sheet - $coil, 4);
                                            $gapUsd = round(($sheet - $coil) * 1000, 4);
                                            $set('gap_steel_usd', $gapUsd);
                                            $set('gap_steel_kg_usd', $gapKg);
                                        }
                                    })
                                    ->helperText('Otomatis: Coil/Wire ($) + Gap ($)')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('gap_steel_kg_usd')
                                    ->label('Gap / Kg ( USD )')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->prefix('$')
                                    ->placeholder('Otomatis: Gap MT ÷ 1000')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $val = (float) ($state ?? 0);
                                        $mt = round($val * 1000, 4);
                                        $set('gap_steel_usd', $mt);

                                        $coil = (float) ($get('coil_price') ?? 0);
                                        if ($coil > 0) {
                                            $sheet = round($coil + $val, 4);
                                            $set('sheet_price', $sheet);
                                            $rate = (float) ($get('exchange_rate') ?? 1);
                                            $set('sheet_price_idr', round($sheet * $rate, 2));
                                        }
                                    })
                                    ->helperText('Rumus: Gap / MT ÷ 1.000')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('coil_price_idr')
                                    ->label('Coil (IDR)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->placeholder('Otomatis: Coil × Kurs')
                                    ->helperText('Otomatis: Coil × Ex Rate')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('sheet_price_idr')
                                    ->label('Sheet (IDR)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->placeholder('Otomatis: Sheet × Kurs')
                                    ->helperText('Otomatis: Sheet × Ex Rate')
                                    ->columnSpan(1),
                            ])->columns(3),

                        // SECTION 4: Opsi Lanjutan & Catatan (Terlipat Rapi)
                        Forms\Components\Section::make('4. Opsi Lanjutan & Catatan (Opsional)')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'Active' => 'Active (Sedang Berlaku)',
                                        'Draft' => 'Draft (Dalam Pengajuan)',
                                        'Archived' => 'Archived (Arsip)',
                                    ])
                                    ->default('Active')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan Pasterner')
                                    ->placeholder('Misal: Berdasarkan surat kesepakatan harga No. SKH-2026/PAS-01...')
                                    ->columnSpanFull(),
                            ])->columns(2),
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
                    ->formatStateUsing(fn ($state) => Fastener::cleanSteelSpec($state))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('double_thickness')
                    ->label('Thickness(mm)')
                    ->wrapHeader()
                    ->formatStateUsing(function ($state, Fastener $record) {
                        $val = $state ?: $record->diameter;
                        if ($val === null || $val === '') {
                            return '-';
                        }

                        return trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $val));
                    })
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('gap_steel_usd')
                    ->label('Gap/MT (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, Fastener $record) => $state !== null && (float) $state != 0 ? "{$record->currency_code} ".number_format((float) $state, 2) : '-')
                    ->weight('bold')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('gap_steel_kg_usd')
                    ->label('Gap/Kg (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(function ($state, Fastener $record) {
                        if ($state !== null && (float) $state != 0) {
                            return "{$record->currency_code} ".number_format((float) $state, 4);
                        }
                        if ($record->gap_steel_usd !== null && (float) $record->gap_steel_usd != 0) {
                            return "{$record->currency_code} ".number_format((float) $record->gap_steel_usd / 1000, 4);
                        }

                        return '-';
                    })
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Ex Rate (IDR)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('coil_price')
                    ->label('Coil (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(fn ($state, Fastener $record) => $state && (float) $state > 0 ? "{$record->currency_code} ".number_format((float) $state, 4) : '-')
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
                    ->formatStateUsing(fn ($state, Fastener $record) => $state && (float) $state > 0 ? "{$record->currency_code} ".number_format((float) $state, 4) : '-')
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
                        'Q1' => 'Q1 (Kuartal 1)',
                        'Q2' => 'Q2 (Kuartal 2)',
                        'Q3' => 'Q3 (Kuartal 3)',
                        'Q4' => 'Q4 (Kuartal 4)',
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
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit Pasterner'),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus Pasterner'),
            ])
            ->columnToggleFormColumns(2)
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
