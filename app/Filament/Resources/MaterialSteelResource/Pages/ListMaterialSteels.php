<?php

namespace App\Filament\Resources\MaterialSteelResource\Pages;

use App\Filament\Resources\MaterialSteelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListMaterialSteels extends ListRecords
{
    protected static string $resource = MaterialSteelResource::class;

    protected static string $view = 'filament.resources.material-steels.pages.list-material-steels';

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('+ Penetapan Material Steel Baru'),
        ];
    }
}
