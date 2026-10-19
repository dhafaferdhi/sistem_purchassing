<?php

namespace App\Filament\Widgets;

use App\Models\Quotation;
use Filament\Widgets\ChartWidget;

class QuotationChart extends ChartWidget
{
    protected static ?string $heading = 'Distribusi Estimasi Biaya per Kategori';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $fastenerCount = Quotation::where('category', 'Fastener')->count();
        $presspartCount = Quotation::where('category', 'Presspart')->count();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Dokumen',
                    'data' => [$fastenerCount, $presspartCount],
                    'backgroundColor' => [
                        '#38bdf8', // Sky blue for Fastener
                        '#fbbf24', // Amber/Yellow for Presspart
                    ],
                ],
            ],
            'labels' => ['Fastener', 'Presspart'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

