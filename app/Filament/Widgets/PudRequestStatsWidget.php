<?php

namespace App\Filament\Widgets;

use App\Models\PudRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PudRequestStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = PudRequest::where('is_deleted', false)->count();
        $delivered = PudRequest::where('is_deleted', false)->where('status', PudRequest::STATUS_DELIVERED)->count();
        $quotation = PudRequest::where('is_deleted', false)->where('status', PudRequest::STATUS_REQUEST_QUOTATION)->count();
        $review = PudRequest::where('is_deleted', false)->where('status', PudRequest::STATUS_REVIEW_NEGOTIATION)->count();
        $code = PudRequest::where('is_deleted', false)->where('status', PudRequest::STATUS_ITEM_CODE_CREATED)->count();
        $closed = PudRequest::where('is_deleted', false)->where('status', PudRequest::STATUS_CLOSED)->count();

        return [
            Stat::make('Total Request', $total)
                ->description('Seluruh request aktif')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart([3, 5, 4, 6, 5, 8, $total]),

            Stat::make('Delivered to PUD', $delivered)
                ->description('Permintaan baru masuk')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color('gray'),

            Stat::make('Request Quotation', $quotation)
                ->description('Proses penawaran vendor')
                ->descriptionIcon('heroicon-m-document-magnifying-glass')
                ->color('warning'),

            Stat::make('Review & Negotiation', $review)
                ->description('Negosiasi harga & spesifikasi')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('orange'),

            Stat::make('Item Code Created', $code)
                ->description('Kode barang selesai dibuat')
                ->descriptionIcon('heroicon-m-qr-code')
                ->color('info'),

            Stat::make('Closed', $closed)
                ->description('Pengadaan selesai 100%')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
