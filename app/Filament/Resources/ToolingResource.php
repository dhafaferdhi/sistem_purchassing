<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolingResource\Pages;
use App\Models\Tooling;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ToolingResource extends Resource
{
    protected static ?string $model = Tooling::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Master Tooling (Dies & Mold)';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Tooling / Cetakan')
                    ->description('Detail spesifikasi dies, mold, atau jig aset perusahaan / supplier')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->schema([
                        Forms\Components\TextInput::make('tooling_code')
                            ->label('Kode Tooling')
                            ->placeholder('Contoh: DIES-STP-001 / MOLD-PL-001')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Tooling / Cetakan')
                            ->placeholder('Contoh: Dies Progressive Bracket Bumper')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Tipe Tooling')
                            ->options([
                                'Dies' => 'Dies (Stamping Besi)',
                                'Mold' => 'Mold (Injection Plastik)',
                                'Jig' => 'Jig & Fixture (Welding/Assembly)',
                                'Pattern' => 'Pattern (Casting/Foaming)',
                            ])
                            ->required()
                            ->default('Dies'),

                        Forms\Components\Select::make('ownership')
                            ->label('Kepemilikan (Ownership)')
                            ->options([
                                'Customer Owned' => 'Customer Owned (Milik Perusahaan / Titipan)',
                                'Vendor Owned' => 'Vendor Owned (Milik Supplier)',
                            ])
                            ->required()
                            ->default('Customer Owned'),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Lokasi Supplier / Vendor Pemegang Tooling')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('part_id')
                            ->label('Part Number Terkait')
                            ->relationship('part', 'part_number')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])->columns(2),

                Forms\Components\Section::make('Kapasitas & Parameter Mesin')
                    ->icon('heroicon-o-cpu-chip')
                    ->schema([
                        Forms\Components\TextInput::make('tonnage')
                            ->label('Kebutuhan Tonase Mesin')
                            ->placeholder('Contoh: 110 Ton, 300 Ton, 500 Ton')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cavity_count')
                            ->label('Jumlah Cavity (Output per Stroke)')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('Contoh: 1 untuk Single Cavity, 2 atau 4 untuk Multi-Cavity'),

                        Forms\Components\TextInput::make('initial_cost')
                            ->label('Biaya Pembuatan Tooling (Rp)')
                            ->prefix('Rp')
                            ->numeric()
                            ->default(0.00)
                            ->helperText('Nilai investasi cetakan / dies'),
                    ])->columns(3),

                Forms\Components\Section::make('Monitoring Umur & Kondisi Tooling')
                    ->description('Lacak akumulasi stroke dan status kesiapan cetakan')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Forms\Components\TextInput::make('lifetime_strokes')
                            ->label('Target Garansi Umur (Lifetime Strokes)')
                            ->required()
                            ->numeric()
                            ->default(300000)
                            ->helperText('Batas maksimal stroke sebelum rekondisi total'),

                        Forms\Components\TextInput::make('current_strokes')
                            ->label('Akumulasi Stroke Terpakai')
                            ->required()
                            ->numeric()
                            ->default(0),

                        Forms\Components\Select::make('status')
                            ->label('Status Cetakan')
                            ->options([
                                'Ready' => 'Ready (Siap Produksi)',
                                'In Production' => 'In Production (Sedang Digunakan)',
                                'Maintenance' => 'Maintenance (Perlu Servis / Asah)',
                                'Scrap' => 'Scrap (Habis Umur / Rusak)',
                            ])
                            ->required()
                            ->default('Ready'),

                        Forms\Components\TextInput::make('location_notes')
                            ->label('Lokasi Penyimpanan / Line Mesin')
                            ->placeholder('Contoh: PT Maju Baja Plant 1, Line Press 3, Rak B-02')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Riwayat Servis / Modifikasi')
                            ->columnSpanFull()
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tooling_code')
                    ->label('Kode Tooling')
                    ->weight('bold')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Tooling')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Dies' => 'info',
                        'Mold' => 'warning',
                        'Jig' => 'gray',
                        default => 'primary',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('ownership')
                    ->label('Kepemilikan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Customer Owned' => 'success',
                        'Vendor Owned' => 'purple',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Vendor Pemegang')
                    ->placeholder('Internal Plant')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('part.part_number')
                    ->label('Part Number')
                    ->placeholder('-')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tonnage')
                    ->label('Tonase')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('cavity_count')
                    ->label('Cavity')
                    ->badge()
                    ->color('secondary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_strokes')
                    ->label('Stroke Terpakai')
                    ->formatStateUsing(fn ($record) => number_format($record->current_strokes, 0, ',', '.').' / '.number_format($record->lifetime_strokes, 0, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('wear_percentage')
                    ->label('Keausan (%)')
                    ->state(fn (Tooling $record): float => $record->wear_percentage)
                    ->formatStateUsing(fn ($state) => $state.'%')
                    ->badge()
                    ->color(fn (float $state): string => match (true) {
                        $state >= 90 => 'danger',
                        $state >= 70 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Ready' => 'success',
                        'In Production' => 'info',
                        'Maintenance' => 'warning',
                        'Scrap' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe Tooling')
                    ->options([
                        'Dies' => 'Dies',
                        'Mold' => 'Mold',
                        'Jig' => 'Jig',
                        'Pattern' => 'Pattern',
                    ]),

                Tables\Filters\SelectFilter::make('ownership')
                    ->label('Kepemilikan')
                    ->options([
                        'Customer Owned' => 'Customer Owned',
                        'Vendor Owned' => 'Vendor Owned',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Ready' => 'Ready',
                        'In Production' => 'In Production',
                        'Maintenance' => 'Maintenance',
                        'Scrap' => 'Scrap',
                    ]),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Vendor Pemegang')
                    ->relationship('supplier', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('update_strokes')
                    ->label('Input Stroke')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('additional_strokes')
                            ->label('Tambahan Jumlah Stroke Produksi')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Nilai ini akan ditambahkan ke akumulasi stroke saat ini'),
                    ])
                    ->action(function (Tooling $record, array $data): void {
                        $record->current_strokes += (int) $data['additional_strokes'];
                        if ($record->current_strokes >= $record->lifetime_strokes) {
                            $record->status = 'Maintenance';
                        }
                        $record->save();

                        Notification::make()
                            ->title('Stroke Berhasil Ditambahkan')
                            ->body("Akumulasi stroke untuk {$record->tooling_code} kini: ".number_format($record->current_strokes, 0, ',', '.'))
                            ->success()
                            ->send();
                    }),

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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListToolings::route('/'),
            'create' => Pages\CreateTooling::route('/create'),
            'edit' => Pages\EditTooling::route('/{record}/edit'),
        ];
    }
}
