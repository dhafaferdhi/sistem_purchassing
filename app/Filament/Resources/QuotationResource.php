<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Models\GlobalVariable;
use App\Models\Material;
use App\Models\Process;
use App\Models\Quotation;
use App\Services\CostCalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationGroup = 'Kalkulator & Purchasing';

    protected static ?string $navigationLabel = 'Estimasi Biaya (Quotation)';

    protected static ?int $navigationSort = 3;

    public static function updateCalculations(Get $get, Set $set): void
    {
        $category = $get('category') ?? '';
        $weight = (float) ($get('weight') ?? 0);
        $basicRate = (float) ($get('snapshot_basic_rate') ?? 0);
        $exchangeRate = (float) ($get('snapshot_exchange_rate') ?? 20000);
        $fluktuasi = (float) ($get('snapshot_fluktuasi') ?? 0);

        // Scrap Recovery Calculation
        $grossWeight = (float) ($get('gross_weight') ?? 0);
        $netWeight = (float) ($get('net_weight') ?? 0);
        $scrapPricePerKg = (float) ($get('scrap_price_per_kg') ?? 0);

        if ($grossWeight > 0 && $netWeight > 0) {
            $scrap = CostCalculationService::calculateScrapRecovery($grossWeight, $netWeight, $scrapPricePerKg);
            $set('scrap_weight', $scrap['scrap_weight']);
            $set('scrap_recovery_value', $scrap['scrap_recovery_value']);
            if ($weight <= 0) {
                $weight = $grossWeight;
                $set('weight', $grossWeight);
            }
        }

        // 1. Hitung Price IDR
        $priceIdr = CostCalculationService::calculatePriceIdr(
            $category,
            $weight,
            $basicRate,
            $exchangeRate,
            $fluktuasi
        );

        // Kurangkan Nilai Scrap Recovery jika ada
        $scrapRecovery = (float) ($get('scrap_recovery_value') ?? 0);
        if ($scrapRecovery > 0) {
            $priceIdr = CostCalculationService::calculateNetMaterialCost($priceIdr, $scrapRecovery);
        }
        $set('price_idr', $priceIdr);

        // 2. Hitung Total Biaya Proses dari Repeater
        $processes = $get('quotationProcesses') ?? [];
        $totalProcessCost = 0.0;
        foreach ($processes as $proc) {
            $totalProcessCost += (float) ($proc['applied_cost'] ?? 0);
        }
        $totalProcessCost = round($totalProcessCost, 2);
        $set('total_process_cost', $totalProcessCost);

        // 3. Hitung Profit Margin (default 10%)
        $profitPercentage = (float) ($get('profit_percentage') ?? 10);
        $profit = CostCalculationService::calculateProfit($priceIdr, $totalProcessCost, $profitPercentage);
        $set('profit', $profit);

        // 4. Hitung Logistics & Grand Total Cost
        $transport = (float) ($get('transport') ?? 100);
        $packing = (float) ($get('packing') ?? 50);
        $totalCost = CostCalculationService::calculateTotalCost(
            $priceIdr,
            $totalProcessCost,
            $profit,
            $transport,
            $packing
        );
        $set('total_cost', $totalCost);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        // SECTION 1: Informasi Dokumen & Spesifikasi Produk
                        Forms\Components\Section::make('1. Spesifikasi Komponen & Material')
                            ->description('Pilih material dan input spesifikasi teknis produk')
                            ->icon('heroicon-o-cube')
                            ->schema([
                                Forms\Components\TextInput::make('document_number')
                                    ->label('No. Dokumen')
                                    ->placeholder('[Dibuat otomatis oleh sistem]')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('user_id')
                                    ->default(fn () => Auth::id()),

                                Forms\Components\TextInput::make('drawing_number')
                                    ->label('No. Drawing Teknik')
                                    ->placeholder('Contoh: DWG-STP-001')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('revision_number')
                                    ->label('Revisi')
                                    ->default('Rev 0')
                                    ->columnSpan(1),

                                Forms\Components\Select::make('material_id')
                                    ->label('Pilih Material')
                                    ->options(function () {
                                        return Material::where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(function ($m) {
                                                return [$m->id => "{$m->spec} ({$m->category}) - Rate: \${$m->basic_rate}"];
                                            });
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        if (! $state) {
                                            return;
                                        }
                                        $material = Material::find($state);
                                        if ($material) {
                                            $set('category', $material->category);
                                            $set('snapshot_basic_rate', $material->basic_rate);

                                            // Ambil snapshot exchange rate & fluktuasi
                                            $exRate = GlobalVariable::getValue('exchange_rate', 20000.0);
                                            $set('snapshot_exchange_rate', $exRate);

                                            if ($material->category === 'Presspart') {
                                                $fluktuasi = GlobalVariable::getValue('fluktuasi_jfe', -0.02);
                                                $set('snapshot_fluktuasi', $fluktuasi);
                                            } else {
                                                $set('snapshot_fluktuasi', 0.0);
                                            }

                                            static::updateCalculations($get, $set);
                                        }
                                    })
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('category')
                                    ->label('Kategori')
                                    ->readOnly()
                                    ->required()
                                    ->columnSpan(1),

                                // Field Dinamis Fastener
                                Forms\Components\TextInput::make('diameter')
                                    ->label('Diameter (mm)')
                                    ->placeholder('Contoh: 6')
                                    ->visible(fn (Get $get) => $get('category') === 'Fastener')
                                    ->columnSpan(1),

                                // Field Dinamis Presspart
                                Forms\Components\TextInput::make('thickness')
                                    ->label('Thickness / Ketebalan (mm)')
                                    ->placeholder('Contoh: 2')
                                    ->visible(fn (Get $get) => $get('category') === 'Presspart')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('size_material')
                                    ->label('Size Material')
                                    ->placeholder('Contoh: 200X1219')
                                    ->visible(fn (Get $get) => $get('category') === 'Presspart')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('cavity_count')
                                    ->label('Jumlah Cavity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('weight')
                                    ->label('Weight (Berat komponen - kg)')
                                    ->required()
                                    ->numeric()
                                    ->step(0.0001)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateCalculations($get, $set))
                                    ->helperText('Contoh: 0.05 untuk Fastener, 0.15 untuk Presspart')
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('validity_until')
                                    ->label('Masa Berlaku Penawaran')
                                    ->default(now()->addDays(30))
                                    ->columnSpan(1),

                                Forms\Components\FileUpload::make('drawing_file')
                                    ->label('Lampiran Drawing (CAD / PDF / Gambar)')
                                    ->disk('public')
                                    ->directory('drawings')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->columnSpan(2),
                            ])->columns(3),

                        // SECTION 1.5: Scrap Recovery & Efisiensi Material
                        Forms\Components\Section::make('Efisiensi Material & Scrap Recovery (Khusus Presspart)')
                            ->description('Perhitungan sisa potongan plat besi / scrap untuk mengurangi HPP')
                            ->icon('heroicon-o-arrow-path')
                            ->collapsible()
                            ->schema([
                                Forms\Components\TextInput::make('gross_weight')
                                    ->label('Gross Weight (kg) - Lembaran Awal')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateCalculations($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('net_weight')
                                    ->label('Net Weight (kg) - Part Jadi')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateCalculations($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('scrap_weight')
                                    ->label('Scrap Weight (kg)')
                                    ->readOnly()
                                    ->numeric()
                                    ->step(0.0001)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('scrap_price_per_kg')
                                    ->label('Harga Jual Scrap Besi / kg (IDR)')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateCalculations($get, $set))
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('scrap_recovery_value')
                                    ->label('Scrap Recovery (Pengurang HPP)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->columnSpan(2),
                            ])->columns(3),

                        // SECTION 2: Tahapan Proses Manufaktur
                        Forms\Components\Section::make('2. Tahapan Proses Manufaktur')
                            ->description('Tambahkan tahapan proses yang dilalui produk beserta biayanya')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Repeater::make('quotationProcesses')
                                    ->relationship('quotationProcesses')
                                    ->schema([
                                        Forms\Components\Select::make('process_id')
                                            ->label('Pilih Proses')
                                            ->options(function (Get $get) {
                                                // Filter proses sesuai kategori atau Both
                                                $category = $get('../../category');
                                                $query = Process::where('is_active', true);
                                                if ($category) {
                                                    $query->whereIn('category', [$category, 'Both']);
                                                }

                                                return $query->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if (! $state) {
                                                    return;
                                                }
                                                $proc = Process::find($state);
                                                if ($proc) {
                                                    $set('process_name', $proc->name);
                                                    $set('applied_cost', $proc->default_cost);
                                                }
                                            })
                                            ->columnSpan(2),

                                        Forms\Components\Hidden::make('process_name'),

                                        Forms\Components\TextInput::make('applied_cost')
                                            ->label('Biaya Proses (IDR)')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->step(0.01)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(1),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('+ Tambah Proses')
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateCalculations($get, $set)),
                            ]),
                    ])->columnSpan(2),

                // SECTION 3: Panel Hasil Kalkulasi & Biaya (Kanan)
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Ringkasan Biaya (Cost Summary)')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\TextInput::make('snapshot_basic_rate')
                                    ->label('Basic Rate (USD)')
                                    ->prefix('$')
                                    ->readOnly(),

                                Forms\Components\TextInput::make('snapshot_exchange_rate')
                                    ->label('Exchange Rate (IDR)')
                                    ->prefix('Rp')
                                    ->readOnly(),

                                Forms\Components\TextInput::make('snapshot_fluktuasi')
                                    ->label('Fluktuasi JFE')
                                    ->readOnly()
                                    ->helperText('Hanya berlaku untuk Presspart (misal: -0.02)'),

                                Forms\Components\TextInput::make('price_idr')
                                    ->label('Harga Material (Price IDR)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->extraInputAttributes(['class' => 'font-bold text-primary-600']),

                                Forms\Components\TextInput::make('total_process_cost')
                                    ->label('Total Biaya Proses')
                                    ->prefix('Rp')
                                    ->readOnly(),

                                Forms\Components\TextInput::make('profit_percentage')
                                    ->label('Margin Profit (%)')
                                    ->suffix('%')
                                    ->default(10)
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateCalculations($get, $set)),

                                Forms\Components\TextInput::make('profit')
                                    ->label('Nominal Profit (IDR)')
                                    ->prefix('Rp')
                                    ->readOnly(),

                                Forms\Components\TextInput::make('transport')
                                    ->label('Biaya Transport (IDR)')
                                    ->prefix('Rp')
                                    ->default(100)
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateCalculations($get, $set)),

                                Forms\Components\TextInput::make('packing')
                                    ->label('Biaya Packing (IDR)')
                                    ->prefix('Rp')
                                    ->default(50)
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateCalculations($get, $set)),

                                Forms\Components\TextInput::make('total_cost')
                                    ->label('GRAND TOTAL COST (HPP)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->extraInputAttributes([
                                        'class' => 'font-extrabold text-lg text-emerald-600 bg-emerald-50 dark:bg-emerald-950 p-2 rounded',
                                    ]),

                                Forms\Components\Select::make('status')
                                    ->label('Status Dokumen')
                                    ->options([
                                        'draft' => 'Draft',
                                        'submitted' => 'Diajukan (Submitted)',
                                        'approved' => 'Disetujui (Approved)',
                                        'rejected' => 'Ditolak (Rejected)',
                                    ])
                                    ->default('draft')
                                    ->required(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan Tambahan')
                                    ->rows(3),
                            ]),
                    ])->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->weight('bold')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Fastener' => 'info',
                        'Presspart' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.spec')
                    ->label('Spec Material')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('drawing_number')
                    ->label('No. Drawing')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('revision_number')
                    ->label('Rev')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Berat (kg)')
                    ->formatStateUsing(fn ($state) => number_format($state, 4).' kg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('scrap_recovery_value')
                    ->label('Scrap Recovery')
                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('price_idr')
                    ->label('Price IDR')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_process_cost')
                    ->label('Biaya Proses')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('profit')
                    ->label('Profit')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->weight('bold')
                    ->color('success')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Diajukan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Estimator')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'Fastener' => 'Fastener',
                        'Presspart' => 'Presspart',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Diajukan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                // Aksi Unduh PDF
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn (Quotation $record) => route('quotations.pdf', $record))
                    ->openUrlInNewTab(),

                // Aksi Submit for Approval
                Tables\Actions\Action::make('submit')
                    ->label('Ajukan')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (Quotation $record) => in_array($record->status, ['draft', 'rejected']))
                    ->requiresConfirmation()
                    ->action(function (Quotation $record) {
                        $record->update(['status' => 'submitted']);
                        Notification::make()
                            ->title('Quotation berhasil diajukan untuk persetujuan.')
                            ->success()
                            ->send();
                    }),

                // Aksi Approve (Khusus Approver / Admin)
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Quotation $record) => $record->status === 'submitted' && (Auth::user()?->isAdmin() || Auth::user()?->isApprover()))
                    ->requiresConfirmation()
                    ->action(function (Quotation $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                        Notification::make()
                            ->title("Dokumen {$record->document_number} telah disetujui.")
                            ->success()
                            ->send();
                    }),

                // Aksi Reject
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Quotation $record) => $record->status === 'submitted' && (Auth::user()?->isAdmin() || Auth::user()?->isApprover()))
                    ->form([
                        Forms\Components\Textarea::make('rejection_note')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function (Quotation $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'notes' => ($record->notes ? $record->notes."\n" : '').'[Ditolak]: '.$data['rejection_note'],
                        ]);
                        Notification::make()
                            ->title("Dokumen {$record->document_number} telah ditolak.")
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('1. Informasi Dokumen & Spesifikasi Produk')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('document_number')
                            ->label('No. Dokumen')
                            ->weight('bold')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'submitted' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('category')
                            ->label('Kategori')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Fastener' => 'info',
                                'Presspart' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('material.spec')
                            ->label('Material Spec')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('drawing_number')
                            ->label('No. Drawing')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('revision_number')
                            ->label('Revisi')
                            ->badge(),
                        Infolists\Components\TextEntry::make('cavity_count')
                            ->label('Jumlah Cavity')
                            ->placeholder('1'),
                        Infolists\Components\TextEntry::make('validity_until')
                            ->label('Masa Berlaku')
                            ->date('d M Y')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('diameter')
                            ->label('Diameter (mm)')
                            ->visible(fn (Quotation $record) => $record->category === 'Fastener'),
                        Infolists\Components\TextEntry::make('thickness')
                            ->label('Thickness (mm)')
                            ->visible(fn (Quotation $record) => $record->category === 'Presspart'),
                        Infolists\Components\TextEntry::make('size_material')
                            ->label('Size Material')
                            ->visible(fn (Quotation $record) => $record->category === 'Presspart'),
                        Infolists\Components\TextEntry::make('weight')
                            ->label('Weight (Berat)')
                            ->formatStateUsing(fn ($state) => number_format($state, 4).' kg'),
                        Infolists\Components\TextEntry::make('gross_weight')
                            ->label('Gross Weight')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 4).' kg' : '-')
                            ->visible(fn (Quotation $record) => (float) $record->gross_weight > 0),
                        Infolists\Components\TextEntry::make('net_weight')
                            ->label('Net Weight')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 4).' kg' : '-')
                            ->visible(fn (Quotation $record) => (float) $record->net_weight > 0),
                        Infolists\Components\TextEntry::make('scrap_recovery_value')
                            ->label('Scrap Recovery')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                            ->visible(fn (Quotation $record) => (float) $record->scrap_recovery_value > 0),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Estimator'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Tanggal Pembuatan')
                            ->dateTime('d M Y H:i'),
                    ])->columns(3),

                Infolists\Components\Section::make('2. Parameter Pasar (Terkunci saat Kalkulasi)')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Infolists\Components\TextEntry::make('snapshot_basic_rate')
                            ->label('Basic Rate')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('snapshot_exchange_rate')
                            ->label('Exchange Rate (IDR)')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 0, ',', '.')),
                        Infolists\Components\TextEntry::make('snapshot_fluktuasi')
                            ->label('Fluktuasi JFE')
                            ->formatStateUsing(fn ($state, Quotation $record) => $record->category === 'Presspart' ? number_format($state, 4) : 'N/A'),
                        Infolists\Components\TextEntry::make('price_idr')
                            ->label('Harga Material (Price IDR)')
                            ->weight('bold')
                            ->color('primary')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.')),
                    ])->columns(4),

                Infolists\Components\Section::make('3. Rincian Proses Manufaktur')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('quotationProcesses')
                            ->label('Tahap Proses')
                            ->schema([
                                Infolists\Components\TextEntry::make('process_name')
                                    ->label('Nama Proses')
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('applied_cost')
                                    ->label('Biaya Terkunci')
                                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.')),
                            ])->columns(2),
                    ]),

                Infolists\Components\Section::make('4. Rekapitulasi Biaya & Grand Total')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Infolists\Components\TextEntry::make('price_idr')
                            ->label('Total Material')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.')),
                        Infolists\Components\TextEntry::make('total_process_cost')
                            ->label('Total Proses')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.')),
                        Infolists\Components\TextEntry::make('profit')
                            ->label('Profit ('.$infolist->record->profit_percentage.'%)')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.')),
                        Infolists\Components\TextEntry::make('transport')
                            ->label('Biaya Transport')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.')),
                        Infolists\Components\TextEntry::make('packing')
                            ->label('Biaya Packing')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.')),
                        Infolists\Components\TextEntry::make('total_cost')
                            ->label('GRAND TOTAL (HPP FINAL)')
                            ->weight('heavy')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->color('success')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.')),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan Dokumen')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan khusus.'),
                        Infolists\Components\TextEntry::make('approvedBy.name')
                            ->label('Disetujui Oleh')
                            ->placeholder('Belum Disetujui'),
                        Infolists\Components\TextEntry::make('approved_at')
                            ->label('Waktu Persetujuan')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                    ])->columns(3),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'material', 'approvedBy']);
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
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => Pages\ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}
