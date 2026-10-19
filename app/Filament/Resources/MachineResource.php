<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MachineResource\Pages;
use App\Models\Machine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MachineResource extends Resource
{
    protected static ?string $model = Machine::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Master Mesin & Routing';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Mesin / Lini Produksi')
                            ->placeholder('Contoh: Mesin Stamping 500 Ton, Mesin Stamping 300 Ton Tandem')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('category')
                            ->label('Kategori Manufaktur')
                            ->options([
                                'Steel' => 'Steel (Stamping / Logam Besi)',
                                'Non-Steel' => 'Non-Steel (Injection Plastik / Foaming / Sewing)',
                            ])
                            ->default('Steel')
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('stamping_type')
                            ->label('Tipe Mesin (Stamping)')
                            ->options([
                                'Progressive' => 'Progressive (Mesin otomatis beruntun dalam satu Dies)',
                                'Tandem' => 'Tandem (Mesin manual berjejer, part dipindah antar mesin)',
                                'General' => 'General / Non-Stamping',
                            ])
                            ->default('Progressive')
                            ->required()
                            ->helperText('Menentukan jenis setup Dies & alur pengerjaan'),

                        Forms\Components\Select::make('tonnage')
                            ->label('Kapasitas Tonase Mesin')
                            ->options([
                                '100 Ton' => '100 Ton (Small Parts / Blanking)',
                                '300 Ton' => '300 Ton (Medium Stamping / Bending)',
                                '500 Ton' => '500 Ton (Heavy Stamping / Drawing)',
                                '800 Ton' => '800 Ton (Deep Drawing / Big Frame)',
                                '1200 Ton' => '1200 Ton (Ultra Heavy Press)',
                                'Non-Press' => 'Non-Press (Injection / Foaming / Sewing)',
                            ])
                            ->default('500 Ton')
                            ->required()
                            ->helperText('Semakin besar tonase, biasanya biaya per jam (cost per hour) semakin mahal.'),

                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Biaya Operasional Mesin (Machine Rate / Jam)')
                            ->prefix('Rp')
                            ->numeric()
                            ->required()
                            ->helperText('Cost per hour mesin untuk kalkulasi harga pengerjaan part.'),

                        Forms\Components\TextInput::make('operator_hourly_rate')
                            ->label('Biaya Operator (Manpower Rate / Jam)')
                            ->prefix('Rp')
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('default_capacity_per_hour')
                            ->label('Kapasitas Output Rata-rata (Pcs / Jam)')
                            ->numeric()
                            ->default(120)
                            ->required()
                            ->suffix('pcs/jam'),

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
                    ->label('Nama Mesin')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stamping_type')
                    ->label('Tipe Stamping')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Progressive' => 'success',
                        'Tandem' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('tonnage')
                    ->label('Tonase')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('hourly_rate')
                    ->label('Cost Per Hour (Mesin)')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.') . ' / jam')
                    ->sortable(),

                Tables\Columns\TextColumn::make('default_capacity_per_hour')
                    ->label('Kapasitas Output')
                    ->formatStateUsing(fn ($state) => $state . ' pcs/jam')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stamping_type')
                    ->label('Tipe Stamping')
                    ->options([
                        'Progressive' => 'Progressive',
                        'Tandem' => 'Tandem',
                        'General' => 'General',
                    ]),
                Tables\Filters\SelectFilter::make('tonnage')
                    ->label('Tonase')
                    ->options([
                        '100 Ton' => '100 Ton',
                        '300 Ton' => '300 Ton',
                        '500 Ton' => '500 Ton',
                        '800 Ton' => '800 Ton',
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
            'index' => Pages\ListMachines::route('/'),
            'create' => Pages\CreateMachine::route('/create'),
            'edit' => Pages\EditMachine::route('/{record}/edit'),
        ];
    }
}
