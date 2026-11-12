<?php

namespace App\Models;

use App\Services\CurrencyRecalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    public static bool $skipAutoRecalculate = false;

    protected static bool $batchCacheEnabled = false;

    /** @var array<int, self> */
    protected static array $batchCacheById = [];

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
            static::refreshBatchCacheItem($currency);

            if (static::$skipAutoRecalculate) {
                return;
            }

            // Otomatis hitung ulang seluruh data Material Steel & Stamping Part jika nilai kurs, tahun, kuartal, bulan, atau kode berubah
            if ($currency->wasChanged('exchange_rate_to_idr') || $currency->wasChanged('year') || $currency->wasChanged('quarter') || $currency->wasChanged('month') || $currency->wasChanged('code')) {
                CurrencyRecalculationService::dispatchOrRunForCurrency($currency);
            }
        });

        static::deleting(function (Currency $currency) {
            unset(static::$batchCacheById[$currency->id]);

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

    /**
     * Jalankan callback tanpa memicu auto-recalculate di event saved Currency.
     */
    public static function withoutAutoRecalculate(callable $callback): mixed
    {
        $previous = static::$skipAutoRecalculate;
        static::$skipAutoRecalculate = true;

        try {
            return $callback();
        } finally {
            static::$skipAutoRecalculate = $previous;
        }
    }

    /**
     * Aktifkan cache mata uang dalam memori selama pemrosesan batch agar hook saving tidak mengulang query per baris.
     */
    public static function withBatchCache(callable $callback): mixed
    {
        $wasEnabled = static::$batchCacheEnabled;
        if (! $wasEnabled) {
            static::$batchCacheEnabled = true;
            static::$batchCacheById = static::query()->get()->keyBy('id')->all();
        }

        try {
            return $callback();
        } finally {
            if (! $wasEnabled) {
                static::$batchCacheEnabled = false;
                static::$batchCacheById = [];
            }
        }
    }

    protected static function refreshBatchCacheItem(Currency $currency): void
    {
        if (static::$batchCacheEnabled) {
            static::$batchCacheById[$currency->id] = $currency;
        }
    }

    /**
     * Cari Currency berdasarkan ID menggunakan batch cache bila aktif.
     */
    public static function findCached(?int $id): ?self
    {
        if (! $id) {
            return null;
        }

        if (static::$batchCacheEnabled) {
            return static::$batchCacheById[$id] ??= static::find($id);
        }

        return static::find($id);
    }

    /**
     * Selesaikan pencocokan currency berdasarkan kode, tahun, dan kuartal (aman & hemat query saat batch).
     */
    public static function resolvePeriodMatch(?int $currencyId, string $code, ?int $year, ?string $quarter): ?self
    {
        $normalizedCode = strtoupper($code ?: 'USD');
        $curr = static::findCached($currencyId);

        $needsMatch = ! $curr
            || $curr->year != $year
            || ($quarter && $curr->quarter != $quarter)
            || (strtoupper($curr->code) != $normalizedCode);

        if (! $needsMatch) {
            return $curr;
        }

        if (static::$batchCacheEnabled) {
            $pool = collect(static::$batchCacheById);

            return $pool->first(fn (Currency $c) => strtoupper($c->code) === $normalizedCode && $c->year == $year && (! $quarter || $c->quarter == $quarter))
                ?? $pool->first(fn (Currency $c) => strtoupper($c->code) === $normalizedCode && $c->year == $year)
                ?? $pool->first(fn (Currency $c) => strtoupper($c->code) === $normalizedCode)
                ?? $pool->first();
        }

        return static::where('code', $code ?: 'USD')
            ->where('year', $year)
            ->when($quarter, fn ($q) => $q->where('quarter', $quarter))
            ->first()
            ?? static::where('code', $code ?: 'USD')
                ->where('year', $year)
                ->first()
            ?? static::where('code', $code ?: 'USD')->first()
            ?? static::first();
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
