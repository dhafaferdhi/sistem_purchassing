<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationProcess extends Model
{
    protected $fillable = [
        'quotation_id',
        'process_id',
        'process_name',
        'applied_cost',
    ];

    protected function casts(): array
    {
        return [
            'applied_cost' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }
}
