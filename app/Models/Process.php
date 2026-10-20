<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Process extends Model
{
    protected $fillable = [
        'name',
        'stage_type',
        'category',
        'machine_id',
        'cycle_time_seconds',
        'tooling_type',
        'dies_code',
        'dies_name',
        'default_cost',
        'is_active',
    ];

    protected $attributes = [
        'category' => 'Both',
        'stage_type' => 'General',
        'tooling_type' => 'None',
        'cycle_time_seconds' => 0,
        'default_cost' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'default_cost' => 'decimal:4',
            'cycle_time_seconds' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Process $process) {
            if (empty($process->category)) {
                $process->category = 'Both';
            }
            if (empty($process->stage_type)) {
                $process->stage_type = 'General';
            }
        });
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function quotationProcesses(): HasMany
    {
        return $this->hasMany(QuotationProcess::class);
    }
}
