<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcessResource\Pages;
use App\Models\Machine;
use App\Models\Process;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProcessResource extends Resource
{
    protected static ?string $model = Process::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Master Proses (Routing)';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Proses')
                            ->placeholder('Contoh: Blanking & Piercing Bracket, Bending Tandem')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('stage_type')
                            ->label('Tahapan Pengerjaan (Process Stage)')
                            ->options([
                                'Blanking' => 'Blanking (Pemotongan plat awal)',
                                'Piercing' => 'Piercing (Pembuatan lubang)',
                                'Bending' => 'Bending (Penekukan plat)',
                                'Drawing' => 'Drawing (Penarikan / Pencetakan cekung)',
                                'Trimming' => 'Trimming (Perapian sisi pinggir)',
                                'Heading & Forging' => 'Heading & Forging (Pembentukan baut/fastener)',
                                'Heat treatment' => 'Heat treatment (Perlakuan panas)',
                                'Injection' => 'Injection (Pencetakan plastik)',
                                'Foaming' => 'Foaming (Pencetakan busa urethane)',
                                'Sewing' => 'Sewing (Penjahitan kain cover)',
                                'Inspection' => 'Inspection (Pemeriksaan kualitas)',
                                'General' => 'General / Proses Lainnya',
                            ])
                            ->default('Blanking')
                            ->required(),

                        Forms\Components\Select::make('machine_id')
                            ->label('Mesin yang Digunakan (Routing Machine)')
                            ->options(function () {
                                return Machine::all()->mapWithKeys(function ($m) {
                                    $rate = 'Rp ' . number_format($m->hourly_rate, 0, ',', '.') . '/jam';
                                    return [$m->id => "{$m->name} [{$m->stamping_type} - {$m->tonnage}] ({$rate})"];
                                });
                            })
                            ->searchable()
                            ->placeholder('Pilih mesin produksi'),

                        Forms\Components\Select::make('tooling_type')
                            ->label('Jenis Tooling / Cetakan')
                            ->options([
                                'Dies' => 'Dies (Cetakan Stamping Besi)',
                                'Mold' => 'Mold (Cetakan Injection Plastik)',
                                'Jig' => 'Jig / Fixture Pengelasan / Pengecekan',
                                'None' => 'None (Tanpa Tooling Khusus)',
                            ])
                            ->default('Dies')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('dies_code')
                            ->label('Kode Dies / Mold')
                            ->placeholder('Contoh: DIES-STP-001, MOLD-PL-88')
                            ->visible(fn (Forms\Get $get) => in_array($get('tooling_type'), ['Dies', 'Mold', 'Jig'])),

                        Forms\Components\TextInput::make('dies_name')
                            ->label('Nama Tooling / Cetakan')
                            ->placeholder('Contoh: Dies Progressive Bracket Front')
                            ->visible(fn (Forms\Get $get) => in_array($get('tooling_type'), ['Dies', 'Mold', 'Jig'])),

                        Forms\Components\TextInput::make('cycle_time_seconds')
                            ->label('Waktu Siklus (Cycle Time)')
                            ->numeric()
                            ->default(15)
                            ->suffix('detik/pcs')
                            ->helperText('Durasi pengerjaan per 1 pcs'),

                        Forms\Components\TextInput::make('default_cost')
                            ->label('Standar Biaya Proses (Cost per Pcs)')
                            ->prefix('Rp')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Biaya estimasi proses manufaktur per pcs (IDR)'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Proses')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stage_type')
                    ->label('Tahapan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Blanking', 'Piercing' => 'info',
                        'Bending', 'Drawing' => 'warning',
                        'Heading & Forging' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('machine.name')
                    ->label('Mesin')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('machine.stamping_type')
                    ->label('Tipe Mesin')
                    ->badge()
                    ->color('purple')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('machine.tonnage')
                    ->label('Tonase')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('dies_code')
                    ->label('Kode Dies')
                    ->badge()
                    ->color('success')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('default_cost')
                    ->label('Biaya Standar')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 2, ',', '.'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage_type')
                    ->label('Tahapan Proses')
                    ->options([
                        'Blanking' => 'Blanking',
                        'Piercing' => 'Piercing',
                        'Bending' => 'Bending',
                        'Drawing' => 'Drawing',
                        'Trimming' => 'Trimming',
                    ]),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcesses::route('/'),
            'create' => Pages\CreateProcess::route('/create'),
            'edit' => Pages\EditProcess::route('/{record}/edit'),
        ];
    }
}
