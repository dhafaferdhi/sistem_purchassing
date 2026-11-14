<?php

namespace App\Filament\Resources\MaterialSteelArResource\Pages;

use App\Filament\Resources\MaterialSteelArResource;
use App\Filament\Resources\MaterialSteelResource\Pages\EditMaterialSteel;

class EditMaterialSteelAr extends EditMaterialSteel
{
    protected static string $resource = MaterialSteelArResource::class;

    protected static ?string $title = 'Ubah Data Material Steel AR';

    protected function getPriceType(): string
    {
        return 'AR';
    }
}
