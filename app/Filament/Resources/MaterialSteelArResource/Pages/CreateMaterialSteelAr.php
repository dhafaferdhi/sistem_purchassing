<?php

namespace App\Filament\Resources\MaterialSteelArResource\Pages;

use App\Filament\Resources\MaterialSteelArResource;
use App\Filament\Resources\MaterialSteelResource\Pages\CreateMaterialSteel;

class CreateMaterialSteelAr extends CreateMaterialSteel
{
    protected static string $resource = MaterialSteelArResource::class;

    protected static ?string $title = 'Tambah Data Material Steel AR';

    protected function getPriceType(): string
    {
        return 'AR';
    }
}
