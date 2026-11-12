<?php

namespace App\Filament\Widgets;

use App\Models\PudRequest;
use Filament\Widgets\Widget;

class PudPlantStatusOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.pud-plant-status-overview';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $plants = PudRequest::PLANTS;
        $statuses = [
            ['name' => PudRequest::STATUS_DELIVERED, 'color' => 'bg-slate-500'],
            ['name' => PudRequest::STATUS_REQUEST_QUOTATION, 'color' => 'bg-amber-500'],
            ['name' => PudRequest::STATUS_REVIEW_NEGOTIATION, 'color' => 'bg-orange-500'],
            ['name' => PudRequest::STATUS_ITEM_CODE_CREATED, 'color' => 'bg-blue-600'],
            ['name' => PudRequest::STATUS_CLOSED, 'color' => 'bg-emerald-600'],
        ];

        $monthStart = now()->startOfMonth()->toDateString();
        $nextMonthStart = now()->startOfMonth()->addMonth()->toDateString();

        $rows = PudRequest::query()
            ->where('is_deleted', false)
            ->where('request_date', '>=', $monthStart)
            ->where('request_date', '<', $nextMonthStart)
            ->selectRaw('plant, status, COUNT(*) as aggregate')
            ->groupBy('plant', 'status')
            ->get();

        $plantTotals = array_fill_keys($plants, 0);
        $statusCounts = [];
        foreach ($rows as $row) {
            $plant = (string) $row->plant;
            $status = (string) $row->status;
            $count = (int) $row->aggregate;
            $plantTotals[$plant] = ($plantTotals[$plant] ?? 0) + $count;
            $statusCounts[$plant][$status] = $count;
        }

        return [
            'plants' => $plants,
            'statuses' => $statuses,
            'plantTotals' => $plantTotals,
            'statusCounts' => $statusCounts,
        ];
    }
}
