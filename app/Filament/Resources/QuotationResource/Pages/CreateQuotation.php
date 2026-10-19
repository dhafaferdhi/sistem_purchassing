<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Services\CostCalculationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id() ?? $data['user_id'] ?? 1;

        $category = $data['category'] ?? '';
        $weight = (float) ($data['weight'] ?? 0);
        $basicRate = (float) ($data['snapshot_basic_rate'] ?? 0);
        $exchangeRate = (float) ($data['snapshot_exchange_rate'] ?? 20000);
        $fluktuasi = (float) ($data['snapshot_fluktuasi'] ?? 0);

        $data['price_idr'] = CostCalculationService::calculatePriceIdr(
            $category,
            $weight,
            $basicRate,
            $exchangeRate,
            $fluktuasi
        );

        $totalProcess = 0.0;
        if (!empty($data['quotationProcesses']) && is_array($data['quotationProcesses'])) {
            foreach ($data['quotationProcesses'] as $proc) {
                $totalProcess += (float) ($proc['applied_cost'] ?? 0);
            }
        }
        $data['total_process_cost'] = round($totalProcess, 2);

        $profitPercentage = (float) ($data['profit_percentage'] ?? 10);
        $data['profit'] = CostCalculationService::calculateProfit($data['price_idr'], $data['total_process_cost'], $profitPercentage);

        $transport = (float) ($data['transport'] ?? 100);
        $packing = (float) ($data['packing'] ?? 50);
        $data['total_cost'] = CostCalculationService::calculateTotalCost(
            $data['price_idr'],
            $data['total_process_cost'],
            $data['profit'],
            $transport,
            $packing
        );

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
