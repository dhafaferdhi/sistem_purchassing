<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialSteelArResource\Pages;
use App\Models\MaterialSteel;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialSteelArResource extends Resource
{
    protected static ?string $model = MaterialSteel::class;

    protected static ?string $slug = 'material-steel-ars';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Material Steel AR';

    protected static ?string $modelLabel = 'Material Steel AR';

    protected static ?string $pluralModelLabel = 'Material Steel AR';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['currency', 'supplier', 'material'])
            ->where('price_type', 'AR');
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
            'index' => Pages\ListMaterialSteelArs::route('/'),
            'create' => Pages\CreateMaterialSteelAr::route('/create'),
            'edit' => Pages\EditMaterialSteelAr::route('/{record}/edit'),
        ];
    }
}
