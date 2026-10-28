<?php

namespace App\Filament\Resources\CurrencyResource\RelationManagers;

use App\Filament\Resources\StampingPartResource;
use App\Models\Currency;
use App\Models\StampingPart;
use App\Services\CurrencyRecalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StampingPartsRelationManager extends RelationManager
{
    protected static string $relationship = 'stampingParts';

    protected static ?string $title = 'Stamping Part Terkait';

    protected static ?string $icon = 'heroicon-o-wrench-screwdriver';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('steel_spec')
                    ->label('Spesifikasi Baja')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('steel_spec')
            ->columns([
                Tables\Columns\TextColumn::make('year')
                    ->label('Tahun')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quarter')
                    ->label('Kuartal')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Q1' => 'info',
                        'Q2' => 'success',
                        'Q3' => 'warning',
                        'Q4' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendor_code')
                    ->label('Vendor Code')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('steel_spec')
                    ->label('Spec')
                    ->formatStateUsing(fn ($state) => StampingPart::cleanSteelSpec($state))
                    ->searchable(),

                Tables\Columns\TextColumn::make('double_thickness')
                    ->label('Thickness(mm)')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '-';
                        }

                        return trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $state));
                    })
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('berat')
                    ->label('Berat')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '-';
                        }

                        return number_format((float) $state, 4, ',', '.').' kg';
                    })
                    ->placeholder('-')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Kurs')
                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? 'Rp '.number_format((float) $state, 0, ',', '.') : '-')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('coil_price')
                    ->label('Coil ($)')
                    ->formatStateUsing(fn ($record) => $record->coil_price !== null && $record->coil_price !== '' ? '$'.number_format((float) $record->coil_price, 4, ',', '.') : '-')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('coil_price_idr')
                    ->label('Coil (Rp)')
                    ->formatStateUsing(fn ($record) => $record->coil_price_idr !== null && $record->coil_price_idr !== '' ? 'Rp '.number_format((float) $record->coil_price_idr, 0, ',', '.') : '-')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('sheet_price')
                    ->label('Sheet ($)')
                    ->formatStateUsing(fn ($record) => $record->sheet_price !== null && $record->sheet_price !== '' ? '$'.number_format((float) $record->sheet_price, 4, ',', '.') : '-')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('sheet_price_idr')
                    ->label('Sheet (Rp)')
                    ->formatStateUsing(fn ($record) => $record->sheet_price_idr !== null && $record->sheet_price_idr !== '' ? 'Rp '.number_format((float) $record->sheet_price_idr, 0, ',', '.') : '-')
                    ->alignRight(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync_stamping_parts')
                    ->label('Terapkan Kurs ke Semua Stamping Part')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kalkulasi Ulang Harga Seluruh Stamping Part')
                    ->modalDescription(function () {
                        /** @var Currency $owner */
                        $owner = $this->getOwnerRecord();

                        return "Seluruh harga Stamping Part yang terhubung dengan mata uang {$owner->code} ({$owner->month_name} {$owner->year}) akan dihitung ulang ke IDR menggunakan kurs Rp ".number_format((float) $owner->exchange_rate_to_idr, 0, ',', '.').'.';
                    })
                    ->action(function () {
                        /** @var Currency $owner */
                        $owner = $this->getOwnerRecord();
                        $updated = CurrencyRecalculationService::recalculateStampingPartsForCurrency($owner);

                        Notification::make()
                            ->title("Sukses: {$updated} data Stamping Part berhasil disinkronkan ke kurs Rp ".number_format((float) $owner->exchange_rate_to_idr, 0, ',', '.').'.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_stamping')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (StampingPart $record): string => StampingPartResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
