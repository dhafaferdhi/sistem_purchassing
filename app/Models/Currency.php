<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'exchange_rate_to_idr',
        'last_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate_to_idr' => 'decimal:4',
            'last_updated_at' => 'datetime',
        ];
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function partQuotations(): HasMany
    {
        return $this->hasMany(PartQuotation::class);
    }
}

