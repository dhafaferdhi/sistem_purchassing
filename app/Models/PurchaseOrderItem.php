<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $part_id
 * @property string $item_code
 * @property string $description
 * @property string|null $vendor_item_code
 * @property int|null $moq
 * @property int|null $lead_time_days
 * @property float $quantity
 * @property float $quantity_received
 * @property float $quantity_rejected
 * @property string $unit
 * @property float $unit_price_idr
 * @property float $total_price_idr
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read float $remaining_quantity
 * @property-read \App\Models\PurchaseOrder|null $purchaseOrder
 * @property-read \App\Models\Part|null $part
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GoodsReceiptItem> $goodsReceiptItems
 */
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
