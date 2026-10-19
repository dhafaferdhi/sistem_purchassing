<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuotationExportController extends Controller
{
    public function exportCsv(): StreamedResponse
    {
        $fileName = 'Rekap_Estimasi_Purchasing_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'No Dokumen',
            'Kategori',
            'Spec Material',
            'Diameter (mm)',
            'Thickness (mm)',
            'Size Material',
            'Weight (kg)',
            'Basic Rate (USD)',
            'Exchange Rate (IDR)',
            'Fluktuasi JFE',
            'Price IDR',
            'Total Biaya Proses (IDR)',
            'Profit (%)',
            'Profit (IDR)',
            'Transport (IDR)',
            'Packing (IDR)',
            'Total Cost (IDR)',
            'Status',
            'Estimator',
            'Disetujui Oleh',
            'Tanggal Pembuatan',
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM so Excel opens indonesian/symbols properly
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, $columns);

            $quotations = Quotation::with(['user', 'material', 'approvedBy'])->orderBy('id', 'desc')->get();

            foreach ($quotations as $q) {
                fputcsv($file, [
                    $q->document_number,
                    $q->category,
                    $q->material?->spec ?? '-',
                    $q->diameter ?? '-',
                    $q->thickness ?? '-',
                    $q->size_material ?? '-',
                    $q->weight,
                    $q->snapshot_basic_rate,
                    $q->snapshot_exchange_rate,
                    $q->snapshot_fluktuasi,
                    $q->price_idr,
                    $q->total_process_cost,
                    $q->profit_percentage . '%',
                    $q->profit,
                    $q->transport,
                    $q->packing,
                    $q->total_cost,
                    strtoupper($q->status),
                    $q->user?->name ?? '-',
                    $q->approvedBy?->name ?? '-',
                    $q->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

