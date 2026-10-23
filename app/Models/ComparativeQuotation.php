<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComparativeQuotation extends Model
{
    protected $fillable = [
        'part_id',
        'part_number',
        'part_name',
        'part_type',
        'supplier_id',
        'supplier_name',
        'buyer_name',
        'model_name',
        'monthly_volume',
        'drawing_reference',
        'eci_number',
        'period_label',
        'period_date',
        'year',
        'quarter',
        'sequence',
        'old_exchange_rate',
        'new_exchange_rate',
        'exchange_fluctuation',
        'material_breakdown',
        'material_total',
        'process_breakdown',
        'process_total',
        'subtotal',
        'overhead_supplied_percent',
        'overhead_supplied_cost',
        'overhead_self_percent',
        'overhead_self_cost',
        'profit_percent',
        'profit_cost',
        'transport_cost',
        'packing_cost',
        'part_cost',
        'tooling_cost',
        'tooling_details',
        'total_cost',
        'cost_difference',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'year' => 'integer',
            'sequence' => 'integer',
            'monthly_volume' => 'integer',
            'old_exchange_rate' => 'decimal:4',
            'new_exchange_rate' => 'decimal:4',
            'exchange_fluctuation' => 'decimal:6',
            'material_breakdown' => 'array',
            'material_total' => 'decimal:4',
            'process_breakdown' => 'array',
            'process_total' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'overhead_supplied_percent' => 'decimal:2',
            'overhead_supplied_cost' => 'decimal:4',
            'overhead_self_percent' => 'decimal:2',
            'overhead_self_cost' => 'decimal:4',
            'profit_percent' => 'decimal:2',
            'profit_cost' => 'decimal:4',
            'transport_cost' => 'decimal:4',
            'packing_cost' => 'decimal:4',
            'part_cost' => 'decimal:4',
            'tooling_cost' => 'decimal:4',
            'tooling_details' => 'array',
            'total_cost' => 'decimal:4',
            'cost_difference' => 'decimal:4',
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
     * Helper kalkulasi ulang total & selisih
     */
    public function recalculate(): void
    {
        $this->subtotal = round((float) $this->material_total + (float) $this->process_total, 4);

        if ((float) $this->overhead_supplied_cost <= 0 && (float) $this->overhead_supplied_percent > 0) {
            $this->overhead_supplied_cost = round(((float) $this->overhead_supplied_percent / 100) * (float) $this->process_total, 4);
        }

        if ((float) $this->overhead_self_cost <= 0 && (float) $this->overhead_self_percent > 0) {
            $base = ($this->part_type === 'Stamping') ? (float) $this->material_total : (float) $this->subtotal;
            $this->overhead_self_cost = round(((float) $this->overhead_self_percent / 100) * $base, 4);
        }

        if ((float) $this->profit_cost <= 0 && (float) $this->profit_percent > 0) {
            $this->profit_cost = round(((float) $this->profit_percent / 100) * ((float) $this->subtotal + (float) $this->overhead_supplied_cost), 4);
        }

        if ((float) $this->part_cost <= 0) {
            $this->part_cost = round(
                (float) $this->subtotal
                + (float) $this->overhead_supplied_cost
                + (float) $this->overhead_self_cost
                + (float) $this->profit_cost
                + (float) $this->transport_cost
                + (float) $this->packing_cost,
                4
            );
        }

        $this->total_cost = round((float) $this->part_cost + (float) $this->tooling_cost, 4);
    }
}
