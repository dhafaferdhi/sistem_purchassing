<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property string $po_number
 * @property int $supplier_id
 * @property int $user_id
 * @property int|null $quotation_id
 * @property \Carbon\Carbon|null $order_date
 * @property \Carbon\Carbon|null $delivery_date
 * @property string $payment_terms
 * @property int $currency_id
 * @property float $exchange_rate
 * @property float $subtotal_idr
 * @property float $tax_percentage
 * @property float $tax_amount_idr
 * @property float $total_amount_idr
 * @property string $status
 * @property string $delivery_status
 * @property int|null $approved_by
 * @property string|null $notes
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read float $total_ordered_quantity
 * @property-read float $total_received_quantity
 * @property-read float $remaining_quantity
 * @property-read float $delivery_percentage
 * @property-read string $delivery_progress
 * @property-read \App\Models\Supplier|null $supplier
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Quotation|null $quotation
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\User|null $approvedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrderItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GoodsReceipt> $goodsReceipts
 */
class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number',
        'supplier_id',
        'user_id',
        'quotation_id',
        'order_date',
        'delivery_date',
        'payment_terms',
        'currency_id',
        'exchange_rate',
        'subtotal_idr',
        'tax_percentage',
        'tax_amount_idr',
        'total_amount_idr',
        'status',
        'delivery_status',
        'approved_by',
        'notes',
    ];

    protected $attributes = [
        'status' => 'Draft',
        'delivery_status' => 'Unfulfilled',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'delivery_date' => 'date',
            'exchange_rate' => 'decimal:4',
            'subtotal_idr' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'tax_amount_idr' => 'decimal:2',
            'total_amount_idr' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $po) {
            if (empty($po->po_number)) {
                $po->po_number = static::generatePoNumber();
            }
            if (empty($po->user_id)) {
                $po->user_id = Auth::id() ?? (int) (User::value('id') ?? 1);
            }
        });

        static::saving(function (PurchaseOrder $po) {
            $taxRate = (float) ($po->tax_percentage ?: 11.0);
            $po->tax_amount_idr = round(((float) $po->subtotal_idr) * ($taxRate / 100), 2);
            $po->total_amount_idr = round(((float) $po->subtotal_idr) + $po->tax_amount_idr, 2);
        });
    }

    public static function generatePoNumber(): string
    {
        $prefix = 'PO-'.date('Ym').'-';
        $latest = static::where('po_number', 'LIKE', $prefix.'%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($latest) {
            $lastSeq = (int) substr($latest->po_number, strlen($prefix));
            $nextNumber = $lastSeq + 1;
        }

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function recalculateDeliveryStatus(): void
    {
        $ordered = (float) $this->items()->sum('quantity');
        $received = (float) $this->items()->sum('quantity_received');

        if ($received <= 0) {
            $status = 'Unfulfilled';
        } elseif ($received >= $ordered) {
            $status = ($received > $ordered) ? 'Over Received' : 'Fully Received';
        } else {
            $status = 'Partially Received';
        }

        $this->updateQuietly(['delivery_status' => $status]);
    }

    public function getTotalOrderedQuantityAttribute(): float
    {
        return (float) $this->items->sum('quantity');
    }

    public function getTotalReceivedQuantityAttribute(): float
    {
        return (float) $this->items->sum('quantity_received');
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max(0.0, (float) ($this->total_ordered_quantity - $this->total_received_quantity));
    }

    public function getDeliveryPercentageAttribute(): float
    {
        $ordered = $this->total_ordered_quantity;
        if ($ordered <= 0) {
            return 0.0;
        }

        return min(100.0, round(($this->total_received_quantity / $ordered) * 100, 1));
    }

    public function getDeliveryProgressAttribute(): string
    {
        return number_format($this->total_received_quantity, 0, ',', '.').' / '.number_format($this->total_ordered_quantity, 0, ',', '.').' pcs ('.$this->delivery_percentage.'%)';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
