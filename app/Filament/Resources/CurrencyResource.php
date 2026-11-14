<?php

namespace App\Filament\Resources;

use App\Filament\Pages\MonthlyRollover;
use App\Filament\Resources\CurrencyResource\Pages;
use App\Jobs\RecalculateCurrencyRatesJob;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Services\CurrencyRecalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Kurs & Gap Kuartalan';

    protected static ?string $modelLabel = 'Kurs & Gap Kuartalan';

    protected static ?string $pluralModelLabel = 'Kurs & Gap Kuartalan';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('1. Periode & Patokan Kurs Mata Uang')
                    ->description('Tentukan tahun dan kuartal kontrak beserta patokan kurs Dollar (USD) dan Yen (JPY) ke Rupiah.')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        Forms\Components\Select::make('year')
                            ->label('Tahun Kontrak')
                            ->options(function () {
                                $current = (int) date('Y');
                                $years = [];
                                for ($y = $current - 2; $y <= $current + 3; $y++) {
                                    $years[$y] = (string) $y;
                                }

                                return $years;
                            })
                            ->default((int) date('Y'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                static::syncExistingPeriodDefaults($get, $set);
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
                                static::syncExistingPeriodDefaults($get, $set);
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('kurs_usd')
                            ->label('Kurs Dollar ($ ke IDR)')
                            ->prefix('Rp')
                            ->numeric()
                            ->step(0.0001)
                            ->required()
                            ->default(16000)
                            ->placeholder('16000')
                            ->helperText('Patokan nilai tukar 1 USD ke Rupiah untuk kuartal ini')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                static::recalculateFormGaps($get, $set);
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('kurs_jpy')
                            ->label('Kurs Yen (JPY ke IDR)')
                            ->prefix('Rp')
                            ->numeric()
                            ->step(0.0001)
                            ->default(105)
                            ->placeholder('105')
                            ->helperText('Nilai tukar 1 JPY ke Rupiah (opsional)')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Kuartal')
                            ->placeholder('Contoh: Kesepakatan kurs dan fluktuasi baja Q1 2027 PT. POSMI STEEL INDONESIA')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('2. Patokan Fluktuasi Gap Baja AP (Pembelian)')
                    ->description('Masukkan fluktuasi gap baja AP per MT ($/MT). Gap $/Kg dan ekuivalen Rupiah dihitung otomatis dari Kurs Dollar.')
                    ->icon('heroicon-o-shopping-bag')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('gap_ap_usd')
                                ->label(new HtmlString('Gap Baja AP<br><span class="text-xs font-normal text-gray-500">Pembelian ($/MT)</span>'))
                                ->prefix('$')
                                ->numeric()
                                ->step(0.0001)
                                ->default(0)
                                ->placeholder('-57 atau 50')
                                ->helperText('Input patokan fluktuasi pembelian baja AP')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    static::recalculateFormGaps($get, $set);
                                }),

                            Forms\Components\TextInput::make('gap_ap_kg_usd')
                                ->label(new HtmlString('Gap AP ($/Kg)<br><span class="text-xs font-normal text-gray-500">Otomatis (Gap ÷ 1.000)</span>'))
                                ->prefix('$')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('0.0000'),

                            Forms\Components\TextInput::make('gap_ap_idr')
                                ->label(new HtmlString('Gap AP (Rp/Kg)<br><span class="text-xs font-normal text-gray-500">Otomatis ($/Kg × Kurs)</span>'))
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('Rp 0'),
                        ]),

                        Forms\Components\Section::make('Rincian Spesifikasi Baja AP (Opsional jika HRC & CRC berbeda)')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('gap_hrc_usd')
                                        ->label('Gap HRC AP ($/MT)')
                                        ->prefix('$')
                                        ->numeric()
                                        ->default(0)
                                        ->placeholder('-57')
                                        ->helperText('Khusus baja Hot Rolled (SPH270C, SPH440, SPHC, SAPH)'),

                                    Forms\Components\TextInput::make('gap_crc_usd')
                                        ->label('Gap CRC AP ($/MT)')
                                        ->prefix('$')
                                        ->numeric()
                                        ->default(0)
                                        ->placeholder('-57')
                                        ->helperText('Khusus baja Cold Rolled (SPC270C, SPC440, SPCC)'),
                                ]),
                            ]),
                    ]),

                Forms\Components\Section::make('3. Patokan Fluktuasi Gap Baja AR (Penjualan)')
                    ->description('Masukkan fluktuasi gap baja AR per MT ($/MT). Gap $/Kg dan ekuivalen Rupiah dihitung otomatis dari Kurs Dollar.')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('gap_ar_usd')
                                ->label(new HtmlString('Gap Baja AR<br><span class="text-xs font-normal text-gray-500">Penjualan ($/MT)</span>'))
                                ->prefix('$')
                                ->numeric()
                                ->step(0.0001)
                                ->default(0)
                                ->placeholder('-50 atau 100')
                                ->helperText('Input patokan fluktuasi penjualan baja AR')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    static::recalculateFormGaps($get, $set);
                                }),

                            Forms\Components\TextInput::make('gap_ar_kg_usd')
                                ->label(new HtmlString('Gap AR ($/Kg)<br><span class="text-xs font-normal text-gray-500">Otomatis (Gap ÷ 1.000)</span>'))
                                ->prefix('$')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('0.0000'),

                            Forms\Components\TextInput::make('gap_ar_idr')
                                ->label(new HtmlString('Gap AR (Rp/Kg)<br><span class="text-xs font-normal text-gray-500">Otomatis ($/Kg × Kurs)</span>'))
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('Rp 0'),
                        ]),

                        Forms\Components\Section::make('Rincian Spesifikasi Baja AR (Opsional jika HRC & CRC berbeda)')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('gap_hrc_ar_usd')
                                        ->label('Gap HRC AR ($/MT)')
                                        ->prefix('$')
                                        ->numeric()
                                        ->default(0)
                                        ->placeholder('-50')
                                        ->helperText('Khusus baja Hot Rolled AR'),

                                    Forms\Components\TextInput::make('gap_crc_ar_usd')
                                        ->label('Gap CRC AR ($/MT)')
                                        ->prefix('$')
                                        ->numeric()
                                        ->default(0)
                                        ->placeholder('-50')
                                        ->helperText('Khusus baja Cold Rolled AR'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function syncExistingPeriodDefaults(Get $get, Set $set): void
    {
        $y = $get('year');
        $q = $get('quarter');
        if ($y && $q) {
            $usd = Currency::where('code', 'USD')->where('year', $y)->where('quarter', $q)->first();
            $jpy = Currency::where('code', 'JPY')->where('year', $y)->where('quarter', $q)->first();
            if ($usd) {
                $set('kurs_usd', $usd->exchange_rate_to_idr);
                $set('gap_ap_usd', $usd->gap_ap_usd ?? $usd->gap_hrc_usd ?? 0);
                $set('gap_ar_usd', $usd->gap_ar_usd ?? 0);
                $set('gap_hrc_usd', $usd->gap_hrc_usd ?? 0);
                $set('gap_crc_usd', $usd->gap_crc_usd ?? 0);
                $set('gap_hrc_ar_usd', $usd->gap_hrc_ar_usd ?? 0);
                $set('gap_crc_ar_usd', $usd->gap_crc_ar_usd ?? 0);
                $set('notes', $usd->notes);
            }
            if ($jpy) {
                $set('kurs_jpy', $jpy->exchange_rate_to_idr);
            }
            static::recalculateFormGaps($get, $set);
        }
    }

    public static function recalculateFormGaps(Get $get, Set $set): void
    {
        $rate = (float) ($get('kurs_usd') ?? 16000);
        $gapAp = (float) ($get('gap_ap_usd') ?? 0);
        $gapAr = (float) ($get('gap_ar_usd') ?? 0);

        $apKg = round($gapAp / 1000, 4);
        $arKg = round($gapAr / 1000, 4);

        $set('gap_ap_kg_usd', number_format($apKg, 4, '.', ''));
        $set('gap_ap_idr', 'Rp ' . number_format(round($apKg * $rate), 0, ',', '.'));

        $set('gap_ar_kg_usd', number_format($arKg, 4, '.', ''));
        $set('gap_ar_idr', 'Rp ' . number_format(round($arKg * $rate), 0, ',', '.'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->defaultSort(fn (Builder $query) => $query->orderBy('year', 'desc')->orderBy('quarter', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label('Periode Kuartal')
                    ->badge()
                    ->state(fn (Currency $record) => "{$record->year} - {$record->quarter}")
                    ->color(fn (Currency $record): string => match ($record->quarter) {
                        'Q1' => 'info',
                        'Q2' => 'success',
                        'Q3' => 'warning',
                        'Q4' => 'primary',
                        default => 'gray',
                    })
                    ->weight('bold')
                    ->alignCenter()
                    ->sortable(['year', 'quarter']),

                Tables\Columns\TextColumn::make('exchange_rate_to_idr')
                    ->label('Kurs Dollar ($ ke IDR)')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->weight('bold')
                    ->color('primary')
                    ->alignRight()
                    ->sortable()
                    ->tooltip('Klik untuk langsung mengubah angka Kurs Dollar')
                    ->action(
                        Tables\Actions\Action::make('quick_edit_rate_column')
                            ->label('Ubah Kurs Dollar')
                            ->modalHeading(fn (Currency $record) => "Ubah Kurs Dollar ({$record->quarter} {$record->year})")
                            ->modalDescription('Ubah patokan nilai tukar Dollar ($) ke Rupiah untuk kuartal ini. Perubahan akan otomatis menghitung ulang data pada kuartal ini.')
                            ->modalSubmitActionLabel('Simpan & Update Kurs')
                            ->form([
                                Forms\Components\TextInput::make('exchange_rate_to_idr')
                                    ->label('Kurs Dollar ($ ke IDR)')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->required()
                                    ->default(fn (Currency $record) => $record->exchange_rate_to_idr)
                                    ->helperText('Contoh: 16250'),
                            ])
                            ->action(function (Currency $record, array $data) {
                                $newRate = (float) $data['exchange_rate_to_idr'];
                                $record->update(['exchange_rate_to_idr' => $newRate]);
                                CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($record, true);
                                CurrencyRecalculationService::recalculateForCurrency($record);

                                Notification::make()
                                    ->title("Kurs Dollar ({$record->quarter} {$record->year}) Berhasil Diperbarui!")
                                    ->body("Kurs Dollar Rp " . number_format($newRate, 0, ',', '.') . " telah diterapkan ke seluruh data kuartal ini.")
                                    ->success()
                                    ->send();
                            })
                    ),

                Tables\Columns\TextColumn::make('gap_ap_usd')
                    ->label('Gap Baja AP (Pembelian)')
                    ->state(fn (Currency $record) => (float) ($record->gap_ap_usd ?? $record->gap_hrc_usd ?? 0))
                    ->formatStateUsing(fn ($state) => ($state > 0 ? "+$" : "$ ") . number_format((float) $state, 2, '.', '') . ' / MT')
                    ->description(function (Currency $record) {
                        $apMt = (float) ($record->gap_ap_usd ?? $record->gap_hrc_usd ?? 0);
                        $kg = round($apMt / 1000, 4);
                        $idr = round($kg * (float) $record->exchange_rate_to_idr);
                        return number_format($kg, 4, '.', '') . ' $/kg (Rp ' . number_format($idr, 0, ',', '.') . ')';
                    })
                    ->weight('bold')
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray'))
                    ->alignRight()
                    ->sortable()
                    ->tooltip('Klik untuk langsung mengubah Gap Baja AP')
                    ->action(
                        Tables\Actions\Action::make('quick_edit_gap_ap_col')
                            ->label('Ubah Gap Baja AP')
                            ->modalHeading(fn (Currency $record) => "Ubah Gap Baja AP ({$record->quarter} {$record->year})")
                            ->modalDescription('Masukkan patokan fluktuasi pembelian ($/MT). Gap $/kg dan ekuivalen Rupiah akan otomatis dihitung.')
                            ->modalSubmitActionLabel('Simpan & Update Gap AP')
                            ->form([
                                Forms\Components\TextInput::make('gap_ap_usd')
                                    ->label('Gap Baja AP ($/MT)')
                                    ->prefix('$')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->required()
                                    ->default(fn (Currency $record) => (float) ($record->gap_ap_usd ?? $record->gap_hrc_usd ?? 0))
                                    ->helperText('Contoh: -57 atau 50'),
                            ])
                            ->action(function (Currency $record, array $data) {
                                $newGap = (float) $data['gap_ap_usd'];
                                $record->update([
                                    'gap_ap_usd' => $newGap,
                                    'gap_hrc_usd' => $newGap,
                                    'gap_crc_usd' => $newGap,
                                ]);
                                CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($record, true);
                                CurrencyRecalculationService::recalculateForCurrency($record);

                                Notification::make()
                                    ->title("Gap Baja AP ({$record->quarter} {$record->year}) Berhasil Diperbarui!")
                                    ->body("Gap AP \${$newGap}/MT telah diterapkan ke kuartal ini.")
                                    ->success()
                                    ->send();
                            })
                    ),

                Tables\Columns\TextColumn::make('gap_ar_usd')
                    ->label('Gap Baja AR (Penjualan)')
                    ->state(fn (Currency $record) => (float) ($record->gap_ar_usd ?? 0))
                    ->formatStateUsing(fn ($state) => ($state > 0 ? "+$" : "$ ") . number_format((float) $state, 2, '.', '') . ' / MT')
                    ->description(function (Currency $record) {
                        $arMt = (float) ($record->gap_ar_usd ?? 0);
                        $kg = round($arMt / 1000, 4);
                        $idr = round($kg * (float) $record->exchange_rate_to_idr);
                        return number_format($kg, 4, '.', '') . ' $/kg (Rp ' . number_format($idr, 0, ',', '.') . ')';
                    })
                    ->weight('bold')
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray'))
                    ->alignRight()
                    ->sortable()
                    ->tooltip('Klik untuk langsung mengubah Gap Baja AR')
                    ->action(
                        Tables\Actions\Action::make('quick_edit_gap_ar_col')
                            ->label('Ubah Gap Baja AR')
                            ->modalHeading(fn (Currency $record) => "Ubah Gap Baja AR ({$record->quarter} {$record->year})")
                            ->modalDescription('Masukkan patokan fluktuasi penjualan ($/MT). Gap $/kg dan ekuivalen Rupiah akan otomatis dihitung.')
                            ->modalSubmitActionLabel('Simpan & Update Gap AR')
                            ->form([
                                Forms\Components\TextInput::make('gap_ar_usd')
                                    ->label('Gap Baja AR ($/MT)')
                                    ->prefix('$')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->required()
                                    ->default(fn (Currency $record) => (float) ($record->gap_ar_usd ?? 0))
                                    ->helperText('Contoh: -50 atau 100'),
                            ])
                            ->action(function (Currency $record, array $data) {
                                $newGap = (float) $data['gap_ar_usd'];
                                $record->update([
                                    'gap_ar_usd' => $newGap,
                                    'gap_hrc_ar_usd' => $newGap,
                                    'gap_crc_ar_usd' => $newGap,
                                ]);
                                CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($record, true);
                                CurrencyRecalculationService::recalculateForCurrency($record);

                                Notification::make()
                                    ->title("Gap Baja AR ({$record->quarter} {$record->year}) Berhasil Diperbarui!")
                                    ->body("Gap AR \${$newGap}/MT telah diterapkan ke kuartal ini.")
                                    ->success()
                                    ->send();
                            })
                    ),

                Tables\Columns\TextColumn::make('kurs_jpy')
                    ->label('Kurs Yen (¥)')
                    ->state(function (Currency $record) {
                        $jpy = Currency::where('code', 'JPY')
                            ->where('year', $record->year)
                            ->where('quarter', $record->quarter)
                            ->value('exchange_rate_to_idr');

                        return $jpy ? 'Rp ' . number_format((float) $jpy, 2, ',', '.') : '-';
                    })
                    ->alignRight(),

                Tables\Columns\TextColumn::make('last_updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum pernah')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('year')
                    ->label('Filter Tahun')
                    ->options(function () {
                        return Currency::query()->distinct()->orderBy('year', 'desc')->pluck('year', 'year')->toArray();
                    }),
                Tables\Filters\SelectFilter::make('quarter')
                    ->label('Filter Kuartal')
                    ->options([
                        'Q1' => 'Q1 (Januari - Maret)',
                        'Q2' => 'Q2 (April - Juni)',
                        'Q3' => 'Q3 (Juli - September)',
                        'Q4' => 'Q4 (Oktober - Desember)',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('all_rollover_page')
                    ->label('Salin & Update Bulanan')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->url(MonthlyRollover::getUrl()),
            ])
            ->actions([
                Tables\Actions\Action::make('sync_to_quarter_records')
                    ->label('Sinkronkan ke Data Terkait')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Currency $record) => "Sinkronkan Parameter {$record->quarter} {$record->year}?")
                    ->modalDescription(function (Currency $record) {
                        $rateFmt = number_format((float) $record->exchange_rate_to_idr, 0, ',', '.');
                        $gapApVal = (float) ($record->gap_ap_usd ?? $record->gap_hrc_usd ?? 0);
                        $gapArVal = (float) ($record->gap_ar_usd ?? 0);
                        $apFmt = number_format($gapApVal, 2, '.', '');
                        $arFmt = number_format($gapArVal, 2, '.', '');

                        $steelCnt = MaterialSteel::where('year', $record->year)->where('quarter', $record->quarter)->count();
                        $stampingCnt = StampingPart::where('year', $record->year)->where('quarter', $record->quarter)->count();
                        $fastenerCnt = Fastener::where('year', $record->year)->where('quarter', $record->quarter)->count();

                        return new HtmlString("
                            <div class='space-y-2 text-sm'>
                                <p>Nilai patokan yang akan diterapkan pada <strong>Kuartal {$record->quarter} {$record->year}</strong>:</p>
                                <ul class='list-disc pl-5 space-y-1 font-semibold text-gray-800 dark:text-gray-200'>
                                    <li>Kurs Dollar ($): Rp {$rateFmt}</li>
                                    <li>Gap Baja AP (Pembelian): \${$apFmt} / MT</li>
                                    <li>Gap Baja AR (Penjualan): \${$arFmt} / MT</li>
                                </ul>
                                <p class='mt-2'>Data terdampak pada kuartal ini:</p>
                                <div class='flex gap-2 text-xs'>
                                    <span class='px-2 py-1 bg-blue-100 text-blue-800 rounded'>{$steelCnt} Material Steel</span>
                                    <span class='px-2 py-1 bg-purple-100 text-purple-800 rounded'>{$stampingCnt} Stamping Part</span>
                                    <span class='px-2 py-1 bg-amber-100 text-amber-800 rounded'>{$fastenerCnt} Fasteners</span>
                                </div>
                                <p class='text-xs text-gray-500 italic mt-2'>* Catatan: Data kuartal lain (seperti Q4 2026 atau kuartal lainnya) tidak akan terpengaruh.</p>
                            </div>
                        ");
                    })
                    ->action(function (Currency $record) {
                        $stats = CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($record, true);

                        Notification::make()
                            ->title("Sinkronisasi Kuartal {$record->quarter} {$record->year} Selesai!")
                            ->body("Berhasil memperbarui: {$stats['material_steels']} Material Steel, {$stats['stamping_parts']} Stamping Part, dan {$stats['fasteners']} Fasteners.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('quick_update_rate')
                    ->label('Ubah Kurs & Gap')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->modalHeading(fn (Currency $record) => "Ubah Patokan {$record->quarter} {$record->year}")
                    ->form([
                        Forms\Components\TextInput::make('exchange_rate_to_idr')
                            ->label('Kurs Dollar ($ ke IDR)')
                            ->prefix('Rp')
                            ->numeric()
                            ->required()
                            ->default(fn (Currency $record) => $record->exchange_rate_to_idr),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('gap_ap_usd')
                                ->label('Gap Baja AP ($/MT)')
                                ->prefix('$')
                                ->numeric()
                                ->default(fn (Currency $record) => (float) ($record->gap_ap_usd ?? $record->gap_hrc_usd ?? 0))
                                ->helperText('Patokan fluktuasi pembelian AP'),

                            Forms\Components\TextInput::make('gap_ar_usd')
                                ->label('Gap Baja AR ($/MT)')
                                ->prefix('$')
                                ->numeric()
                                ->default(fn (Currency $record) => (float) ($record->gap_ar_usd ?? 0))
                                ->helperText('Patokan fluktuasi penjualan AR'),
                        ]),

                        Forms\Components\TextInput::make('kurs_jpy')
                            ->label('Kurs Yen (JPY ke IDR)')
                            ->prefix('Rp')
                            ->numeric()
                            ->default(function (Currency $record) {
                                return Currency::where('code', 'JPY')->where('year', $record->year)->where('quarter', $record->quarter)->value('exchange_rate_to_idr') ?? 105;
                            }),

                        Forms\Components\Toggle::make('auto_sync_records')
                            ->label('Otomatis sinkronkan ke seluruh data Material Steel AP & AR, Stamping, dan Fasteners kuartal ini')
                            ->default(true),
                    ])
                    ->action(function (Currency $record, array $data) {
                        $rate = (float) $data['exchange_rate_to_idr'];
                        $rateJpy = (float) ($data['kurs_jpy'] ?? 105);
                        $gapAp = (float) ($data['gap_ap_usd'] ?? 0);
                        $gapAr = (float) ($data['gap_ar_usd'] ?? 0);

                        Currency::withoutAutoRecalculate(function () use ($record, $rate, $gapAp, $gapAr, $rateJpy): void {
                            $record->update([
                                'exchange_rate_to_idr' => $rate,
                                'gap_ap_usd' => $gapAp,
                                'gap_ar_usd' => $gapAr,
                                'gap_hrc_usd' => $gapAp,
                                'gap_crc_usd' => $gapAp,
                                'gap_hrc_ar_usd' => $gapAr,
                                'gap_crc_ar_usd' => $gapAr,
                            ]);

                            $jpy = Currency::where('code', 'JPY')
                                ->where('year', $record->year)
                                ->where(function ($q) use ($record) {
                                    $q->where('quarter', $record->quarter)
                                        ->orWhere('month', $record->month ?? 1);
                                })
                                ->first();

                            if (! $jpy) {
                                $jpy = new Currency([
                                    'code' => 'JPY',
                                    'name' => 'Japanese Yen',
                                    'symbol' => '¥',
                                    'year' => $record->year,
                                    'quarter' => $record->quarter,
                                    'month' => $record->month ?? 1,
                                ]);
                            }
                            $jpy->exchange_rate_to_idr = $rateJpy;
                            $jpy->quarter = $record->quarter;
                            $jpy->month = $record->month ?? 1;
                            $jpy->saveQuietly();
                        });

                        $autoSync = ! isset($data['auto_sync_records']) || (bool) $data['auto_sync_records'];

                        if ($autoSync) {
                            $stats = CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($record, true);
                            CurrencyRecalculationService::recalculateForCurrency($record);

                            Notification::make()
                                ->title("Patokan Kuartal {$record->quarter} {$record->year} Diperbarui!")
                                ->body("Kurs Rp " . number_format($rate, 0, ',', '.') . " serta Gap AP & AR berhasil diterapkan ke {$stats['total']} data terkait.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("Patokan Kuartal {$record->quarter} {$record->year} Disimpan!")
                                ->body("Nilai baru telah tersimpan di master data.")
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->before(function (Currency $record, Tables\Actions\DeleteAction $action) {
                        if (strtoupper($record->code) === 'IDR') {
                            Notification::make()
                                ->danger()
                                ->title('Gagal Menghapus Kurs!')
                                ->body('Mata uang IDR (Rupiah) merupakan mata uang dasar sistem dan tidak dapat dihapus.')
                                ->send();

                            $action->cancel();
                        }
                    })
                    ->successNotificationTitle('Data Kurs Mata Uang Berhasil Dihapus!'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, Collection $records) {
                            $hasIdr = $records->contains(fn ($c) => strtoupper($c->code) === 'IDR');
                            if ($hasIdr) {
                                Notification::make()
                                    ->danger()
                                    ->title('Gagal Menghapus!')
                                    ->body('Mata uang IDR (Rupiah) merupakan patokan dasar sistem dan tidak dapat dihapus.')
                                    ->send();

                                $action->cancel();
                            }
                        })
                        ->successNotificationTitle('Data Kurs Mata Uang Berhasil Dihapus!'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
