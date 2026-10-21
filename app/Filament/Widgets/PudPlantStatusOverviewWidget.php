<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class PudPlantStatusOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.pud-plant-status-overview';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';
}
