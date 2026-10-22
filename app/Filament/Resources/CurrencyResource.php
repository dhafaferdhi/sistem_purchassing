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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Kurs Mata Uang';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Kurs Mata Uang')
                    ->description('Masukkan patokan kurs kuartalan untuk USD dan JPY. Sistem akan otomatis memperbarui harga pada Material Steel, Stamping Part, dan Pasterner.')
                    ->schema([
                        Forms\Components\Select::make('year')
                            ->label('Tahun')
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
                            ->afterStateUpdated(function (Get $get, Forms\Set $set) {
                                $y = $get('year');
                                $q = $get('quarter');
                                if ($y && $q) {
                                    $usd = Currency::where('code', 'USD')->where('year', $y)->where('quarter', $q)->first();
                                    $jpy = Currency::where('code', 'JPY')->where('year', $y)->where('quarter', $q)->first();
                                    if ($usd) {
                                        $set('kurs_usd', $usd->exchange_rate_to_idr);
                                    }
                                    if ($jpy) {
                                        $set('kurs_jpy', $jpy->exchange_rate_to_idr);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('quarter')
                            ->label('Quartal')
                            ->options([
                                'Q1' => 'Q1',
                                'Q2' => 'Q2',
                                'Q3' => 'Q3',
                                'Q4' => 'Q4',
                            ])
                            ->default('Q1')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Forms\Set $set) {
                                $y = $get('year');
                                $q = $get('quarter');
                                if ($y && $q) {
                                    $usd = Currency::where('code', 'USD')->where('year', $y)->where('quarter', $q)->first();
                                    $jpy = Currency::where('code', 'JPY')->where('year', $y)->where('quarter', $q)->first();
                                    if ($usd) {
                                        $set('kurs_usd', $usd->exchange_rate_to_idr);
                                    }
                                    if ($jpy) {
                                        $set('kurs_jpy', $jpy->exchange_rate_to_idr);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('kurs_usd')
                            ->label('Kurs USD')
                            ->prefix('Rp')
                            ->numeric()
                            ->step(0.0001)
                            ->required()
                            ->placeholder('Contoh: 16000')
                            ->helperText('Nilai tukar 1 USD ke Rupiah'),

                        Forms\Components\TextInput::make('kurs_jpy')
                            ->label('Kurs JPY')
                            ->prefix('Rp')
                            ->numeric()
                            ->step(0.0001)
                            ->required()
                            ->placeholder('Contoh: 105')
                            ->helperText('Nilai tukar 1 JPY ke Rupiah'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->defaultSort('year', 'desc')
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
                Tables\Filters\TernaryFilter::make('has_linked_data')
                    ->label('Status Kaitan Data')
                    ->placeholder('Semua Kurs')
                    ->trueLabel('Hanya yang memiliki data (Baja / Part / Pasterner)')
                    ->falseLabel('Kurs tanpa kaitan data')
                    ->queries(
                        true: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereHas('materialSteels')
                                ->orWhereHas('stampingParts')
                                ->orWhereHas('fasteners');
                        }),
                        false: fn (Builder $query) => $query->whereDoesntHave('materialSteels')
                            ->whereDoesntHave('stampingParts')
                            ->whereDoesntHave('fasteners'),
                    ),
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
        ];
    }
}
