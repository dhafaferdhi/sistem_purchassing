<?php

namespace App\Filament\Resources\PartResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PriceHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'priceHistories';

    protected static ?string $title = 'Riwayat & Tren Harga (Price History)';

    protected static ?string $icon = 'heroicon-o-chart-bar';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('price_date')
                    ->label('Tanggal')
                    ->default(now())
                    ->required(),

                Forms\Components\Select::make('supplier_id')
                    ->label('Supplier / Vendor')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('source_type')
                    ->label('Sumber')
                    ->options([
                        'Purchase Order' => 'Purchase Order (PO)',
                        'Quotation' => 'Quotation Estimasi',
                        'Supplier Catalog' => 'Katalog Supplier',
                        'Manual Update' => 'Penyesuaian Manual',
                    ])
                    ->default('Purchase Order')
                    ->required(),

                Forms\Components\TextInput::make('reference_number')
                    ->label('No. Referensi (PO / Quo)')
                    ->placeholder('Contoh: PO-202610-0001')
                    ->maxLength(255),

                Forms\Components\TextInput::make('unit_price_idr')
                    ->label('Harga Satuan (IDR)')
                    ->prefix('Rp')
                    ->numeric()
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan Kenaikan / Negosiasi')
                    ->placeholder('Contoh: Diskon volume order 5.000 pcs / Penyesuaian kurs USD')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_number')
            ->columns([
                Tables\Columns\TextColumn::make('price_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Sumber')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Purchase Order' => 'info',
                        'Quotation' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('No. Ref')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('unit_price_idr')
                    ->label('Harga Satuan (IDR)')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_change_percentage')
                    ->label('Perubahan (%)')
                    ->badge()
                    ->color(fn (?float $state): string => match (true) {
                        is_null($state) => 'gray',
                        $state > 0 => 'danger',
                        $state < 0 => 'success',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (?float $state) => is_null($state) ? 'Dasar' : ($state > 0 ? "▲ +{$state}%" : "▼ {$state}%")),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Keterangan')
                    ->limit(35),
            ])
            ->defaultSort('price_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('+ Catat Harga Baru'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
