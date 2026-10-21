<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PudRequest extends Model
{
    public const STATUS_DELIVERED = 'Delivered to PUD';

    public const STATUS_REQUEST_QUOTATION = 'Request Quotation';

    public const STATUS_REVIEW_NEGOTIATION = 'Review & Negotiation';

    public const STATUS_ITEM_CODE_CREATED = 'Item Code Created';

    public const STATUS_CLOSED = 'Closed';

    public const STATUS_CANCELLED = 'Cancelled by User';

    public const PLANTS = ['SUNTER', 'KIIC', 'SC1', 'SC2'];

    public const UNITS = ['Pcs', 'Set', 'Box', 'Unit', 'Lot', 'Trip', 'Case'];

    public const CURRENCIES = ['Rp', 'Yen', 'Dollar'];

    public const PLANT_DIVISIONS = [
        'SUNTER' => ['ACCOUNTING', 'ASSY', 'GA', 'IT', 'MTC', 'PPIC', 'QUALITY'],
        'KIIC' => ['ASSY', 'FLC', 'GA', 'INJECTION', 'IR', 'IT', 'MTC', 'PE', 'PPIC ASSY', 'PPIC WELDING', 'PROJECT SUPPORT', 'QUALITY', 'STAMPING', 'URETHANE', 'WELDING'],
        'SC1' => ['ASSY', 'GA', 'IT', 'MTC', 'PPIC', 'QUALITY', 'URETHANE'],
        'SC2' => ['ADMIN PLANT', 'GA', 'IT'],
    ];

    protected $fillable = [
        'request_number',
        'request_date',
        'user_id',
        'user_name',
        'division',
        'plant',
        'part_name',
        'brand',
        'type',
        'qty',
        'unit',
        'purpose',
        'spec',
        'photo',
        'status',
        'closed_at',
        'cancelled_at',
        'supplier_1_name',
        'supplier_1_currency_1',
        'supplier_1_price_1',
        'supplier_1_currency_2',
        'supplier_1_price_2',
        'supplier_1_quotation_file',
        'supplier_2_name',
        'supplier_2_currency_1',
        'supplier_2_price_1',
        'supplier_2_currency_2',
        'supplier_2_price_2',
        'supplier_2_quotation_file',
        'supplier_3_name',
        'supplier_3_currency_1',
        'supplier_3_price_1',
        'supplier_3_currency_2',
        'supplier_3_price_2',
        'supplier_3_quotation_file',
        'selected_supplier',
        'item_code',
        'feedback',
        'internal_notes',
        'history',
        'is_deleted',
        'deleted_at',
        'deleted_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_DELIVERED,
        'unit' => 'Pcs',
        'qty' => 1,
        'supplier_1_currency_1' => 'Rp',
        'supplier_1_currency_2' => 'Rp',
        'supplier_2_currency_1' => 'Rp',
        'supplier_2_currency_2' => 'Rp',
        'supplier_3_currency_1' => 'Rp',
        'supplier_3_currency_2' => 'Rp',
        'is_deleted' => false,
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'deleted_at' => 'datetime',
            'is_deleted' => 'boolean',
            'qty' => 'decimal:2',
            'supplier_1_price_1' => 'decimal:2',
            'supplier_1_price_2' => 'decimal:2',
            'supplier_2_price_1' => 'decimal:2',
            'supplier_2_price_2' => 'decimal:2',
            'supplier_3_price_1' => 'decimal:2',
            'supplier_3_price_2' => 'decimal:2',
            'history' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PudRequest $model) {
            if (empty($model->request_number)) {
                $model->request_number = static::generateRequestNumber();
            }
            if (empty($model->request_date)) {
                $model->request_date = now()->toDateString();
            }
            if (empty($model->history)) {
                $model->history = [
                    [
                        'action' => 'Request Created',
                        'by' => $model->user_name ?: (auth()->user()?->name ?? 'System'),
                        'time' => now()->format('d/m/Y H:i'),
                    ],
                ];
            }
        });

        static::updating(function (PudRequest $model) {
            if ($model->isDirty('status')) {
                if ($model->status === self::STATUS_CLOSED && empty($model->closed_at)) {
                    $model->closed_at = now();
                }
                if ($model->status === self::STATUS_CANCELLED && empty($model->cancelled_at)) {
                    $model->cancelled_at = now();
                }
            }
        });
    }

    public static function generateRequestNumber(): string
    {
        $year = date('Y');
        $prefix = "PUD-{$year}-";
        $latest = static::where('request_number', 'LIKE', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($latest && preg_match('/PUD-\d+-(\d+)/', $latest->request_number, $matches)) {
            $next = ((int) $matches[1]) + 1;
        } else {
            $next = 1;
        }

        return sprintf('PUD-%s-%06d', $year, $next);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Hitung hari kerja (Senin - Jumat) sejak request dibuat
     */
    public function getWorkDaysElapsedAttribute(): int
    {
        $start = Carbon::parse($this->created_at ?? $this->request_date ?? now())->startOfDay();
        $end = Carbon::now()->startOfDay();

        if ($start->greaterThanOrEqualTo($end)) {
            return 1;
        }

        $days = 0;
        $period = $start->copy();
        while ($period->lessThanOrEqualTo($end)) {
            if (! $period->isWeekend()) {
                $days++;
            }
            $period->addDay();
        }

        return max(1, $days);
    }

    /**
     * Hitung status progress SLA target 5 hari kerja
     */
    public function getSlaProgressAttribute(): string
    {
        if ($this->status === self::STATUS_CLOSED) {
            return 'Closed';
        }
        if ($this->status === self::STATUS_CANCELLED) {
            return 'Cancelled';
        }

        $days = $this->work_days_elapsed;
        $target = 5;

        if ($days <= max(1, $target - 3)) {
            return 'On Track'; // Hari 1-2
        }
        if ($days === $target - 2) {
            return 'Attention'; // Hari 3
        }
        if ($days === $target - 1) {
            return 'Due Soon'; // Hari 4
        }

        return 'Overdue'; // Hari 5+
    }

    public function getSlaBadgeColorAttribute(): string
    {
        return match ($this->sla_progress) {
            'On Track' => 'success',
            'Attention' => 'warning',
            'Due Soon' => 'orange',
            'Overdue' => 'danger',
            'Closed' => 'success',
            'Cancelled' => 'gray',
            default => 'info',
        };
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CLOSED => 'success',
            self::STATUS_ITEM_CODE_CREATED => 'info',
            self::STATUS_REVIEW_NEGOTIATION => 'orange',
            self::STATUS_REQUEST_QUOTATION => 'warning',
            self::STATUS_CANCELLED => 'gray',
            default => 'slate',
        };
    }

    public function addHistory(string $action, ?string $by = null): void
    {
        $history = $this->history ?? [];
        $history[] = [
            'action' => $action,
            'by' => $by ?: (auth()->user()?->name ?? 'System'),
            'time' => now()->format('d/m/Y H:i'),
        ];
        $this->history = $history;
    }
}
