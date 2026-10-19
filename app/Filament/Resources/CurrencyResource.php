<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use App\Services\CurrencyRecalculationService;
use Filament\Forms;
use Filament\Forms\Form;
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

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Mata Uang')
                            ->placeholder('USD, JPY, EUR')
                            ->required()
                            ->maxLength(10),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Mata Uang')
                            ->placeholder('US Dollar, Japanese Yen')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('symbol')
                            ->label('Simbol')
                            ->placeholder('$, ¥, €')
                            ->default('Rp')
                            ->maxLength(10),
                        Forms\Components\TextInput::make('exchange_rate_to_idr')
                            ->label('Nilai Kurs ke IDR (Rupiah)')
                            ->prefix('Rp')
                            ->required()
                            ->numeric()
                            ->step(0.0001)
                            ->helperText('Contoh: 1 USD = 16000 IDR, 1 JPY = 105 IDR'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 2, ',', '.'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_updated_at')
                    ->label('Terakhir Dihitung Ulang')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum pernah')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('recalculate_all')
                    ->label('Otomasi Hitung Ulang Semua (Kuartalan)')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Hitung Ulang Seluruh Harga Part (Kuartalan)')
                    ->modalDescription('Sistem akan mengkalkulasi ulang seluruh part quotation yang menggunakan mata uang asing ke dalam Rupiah (IDR) berdasarkan kurs terbaru saat ini.')
                    ->action(function () {
                        $count = CurrencyRecalculationService::recalculateAll();
                        Notification::make()
                            ->title("Sukses: {$count} data quotation berhasil dihitung ulang otomatis ke IDR.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('recalculate_currency')
                    ->label('Hitung Ulang ke IDR')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Currency $record) => "Kalkulasi Ulang Seluruh Part untuk {$record->code}")
                    ->modalDescription(fn (Currency $record) => "Sistem akan menghitung ulang seluruh harga part yang menggunakan mata uang {$record->code} ke dalam Rupiah (IDR) dengan kurs Rp " . number_format($record->exchange_rate_to_idr, 2, ',', '.') . " tanpa perlu mengedit part satu per satu.")
                    ->action(function (Currency $record) {
                        $count = CurrencyRecalculationService::recalculateForCurrency($record);
                        Notification::make()
                            ->title("Selesai: {$count} part dengan mata uang {$record->code} berhasil dihitung ulang ke IDR.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
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
            //
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
