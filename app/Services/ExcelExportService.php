<?php

namespace App\Services;

use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ExcelExportService
{
    /**
     * Konversi index kolom (0-based) ke huruf koordinat Excel (0 -> A, 25 -> Z, 26 -> AA)
     */
    public static function indexToColumnLetter(int $index): string
    {
        $letter = '';
        $temp = $index;
        while ($temp >= 0) {
            $letter = chr(($temp % 26) + 65).$letter;
            $temp = intdiv($temp, 26) - 1;
        }

        return $letter;
    }

    /**
     * Buat file Excel (.xlsx) valid menggunakan ZipArchive murni
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public static function createXlsxFile(string $outputPath, array $headers, array $rows): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 5. xl/worksheets/sheet1.xml
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>';

        $allRows = array_merge([$headers], $rows);
        foreach ($allRows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $xml .= '<row r="'.$rowNum.'">';
            $cIdx = 0;
            foreach ($row as $val) {
                $colLetter = static::indexToColumnLetter($cIdx);
                $cellRef = $colLetter.$rowNum;
                $cIdx++;

                if ($val === null || $val === '') {
                    $xml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t></t></is></c>';
                } elseif (is_numeric($val) && ! str_starts_with((string) $val, '0') && (string) (float) $val === (string) $val) {
                    $xml .= '<c r="'.$cellRef.'"><v>'.$val.'</v></c>';
                } else {
                    $escaped = htmlspecialchars((string) $val, ENT_XML1, 'UTF-8');
                    $xml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);

        return $zip->close();
    }

    /**
     * Download response untuk data tabular (mendukung .xlsx dan .csv)
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public static function streamDownloadTable(string $baseFileName, array $headers, array $rows, string $format = 'xlsx'): StreamedResponse
    {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $fileName = "{$baseFileName}.{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $out = fopen('php://output', 'w');
                // UTF-8 BOM untuk kompatibilitas Microsoft Excel
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        }

        // Format XLSX
        $tempDir = storage_path('app');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir.'/export_'.uniqid().'.xlsx';

        static::createXlsxFile($tempPath, $headers, $rows);

        return response()->streamDownload(function () use ($tempPath) {
            if (file_exists($tempPath)) {
                readfile($tempPath);
                unlink($tempPath);
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Export data MaterialSteel
     *
     * @param  iterable<MaterialSteel>  $records
     */
    public static function exportMaterialSteel(iterable $records, string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Kuartal',
            'Tahun',
            'Vendor Code',
            'Supplier Name',
            'Spesifikasi Baja',
            'Thickness (mm)',
            'Size',
            'Gap HRC ($/MT)',
            'Gap CRC ($/MT)',
            'Kurs USD',
            'Coil ($)',
            'Coil (IDR)',
            'Sheet ($)',
            'Sheet (IDR)',
            'AP Coil ($)',
            'AP Coil (IDR)',
            'AP Sheet ($)',
            'AP Sheet (IDR)',
            'Status',
            'Catatan',
        ];

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                $r->quarter,
                $r->year,
                $r->vendor_code ?: ($r->supplier?->code ?? '-'),
                $r->supplier_name ?: ($r->supplier?->name ?? '-'),
                $r->steel_spec,
                $r->double_thickness,
                $r->size,
                $r->hr_steel_usd !== null ? (float) $r->hr_steel_usd : (float) ($r->gap_steel_usd ?? 0),
                $r->cr_steel_usd !== null ? (float) $r->cr_steel_usd : (float) ($r->gap_steel_usd ?? 0),
                (float) $r->exchange_rate,
                (float) $r->coil_price,
                (float) $r->coil_price_idr,
                (float) $r->sheet_price,
                (float) $r->sheet_price_idr,
                (float) ($r->ap_coil_price ?: $r->coil_price),
                (float) ($r->ap_coil_price_idr ?: $r->coil_price_idr),
                (float) ($r->ap_sheet_price ?: $r->sheet_price),
                (float) ($r->ap_sheet_price_idr ?: $r->sheet_price_idr),
                $r->status ?: 'Active',
                $r->notes ?? '',
            ];
        }

        $dateSuffix = date('Ymd_His');

        return static::streamDownloadTable("Material_Steel_Export_{$dateSuffix}", $headers, $rows, $format);
    }

    /**
     * Download template impor MaterialSteel
     */
    public static function downloadMaterialSteelTemplate(string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Kuartal',
            'Tahun',
            'Supplier',
            'Spesifikasi Baja',
            'Thickness',
            'Gap HRC ($/MT)',
            'Gap CRC ($/MT)',
            'Kurs USD',
            'Coil ($)',
            'Sheet ($)',
            'AP Coil ($)',
            'AP Sheet ($)',
        ];

        $samples = [
            ['Q2', 2025, 'PT HSSI', 'SPH270C-OD', '1.6', -57, -57, 16022, 1.2096, 1.2346, 1.2096, 1.2346],
        ];

        return static::streamDownloadTable('Template_Import_Material_Steel', $headers, $samples, $format);
    }

    /**
     * Export data StampingPart
     *
     * @param  iterable<StampingPart>  $records
     */
    public static function exportStampingPart(iterable $records, string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Part Number',
            'Part Name',
            'Model',
            'Kuartal',
            'Tahun',
            'Supplier',
            'Spesifikasi Material',
            'Thk (mm)',
            'Wide (mm)',
            'Pitch (mm)',
            'Yield Rate',
            'Berat Blank (kg)',
            'Scrap Weight (kg)',
            'Material Price ($)',
            'Material Price (IDR)',
            'Gross Material Cost',
            'Scrap Price (IDR)',
            'Scrap Cost',
            'Total Material (1)',
            'Machine Ton',
            'SPH',
            'Line Charge',
            'Total Proses (2)',
            'Subtotal (3)',
            'OH Supplied (%)',
            'OH Supplied Cost (4)',
            'OH Self (%)',
            'OH Self Cost (5)',
            'Tooling Depr (7)',
            'Part Cost (8)',
            'Status',
        ];

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                $r->part_number,
                $r->part_name,
                $r->model,
                $r->quarter,
                $r->year,
                $r->supplier_name ?: ($r->supplier?->name ?? '-'),
                $r->spec ?: ($r->materialSteel?->steel_spec ?? '-'),
                (float) $r->thickness,
                (float) $r->wide,
                (float) $r->pitch,
                (float) $r->yield_rate,
                (float) $r->berat,
                (float) $r->scrap_weight,
                (float) $r->material_price,
                (float) $r->material_price_idr,
                (float) $r->gross_mat_cost,
                (float) $r->scrap_price,
                (float) $r->scrap_cost,
                (float) $r->mat_total,
                $r->tonnage,
                (float) $r->man_hour,
                (float) $r->charge,
                (float) $r->proc_total,
                (float) $r->subtotal,
                (float) $r->oh_supplied_pct,
                (float) $r->oh_supplied_cost,
                (float) $r->oh_self_pct,
                (float) $r->oh_self_cost,
                (float) $r->tooling_depr,
                (float) $r->part_cost,
                $r->status ?: 'Active',
            ];
        }

        $dateSuffix = date('Ymd_His');

        return static::streamDownloadTable("Stamping_Parts_Export_{$dateSuffix}", $headers, $rows, $format);
    }

    /**
     * Download template impor StampingPart
     */
    public static function downloadStampingPartTemplate(string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Part Number',
            'Part Name',
            'Model',
            'Kuartal',
            'Tahun',
            'Supplier',
            'Spec Material',
            'Thickness',
            'Wide',
            'Pitch',
            'Yield Rate',
            'Scrap Weight',
            'Scrap Price',
            'Tonnage',
            'SPH',
            'Line Charge',
            'OH Supplied %',
            'OH Self %',
            'Tooling Depr',
        ];

        $samples = [
            ['72388-X7V00-A', 'BRKT, SEAT BELT ANCHOR LH', 'D26A', 'Q2', 2025, 'PT. POSMI STEEL INDONESIA', 'SPH440-OD', 2.6, 390, 148, 2, 0.278, -1500, 'PROGRESSIVE 250T', 9, 45, 11, 4, 0],
        ];

        return static::streamDownloadTable('Template_Import_Stamping_Part', $headers, $samples, $format);
    }

    /**
     * Export data Fastener
     *
     * @param  iterable<Fastener>  $records
     */
    public static function exportFastener(iterable $records, string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Part Number',
            'Part Name',
            'Model',
            'Kuartal',
            'Tahun',
            'Supplier',
            'Spesifikasi Material',
            'Tipe',
            'Diameter (mm)',
            'Pitch (mm)',
            'Berat (kg)',
            'Scrap (kg)',
            'Material Price ($)',
            'Material Price (IDR)',
            'Gross Material Cost',
            'Scrap Price',
            'Scrap Cost',
            'Total Material',
            'Total Proses',
            'Subtotal',
            'Part Cost',
            'Status',
        ];

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                $r->part_number,
                $r->part_name,
                $r->model,
                $r->quarter,
                $r->year,
                $r->supplier_name ?: ($r->supplier?->name ?? '-'),
                $r->spec ?: '-',
                $r->fastener_type ?: 'BOLT',
                (float) $r->diameter,
                (float) $r->pitch,
                (float) $r->berat,
                (float) $r->scrap,
                (float) $r->material_price,
                (float) $r->material_price_idr,
                (float) $r->gross_mat_cost,
                (float) $r->scrap_price,
                (float) $r->scrap_cost,
                (float) $r->mat_total,
                (float) $r->proc_total,
                (float) $r->subtotal,
                (float) $r->part_cost,
                $r->status ?: 'Active',
            ];
        }

        $dateSuffix = date('Ymd_His');

        return static::streamDownloadTable("Fasteners_Export_{$dateSuffix}", $headers, $rows, $format);
    }

    /**
     * Download template impor Fastener
     */
    public static function downloadFastenerTemplate(string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Part Number',
            'Part Name',
            'Model',
            'Kuartal',
            'Tahun',
            'Supplier',
            'Spec Material',
            'Tipe Fastener',
            'Diameter',
            'Pitch',
            'Berat',
            'Scrap',
        ];

        $samples = [
            ['89911-X7095', 'BOLT', 'D26A', 'Q2', 2025, 'PT. TRI CENTRUM FORTUNA', 'S10C', 'BOLT', 10, 1.25, 0.035, 0.005],
        ];

        return static::streamDownloadTable('Template_Import_Fastener', $headers, $samples, $format);
    }
}
