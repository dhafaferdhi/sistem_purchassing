<?php

namespace App\Filament\Resources\MaterialSteelApResource\Pages;

use App\Filament\Resources\MaterialSteelApResource;
use App\Filament\Resources\MaterialSteelResource\Pages\CreateMaterialSteel;

class CreateMaterialSteelAp extends CreateMaterialSteel
{
    protected static string $resource = MaterialSteelApResource::class;

    protected static ?string $title = 'Tambah Data Material Steel AP';

    protected function getPriceType(): string
    {
        return 'AP';
    }
}
