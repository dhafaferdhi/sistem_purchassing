<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quotation_id
 * @property int $process_id
 * @property string $process_name
 * @property float $applied_cost
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
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
