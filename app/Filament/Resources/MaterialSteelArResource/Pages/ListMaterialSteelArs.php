<?php

namespace App\Filament\Resources\MaterialSteelArResource\Pages;

use App\Filament\Resources\MaterialSteelArResource;
use App\Filament\Resources\MaterialSteelResource\Pages\ListMaterialSteels;
use App\Models\MaterialSteel;

class ListMaterialSteelArs extends ListMaterialSteels
{
    protected static string $resource = MaterialSteelArResource::class;

    protected static ?string $title = 'Master Data Material Steel AR';

    public function mount(): void
    {
        parent::mount();

        MaterialSteel::syncAllMissingCounterparts();
    }

    protected function getPriceType(): string
    {
        return 'AR';
    }
}
