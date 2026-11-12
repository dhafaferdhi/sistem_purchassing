<?php

namespace App\Jobs;

use App\Models\Currency;
use App\Services\CurrencyRecalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateCurrencyRatesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $currencyId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $currency = Currency::find($this->currencyId);
        if (! $currency) {
            return;
        }

        CurrencyRecalculationService::recalculateForCurrencyWithStats($currency);
    }
}
