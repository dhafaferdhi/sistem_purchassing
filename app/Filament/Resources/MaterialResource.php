<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialResource\Pages;
use App\Models\Currency;
use App\Models\Material;
use App\Models\Supplier;
use App\Services\MaterialMassUpdateService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Master Material';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('spec')
                            ->label('Material Spec')
                            ->placeholder('Contoh: SPHC, SPH270-OD, PP Resin, PU Foam')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('main_category')
                            ->label('Kategori Utama (Raw Material)')
                            ->options([
                                'Steel' => 'Steel (Baja / Besi / Logam)',
                                'Non-Steel' => 'Non-Steel (Bukan Besi - Plastik, Busa, Kain)',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('sub_category')
                            ->label('Sub-Kategori / Jenis Komponen')
                            ->options(function (Forms\Get $get) {
                                $main = $get('main_category');
                                if ($main === 'Steel') {
                                    return [
                                        'Frame' => 'Frame (Rangka)',
                                        'Bolt' => 'Bolt (Baut / Fastener)',
                                        'Bracket' => 'Bracket (Plat Besi)',
                                        'Sheet Metal' => 'Sheet Metal / Plat Coil',
                                    ];
                                }
                                if ($main === 'Non-Steel') {
                                    return [
                                        'Plastik' => 'Plastik (Resin / Biji Plastik)',
                                        'Busa (Urethane)' => 'Busa (Urethane / PU Foam)',
                                        'Cover (Kain)' => 'Cover (Kain / Fabric / Kulit)',
                                    ];
                                }

                                return [
                                    'Frame' => 'Frame',
                                    'Bolt' => 'Bolt',
                                    'Bracket' => 'Bracket',
                                    'Plastik' => 'Plastik',
                                    'Busa (Urethane)' => 'Busa (Urethane)',
                                    'Cover (Kain)' => 'Cover (Kain)',
                                ];
                            })
                            ->required(),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Pabrikan / Supplier Material')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih Supplier (Opsional)'),

                        Forms\Components\Select::make('currency_id')
                            ->label('Mata Uang Pembelian')
                            ->relationship('currency', 'code')
                            ->default(fn () => Currency::where('code', 'USD')->first()?->id)
                            ->required(),

                        Forms\Components\TextInput::make('basic_rate')
                            ->label('Basic Rate (Harga Dasar Material)')
                            ->required()
                            ->numeric()
                            ->step(0.0001)
                            ->helperText('Contoh: 1.85 (USD), 2.135 (USD), 65000 (IDR)'),

                        Forms\Components\Select::make('unit')
                            ->label('Satuan Unit')
                            ->options([
                                'kg' => 'kg (Kilogram)',
                                'meter' => 'meter (Panjang)',
                                'pcs' => 'pcs (Pieces)',
                                'liter' => 'liter',
                            ])
                            ->default('kg')
                            ->required(),

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
                Tables\Columns\TextColumn::make('spec')
                    ->label('Material Spec')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('main_category')
                    ->label('Kategori Utama')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Steel' => 'info',
                        'Non-Steel' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('sub_category')
                    ->label('Sub-Kategori')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('basic_rate')
                    ->label('Basic Rate')
                    ->formatStateUsing(function ($state, Material $record) {
                        $sym = $record->currency?->symbol ?? '$';

                        return $sym.' '.number_format($state, 4, ',', '.').' / '.$record->unit;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('parts_count')
                    ->label('Digunakan di (Part)')
                    ->counts('parts')
                    ->badge()
                    ->color('success')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('main_category')
                    ->label('Kategori Utama')
                    ->options([
                        'Steel' => 'Steel (Baja / Besi)',
                        'Non-Steel' => 'Non-Steel (Bukan Besi)',
                    ]),
                Tables\Filters\SelectFilter::make('sub_category')
                    ->label('Sub-Kategori')
                    ->options([
                        'Frame' => 'Frame',
                        'Bolt' => 'Bolt',
                        'Bracket' => 'Bracket',
                        'Plastik' => 'Plastik',
                        'Busa (Urethane)' => 'Busa (Urethane)',
                        'Cover (Kain)' => 'Cover (Kain)',
                    ]),
            ])
            ->actions([
                // FITUR D: WHERE-USED FEATURE (Lacak Part mana saja yang menggunakan material ini)
                Tables\Actions\Action::make('where_used')
                    ->label('Where-Used')
                    ->icon('heroicon-o-magnifying-glass-circle')
                    ->color('primary')
                    ->modalHeading(fn (Material $record) => "Where-Used: Part yang Terbuat dari {$record->spec}")
                    ->modalDescription('Berikut adalah seluruh Part Number dan Assembly yang menggunakan material ini:')
                    ->modalContent(function (Material $record) {
                        $parts = MaterialMassUpdateService::getWhereUsedParts($record);

                        return view('filament.components.where-used-modal', compact('record', 'parts'));
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                // FITUR D: MASS UPDATE KENAIKAN HARGA SUPPLIER
                Tables\Actions\Action::make('mass_update_price')
                    ->label('Mass Update')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('warning')
                    ->modalHeading(fn (Material $record) => "Mass Update Kenaikan Harga: {$record->spec}")
                    ->modalDescription(fn (Material $record) => "Harga saat ini: {$record->currency?->symbol} ".number_format($record->basic_rate, 4).'. Masukkan persentase kenaikan harga untuk otomatis memperbarui harga seluruh Part terdampak.')
                    ->form([
                        Forms\Components\TextInput::make('percentage_change')
                            ->label('Persentase Perubahan Harga (%)')
                            ->placeholder('Contoh: 10 untuk kenaikan +10%, atau -5 untuk penurunan -5%')
                            ->numeric()
                            ->step(0.01)
                            ->required()
                            ->suffix('%')
                            ->helperText('Contoh: Jika Resin Plastik naik 10%, masukkan angka 10'),
                    ])
                    ->action(function (Material $record, array $data) {
                        $percent = (float) $data['percentage_change'];
                        $result = MaterialMassUpdateService::massUpdatePrice($record, $percent);

                        Notification::make()
                            ->title("Mass Update Berhasil untuk {$record->spec}")
                            ->body('Rate baru: Rp/USD '.number_format($result['new_basic_rate'], 4).' ('.($percent > 0 ? "+{$percent}" : "{$percent}")."%). Sebanyak {$result['affected_parts_count']} part & {$result['updated_quotations_count']} quotation berhasil di-recalculate otomatis.")
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'currency'])
            ->withCount('parts');
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
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
