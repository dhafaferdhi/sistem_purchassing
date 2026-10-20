<?php

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Resources\MaterialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterial extends CreateRecord
{
    protected static string $resource = MaterialResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['category'])) {
            $data['category'] = $data['sub_category'] ?? ($data['main_category'] ?? 'Steel');
        }

        return $data;
    }
}
