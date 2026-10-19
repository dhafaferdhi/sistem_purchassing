<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartResource\Pages;
use App\Filament\Resources\PartResource\RelationManagers\VendorItemsRelationManager;
use App\Models\Material;
use App\Models\Part;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartResource extends Resource
{
    protected static ?string $model = Part::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Master Data Barang (Item Master)';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('1. Identitas Barang (Item Master)')
                            ->description('Pendaftaran kode unik dan spesifikasi barang internal')
                            ->schema([
                                Forms\Components\TextInput::make('item_code')
                                    ->label('Item Code (Kode Unik Internal)')
                                    ->placeholder('Misal: STP-001, FST-001, MAT-001')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('description')
                                    ->label('Description (Nama & Spesifikasi Barang)')
                                    ->placeholder('Misal: Bracket Bumper Depan, Bolt M6 Flange')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\Select::make('item_category')
                                    ->label('Kategori Part Number')
                                    ->options([
                                        'Material' => 'Material (Bahan baku mentah seperti plat baja / coil)',
                                        'Fastener' => 'Fastener (Baut, mur, ring, sekrup, pin)',
                                        'Stamping' => 'Stamping (Part hasil cetakan besi / Dies)',
                                        'Assembly' => 'Assembly (Rakitan beberapa komponen)',
                                        'Non-Steel' => 'Non-Steel (Plastik, Busa, Cover Fabric)',
                                    ])
                                    ->default('Stamping')
                                    ->required()
                                    ->live(),

                                Forms\Components\Select::make('type')
                                    ->label('Tipe Struktur')
                                    ->options([
                                        'Component' => 'Single Component (Part Satuan)',
                                        'Assembly' => 'Assembly (Memiliki Sub-Komponen BOM)',
                                    ])
                                    ->default('Component')
                                    ->required()
                                    ->live(),

                                Forms\Components\Select::make('material_id')
                                    ->label('Bahan Baku Mentah (Raw Material)')
                                    ->relationship('material', 'spec')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Forms\Get $get) => in_array($get('item_category'), ['Stamping', 'Fastener', 'Non-Steel', 'Component']))
                                    ->placeholder('Pilih material dasar'),

                                Forms\Components\TextInput::make('weight')
                                    ->label('Berat Barang (kg)')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->default(0)
                                    ->helperText('Contoh: 0.4500 kg'),

                                Forms\Components\TextInput::make('dimensions')
                                    ->label('Dimensi / Ukuran')
                                    ->placeholder('Contoh: 150x80x2 mm, M6x25 mm'),

                                Forms\Components\Select::make('unit')
                                    ->label('Satuan Unit')
                                    ->options([
                                        'pcs' => 'pcs (Pieces)',
                                        'kg' => 'kg (Kilogram)',
                                        'coil' => 'coil (Gulungan Plat)',
                                        'sheet' => 'sheet (Lembaran)',
                                        'set' => 'set (Set Rakitan)',
                                    ])
                                    ->default('pcs')
                                    ->required(),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Status Aktif')
                                    ->default(true),
                            ])->columns(2),

                        // Section Struktur BOM jika bertipe Assembly
                        Forms\Components\Section::make('2. Struktur Bill of Materials (BOM)')
                            ->description('Penyusun rakitan komponen internal')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'Assembly' || $get('item_category') === 'Assembly')
                            ->schema([
                                Forms\Components\Repeater::make('bomComponents')
                                    ->relationship('bomComponents')
                                    ->schema([
                                        Forms\Components\Select::make('component_part_id')
                                            ->label('Pilih Part Komponen')
                                            ->options(fn () => Part::all()->mapWithKeys(fn ($p) => [$p->id => "[{$p->item_code}] {$p->description} ({$p->item_category})"]))
                                            ->searchable()
                                            ->required()
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Qty per Unit')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->columnSpan(1),
                                        Forms\Components\Select::make('level')
                                            ->label('Level')
                                            ->options([
                                                1 => 'Level 1',
                                                2 => 'Level 2',
                                            ])
                                            ->default(1)
                                            ->required()
                                            ->columnSpan(1),
                                        Forms\Components\TextInput::make('notes')
                                            ->label('Catatan Posisi / Lokasi')
                                            ->columnSpan(2),
                                    ])
                                    ->columns(6)
                                    ->defaultItems(0)
                                    ->addActionLabel('+ Tambah Komponen BOM'),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_code')
                    ->label('Item Code')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description / Spesifikasi')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item_category')
                    ->label('Kategori Part')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Material' => 'danger',
                        'Fastener' => 'info',
                        'Stamping' => 'warning',
                        'Assembly' => 'purple',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.spec')
                    ->label('Raw Material')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Berat')
                    ->formatStateUsing(fn ($state) => number_format($state, 4) . ' kg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendor_items_count')
                    ->label('Mapped Vendor')
                    ->counts('vendorItems')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('item_category')
                    ->label('Kategori Barang')
                    ->options([
                        'Material' => 'Material (Bahan Mentah / Coil)',
                        'Fastener' => 'Fastener (Baut / Mur)',
                        'Stamping' => 'Stamping (Cetakan Besi)',
                        'Assembly' => 'Assembly (Rakitan)',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('bom_tree')
                    ->label('BOM Tree')
                    ->icon('heroicon-o-bars-3-bottom-left')
                    ->color('success')
                    ->visible(fn (Part $record) => $record->type === 'Assembly' || $record->bomComponents()->exists())
                    ->modalHeading(fn (Part $record) => "Struktur BOM: [{$record->item_code}] {$record->description}")
                    ->modalContent(fn (Part $record) => view('filament.components.bom-tree-view', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            VendorItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParts::route('/'),
            'create' => Pages\CreatePart::route('/create'),
            'edit' => Pages\EditPart::route('/{record}/edit'),
        ];
    }
}
