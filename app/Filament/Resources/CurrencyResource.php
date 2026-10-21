<?php

namespace App\Filament\Resources;

use App\Filament\Pages\MonthlyRollover;
use App\Filament\Resources\CurrencyResource\Pages;
use App\Filament\Resources\CurrencyResource\RelationManagers\FastenersRelationManager;
use App\Filament\Resources\CurrencyResource\RelationManagers\MaterialSteelsRelationManager;
use App\Filament\Resources\CurrencyResource\RelationManagers\StampingPartsRelationManager;
use App\Models\Currency;
use App\Services\CurrencyRecalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Kurs Mata Uang';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Select::make('year')
                            ->label('Tahun Kurs (Fiscal / Budget Year)')
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
                            ->helperText('Tahun berlakunya patokan kurs ini'),

                        Forms\Components\Select::make('quarter')
                            ->label('Kuartal Kurs (Quarter)')
                            ->options([
                                'Q1' => 'Q1 (Januari - Maret)',
                                'Q2' => 'Q2 (April - Juni)',
                                'Q3' => 'Q3 (Juli - September)',
                                'Q4' => 'Q4 (Oktober - Desember)',
                            ])
                            ->default('Q1')
                            ->required()
                            ->helperText('Pilihan kuartal berlaku (sama seperti Material Steel & Stamping Part)'),

                        Forms\Components\Select::make('month')
                            ->label('Bulan Kurs')
                            ->options([
                                1 => 'Januari (Bulan 1)',
                                2 => 'Februari (Bulan 2)',
                                3 => 'Maret (Bulan 3)',
                                4 => 'April (Bulan 4)',
                                5 => 'Mei (Bulan 5)',
                                6 => 'Juni (Bulan 6)',
                                7 => 'Juli (Bulan 7)',
                                8 => 'Agustus (Bulan 8)',
                                9 => 'September (Bulan 9)',
                                10 => 'Oktober (Bulan 10)',
                                11 => 'November (Bulan 11)',
                                12 => 'Desember (Bulan 12)',
                            ])
                            ->default((int) date('n'))
                            ->required()
                            ->helperText('Bulan penetapan kurs (Januari - Desember)'),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Mata Uang')
                            ->placeholder('USD, JPY, IDR')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('year', $get('year') ?? date('Y'))->where('quarter', $get('quarter') ?? 'Q1')),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Mata Uang')
                            ->placeholder('US Dollar, Japanese Yen, Indonesian Rupiah')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('symbol')
                            ->label('Simbol')
                            ->placeholder('$, ¥, Rp')
                            ->default('Rp')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('exchange_rate_to_idr')
                            ->label('Nilai Kurs ke IDR (Rupiah)')
                            ->prefix('Rp')
                            ->required()
                            ->numeric()
                            ->step(0.0001)
                            ->helperText('Contoh: 16000 (untuk USD) atau 105 (untuk Yen/JPY)'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label('Kuartal')
                    ->badge()
                    ->state(fn (Currency $record) => "{$record->year} - {$record->quarter}")
                    ->color(fn (Currency $record): string => match ($record->quarter) {
                        'Q1' => 'info',
                        'Q2' => 'success',
                        'Q3' => 'warning',
                        'Q4' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(['year', 'quarter']),
                Tables\Columns\TextColumn::make('year')
                    ->label('Tahun')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quarter')
                    ->label('Kuartal Ref')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('month')
                    ->label('Bulan')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        1 => 'Januari',
                        2 => 'Februari',
                        3 => 'Maret',
                        4 => 'April',
                        5 => 'Mei',
                        6 => 'Juni',
                        7 => 'Juli',
                        8 => 'Agustus',
                        9 => 'September',
                        10 => 'Oktober',
                        11 => 'November',
                        12 => 'Desember',
                        default => $state ? "Bulan {$state}" : '-'
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->color('info')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Mata Uang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('symbol')
                    ->label('Simbol')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('exchange_rate_to_idr')
                    ->label('Kurs ke IDR')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('material_steels_count')
                    ->counts('materialSteels')
                    ->label('Linked Material Steel')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->suffix(' baja')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stamping_parts_count')
                    ->counts('stampingParts')
                    ->label('Linked Stamping Part')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->suffix(' part')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fasteners_count')
                    ->counts('fasteners')
                    ->label('Linked Pasterner')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->suffix(' pasterner')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_updated_at')
                    ->label('Terakhir Dihitung Ulang')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum pernah')
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
                Tables\Filters\SelectFilter::make('code')
                    ->label('Filter Mata Uang')
                    ->options([
                        'USD' => 'USD (US Dollar)',
                        'JPY' => 'JPY / YEN (Japanese Yen)',
                        'IDR' => 'IDR (Indonesian Rupiah)',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('all_rollover_page')
                    ->label('Salin & Update Bulanan')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->url(MonthlyRollover::getUrl()),
            ])
            ->actions([
                Tables\Actions\Action::make('quick_update_rate')
                    ->label('Ubah Kurs')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->modalHeading(fn (Currency $record) => "Ubah Nilai Kurs {$record->code} ({$record->period})")
                    ->modalDescription('Saat nilai kurs disimpan, seluruh data di Material Steel, Stamping Part, dan Pasterner akan otomatis dihitung ulang ke Rupiah (IDR).')
                    ->form([
                        Forms\Components\TextInput::make('exchange_rate_to_idr')
                            ->label('Nilai Kurs Baru ke IDR')
                            ->prefix('Rp')
                            ->numeric()
                            ->required()
                            ->default(fn (Currency $record) => $record->exchange_rate_to_idr)
                            ->helperText('Contoh: 16000 atau 16500'),
                    ])
                    ->action(function (Currency $record, array $data) {
                        $record->update(['exchange_rate_to_idr' => $data['exchange_rate_to_idr']]);
                        $stats = CurrencyRecalculationService::recalculateForCurrencyWithStats($record);

                        Notification::make()
                            ->title("Kurs {$record->code} Berhasil Diperbarui!")
                            ->body('Kurs baru Rp '.number_format((float) $record->exchange_rate_to_idr, 0, ',', '.')." telah aktif. Otomatis menghitung ulang: {$stats['material_steels']} Material Steel, {$stats['stamping_parts']} Stamping Part, dan {$stats['fasteners']} Pasterner.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MaterialSteelsRelationManager::class,
            StampingPartsRelationManager::class,
            FastenersRelationManager::class,
        ];
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
