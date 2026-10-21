<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlobalVariableResource\Pages;
use App\Models\GlobalVariable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class GlobalVariableResource extends Resource
{
    protected static ?string $model = GlobalVariable::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-variable';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Variabel Global';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Key / Kode Variabel')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->maxLength(255)
                            ->helperText('Contoh: exchange_rate, fluktuasi_jfe'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Variabel')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Contoh: Exchange Rate (USD to IDR), Fluktuasi JFE'),
                        Forms\Components\TextInput::make('value')
                            ->label('Nilai (Value)')
                            ->required()
                            ->numeric()
                            ->step(0.0001)
                            ->helperText('Masukkan nilai desimal, misal: 20000 atau -0.02'),
                        Forms\Components\Hidden::make('updated_by')
                            ->default(fn () => Auth::id()),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Variabel')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('key')
                    ->label('Key')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Nilai')
                    ->formatStateUsing(function ($state, GlobalVariable $record) {
                        if ($record->key === 'exchange_rate') {
                            return 'Rp '.number_format($state, 2, ',', '.');
                        }
                        if ($record->key === 'default_profit_percentage') {
                            return number_format($state, 2).' %';
                        }

                        return number_format($state, 4);
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label('Diperbarui Oleh')
                    ->placeholder('System')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diubah')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['updated_by'] = Auth::id();

                        return $data;
                    }),
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
            'index' => Pages\ListGlobalVariables::route('/'),
            'create' => Pages\CreateGlobalVariable::route('/create'),
            'edit' => Pages\EditGlobalVariable::route('/{record}/edit'),
        ];
    }
}
