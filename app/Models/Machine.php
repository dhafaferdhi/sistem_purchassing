<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    protected $fillable = [
        'name',
        'category',
        'stamping_type',
        'tonnage',
        'hourly_rate',
        'operator_hourly_rate',
        'default_capacity_per_hour',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'operator_hourly_rate' => 'decimal:2',
            'default_capacity_per_hour' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class);
    }
}
