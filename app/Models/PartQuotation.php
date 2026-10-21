<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartQuotation extends Model
{
    protected $fillable = [
        'part_id',
        'supplier_id',
        'currency_id',
        'exchange_rate_to_idr',
        'material_cost_original',
        'material_cost_idr',
        'machine_cost_original',
        'machine_cost_idr',
        'manpower_cost_idr',
        'packaging_cost_idr',
        'transport_cost_idr',
        'tooling_type',
        'tooling_payment_type',
        'tooling_cost_total',
        'tooling_amortization_qty',
        'tooling_cost_per_piece',
        'total_cost_idr',
        'status',
        'is_selected',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate_to_idr' => 'decimal:4',
            'material_cost_original' => 'decimal:4',
            'material_cost_idr' => 'decimal:2',
            'machine_cost_original' => 'decimal:4',
            'machine_cost_idr' => 'decimal:2',
            'manpower_cost_idr' => 'decimal:2',
            'packaging_cost_idr' => 'decimal:2',
            'transport_cost_idr' => 'decimal:2',
            'tooling_cost_total' => 'decimal:2',
            'tooling_amortization_qty' => 'integer',
            'tooling_cost_per_piece' => 'decimal:2',
            'total_cost_idr' => 'decimal:2',
            'is_selected' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PartQuotation $pq) {
            $pq->recalculateTotals();
        });
    }

    public function recalculateTotals(): void
    {
        $rate = (float) ($this->exchange_rate_to_idr ?: 1);

        // Konversi Material & Machine ke IDR
        $this->material_cost_idr = round(((float) $this->material_cost_original) * $rate, 2);
        $this->machine_cost_idr = round(((float) $this->machine_cost_original) * $rate, 2);

        // Amortisasi Tooling jika dipilih
        if (strcasecmp($this->tooling_payment_type, 'Amortisasi') === 0 && (int) $this->tooling_amortization_qty > 0) {
            $this->tooling_cost_per_piece = round(((float) $this->tooling_cost_total) / ((int) $this->tooling_amortization_qty), 2);
        } else {
            $this->tooling_cost_per_piece = 0;
        }

        // Grand Total IDR per Piece
        $this->total_cost_idr = round(
            $this->material_cost_idr +
            $this->machine_cost_idr +
            (float) $this->manpower_cost_idr +
            (float) $this->packaging_cost_idr +
            (float) $this->transport_cost_idr +
            $this->tooling_cost_per_piece,
            2
        );
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
