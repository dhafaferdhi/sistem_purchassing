<?php

namespace App\Filament\Resources\MaterialSteelApResource\Pages;

use App\Filament\Resources\MaterialSteelApResource;
use App\Filament\Resources\MaterialSteelResource\Pages\EditMaterialSteel;

class EditMaterialSteelAp extends EditMaterialSteel
{
    protected static string $resource = MaterialSteelApResource::class;

    protected static ?string $title = 'Ubah Data Material Steel AP';

    protected function getPriceType(): string
    {
        return 'AP';
    }
}
