<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'part_id',
        'item_code',
        'description',
        'vendor_item_code',
        'moq',
        'lead_time_days',
        'quantity',
        'quantity_received',
        'quantity_rejected',
        'unit',
        'unit_price_idr',
        'total_price_idr',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'quantity_received' => 'decimal:2',
            'quantity_rejected' => 'decimal:2',
            'moq' => 'integer',
            'lead_time_days' => 'integer',
            'unit_price_idr' => 'decimal:2',
            'total_price_idr' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item) {
            $item->total_price_idr = round(((float) $item->quantity) * ((float) $item->unit_price_idr), 2);
        });
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max(0.0, (float) ($this->quantity - $this->quantity_received));
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
