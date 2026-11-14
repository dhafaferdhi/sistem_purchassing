<?php

namespace App\Filament\Resources\MaterialSteelApResource\Pages;

use App\Filament\Resources\MaterialSteelApResource;
use App\Filament\Resources\MaterialSteelResource\Pages\ListMaterialSteels;
use App\Models\MaterialSteel;

class ListMaterialSteelAps extends ListMaterialSteels
{
    protected static string $resource = MaterialSteelApResource::class;

    protected static ?string $title = 'Master Data Material Steel AP';

    public function mount(): void
    {
        parent::mount();

        MaterialSteel::syncAllMissingCounterparts();
    }

    protected function getPriceType(): string
    {
        return 'AP';
    }
}
