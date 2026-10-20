<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'part_id',
        'item_code',
        'description',
        'quantity_ordered',
        'quantity_previously_received',
        'quantity_received',
        'quantity_rejected',
        'quantity_remaining',
        'unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:2',
            'quantity_previously_received' => 'decimal:2',
            'quantity_received' => 'decimal:2',
            'quantity_rejected' => 'decimal:2',
            'quantity_remaining' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (GoodsReceiptItem $item) {
            // Update total quantity received & rejected di PurchaseOrderItem
            $poItem = $item->purchaseOrderItem;
            if ($poItem) {
                $totalRecv = static::where('purchase_order_item_id', $poItem->id)->sum('quantity_received');
                $totalRej = static::where('purchase_order_item_id', $poItem->id)->sum('quantity_rejected');

                $poItem->updateQuietly([
                    'quantity_received' => $totalRecv,
                    'quantity_rejected' => $totalRej,
                ]);

                $poItem->purchaseOrder?->recalculateDeliveryStatus();
            }
        });
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
