<?php

namespace App\Filament\Resources\CurrencyResource\RelationManagers;

use App\Filament\Resources\MaterialSteelResource;
use App\Models\Currency;
use App\Models\MaterialSteel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MaterialSteelsRelationManager extends RelationManager
{
    protected static string $relationship = 'materialSteels';

    protected static ?string $title = 'Kontrak Material Steel Terkait';

    protected static ?string $icon = 'heroicon-o-cube';

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
                        'Q4' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendor_code')
                    ->label('Vendor Code')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Supplier / Pabrikan')
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('steel_spec')
                    ->label('Spec')
                    ->formatStateUsing(fn ($state) => MaterialSteel::cleanSteelSpec($state))
                    ->formatStateUsing(fn ($state) => MaterialSteel::cleanSteelSpec($state))
                    ->searchable(),

                Tables\Columns\TextColumn::make('double_thickness')
                    ->label('Thickness(mm)')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '-';
                        }

                        return trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $state));
                    })
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('gap_steel_usd')
                    ->label('Gap/MT ($)')
                    ->formatStateUsing(fn ($record) => MaterialSteelResource::formatUsd($record->gap_steel_usd, $record->currency_code ?? 'USD'))
                    ->weight('bold')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Kurs')
                    ->formatStateUsing(fn ($state) => MaterialSteelResource::formatIdr($state, true))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('coil_price')
                    ->label('Coil ($)')
                    ->formatStateUsing(fn ($record) => MaterialSteelResource::formatUsd($record->coil_price, $record->currency_code ?? 'USD'))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('sheet_price')
                    ->label('Sheet ($)')
                    ->formatStateUsing(fn ($record) => MaterialSteelResource::formatUsd($record->sheet_price, $record->currency_code ?? 'USD'))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Draft' => 'gray',
                        'Expired' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync_all_steels')
                    ->label('Terapkan Kurs ke Semua Kontrak Ini')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kalkulasi Ulang Harga Seluruh Material Steel')
                    ->modalDescription(function () {
                        /** @var Currency $owner */
                        $owner = $this->getOwnerRecord();

                        return "Seluruh harga material steel yang terhubung dengan mata uang {$owner->code} ({$owner->month_name} {$owner->year}) akan dihitung ulang ke IDR menggunakan kurs Rp ".number_format((float) $owner->exchange_rate_to_idr, 0, ',', '.').'.';
                    })
                    ->action(function () {
                        /** @var Currency $owner */
                        $owner = $this->getOwnerRecord();
                        $updated = 0;

                        foreach ($owner->materialSteels as $steel) {
                            $steel->exchange_rate = $owner->exchange_rate_to_idr;
                            $steel->steel_price_idr = round((float) $steel->steel_price * (float) $owner->exchange_rate_to_idr, 2);
                            if ($steel->coil_price) {
                                $steel->coil_price_idr = round((float) $steel->coil_price * (float) $owner->exchange_rate_to_idr, 2);
                            }
                            if ($steel->sheet_price) {
                                $steel->sheet_price_idr = round((float) $steel->sheet_price * (float) $owner->exchange_rate_to_idr, 2);
                            }
                            $steel->save();
                            $updated++;
                        }

                        Notification::make()
                            ->title("Sukses: {$updated} data kontrak material steel berhasil disinkronkan.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_steel')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (MaterialSteel $record): string => MaterialSteelResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
