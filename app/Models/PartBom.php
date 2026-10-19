<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartBom extends Model
{
    protected $fillable = [
        'parent_part_id',
        'component_part_id',
        'quantity',
        'level',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'level' => 'integer',
        ];
    }

    public function parentPart(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'parent_part_id');
    }

    public function componentPart(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'component_part_id');
    }
}

