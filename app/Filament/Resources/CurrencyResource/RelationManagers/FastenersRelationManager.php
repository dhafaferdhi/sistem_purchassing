<?php

namespace App\Filament\Resources\CurrencyResource\RelationManagers;

use App\Filament\Resources\FastenerResource;
use App\Models\Currency;
use App\Models\Fastener;
use App\Services\CurrencyRecalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FastenersRelationManager extends RelationManager
{
    protected static string $relationship = 'fasteners';

    protected static ?string $title = 'Fasteners Terkait';

    protected static ?string $icon = 'heroicon-o-cog-6-tooth';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('steel_spec')
                    ->label('Spesifikasi Material / Wire')
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
                    ->formatStateUsing(fn ($state) => Fastener::cleanSteelSpec($state))
                    ->formatStateUsing(fn ($state) => Fastener::cleanSteelSpec($state))
                    ->searchable(),

                Tables\Columns\TextColumn::make('double_thickness')
                    ->label('Diameter/Thick(mm)')
                    ->formatStateUsing(function ($state, Fastener $record) {
                        $val = $state ?: $record->diameter;
                        if ($val === null || $val === '') {
                            return '-';
                        }

                        return trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $val));
                    })
                    ->placeholder('-'),

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

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Draft' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync_fasteners')
                    ->label('Terapkan Kurs ke Semua Fasteners')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kalkulasi Ulang Harga Seluruh Fasteners')
                    ->modalDescription(function () {
                        /** @var Currency $owner */
                        $owner = $this->getOwnerRecord();

                        return "Seluruh harga Fasteners yang terhubung dengan mata uang {$owner->code} ({$owner->period}) akan dihitung ulang ke IDR menggunakan kurs Rp ".number_format((float) $owner->exchange_rate_to_idr, 0, ',', '.').'.';
                    })
                    ->action(function () {
                        /** @var Currency $owner */
                        $owner = $this->getOwnerRecord();
                        $updated = CurrencyRecalculationService::recalculateFastenersForCurrency($owner);

                        Notification::make()
                            ->title("Sukses: {$updated} data Fasteners berhasil disinkronkan ke kurs Rp ".number_format((float) $owner->exchange_rate_to_idr, 0, ',', '.').'.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_fastener')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Fastener $record): string => FastenerResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
