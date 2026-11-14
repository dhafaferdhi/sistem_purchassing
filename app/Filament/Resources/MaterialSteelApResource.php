<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialSteelApResource\Pages;
use App\Models\MaterialSteel;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialSteelApResource extends Resource
{
    protected static ?string $model = MaterialSteel::class;

    protected static ?string $slug = 'material-steel-aps';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Material Steel AP';

    protected static ?string $modelLabel = 'Material Steel AP';

    protected static ?string $pluralModelLabel = 'Material Steel AP';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['currency', 'supplier', 'material'])
            ->where(function (Builder $query) {
                $query->where('price_type', 'AP')
                    ->orWhereNull('price_type');
            });
    }

    public static function form(Form $form): Form
    {
        return MaterialSteelResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return MaterialSteelResource::table($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialSteelAps::route('/'),
            'create' => Pages\CreateMaterialSteelAp::route('/create'),
            'edit' => Pages\EditMaterialSteelAp::route('/{record}/edit'),
        ];
    }
}
