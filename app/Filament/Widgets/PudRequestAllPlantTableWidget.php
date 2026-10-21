<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class PudRequestAllPlantTableWidget extends Widget
{
    protected static string $view = 'filament.widgets.pud-request-all-plant-table';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';
}
