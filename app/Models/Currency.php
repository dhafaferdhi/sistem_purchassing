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
        'gap_ap_usd',
        'gap_ap_kg_usd',
        'gap_ap_idr',
        'gap_ar_usd',
        'gap_ar_kg_usd',
        'gap_ar_idr',
        'gap_hrc_usd',
        'gap_hrc_kg_usd',
        'gap_hrc_idr',
        'gap_crc_usd',
        'gap_crc_kg_usd',
        'gap_crc_idr',
        'gap_hrc_ar_usd',
        'gap_hrc_ar_kg_usd',
        'gap_hrc_ar_idr',
        'gap_crc_ar_usd',
        'gap_crc_ar_kg_usd',
        'gap_crc_ar_idr',
        'notes',
        'last_updated_at',
    ];

    protected $attributes = [
        'year' => 2026,
        'quarter' => 'Q1',
        'month' => 10,
        'gap_ap_usd' => 0,
        'gap_ap_kg_usd' => 0,
        'gap_ap_idr' => 0,
        'gap_ar_usd' => 0,
        'gap_ar_kg_usd' => 0,
        'gap_ar_idr' => 0,
        'gap_hrc_usd' => 0,
        'gap_hrc_kg_usd' => 0,
        'gap_hrc_idr' => 0,
        'gap_crc_usd' => 0,
        'gap_crc_kg_usd' => 0,
        'gap_crc_idr' => 0,
        'gap_hrc_ar_usd' => 0,
        'gap_hrc_ar_kg_usd' => 0,
        'gap_hrc_ar_idr' => 0,
        'gap_crc_ar_usd' => 0,
        'gap_crc_ar_kg_usd' => 0,
        'gap_crc_ar_idr' => 0,
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'exchange_rate_to_idr' => 'decimal:4',
            'gap_ap_usd' => 'decimal:4',
            'gap_ap_kg_usd' => 'decimal:4',
            'gap_ap_idr' => 'decimal:2',
            'gap_ar_usd' => 'decimal:4',
            'gap_ar_kg_usd' => 'decimal:4',
            'gap_ar_idr' => 'decimal:2',
            'gap_hrc_usd' => 'decimal:4',
            'gap_hrc_kg_usd' => 'decimal:4',
            'gap_hrc_idr' => 'decimal:2',
            'gap_crc_usd' => 'decimal:4',
            'gap_crc_kg_usd' => 'decimal:4',
            'gap_crc_idr' => 'decimal:2',
            'gap_hrc_ar_usd' => 'decimal:4',
            'gap_hrc_ar_kg_usd' => 'decimal:4',
            'gap_hrc_ar_idr' => 'decimal:2',
            'gap_crc_ar_usd' => 'decimal:4',
            'gap_crc_ar_kg_usd' => 'decimal:4',
            'gap_crc_ar_idr' => 'decimal:2',
            'last_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Currency $currency) {
            if (empty($currency->name)) {
                $currency->name = match (strtoupper((string) ($currency->code ?: 'USD'))) {
                    'USD' => 'US Dollar',
                    'JPY', 'YEN' => 'Japanese Yen',
                    'IDR' => 'Indonesian Rupiah',
                    default => $currency->code ?: 'Currency',
                };
            }
            if (empty($currency->symbol)) {
                $currency->symbol = match (strtoupper((string) ($currency->code ?: 'USD'))) {
                    'USD' => '$',
                    'JPY', 'YEN' => '¥',
                    'IDR' => 'Rp',
                    default => '$',
                };
            }

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

            // Otomatis hitung Gap $/kg dan Gap IDR dari Gap $/MT dan Kurs Dollar
            // Rumus: Gap ($/Kg) = Gap ($/MT) / 1000
            // Rumus: Gap (IDR)  = Gap ($/Kg) * Kurs Dollar
            $rate = (float) ($currency->exchange_rate_to_idr ?? 0);

            // 1. Perhitungan Gap AP (Pembelian)
            if ($currency->gap_ap_usd !== null) {
                $currency->gap_ap_kg_usd = round((float) $currency->gap_ap_usd / 1000, 4);
                $currency->gap_ap_idr = round($currency->gap_ap_kg_usd * $rate, 2);

                // Defaultkan rincian HRC & CRC AP dari gap_ap_usd jika belum dispesifikasikan atau jika gap_ap_usd berubah
                if ($currency->isDirty('gap_ap_usd') && ! $currency->isDirty('gap_hrc_usd')) {
                    $currency->gap_hrc_usd = $currency->gap_ap_usd;
                }
                if ($currency->isDirty('gap_ap_usd') && ! $currency->isDirty('gap_crc_usd')) {
                    $currency->gap_crc_usd = $currency->gap_ap_usd;
                }
                if ((float) ($currency->gap_hrc_usd ?? 0) == 0 && (float) $currency->gap_ap_usd != 0) {
                    $currency->gap_hrc_usd = $currency->gap_ap_usd;
                }
                if ((float) ($currency->gap_crc_usd ?? 0) == 0 && (float) $currency->gap_ap_usd != 0) {
                    $currency->gap_crc_usd = $currency->gap_ap_usd;
                }
            } elseif ($currency->gap_hrc_usd !== null && (float) $currency->gap_hrc_usd != 0) {
                $currency->gap_ap_usd = $currency->gap_hrc_usd;
                $currency->gap_ap_kg_usd = round((float) $currency->gap_hrc_usd / 1000, 4);
                $currency->gap_ap_idr = round($currency->gap_ap_kg_usd * $rate, 2);
            }

            if ($currency->gap_hrc_usd !== null) {
                $currency->gap_hrc_kg_usd = round((float) $currency->gap_hrc_usd / 1000, 4);
                $currency->gap_hrc_idr = round($currency->gap_hrc_kg_usd * $rate, 2);
            }
            if ($currency->gap_crc_usd !== null) {
                $currency->gap_crc_kg_usd = round((float) $currency->gap_crc_usd / 1000, 4);
                $currency->gap_crc_idr = round($currency->gap_crc_kg_usd * $rate, 2);
            }

            // 2. Perhitungan Gap AR (Penjualan)
            if ($currency->gap_ar_usd !== null) {
                $currency->gap_ar_kg_usd = round((float) $currency->gap_ar_usd / 1000, 4);
                $currency->gap_ar_idr = round($currency->gap_ar_kg_usd * $rate, 2);

                // Defaultkan rincian HRC AR & CRC AR dari gap_ar_usd jika belum dispesifikasikan atau jika gap_ar_usd berubah
                if ($currency->isDirty('gap_ar_usd') && ! $currency->isDirty('gap_hrc_ar_usd')) {
                    $currency->gap_hrc_ar_usd = $currency->gap_ar_usd;
                }
                if ($currency->isDirty('gap_ar_usd') && ! $currency->isDirty('gap_crc_ar_usd')) {
                    $currency->gap_crc_ar_usd = $currency->gap_ar_usd;
                }
                if ((float) ($currency->gap_hrc_ar_usd ?? 0) == 0 && (float) $currency->gap_ar_usd != 0) {
                    $currency->gap_hrc_ar_usd = $currency->gap_ar_usd;
                }
                if ((float) ($currency->gap_crc_ar_usd ?? 0) == 0 && (float) $currency->gap_ar_usd != 0) {
                    $currency->gap_crc_ar_usd = $currency->gap_ar_usd;
                }
            }

            if ($currency->gap_hrc_ar_usd !== null) {
                $currency->gap_hrc_ar_kg_usd = round((float) $currency->gap_hrc_ar_usd / 1000, 4);
                $currency->gap_hrc_ar_idr = round($currency->gap_hrc_ar_kg_usd * $rate, 2);
            }
            if ($currency->gap_crc_ar_usd !== null) {
                $currency->gap_crc_ar_kg_usd = round((float) $currency->gap_crc_ar_usd / 1000, 4);
                $currency->gap_crc_ar_idr = round($currency->gap_crc_ar_kg_usd * $rate, 2);
            }
        });

        static::saved(function (Currency $currency) {
            static::refreshBatchCacheItem($currency);

            if (static::$skipAutoRecalculate) {
                return;
            }

            // Otomatis hitung ulang seluruh data Material Steel & Stamping Part jika nilai kurs, gap, tahun, kuartal, bulan, atau kode berubah
            if ($currency->wasChanged('exchange_rate_to_idr')
                || $currency->wasChanged('gap_ap_usd')
                || $currency->wasChanged('gap_ar_usd')
                || $currency->wasChanged('gap_hrc_usd')
                || $currency->wasChanged('gap_crc_usd')
                || $currency->wasChanged('gap_hrc_ar_usd')
                || $currency->wasChanged('gap_crc_ar_usd')
                || $currency->wasChanged('year')
                || $currency->wasChanged('quarter')
                || $currency->wasChanged('month')
                || $currency->wasChanged('code')) {
                CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($currency, true);
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

    public static function syncFromMaterialSteel(MaterialSteel $steel): ?self
    {
        if (empty($steel->year) || empty($steel->quarter)) {
            return null;
        }

        $month = match ($steel->quarter) {
            'Q1' => 1,
            'Q2' => 4,
            'Q3' => 7,
            'Q4' => 10,
            default => 1,
        };

        $code = strtoupper((string) ($steel->currency_code ?: 'USD'));
        $currency = static::where('code', $code)
            ->where('year', $steel->year)
            ->where(function ($q) use ($steel, $month) {
                $q->where('quarter', $steel->quarter)
                    ->orWhere('month', $month);
            })
            ->first();

        if (! $currency) {
            $currency = new static([
                'code' => $code,
                'year' => $steel->year,
                'quarter' => $steel->quarter,
                'month' => $month,
                'name' => match ($code) {
                    'JPY', 'YEN' => 'Japanese Yen',
                    'IDR' => 'Indonesian Rupiah',
                    default => 'US Dollar',
                },
                'symbol' => match ($code) {
                    'JPY', 'YEN' => '¥',
                    'IDR' => 'Rp',
                    default => '$',
                },
                'exchange_rate_to_idr' => $steel->exchange_rate ?: 16000,
            ]);
        } elseif ($steel->exchange_rate && (float) $steel->exchange_rate > 0) {
            $currency->exchange_rate_to_idr = $steel->exchange_rate;
            if (empty($currency->quarter)) {
                $currency->quarter = $steel->quarter;
            }
        }

        $isAr = strtoupper((string) ($steel->price_type ?: 'AP')) === 'AR';
        $steelType = MaterialSteel::determineSteelType($steel->steel_spec);
        $gapMt = (float) ($steel->gap_steel_usd ?? 0);
        $hrMt = (float) ($steel->hr_steel_usd ?? $gapMt);
        $crMt = (float) ($steel->cr_steel_usd ?? $gapMt);

        if ($isAr) {
            if ($gapMt != 0.0 || $hrMt != 0.0 || $crMt != 0.0) {
                $currency->gap_ar_usd = $gapMt;
                $currency->gap_hrc_ar_usd = ($steelType === 'HR') ? ($hrMt ?: $gapMt) : ($currency->gap_hrc_ar_usd ?? $gapMt);
                $currency->gap_crc_ar_usd = ($steelType === 'CR') ? ($crMt ?: $gapMt) : ($currency->gap_crc_ar_usd ?? $gapMt);
            }
        } else {
            if ($gapMt != 0.0 || $hrMt != 0.0 || $crMt != 0.0) {
                $currency->gap_ap_usd = $gapMt;
                $currency->gap_hrc_usd = ($steelType === 'HR') ? ($hrMt ?: $gapMt) : ($currency->gap_hrc_usd ?? $gapMt);
                $currency->gap_crc_usd = ($steelType === 'CR') ? ($crMt ?: $gapMt) : ($currency->gap_crc_usd ?? $gapMt);
            }
        }

        $currency->saveQuietly();

        return $currency;
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
