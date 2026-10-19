<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Models\Currency;
use App\Models\Part;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VendorItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'vendorItems';

    protected static ?string $title = 'Pemetaan Part Vendor (Vendor Item Mapping)';

    protected static ?string $modelLabel = 'Vendor Item';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('part_id')
                    ->label('Barang Internal (Item Master)')
                    ->options(function () {
                        return Part::all()->mapWithKeys(function ($p) {
                            $code = $p->item_code ?: $p->part_number;
                            $desc = $p->description ?: $p->part_name;
                            return [$p->id => "[{$code}] {$desc} ({$p->item_category})"];
                        });
                    })
                    ->searchable()
                    ->required()
                    ->columnSpan(2),

                Forms\Components\TextInput::make('vendor_item_code')
                    ->label('Kode Part Vendor (Vendor Item Code)')
                    ->placeholder('Misal: MBS-BKT-889')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('vendor_item_name')
                    ->label('Nama Part di Sisi Vendor')
                    ->placeholder('Misal: Bracket Bumper Front LH Stamping')
                    ->maxLength(255),

                Forms\Components\Select::make('currency_id')
                    ->label('Mata Uang')
                    ->relationship('currency', 'code')
                    ->default(fn () => Currency::where('code', 'IDR')->first()?->id)
                    ->required(),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Harga Satuan Penawaran')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('moq')
                    ->label('Minimum Order (MOQ)')
                    ->numeric()
                    ->default(100)
                    ->suffix('pcs'),

                Forms\Components\TextInput::make('lead_time_days')
                    ->label('Lead Time')
                    ->numeric()
                    ->default(14)
                    ->suffix('hari'),

                Forms\Components\Toggle::make('is_primary_vendor')
                    ->label('Vendor Utama untuk Part Ini')
                    ->default(true),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan Spesifikasi Vendor')
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('part.item_code')
                    ->label('Item Code Internal')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('part.description')
                    ->label('Nama Barang Internal')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendor_item_code')
                    ->label('Kode Part Vendor')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendor_item_name')
                    ->label('Nama Barang Vendor')
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
                    ->label('+ Tambah Mapping Vendor Item'),
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

