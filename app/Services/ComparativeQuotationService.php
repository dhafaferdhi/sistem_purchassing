<?php

namespace App\Services;

use App\Models\ComparativeQuotation;
use App\Models\Part;
use Illuminate\Support\Collection;

class ComparativeQuotationService
{
    /**
     * Ambil data matriks komparatif multi-periode untuk suatu Part Number
     *
     * @return array{
     *     part_number: string,
     *     part_name: string,
     *     model_name: string,
     *     monthly_volume: int,
     *     supplier_name: string,
     *     buyer_name: string,
     *     drawing_reference: string,
     *     eci_number: string,
     *     periods: Collection<int, ComparativeQuotation>,
     *     material_rows: array<string, array<string, mixed>>,
     *     process_rows: array<string, array<string, mixed>>
     * }
     */
    public static function getComparativeMatrix(string $partNumber): array
    {
        $records = ComparativeQuotation::where('part_number', $partNumber)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            return [
                'part_number' => $partNumber,
                'part_name' => '-',
                'model_name' => 'D26A',
                'monthly_volume' => 0,
                'supplier_name' => '-',
                'buyer_name' => 'PT. FUJI SEAT INDONESIA',
                'drawing_reference' => 'Mass Prod. Drwg',
                'eci_number' => '-',
                'periods' => collect(),
                'material_rows' => [],
                'process_rows' => [],
            ];
        }

        $first = $records->first();

        // Kumpulkan semua nama item material dan proses unik dari seluruh kuartal
        $materialRowKeys = [];
        $processRowKeys = [];

        foreach ($records as $r) {
            if (is_array($r->material_breakdown)) {
                foreach ($r->material_breakdown as $item) {
                    $key = $item['name'] ?? ($item['item_name'] ?? '');
                    if ($key && ! in_array($key, $materialRowKeys, true)) {
                        $materialRowKeys[] = $key;
                    }
                }
            }

            if (is_array($r->process_breakdown)) {
                foreach ($r->process_breakdown as $proc) {
                    $key = $proc['name'] ?? ($proc['process_name'] ?? '');
                    if ($key && ! in_array($key, $processRowKeys, true)) {
                        $processRowKeys[] = $key;
                    }
                }
            }
        }

        // Susun baris terstruktur untuk tabel matriks
        $materialRows = [];
        foreach ($materialRowKeys as $matKey) {
            $row = [
                'name' => $matKey,
                'periods' => [],
            ];
            foreach ($records as $r) {
                $found = null;
                if (is_array($r->material_breakdown)) {
                    foreach ($r->material_breakdown as $item) {
                        if (($item['name'] ?? ($item['item_name'] ?? '')) === $matKey) {
                            $found = $item;
                            break;
                        }
                    }
                }
                $row['periods'][$r->id] = $found;
            }
            $materialRows[] = $row;
        }

        $processRows = [];
        foreach ($processRowKeys as $procKey) {
            $row = [
                'name' => $procKey,
                'periods' => [],
            ];
            foreach ($records as $r) {
                $found = null;
                if (is_array($r->process_breakdown)) {
                    foreach ($r->process_breakdown as $proc) {
                        if (($proc['name'] ?? ($proc['process_name'] ?? '')) === $procKey) {
                            $found = $proc;
                            break;
                        }
                    }
                }
                $row['periods'][$r->id] = $found;
            }
            $processRows[] = $row;
        }

        return [
            'part_number' => $first->part_number,
            'part_name' => $first->part_name,
            'model_name' => $first->model_name ?: 'D26A',
            'monthly_volume' => (int) $first->monthly_volume,
            'supplier_name' => $first->supplier_name,
            'buyer_name' => $first->buyer_name ?: 'PT. FUJI SEAT INDONESIA',
            'drawing_reference' => $first->drawing_reference ?: 'Mass Prod. Drwg',
            'eci_number' => $first->eci_number ?: 'F1225',
            'periods' => $records,
            'material_rows' => $materialRows,
            'process_rows' => $processRows,
        ];
    }

    /**
     * Hitung ulang seluruh selisih kuartal (difference) secara berurutan
     */
    public static function recalculateAllDifferences(string $partNumber): void
    {
        $records = ComparativeQuotation::where('part_number', $partNumber)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        $prevTotal = null;
        $prevSubtotal = null;

        foreach ($records as $index => $rec) {
            $rec->recalculate();

            if ($index === 0 || $prevTotal === null) {
                $rec->cost_difference = 0.0;
            } else {
                $rec->cost_difference = round((float) $rec->total_cost - (float) $prevTotal, 4);
            }

            $rec->save();
            $prevTotal = $rec->total_cost;
            $prevSubtotal = $rec->subtotal;
        }
    }

    /**
     * Ekspor lembar komparatif ke format CSV
     */
    public static function exportToCsv(string $partNumber): string
    {
        $matrix = static::getComparativeMatrix($partNumber);
        $periods = $matrix['periods'];

        if ($periods->isEmpty()) {
            return "No data found for part: {$partNumber}\n";
        }

        $out = fopen('php://memory', 'w');

        // Header 1: Metadata
        fputcsv($out, array_merge(['Part No', $matrix['part_number']], array_fill(0, $periods->count() - 1, '')));
        fputcsv($out, array_merge(['Part Name', $matrix['part_name']], array_fill(0, $periods->count() - 1, '')));
        fputcsv($out, array_merge(['Supplier', $matrix['supplier_name']], array_fill(0, $periods->count() - 1, '')));
        fputcsv($out, array_merge(['Model', $matrix['model_name']], array_fill(0, $periods->count() - 1, '')));
        fputcsv($out, array_merge(['Monthly Volume', $matrix['monthly_volume']], array_fill(0, $periods->count() - 1, '')));
        fputcsv($out, []);

        // Periode Kolom
        $periodRow = ['Period / Quarter'];
        $rateRow = ['Exchange Rate (IDR)'];
        foreach ($periods as $p) {
            $periodRow[] = $p->period_label;
            $rateRow[] = number_format((float) $p->new_exchange_rate, 0, ',', '.');
        }
        fputcsv($out, $periodRow);
        fputcsv($out, $rateRow);
        fputcsv($out, []);

        // 1. Material Breakdown
        fputcsv($out, ['--- 1. MATERIAL BREAKDOWN ---']);
        foreach ($matrix['material_rows'] as $mat) {
            $row = [$mat['name']];
            foreach ($periods as $p) {
                $item = $mat['periods'][$p->id] ?? null;
                $row[] = $item ? number_format((float) ($item['price'] ?? 0), 2, ',', '.') : '-';
            }
            fputcsv($out, $row);
        }

        $matTotalRow = ['(1) Material Total'];
        foreach ($periods as $p) {
            $matTotalRow[] = number_format((float) $p->material_total, 2, ',', '.');
        }
        fputcsv($out, $matTotalRow);
        fputcsv($out, []);

        // 2. Process Breakdown
        fputcsv($out, ['--- 2. PROCESS BREAKDOWN ---']);
        foreach ($matrix['process_rows'] as $proc) {
            $row = [$proc['name']];
            foreach ($periods as $p) {
                $item = $proc['periods'][$p->id] ?? null;
                $row[] = $item ? number_format((float) ($item['price'] ?? 0), 2, ',', '.') : '-';
            }
            fputcsv($out, $row);
        }

        $procTotalRow = ['(2) Process Total'];
        foreach ($periods as $p) {
            $procTotalRow[] = number_format((float) $p->process_total, 2, ',', '.');
        }
        fputcsv($out, $procTotalRow);
        fputcsv($out, []);

        // 3. Subtotal
        $subtotalRow = ['(3) Subtotal = (1) + (2)'];
        foreach ($periods as $p) {
            $subtotalRow[] = number_format((float) $p->subtotal, 2, ',', '.');
        }
        fputcsv($out, $subtotalRow);

        // 4. O/H & Profit
        $ohSuppliedRow = ['(4) O/H & Profit supplied portion'];
        $profitRow = ['(5) Profit / Self portion'];
        $packingRow = ['(7) Packing'];
        $partCostRow = ['(8) Part Cost (Total Cost)'];
        $diffRow = ['Difference vs Prev Quarter'];

        foreach ($periods as $p) {
            $ohSuppliedRow[] = number_format((float) $p->overhead_supplied_cost, 2, ',', '.');
            $profitRow[] = number_format((float) ($p->profit_cost ?: $p->overhead_self_cost), 2, ',', '.');
            $packingRow[] = number_format((float) $p->packing_cost, 2, ',', '.');
            $partCostRow[] = number_format((float) $p->part_cost, 2, ',', '.');
            $diffRow[] = ($p->cost_difference > 0 ? '+' : '').number_format((float) $p->cost_difference, 2, ',', '.');
        }

        fputcsv($out, $ohSuppliedRow);
        fputcsv($out, $profitRow);
        fputcsv($out, $packingRow);
        fputcsv($out, $partCostRow);
        fputcsv($out, $diffRow);

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return (string) $csv;
    }
}
