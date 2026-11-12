<?php

namespace App\Filament\Widgets;

use App\Models\PudRequest;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PudRequestTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Request Trend All Plant';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $year = (int) date('Y');
        $yearStart = sprintf('%04d-01-01', $year);
        $nextYearStart = sprintf('%04d-01-01', $year + 1);
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $counts = array_fill(0, 12, 0);

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "CAST(strftime('%m', request_date) AS INTEGER)"
            : 'MONTH(request_date)';

        $monthlyCounts = PudRequest::query()
            ->where('is_deleted', false)
            ->where('request_date', '>=', $yearStart)
            ->where('request_date', '<', $nextYearStart)
            ->selectRaw("{$monthExpr} as month_num, COUNT(*) as aggregate")
            ->groupByRaw($monthExpr)
            ->pluck('aggregate', 'month_num');

        foreach ($monthlyCounts as $monthNum => $total) {
            $idx = (int) $monthNum - 1;
            if (isset($counts[$idx])) {
                $counts[$idx] = (int) $total;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Request ('.$year.')',
                    'data' => $counts,
                    'backgroundColor' => '#1769d2',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $months,
        ];
    }

    public function getDescription(): ?string
    {
        $year = (int) date('Y');
        $yearStart = sprintf('%04d-01-01', $year);
        $nextYearStart = sprintf('%04d-01-01', $year + 1);

        $countsByPlant = PudRequest::query()
            ->where('is_deleted', false)
            ->where('request_date', '>=', $yearStart)
            ->where('request_date', '<', $nextYearStart)
            ->selectRaw('plant, COUNT(*) as aggregate')
            ->groupBy('plant')
            ->pluck('aggregate', 'plant');

        $plantTotals = [];
        foreach (PudRequest::PLANTS as $p) {
            $c = (int) ($countsByPlant->get($p) ?? 0);
            $plantTotals[] = "{$p}: {$c}";
        }

        return 'Rincian per Plant ('.$year.'): '.implode(' | ', $plantTotals);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
