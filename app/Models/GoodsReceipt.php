<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'grn_number',
        'purchase_order_id',
        'supplier_id',
        'delivery_order_number',
        'received_date',
        'received_by',
        'driver_name',
        'vehicle_number',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GoodsReceipt $grn) {
            if (empty($grn->grn_number)) {
                $grn->grn_number = static::generateGrnNumber();
            }
            if (empty($grn->received_by)) {
                $grn->received_by = auth()->id() ?? User::first()?->id;
            }
        });

        static::saved(function (GoodsReceipt $grn) {
            $grn->purchaseOrder?->recalculateDeliveryStatus();
        });
    }

    public static function generateGrnNumber(): string
    {
        $prefix = 'GRN-'.date('Ym').'-';
        $latest = static::where('grn_number', 'LIKE', $prefix.'%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($latest) {
            $lastSeq = (int) substr($latest->grn_number, strlen($prefix));
            $nextNumber = $lastSeq + 1;
        }

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
