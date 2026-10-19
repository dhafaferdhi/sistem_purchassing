<?php

namespace App\Filament\Widgets;

use App\Models\GlobalVariable;
use App\Models\Material;
use App\Models\Quotation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalQuotations = Quotation::count();
        $approvedQuotations = Quotation::where('status', 'approved')->count();
        $pendingApprovals = Quotation::where('status', 'submitted')->count();
        $exchangeRate = GlobalVariable::getValue('exchange_rate', 20000);
        $fluktuasiJfe = GlobalVariable::getValue('fluktuasi_jfe', -0.02);

        return [
            Stat::make('Total Estimasi', $totalQuotations)
                ->description('Total dokumen kalkulasi dibuat')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('Menunggu Persetujuan', $pendingApprovals)
                ->description('Quotation status submitted')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingApprovals > 0 ? 'warning' : 'gray'),

            Stat::make('Quotation Disetujui', $approvedQuotations)
                ->description('Siap untuk proses purchasing')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Exchange Rate & JFE', 'Rp ' . number_format($exchangeRate, 0, ',', '.'))
                ->description('Fluktuasi JFE: ' . number_format($fluktuasiJfe, 4))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),
        ];
    }
}

