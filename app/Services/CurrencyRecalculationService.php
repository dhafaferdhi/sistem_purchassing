<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\PartQuotation;
use Illuminate\Support\Facades\Log;

class CurrencyRecalculationService
{
    /**
     * Hitung ulang seluruh PartQuotation yang menggunakan currency tertentu
     * saat terjadi pembaruan kurs (misal kuartalan).
     */
    public static function recalculateForCurrency(Currency $currency): int
    {
        $quotations = PartQuotation::where('currency_id', $currency->id)->get();
        $updatedCount = 0;

        foreach ($quotations as $quotation) {
            $quotation->exchange_rate_to_idr = $currency->exchange_rate_to_idr;
            $quotation->recalculateTotals();
            $quotation->save();
            $updatedCount++;
        }

        $currency->update(['last_updated_at' => now()]);

        Log::info("Kuartalan Currency Recalculate: {$updatedCount} part quotations recalculated for currency {$currency->code} with new rate {$currency->exchange_rate_to_idr}");

        return $updatedCount;
    }

    /**
     * Hitung ulang seluruh Quotation untuk semua mata uang
     */
    public static function recalculateAll(): int
    {
        $currencies = Currency::all();
        $totalUpdated = 0;

        foreach ($currencies as $currency) {
            $totalUpdated += self::recalculateForCurrency($currency);
        }

        return $totalUpdated;
    }
}

