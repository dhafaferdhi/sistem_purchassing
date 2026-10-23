<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubAssemblyPart extends Model
{
    protected $fillable = [
        'year',
        'quarter',
        'part_id',
        'part_number',
        'part_name',
        'model_name',
        'monthly_volume',
        'supplier_id',
        'vendor_code',
        'supplier_name',
        'buyer_name',
        'drawing_reference',
        'eci_number',
        'currency_id',
        'currency_code',
        'exchange_rate',
        'material_breakdown',
        'material_total',
        'process_breakdown',
        'process_total',
        'subtotal',
        'overhead_supplied_percent',
        'overhead_supplied_cost',
        'profit_percent',
        'profit_cost',
        'packing_cost',
        'tooling_depreciation',
        'tooling_details',
        'part_cost',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'monthly_volume' => 'integer',
        'exchange_rate' => 'float',
        'material_breakdown' => 'array',
        'material_total' => 'float',
        'process_breakdown' => 'array',
        'process_total' => 'float',
        'subtotal' => 'float',
        'overhead_supplied_percent' => 'float',
        'overhead_supplied_cost' => 'float',
        'profit_percent' => 'float',
        'profit_cost' => 'float',
        'packing_cost' => 'float',
        'tooling_depreciation' => 'float',
        'tooling_details' => 'array',
        'part_cost' => 'float',
    ];

    protected $attributes = [
        'year' => 2026,
        'quarter' => 'Q1',
        'model_name' => 'D26A',
        'monthly_volume' => 16500,
        'buyer_name' => 'PT. FUJI SEAT INDONESIA',
        'drawing_reference' => 'Mass Prod. Drwg (Exchange rate change)',
        'eci_number' => 'F1225',
        'currency_code' => 'USD',
        'exchange_rate' => 16000.00,
        'overhead_supplied_percent' => 11.00,
        'profit_percent' => 0.00,
        'packing_cost' => 120.00,
        'tooling_depreciation' => 0.00,
        'status' => 'Active',
    ];

    protected static function booted(): void
    {
        static::saving(function (SubAssemblyPart $part) {
            $part->calculateFormulas();
        });
    }

    /**
     * Hitung formula kalkulasi biaya Sub-Assembly
     */
    public function calculateFormulas(): void
    {
        // 1. Material Total (dari rincian child parts)
        $matTotal = 0.0;
        if (is_array($this->material_breakdown)) {
            foreach ($this->material_breakdown as $item) {
                $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $price = isset($item['price']) && (float) $item['price'] > 0 ? (float) $item['price'] : round($qty * $unitPrice, 4);
                $matTotal += $price;
            }
        }
        $this->material_total = round($matTotal, 4);

        // 2. Process Total (dari rincian proses pengelasan, pengecatan, dll.)
        $procTotal = 0.0;
        if (is_array($this->process_breakdown)) {
            foreach ($this->process_breakdown as $item) {
                if (isset($item['price']) && (float) $item['price'] > 0) {
                    $procTotal += (float) $item['price'];
                } elseif (isset($item['man_hour']) && isset($item['charge'])) {
                    $procTotal += round((float) $item['man_hour'] * (float) $item['charge'], 4);
                }
            }
        }
        $this->process_total = round($procTotal, 4);

        // 3. Subtotal = Material Total + Process Total
        $this->subtotal = round($this->material_total + $this->process_total, 4);

        // 4. Overhead Supplied (dihitung dari Process Total)
        $ohPercent = (float) ($this->overhead_supplied_percent ?? 11.0);
        $this->overhead_supplied_cost = round(($ohPercent / 100) * $this->process_total, 4);

        // 5. Profit (dihitung dari Subtotal)
        $profitPercent = (float) ($this->profit_percent ?? 0.0);
        $this->profit_cost = round(($profitPercent / 100) * $this->subtotal, 4);

        // 6. Packing & Tooling Depreciation
        $packing = (float) ($this->packing_cost ?? 120.0);
        $tooling = (float) ($this->tooling_depreciation ?? 0.0);

        // 7. Part Cost Akhir
        $this->part_cost = round($this->subtotal + $this->overhead_supplied_cost + $this->profit_cost + $packing + $tooling, 4);
    }

    public static function getPreviousQuarterInfo(string $quarter, int $year): array
    {
        return match ($quarter) {
            'Q1' => [
                'prev_quarter' => 'Q4',
                'prev_year' => $year - 1,
                'label' => 'Q4 '.($year - 1),
            ],
            'Q2' => [
                'prev_quarter' => 'Q1',
                'prev_year' => $year,
                'label' => 'Q1 '.$year,
            ],
            'Q3' => [
                'prev_quarter' => 'Q2',
                'prev_year' => $year,
                'label' => 'Q2 '.$year,
            ],
            'Q4' => [
                'prev_quarter' => 'Q3',
                'prev_year' => $year,
                'label' => 'Q3 '.$year,
            ],
            default => [
                'prev_quarter' => 'Q1',
                'prev_year' => $year,
                'label' => 'Q1 '.$year,
            ],
        };
    }

    /**
     * Cari data acuan dari kuartal sebelumnya
     */
    public static function findPreviousQuarterData(string $quarter, int $year, ?int $supplierId = null, ?string $partNumber = null): ?array
    {
        $info = static::getPreviousQuarterInfo($quarter, $year);
        $prevQuarter = $info['prev_quarter'];
        $prevYear = $info['prev_year'];

        $query = static::where('year', $prevYear)->where('quarter', $prevQuarter);
        if ($partNumber) {
            $query->where('part_number', trim($partNumber));
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $record = $query->latest('id')->first();

        if (! $record && $partNumber) {
            $record = static::where('year', $prevYear)
                ->where('quarter', $prevQuarter)
                ->where('part_number', trim($partNumber))
                ->latest('id')
                ->first();
        }

        if (! $record && $supplierId) {
            $record = static::where('year', $prevYear)
                ->where('quarter', $prevQuarter)
                ->where('supplier_id', $supplierId)
                ->latest('id')
                ->first();
        }

        if (! $record) {
            // Coba ambil dari ComparativeQuotation jika ada
            $cq = ComparativeQuotation::where('year', $prevYear)
                ->where('quarter', $prevQuarter)
                ->when($partNumber, fn ($q) => $q->where('part_number', trim($partNumber)))
                ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
                ->latest('id')
                ->first();

            if ($cq) {
                return [
                    'source' => 'ComparativeQuotation',
                    'prev_year' => $prevYear,
                    'prev_quarter' => $prevQuarter,
                    'label' => $info['label'],
                    'part_number' => $cq->part_number,
                    'part_name' => $cq->part_name,
                    'model_name' => $cq->model_name,
                    'monthly_volume' => $cq->monthly_volume,
                    'drawing_reference' => $cq->drawing_reference,
                    'eci_number' => $cq->eci_number,
                    'supplier_id' => $cq->supplier_id,
                    'supplier_name' => $cq->supplier_name,
                    'exchange_rate' => (float) $cq->new_exchange_rate,
                    'material_breakdown' => $cq->material_breakdown,
                    'process_breakdown' => $cq->process_breakdown,
                    'overhead_supplied_percent' => (float) $cq->overhead_supplied_percent,
                    'profit_percent' => (float) $cq->profit_percent,
                    'packing_cost' => (float) $cq->packing_cost,
                    'tooling_depreciation' => (float) $cq->tooling_cost,
                    'tooling_details' => $cq->tooling_details,
                    'part_cost' => (float) $cq->part_cost,
                ];
            }

            return null;
        }

        return [
            'source' => 'SubAssemblyPart',
            'prev_year' => $prevYear,
            'prev_quarter' => $prevQuarter,
            'label' => $info['label'],
            'part_number' => $record->part_number,
            'part_name' => $record->part_name,
            'model_name' => $record->model_name,
            'monthly_volume' => $record->monthly_volume,
            'drawing_reference' => $record->drawing_reference,
            'eci_number' => $record->eci_number,
            'supplier_id' => $record->supplier_id,
            'vendor_code' => $record->vendor_code,
            'supplier_name' => $record->supplier_name,
            'exchange_rate' => (float) $record->exchange_rate,
            'material_breakdown' => $record->material_breakdown,
            'process_breakdown' => $record->process_breakdown,
            'overhead_supplied_percent' => (float) $record->overhead_supplied_percent,
            'profit_percent' => (float) $record->profit_percent,
            'packing_cost' => (float) $record->packing_cost,
            'tooling_depreciation' => (float) $record->tooling_depreciation,
            'tooling_details' => $record->tooling_details,
            'part_cost' => (float) $record->part_cost,
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
