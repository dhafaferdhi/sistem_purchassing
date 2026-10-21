<?php

namespace App\Models;

use App\Services\CurrencyRecalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'year',
        'quarter',
        'month',
        'code',
        'name',
        'symbol',
        'exchange_rate_to_idr',
        'last_updated_at',
    ];

    protected $attributes = [
        'year' => 2026,
        'quarter' => 'Q1',
        'month' => 10,
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'exchange_rate_to_idr' => 'decimal:4',
            'last_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Currency $currency) {
            if ($currency->quarter && empty($currency->month)) {
                $currency->month = match ($currency->quarter) {
                    'Q1' => 1,
                    'Q2' => 4,
                    'Q3' => 7,
                    'Q4' => 10,
                    default => 1,
                };
            } elseif ($currency->month && empty($currency->quarter)) {
                $currency->quarter = match ((int) $currency->month) {
                    1, 2, 3 => 'Q1',
                    4, 5, 6 => 'Q2',
                    7, 8, 9 => 'Q3',
                    10, 11, 12 => 'Q4',
                    default => 'Q1',
                };
            }
        });

        static::saved(function (Currency $currency) {
            // Otomatis hitung ulang seluruh data Material Steel & Stamping Part jika nilai kurs, tahun, kuartal, bulan, atau kode berubah
            if ($currency->wasChanged('exchange_rate_to_idr') || $currency->wasChanged('year') || $currency->wasChanged('quarter') || $currency->wasChanged('month') || $currency->wasChanged('code')) {
                CurrencyRecalculationService::recalculateForCurrency($currency);
            }
        });

        static::deleting(function (Currency $currency) {
            // Cari mata uang pengganti dengan kode yang sama jika ada (misal USD periode lain)
            $replacement = static::where('code', $currency->code)
                ->where('id', '!=', $currency->id)
                ->first();

            $replacementId = $replacement?->id;

            // Re-assign atau detach foreign key di model-model terkait
            MaterialSteel::where('currency_id', $currency->id)
                ->update([
                    'currency_id' => $replacementId,
                    'currency_code' => $currency->code,
                ]);

            StampingPart::where('currency_id', $currency->id)
                ->update([
                    'currency_id' => $replacementId,
                    'currency_code' => $currency->code,
                ]);

            Fastener::where('currency_id', $currency->id)
                ->update([
                    'currency_id' => $replacementId,
                    'currency_code' => $currency->code,
                ]);

            Material::where('currency_id', $currency->id)
                ->update(['currency_id' => $replacementId]);

            PartQuotation::where('currency_id', $currency->id)
                ->update(['currency_id' => $replacementId]);
        });
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function partQuotations(): HasMany
    {
        return $this->hasMany(PartQuotation::class);
    }

    public function materialSteels(): HasMany
    {
        return $this->hasMany(MaterialSteel::class);
    }

    public function stampingParts(): HasMany
    {
        return $this->hasMany(StampingPart::class);
    }

    public function fasteners(): HasMany
    {
        return $this->hasMany(Fastener::class);
    }

    /**
     * Cari kurs mata uang berdasarkan kode, tahun, dan bulan acuan.
     */
    public static function getRateForYear(string $code, ?int $year = null, ?int $month = null): float
    {
        $year = $year ?? (int) date('Y');
        $query = static::where('code', $code)->where('year', $year);
        if ($month) {
            $currency = (clone $query)->where('month', $month)->first();
            if ($currency) {
                return (float) $currency->exchange_rate_to_idr;
            }
        }

        $currency = (clone $query)->latest('month')->first()
            ?? static::where('code', $code)->latest('year')->latest('month')->first();

        return $currency ? (float) $currency->exchange_rate_to_idr : 1.0;
    }

    /**
     * Nama bulan dalam bahasa Indonesia.
     */
    public function getPeriodAttribute(): string
    {
        return "{$this->year} - {$this->quarter}";
    }

    public function getMonthNameAttribute(): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $months[(int) ($this->month ?: 1)] ?? "Bulan {$this->month}";
    }

    /**
     * Label representasi untuk dropdown select lengkap dengan tahun, bulan, dan nilai kurs tanpa ,00.
     */
    public function getDisplayWithYearAttribute(): string
    {
        return "{$this->code} ({$this->month_name} {$this->year}) - Rp ".number_format($this->exchange_rate_to_idr, 0, ',', '.');
    }
}
