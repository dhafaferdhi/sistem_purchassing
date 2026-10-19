<?php

namespace App\Filament\Resources\PartResource\RelationManagers;

use App\Models\Currency;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VendorItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'vendorItems';

    protected static ?string $title = 'Mapping Vendor & Kode Part Supplier';

    protected static ?string $modelLabel = 'Vendor Mapping';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('supplier_id')
                    ->label('Pilih Vendor / Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->required()
                    ->columnSpan(2),

                Forms\Components\TextInput::make('vendor_item_code')
                    ->label('Kode Part Vendor (Vendor Item Code)')
                    ->placeholder('Contoh: MBS-BKT-889')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('vendor_item_name')
                    ->label('Nama Part di Sisi Vendor')
                    ->placeholder('Contoh: Bracket Front Stamping')
                    ->maxLength(255),

                Forms\Components\Select::make('currency_id')
                    ->label('Mata Uang Penawaran')
                    ->relationship('currency', 'code')
                    ->default(fn () => Currency::where('code', 'IDR')->first()?->id)
                    ->required(),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Harga Penawaran Vendor')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('moq')
                    ->label('Minimum Order (MOQ)')
                    ->numeric()
                    ->default(100)
                    ->suffix('pcs'),

                Forms\Components\TextInput::make('lead_time_days')
                    ->label('Lead Time Pengiriman')
                    ->numeric()
                    ->default(14)
                    ->suffix('hari'),

                Forms\Components\Toggle::make('is_primary_vendor')
                    ->label('Vendor Utama')
                    ->default(true),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan'),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier.code')
                    ->label('Vendor Code')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Nama Vendor')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendor_item_code')
                    ->label('Kode Part Vendor')
                    ->badge()
                    ->color('info')
                    ->fontFamily('mono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('vendor_item_name')
                    ->label('Nama Part di Vendor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Harga Penawaran')
                    ->formatStateUsing(fn ($state, $record) => ($record->currency?->symbol ?? 'Rp') . ' ' . number_format($state, 2, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->formatStateUsing(fn ($state) => $state . ' hari'),

                Tables\Columns\IconColumn::make('is_primary_vendor')
                    ->label('Vendor Utama')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('+ Tambah Mapping Vendor Part'),
            ])
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
}

