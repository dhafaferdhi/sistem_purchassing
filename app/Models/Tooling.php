<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tooling extends Model
{
    protected $fillable = [
        'tooling_code',
        'name',
        'type',
        'ownership',
        'supplier_id',
        'part_id',
        'tonnage',
        'cavity_count',
        'initial_cost',
        'lifetime_strokes',
        'current_strokes',
        'status',
        'location_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'initial_cost' => 'decimal:2',
            'lifetime_strokes' => 'integer',
            'current_strokes' => 'integer',
            'cavity_count' => 'integer',
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

    public function getStrokesRemainingAttribute(): int
    {
        return max(0, $this->lifetime_strokes - $this->current_strokes);
    }

    public function getWearPercentageAttribute(): float
    {
        return round(($this->current_strokes / max(1, $this->lifetime_strokes)) * 100, 1);
    }
}
