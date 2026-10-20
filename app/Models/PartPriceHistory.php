<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartPriceHistory extends Model
{
    protected $fillable = [
        'part_id',
        'supplier_id',
        'price_date',
        'source_type',
        'reference_number',
        'unit_price_idr',
        'previous_price_idr',
        'price_change_percentage',
        'currency_code',
        'original_price',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price_date' => 'date',
            'unit_price_idr' => 'decimal:2',
            'previous_price_idr' => 'decimal:2',
            'price_change_percentage' => 'decimal:2',
            'original_price' => 'decimal:4',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Helper untuk mencatat pergerakan harga part secara otomatis
     */
    public static function logPrice(
        int $partId,
        ?int $supplierId,
        float $unitPriceIdr,
        string $sourceType = 'Purchase Order',
        ?string $referenceNo = null,
        ?string $notes = null,
        ?string $date = null,
        string $currencyCode = 'IDR',
        ?float $originalPrice = null,
        ?int $userId = null
    ): self {
        // Cari harga terakhir sebelum tanggal ini
        $lastRecord = static::where('part_id', $partId)
            ->orderBy('price_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $prevPrice = $lastRecord ? (float) $lastRecord->unit_price_idr : null;
        $changePercentage = null;

        if ($prevPrice && $prevPrice > 0) {
            $changePercentage = round((($unitPriceIdr - $prevPrice) / $prevPrice) * 100, 2);
        }

        return static::create([
            'part_id' => $partId,
            'supplier_id' => $supplierId,
            'price_date' => $date ? Carbon::parse($date) : now(),
            'source_type' => $sourceType,
            'reference_number' => $referenceNo,
            'unit_price_idr' => $unitPriceIdr,
            'previous_price_idr' => $prevPrice,
            'price_change_percentage' => $changePercentage,
            'currency_code' => $currencyCode,
            'original_price' => $originalPrice ?? $unitPriceIdr,
            'notes' => $notes,
            'created_by' => $userId ?? auth()->id(),
        ]);
    }
}
