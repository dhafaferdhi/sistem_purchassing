<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $fillable = [
        'document_number',
        'user_id',
        'material_id',
        'category',
        'diameter',
        'thickness',
        'size_material',
        'weight',
        'gross_weight',
        'net_weight',
        'scrap_weight',
        'scrap_price_per_kg',
        'scrap_recovery_value',
        'cavity_count',
        'drawing_number',
        'drawing_file',
        'revision_number',
        'validity_until',
        'snapshot_basic_rate',
        'snapshot_exchange_rate',
        'snapshot_fluktuasi',
        'price_idr',
        'total_process_cost',
        'profit_percentage',
        'profit',
        'transport',
        'packing',
        'total_cost',
        'status',
        'notes',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:4',
            'gross_weight' => 'decimal:4',
            'net_weight' => 'decimal:4',
            'scrap_weight' => 'decimal:4',
            'scrap_price_per_kg' => 'decimal:2',
            'scrap_recovery_value' => 'decimal:2',
            'cavity_count' => 'integer',
            'validity_until' => 'date',
            'snapshot_basic_rate' => 'decimal:4',
            'snapshot_exchange_rate' => 'decimal:4',
            'snapshot_fluktuasi' => 'decimal:4',
            'price_idr' => 'decimal:2',
            'total_process_cost' => 'decimal:2',
            'profit_percentage' => 'decimal:2',
            'profit' => 'decimal:2',
            'transport' => 'decimal:2',
            'packing' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Quotation $quotation) {
            if (empty($quotation->document_number)) {
                $quotation->document_number = static::generateDocumentNumber();
            }
        });
    }

    public static function generateDocumentNumber(): string
    {
        $prefix = 'QUO-'.date('Ym').'-';
        $latest = static::where('document_number', 'LIKE', $prefix.'%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($latest) {
            $lastSeq = (int) substr($latest->document_number, strlen($prefix));
            $nextNumber = $lastSeq + 1;
        }

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function isExpired(): bool
    {
        return $this->validity_until && $this->validity_until->isPast();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function quotationProcesses(): HasMany
    {
        return $this->hasMany(QuotationProcess::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
