<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorItem extends Model
{
    protected $fillable = [
        'supplier_id',
        'part_id',
        'vendor_item_code',
        'vendor_item_name',
        'unit_price',
        'currency_id',
        'moq',
        'lead_time_days',
        'is_primary_vendor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'moq' => 'integer',
            'lead_time_days' => 'integer',
            'is_primary_vendor' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}

