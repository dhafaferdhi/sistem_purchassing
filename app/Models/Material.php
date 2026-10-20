<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = [
        'spec',
        'category',
        'main_category',
        'sub_category',
        'supplier_id',
        'currency_id',
        'basic_rate',
        'unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'basic_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Material $material) {
            if (empty($material->category)) {
                $material->category = $material->sub_category ?: ($material->main_category ?: 'Steel');
            }
            if (empty($material->main_category)) {
                $material->main_category = 'Steel';
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    /**
     * Get all Parts that use this Material (Where-Used Feature)
     */
    public function getWhereUsedParts()
    {
        return $this->parts()->with(['parentBoms.parentPart'])->get();
    }
}
