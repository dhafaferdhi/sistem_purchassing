<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyRolloverLog extends Model
{
    protected $fillable = [
        'type',
        'source_period',
        'target_period',
        'exchange_rate',
        'currencies_count',
        'material_steels_count',
        'stamping_parts_count',
        'details',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:4',
            'currencies_count' => 'integer',
            'material_steels_count' => 'integer',
            'stamping_parts_count' => 'integer',
            'details' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
