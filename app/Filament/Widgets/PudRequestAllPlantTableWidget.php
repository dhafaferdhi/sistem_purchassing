<?php

namespace App\Filament\Widgets;

use App\Models\PudRequest;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class PudRequestAllPlantTableWidget extends Widget
{
    protected static string $view = 'filament.widgets.pud-request-all-plant-table';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $year = (int) date('Y');
        $yearStart = sprintf('%04d-01-01', $year);
        $nextYearStart = sprintf('%04d-01-01', $year + 1);
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $plants = PudRequest::PLANTS;
        $plantDivisions = PudRequest::PLANT_DIVISIONS;

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "CAST(strftime('%m', request_date) AS INTEGER)"
            : 'MONTH(request_date)';

        $rows = PudRequest::query()
            ->where('is_deleted', false)
            ->where('request_date', '>=', $yearStart)
            ->where('request_date', '<', $nextYearStart)
            ->selectRaw("plant, division, {$monthExpr} as month_num, COUNT(*) as aggregate")
            ->groupBy('plant', 'division')
            ->groupByRaw($monthExpr)
            ->get();

        $plantMonthCounts = [];
        $plantTotals = [];
        $divisionMonthCounts = [];
        $divisionTotals = [];

        foreach ($plants as $plant) {
            $plantMonthCounts[$plant] = array_fill(1, 12, 0);
            $plantTotals[$plant] = 0;
        }

        foreach ($rows as $row) {
            $plant = (string) $row->plant;
            $division = (string) ($row->division ?? '');
            $monthNum = (int) $row->month_num;
            $count = (int) $row->aggregate;

            if ($monthNum < 1 || $monthNum > 12) {
                continue;
            }

            $plantTotals[$plant] = ($plantTotals[$plant] ?? 0) + $count;
            $plantMonthCounts[$plant][$monthNum] = ($plantMonthCounts[$plant][$monthNum] ?? 0) + $count;

            if ($division !== '') {
                $divisionTotals[$plant][$division] = ($divisionTotals[$plant][$division] ?? 0) + $count;
                $divisionMonthCounts[$plant][$division][$monthNum] = ($divisionMonthCounts[$plant][$division][$monthNum] ?? 0) + $count;
            }
        }

        return [
            'year' => $year,
            'months' => $months,
            'plants' => $plants,
            'plantDivisions' => $plantDivisions,
            'plantMonthCounts' => $plantMonthCounts,
            'plantTotals' => $plantTotals,
            'divisionMonthCounts' => $divisionMonthCounts,
            'divisionTotals' => $divisionTotals,
        ];
    }
}
