<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialSteelResource\Pages;
use App\Models\Currency;
use App\Models\Material;
use App\Models\MaterialSteel;
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
        if ($supplierId) {
            $prevRecord = MaterialSteel::where('supplier_id', $supplierId)
                ->when($steelSpec, fn ($q) => $q->where('steel_spec', $steelSpec))
                ->where(function ($q) use ($info) {
                    $q->where('quarter', $info['prev_quarter'])
                        ->where('year', $info['prev_year']);
                })
                ->orWhere(function ($q) use ($supplierId) {
                    $q->where('supplier_id', $supplierId)
                        ->orderBy('id', 'desc');
                })
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        // Auto-Isi dari File Excel (Opsional - Terlipat rapi)
                        Forms\Components\Section::make('Auto-Deteksi & Isi dari File Excel (.xlsx / .csv)')
                            ->description('Unggah file Excel untuk membaca kolom dan mengisi formulir di bawah secara instan dan otomatis')
                            ->icon('heroicon-o-arrow-up-tray')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\FileUpload::make('excel_auto_fill')
                                    ->label('Pilih File Excel (.xlsx / .csv)')
                                    ->helperText('Setelah file diunggah, data baris pertama akan otomatis dideteksi dan mengisikan spesifikasi, ketebalan, dan harga ke formulir.')
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

                        // SECTION 1: Periode & Spesifikasi Material Baja
                        Forms\Components\Section::make('1. Periode & Spesifikasi Material Baja')
                            ->description('Pilih periode, supplier, dan spesifikasi material baja')
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
                                        // Auto-match currency for newly selected year
                                        $currId = $get('currency_id');
                                        if ($currId && $state) {
                                            $curr = Currency::find($currId);
                                            if ($curr) {
                                                $match = Currency::where('code', $curr->code)
                                                    ->where('year', (int) $state)
                                                    ->first();
                                                if ($match) {
                                                    $set('currency_id', $match->id);
                                                    $set('currency_code', $match->code);
                                                    $rate = (float) $match->exchange_rate_to_idr;
                                                    $set('exchange_rate', $rate);
                                                    $coil = (float) ($get('coil_price') ?? 0);
                                                    $sheet = (float) ($get('sheet_price') ?? 0);
                                                    $set('coil_price_idr', round($coil * $rate, 2));
                                                    $set('sheet_price_idr', round($sheet * $rate, 2));
                                                }
                                            }
                                        }
                                        static::updateHistoricalLookback($get, $set);
                                    }),

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
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateHistoricalLookback($get, $set)),
                            ])->columns(2),

                        // SECTION 2: Identitas Vendor & Spesifikasi Baja
                        Forms\Components\Section::make('2. Identitas & Spesifikasi Baja')
                            ->description('Pilih supplier (PT HSSI / PT USCI), spesifikasi, ketebalan, serta acuan kurs dan gap steel')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\Select::make('supplier_id')
                                    ->label('Pilih Supplier (PT HSSI / PT USCI) / Tambah Baru (+)')
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
                                            ->maxLength(50)
                                            ->helperText('Kode unik identitas vendor supplier baja'),

                                        Forms\Components\Select::make('category')
                                            ->label('Kategori')
                                            ->options([
                                                'Steel' => 'Steel (Baja / Raw Material)',
                                                'Component' => 'Component (Part Komponen)',
                                                'Packaging' => 'Packaging',
                                                'Fastener' => 'Fastener',
                                                'Chemical' => 'Chemical',
                                            ])
                                            ->default('Steel')
                                            ->required(),

                                        Forms\Components\TextInput::make('contact_person')
                                            ->label('Contact Person (PIC)')
                                            ->placeholder('Nama kontak Sales / Marketing')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('phone')
                                            ->label('No. Telepon / WhatsApp')
                                            ->placeholder('021-... atau 0812...')
                                            ->tel()
                                            ->maxLength(50),
                                    ])
                                    ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                        return $action
                                            ->modalHeading('Bikin Supplier Baru Langsung')
                                            ->modalButton('Simpan & Gunakan Supplier')
                                            ->modalWidth('lg');
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
                                        static::updateHistoricalLookback($get, $set);
                                    })
                                    ->helperText('Pilihan utama: PT HSSI & PT USCI. Klik tanda (+) untuk membuat supplier baru')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('supplier_name')
                                    ->label('Supplier Name (Nama Supplier / Pabrikan)')
                                    ->placeholder('Ketik sendiri nama supplier atau pilih dari dropdown')
                                    ->required(fn (Get $get) => empty($get('supplier_id')))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state && empty($get('vendor_code'))) {
                                            $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $state), 0, 5));
                                            $set('vendor_code', 'VND-'.($clean ?: 'SUP').'-'.rand(10, 99));
                                        }
                                        static::updateHistoricalLookback($get, $set);
                                    })
                                    ->helperText('Otomatis terisi dari pilihan supplier atau ketik sendiri')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('vendor_code')
                                    ->label('Vendor Code (Kode Supplier)')
                                    ->placeholder('Contoh: VND-HSSI atau VND-USCI')
                                    ->required(fn (Get $get) => empty($get('supplier_id')))
                                    ->helperText('Otomatis terisi atau ketik sendiri kode supplier')
                                    ->columnSpan(1),

                                Forms\Components\Select::make('material_id')
                                    ->label('Pilih dari Master Material (Opsional)')
                                    ->relationship('material', 'spec', fn ($query) => $query->where('main_category', 'Steel'))
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Pilih material baja yang sudah terdaftar...')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $material = Material::find($state);
                                            if ($material) {
                                                $set('steel_spec', $material->spec);
                                                if ($material->basic_rate > 0) {
                                                    $set('coil_price', (float) $material->basic_rate);
                                                }
                                                if ($material->currency_id) {
                                                    $set('currency_id', $material->currency_id);
                                                    $currency = Currency::find($material->currency_id);
                                                    if ($currency) {
                                                        $set('currency_code', $currency->code);
                                                        $set('exchange_rate', (float) $currency->exchange_rate_to_idr);
                                                        $rate = (float) $currency->exchange_rate_to_idr;
                                                        $set('coil_price_idr', round((float) $material->basic_rate * $rate, 2));
                                                        $sheet = (float) ($get('sheet_price') ?? 0);
                                                        $gapUsd = round(($sheet - (float) $material->basic_rate) * 1000, 4);
                                                        $set('gap_steel_usd', $gapUsd);
                                                    }
                                                }
                                            }
                                        }
                                        static::updateHistoricalLookback($get, $set);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Select::make('currency_id')
                                    ->label('Mata Uang & Kurs Patokan (Currency)')
                                    ->options(function (Get $get) {
                                        $selectedYear = (int) ($get('year') ?? date('Y'));

                                        return Currency::orderByRaw('CASE WHEN year = ? THEN 0 ELSE 1 END', [$selectedYear])
                                            ->orderBy('year', 'desc')
                                            ->orderBy('code')
                                            ->get()
                                            ->mapWithKeys(function (Currency $curr) use ($selectedYear) {
                                                $match = $curr->year == $selectedYear ? ' [Tahun Sesuai]' : '';

                                                return [$curr->id => "{$curr->code} ({$curr->name}) - Tahun {$curr->year} | Rp ".number_format($curr->exchange_rate_to_idr, 2, ',', '.').$match];
                                            });
                                    })
                                    ->default(function (Get $get) {
                                        $year = (int) ($get('year') ?? date('Y'));

                                        return Currency::where('code', 'USD')->where('year', $year)->first()?->id
                                            ?? Currency::where('code', 'USD')->first()?->id
                                            ?? Currency::first()?->id;
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $curr = Currency::find($state);
                                            if ($curr) {
                                                $set('currency_code', $curr->code);
                                                $rate = (float) $curr->exchange_rate_to_idr;
                                                $set('exchange_rate', $rate);

                                                $coilPrice = (float) ($get('coil_price') ?? 0);
                                                $sheetPrice = (float) ($get('sheet_price') ?? 0);

                                                $set('coil_price_idr', round($coilPrice * $rate, 2));
                                                $set('sheet_price_idr', round($sheetPrice * $rate, 2));
                                            }
                                        }
                                    })
                                    ->helperText('Otomatis menarik kurs acuan berdasarkan mata uang dan tahun')
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('currency_code')
                                    ->default('USD'),

                                Forms\Components\TextInput::make('steel_spec')
                                    ->label('Spec (Spesifikasi Baja)')
                                    ->placeholder('Contoh: SPCC, SPHC, SPH270-OD')
                                    ->helperText('Hanya kode spesifikasi grade baja tanpa angka ketebalan dan mm (contoh: SPCC, SPH270-OD). Ketebalan diisi pada kolom Thickness(mm).')
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
                                    })
                                    ->dehydrateStateUsing(fn (?string $state) => MaterialSteel::cleanSteelSpec($state))
                                    ->columnSpan(1),

                                // BARIS THICKNESS(MM)
                                Forms\Components\TextInput::make('double_thickness')
                                    ->label('Thickness(mm)')
                                    ->placeholder('Contoh: 1.2 atau 2.0')
                                    ->helperText('Isi angka ketebalan dalam mm (tanpa tulisan mm)')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $clean = trim(preg_replace('/[a-zA-Z\s]+/', '', $state));
                                            $set('double_thickness', $clean);
                                        }
                                    })
                                    ->columnSpan(1),

                                // FIELDSET PERHITUNGAN GAP & CURRENCY (SESUAI EXCEL: GAP/MT, GAP/KG DENGAN RUMUS =GAP/MT/1000, EX RATE)
                                Forms\Components\Fieldset::make('Currency & Gap Perhitungan Baja')
                                    ->schema([
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
                                            ->helperText('Nilai gap per Metric Ton (USD/MT) — contoh: -20, 50')
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('gap_steel_kg_usd')
                                            ->label('Gap / Kg ( USD )')
                                            ->numeric()
                                            ->step(0.0001)
                                            ->prefix('$')
                                            ->placeholder('Contoh: -0.02 atau 0.05')
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
                                            ->helperText('Rumus Excel: = Gap / MT (USD) ÷ 1.000 (contoh: -0.02, 0.05)')
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('exchange_rate')
                                            ->label('Ex Rate ( IDR )')
                                            ->numeric()
                                            ->step(0.0001)
                                            ->required()
                                            ->prefix('Rp')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $rate = (float) ($state ?? 1);
                                                $coilPrice = (float) ($get('coil_price') ?? 0);
                                                $sheetPrice = (float) ($get('sheet_price') ?? 0);

                                                $set('coil_price_idr', round($coilPrice * $rate, 2));
                                                $set('sheet_price_idr', round($sheetPrice * $rate, 2));
                                            })
                                            ->helperText('Kurs acuan mata uang ke Rupiah (contoh: 18000, 20000)')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ])->columns(3),

                        // SECTION 3: Hasil Penetapan Harga Baja (Coil & Sheet)
                        Forms\Components\Section::make('3. Hasil Penetapan Harga Baja (Coil & Sheet)')
                            ->description('Kalkulasi harga baja bentuk Coil dan Sheet dalam USD dan konversi ke Rupiah (IDR)')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\Fieldset::make('Harga Bentuk Coil & Sheet (Baja Gulungan & Lembaran)')
                                    ->schema([
                                        Forms\Components\TextInput::make('coil_price')
                                            ->label('Coil (USD)')
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

                                                $gapKg = round($sheet - $coil, 4);
                                                $gapUsd = round(($sheet - $coil) * 1000, 4);
                                                $set('gap_steel_usd', $gapUsd);
                                                $set('gap_steel_kg_usd', $gapKg);
                                            })
                                            ->helperText('Harga baja bentuk gulungan/coil ($/kg)')
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('coil_price_idr')
                                            ->label('Coil (IDR)')
                                            ->prefix('Rp')
                                            ->readOnly()
                                            ->dehydrated()
                                            ->helperText('Otomatis dihitung: Coil (USD) × Kurs')
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('sheet_price')
                                            ->label('Sheet (USD)')
                                            ->numeric()
                                            ->step(0.0001)
                                            ->prefix('$')
                                            ->placeholder('0.0000')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $sheet = (float) ($state ?? 0);
                                                $rate = (float) ($get('exchange_rate') ?? 1);
                                                $coil = (float) ($get('coil_price') ?? 0);

                                                $set('sheet_price_idr', round($sheet * $rate, 2));

                                                $gapKg = round($sheet - $coil, 4);
                                                $gapUsd = round(($sheet - $coil) * 1000, 4);
                                                $set('gap_steel_usd', $gapUsd);
                                                $set('gap_steel_kg_usd', $gapKg);
                                            })
                                            ->helperText('Harga baja bentuk lembaran/sheet ($/kg)')
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('sheet_price_idr')
                                            ->label('Sheet (IDR)')
                                            ->prefix('Rp')
                                            ->readOnly()
                                            ->dehydrated()
                                            ->helperText('Otomatis dihitung: Sheet (USD) × Kurs')
                                            ->columnSpan(1),
                                    ])->columns(2)->columnSpanFull(),

                                Forms\Components\TextInput::make('fluktuasi_jfe')
                                    ->label('Fluktuasi JFE Surcharge ($ / kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->default(0)
                                    ->helperText('Opsional: Penyesuaian indeks fluktuasi baja JFE')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        // SECTION 4: Status & Catatan
                        Forms\Components\Section::make('4. Informasi Tambahan')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status Kontrak')
                                    ->options([
                                        'Active' => 'Active (Sedang Berlaku)',
                                        'Draft' => 'Draft (Dalam Pengajuan)',
                                        'Archived' => 'Archived (Arsip / Kedaluwarsa)',
                                    ])
                                    ->default('Active')
                                    ->required(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan Negosiasi / Kontrak Supplier')
                                    ->placeholder('Misal: Berdasarkan surat kesepakatan harga No. SKH-2026/KP-01 tanggal 15...')
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

                Tables\Columns\TextColumn::make('gap_steel_kg_usd')
                    ->label('Gap/Kg (USD)')
                    ->wrapHeader()
                    ->formatStateUsing(function ($state, MaterialSteel $record) {
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
