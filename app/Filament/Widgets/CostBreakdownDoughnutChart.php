<?php

namespace App\Filament\Widgets;

use App\Models\Fastener;
use App\Models\StampingPart;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CostBreakdownDoughnutChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = '🍩 Struktur Komposisi Biaya Part Rata-Rata';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $year = (int) ($this->filters['year'] ?? date('Y'));
        $quarter = $this->filters['quarter'] ?? 'all';
        $supplierId = $this->filters['supplier_id'] ?? null;

        $stampingQuery = StampingPart::where('year', $year);
        if ($quarter !== 'all') {
            $stampingQuery->where('quarter', $quarter);
        }
        if ($supplierId) {
            $stampingQuery->where('supplier_id', $supplierId);
        }

        $matTotal = (float) ($stampingQuery->sum('material_total_cost') ?? 0);
        $procTotal = (float) ($stampingQuery->sum('process_total_cost') ?? 0);
        $ohTotal = (float) ($stampingQuery->sum('overhead_self_cost') ?? 0);
        $toolingTotal = (float) ($stampingQuery->sum('tooling_depreciation') ?? 0);

        // Tambahkan akumulasi dari Fasteners
        $fastenerQuery = Fastener::where('year', $year);
        if ($quarter !== 'all') {
            $fastenerQuery->where('quarter', $quarter);
        }
        if ($supplierId) {
            $fastenerQuery->where('supplier_id', $supplierId);
        }

        $matTotal += (float) ($fastenerQuery->sum('material_total') ?? 0);
        $procTotal += (float) ($fastenerQuery->sum('process_total') ?? 0);
        $ohTotal += (float) ($fastenerQuery->sum('overhead_self_cost') ?? 0);
        $toolingTotal += (float) ($fastenerQuery->sum('tooling_depreciation') ?? 0);

        // Nilai representatif jika database pada periode filter masih kosong
        if ($matTotal <= 0 && $procTotal <= 0) {
            $matTotal = 62;
            $procTotal = 23;
            $ohTotal = 12;
            $toolingTotal = 3;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Akumulasi Biaya (Rp)',
                    'data' => [
                        round($matTotal, 2),
                        round($procTotal, 2),
                        round($ohTotal, 2),
                        round($toolingTotal, 2),
                    ],
                    'backgroundColor' => [
                        '#2563eb', // Biru - Raw Material
                        '#7c3aed', // Ungu - Process Manufacturing
                        '#d97706', // Amber - Overhead & Profit (14.3%)
                        '#059669', // Hijau - Tooling & Dies Amortisasi
                    ],
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => [
                '(1) Raw Material Base',
                '(2) Process Manufacturing',
                '(5) Overhead & Profit (14.3%)',
                '(9) Tooling & Dies Depr',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

