<?php

namespace App\Filament\Widgets;

use App\Models\PudRequest;
use Filament\Widgets\ChartWidget;

class PudRequestTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Request Trend All Plant';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $year = (int) date('Y');
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $counts = array_fill(0, 12, 0);

        $requests = PudRequest::where('is_deleted', false)
            ->whereYear('request_date', $year)
            ->get();

        foreach ($requests as $r) {
            $m = (int) $r->request_date->format('n') - 1;
            if (isset($counts[$m])) {
                $counts[$m]++;
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
        $plantTotals = [];
        foreach (PudRequest::PLANTS as $p) {
            $c = PudRequest::where('is_deleted', false)
                ->whereYear('request_date', $year)
                ->where('plant', $p)
                ->count();
            $plantTotals[] = "{$p}: {$c}";
        }

        return 'Rincian per Plant ('.$year.'): '.implode(' | ', $plantTotals);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
