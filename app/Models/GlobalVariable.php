<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property float $value
 * @property int|null $updated_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class GlobalVariable extends Model
{
    protected $fillable = [
        'key',
        'name',
        'value',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getValue(string $key, float $default = 0.0): float
    {
        $record = static::where('key', $key)->first();

        return $record ? (float) $record->value : $default;
    }
}
