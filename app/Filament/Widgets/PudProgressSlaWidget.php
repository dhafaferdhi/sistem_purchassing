<?php

namespace App\Filament\Widgets;

use App\Models\PudRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PudProgressSlaWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $requests = PudRequest::where('is_deleted', false)
            ->whereNotIn('status', [PudRequest::STATUS_CLOSED, PudRequest::STATUS_CANCELLED])
            ->get();

        $onTrack = 0;
        $attention = 0;
        $dueSoon = 0;
        $overdue = 0;

        foreach ($requests as $r) {
            match ($r->sla_progress) {
                'On Track' => $onTrack++,
                'Attention' => $attention++,
                'Due Soon' => $dueSoon++,
                'Overdue' => $overdue++,
                default => null,
            };
        }

        return [
            Stat::make('On Track (Hari 1-2)', $onTrack)
                ->description('Dalam batas waktu aman')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Attention (Hari 3)', $attention)
                ->description('Mendekati tenggat waktu')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make('Due Soon (Hari 4)', $dueSoon)
                ->description('Perlu tindak lanjut segera')
                ->descriptionIcon('heroicon-m-clock')
                ->color('orange'),

            Stat::make('Overdue (> Hari 5)', $overdue)
                ->description('Melewati target SLA 5 hari')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger'),
        ];
    }
}
