<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    protected $fillable = [
        'item_code',
        'part_number',
        'part_name',
        'description',
        'item_category',
        'main_category',
        'sub_category',
        'type',
        'material_id',
        'weight',
        'dimensions',
        'unit',
        'is_active',
    ];

    protected $attributes = [
        'main_category' => 'Steel',
        'type' => 'Component',
        'item_category' => 'Stamping',
        'unit' => 'pcs',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Part $part) {
            // Sinkronisasi otomatis jika salah satu belum terisi
            if (empty($part->item_code)) {
                $part->item_code = $part->part_number;
            }
            if (empty($part->part_number)) {
                $part->part_number = $part->item_code;
            }
            if (empty($part->description)) {
                $part->description = $part->part_name;
            }
            if (empty($part->part_name)) {
                $part->part_name = $part->description;
            }
            if (empty($part->main_category)) {
                $part->main_category = match ($part->item_category) {
                    'Non-Steel' => 'Non-Steel',
                    'Assembly' => 'Assembly',
                    default => 'Steel',
                };
            }
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function bomComponents(): HasMany
    {
        return $this->hasMany(PartBom::class, 'parent_part_id');
    }

    public function parentBoms(): HasMany
    {
        return $this->hasMany(PartBom::class, 'component_part_id');
    }

    public function partQuotations(): HasMany
    {
        return $this->hasMany(PartQuotation::class);
    }

    public function vendorItems(): HasMany
    {
        return $this->hasMany(VendorItem::class);
    }

    public function getBomTreeArray(int $currentLevel = 0): array
    {
        $node = [
            'id' => $this->id,
            'level' => $currentLevel,
            'item_code' => $this->item_code ?: $this->part_number,
            'part_number' => $this->part_number ?: $this->item_code,
            'part_name' => $this->part_name ?: $this->description,
            'item_category' => $this->item_category,
            'main_category' => $this->main_category,
            'sub_category' => $this->sub_category,
            'type' => $this->type,
            'material_spec' => $this->material?->spec ?? '-',
            'weight' => $this->weight,
            'unit' => $this->unit,
            'children' => [],
        ];

        foreach ($this->bomComponents()->with('componentPart.material')->get() as $bomItem) {
            if ($bomItem->componentPart) {
                $childNode = $bomItem->componentPart->getBomTreeArray($currentLevel + 1);
                $childNode['quantity'] = $bomItem->quantity;
                $node['children'][] = $childNode;
            }
        }

        return $node;
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PartPriceHistory::class)->orderBy('price_date', 'desc');
    }
}
