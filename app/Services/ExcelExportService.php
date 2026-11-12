<?php

namespace App\Services;

use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ExcelExportService
{
    public const EXPORT_CHUNK_SIZE = 500;

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
     * Buat file Excel (.xlsx) valid secara streaming ke file XML sementara agar hemat memori untuk ribuan baris.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function createXlsxFile(string $outputPath, array $headers, iterable $rows): bool
    {
        return static::writeStreamingXlsx($outputPath, $headers, function ($handle) use ($rows): void {
            $rowNum = 2;
            foreach ($rows as $row) {
                static::writeXlsxRow($handle, $rowNum, $row);
                $rowNum++;
            }
        });
    }

    /**
     * Download response untuk data tabular (mendukung .xlsx dan .csv).
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function streamDownloadTable(string $baseFileName, array $headers, iterable $rows, string $format = 'xlsx'): StreamedResponse
    {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $fileName = "{$baseFileName}.{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows): void {
                $out = fopen('php://output', 'w');
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

        $tempPath = static::createTempFilePath('xlsx');
        static::createXlsxFile($tempPath, $headers, $rows);

        return static::streamAndDeleteTempXlsx($tempPath, $fileName);
    }

    /**
     * Download response dari Eloquent Builder secara chunked (streaming per 500 baris) agar siap mengekspor puluhan ribu baris.
     *
     * @param  array<int, string>  $headers
     * @param  callable(mixed): array<int, mixed>  $rowMapper
     */
    public static function streamDownloadFromQuery(
        string $baseFileName,
        array $headers,
        Builder $query,
        callable $rowMapper,
        string $format = 'xlsx'
    ): StreamedResponse {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $fileName = "{$baseFileName}.{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $query, $rowMapper): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers);

                (clone $query)->chunk(self::EXPORT_CHUNK_SIZE, function ($chunk) use ($out, $rowMapper): void {
                    foreach ($chunk as $record) {
                        fputcsv($out, $rowMapper($record));
                    }
                });

                fclose($out);
            }, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        }

        $tempPath = static::createTempFilePath('xlsx');
        static::writeStreamingXlsx($tempPath, $headers, function ($handle) use ($query, $rowMapper): void {
            $rowNum = 2;
            (clone $query)->chunk(self::EXPORT_CHUNK_SIZE, function ($chunk) use ($handle, &$rowNum, $rowMapper): void {
                foreach ($chunk as $record) {
                    static::writeXlsxRow($handle, $rowNum, $rowMapper($record));
                    $rowNum++;
                }
            });
        });

        return static::streamAndDeleteTempXlsx($tempPath, $fileName);
    }

    /**
     * Tulis struktur XLSX dengan worksheet XML dialirkan langsung ke disk temp file.
     *
     * @param  array<int, string>  $headers
     * @param  callable(resource): void  $writeDataRows
     */
    protected static function writeStreamingXlsx(string $outputPath, array $headers, callable $writeDataRows): bool
    {
        $sheetTempPath = static::createTempFilePath('xml');
        $handle = fopen($sheetTempPath, 'wb');
        if ($handle === false) {
            return false;
        }

        try {
            fwrite(
                $handle,
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetData>'
            );

            static::writeXlsxRow($handle, 1, $headers);
            $writeDataRows($handle);

            fwrite($handle, '</sheetData></worksheet>');
            fclose($handle);
            $handle = null;

            $zip = new ZipArchive;
            if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return false;
            }

            $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>';
            $zip->addFromString('[Content_Types].xml', $contentTypes);

            $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>';
            $zip->addFromString('_rels/.rels', $rels);

            $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>';
            $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

            $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>';
            $zip->addFromString('xl/workbook.xml', $workbook);

            $zip->addFile($sheetTempPath, 'xl/worksheets/sheet1.xml');

            return $zip->close();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (file_exists($sheetTempPath)) {
                @unlink($sheetTempPath);
            }
        }
    }

    /**
     * Tulis satu baris Excel XML ke stream file secara aman (angka 0 & desimal < 1 tetap bertipe numerik).
     *
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    protected static function writeXlsxRow($handle, int $rowNum, array $row): void
    {
        $rowXml = '<row r="'.$rowNum.'">';
        $cIdx = 0;

        foreach ($row as $val) {
            $cellRef = static::indexToColumnLetter($cIdx).$rowNum;
            $cIdx++;

            if ($val === null || $val === '') {
                $rowXml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t></t></is></c>';
            } elseif (static::isExcelNumericValue($val)) {
                $rowXml .= '<c r="'.$cellRef.'"><v>'.((string) ($val + 0)).'</v></c>';
            } else {
                $escaped = htmlspecialchars((string) $val, ENT_XML1, 'UTF-8');
                $rowXml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
            }
        }

        $rowXml .= '</row>';
        fwrite($handle, $rowXml);
    }

    /**
     * Deteksi apakah nilai harus ditulis sebagai angka di Excel (tetap menjaga kode berawalan nol seperti '00123' sebagai string).
     */
    protected static function isExcelNumericValue(mixed $val): bool
    {
        if (is_int($val) || is_float($val)) {
            return is_finite((float) $val);
        }

        if (! is_string($val) || ! is_numeric($val)) {
            return false;
        }

        if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $val) !== 1) {
            return false;
        }

        return strlen(ltrim(explode('.', $val)[0], '-')) <= 15;
    }

    protected static function createTempFilePath(string $extension): string
    {
        $tempDir = storage_path('app');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir.'/export_'.uniqid('', true).'.'.$extension;
    }

    protected static function streamAndDeleteTempXlsx(string $tempPath, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($tempPath): void {
            if (file_exists($tempPath)) {
                readfile($tempPath);
                @unlink($tempPath);
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Generator/helper untuk memetakan iterable (Collection / LazyCollection / array) dengan eager-load aman.
     *
     * @param  iterable<int, mixed>  $records
     * @param  callable(mixed): array<int, mixed>  $rowMapper
     * @return \Generator<int, array<int, mixed>>
     */
    protected static function mapIterableWithSupplier(iterable $records, callable $rowMapper): \Generator
    {
        if ($records instanceof EloquentCollection) {
            $records->loadMissing('supplier');
        }

        foreach ($records as $record) {
            yield $rowMapper($record);
        }
    }

    /**
     * Export data MaterialSteel (mendukung Eloquent Builder chunked atau iterable).
     *
     * @param  Builder<MaterialSteel>|iterable<MaterialSteel>  $records
     */
    public static function exportMaterialSteel(Builder|iterable $records, string $format = 'xlsx'): StreamedResponse
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

        $mapper = function (MaterialSteel $r): array {
            $supplier = $r->relationLoaded('supplier') ? $r->getRelation('supplier') : null;

            return [
                $r->quarter,
                $r->year,
                $r->vendor_code ?: ($supplier?->code ?? '-'),
                $r->supplier_name ?: ($supplier?->name ?? '-'),
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
        };

        $dateSuffix = date('Ymd_His');
        $baseName = "Material_Steel_Export_{$dateSuffix}";

        if ($records instanceof Builder) {
            return static::streamDownloadFromQuery($baseName, $headers, $records->with('supplier'), $mapper, $format);
        }

        return static::streamDownloadTable($baseName, $headers, static::mapIterableWithSupplier($records, $mapper), $format);
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
     * Export data StampingPart (mendukung Eloquent Builder chunked atau iterable).
     *
     * @param  Builder<StampingPart>|iterable<StampingPart>  $records
     */
    public static function exportStampingPart(Builder|iterable $records, string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Part Number',
            'Part Number to Compare',
            'Part Name',
            'Model',
            'Monthly Volume',
            'Drawing Reference',
            'ECI Number',
            'Quotation Date',
            'Kuartal',
            'Tahun',
            'Supplier',
            'Spec Material',
            'Thk (mm)',
            'Size',
            'Wide (mm)',
            'Pitch (mm)',
            'Yield Rate',
            'Berat Blank (kg)',
            'Kurs USD',
            'Exchg Fluctuation',
            'Coil ($)',
            'Coil (IDR)',
            'Sheet ($)',
            'Sheet (IDR)',
            'Scrap Weight (kg)',
            'Scrap Price / kg (Rp)',
            'Gross Material Cost (Rp)',
            'Scrap Recovery Cost (Rp)',
            '(1) Total Material Cost (Rp)',
            'Process Name / Tonnage',
            'SPH (Man Hour)',
            'Process Charge',
            '(2) Total Process Cost (Rp)',
            '(3) Sub Total (Rp)',
            'OH Supplied (%)',
            '(4) OH Supplied Cost (Rp)',
            'OH Self (%)',
            '(5) OH Self Cost (Rp)',
            '(6) Transportation Cost (Rp)',
            '(7) Packing Cost (Rp)',
            '(8) Part Cost Excl. Tooling (Rp)',
            '(9) Die & Jig Depreciation (Rp)',
            'TOTAL FINAL PART COST (Rp)',
            'Tooling Details (JSON)',
            'Status',
            'Remarks',
            'Comments',
            'Alteration',
            'ETC Contents',
            'Notes',
        ];

        $mapper = function (StampingPart $r): array {
            $supplier = $r->relationLoaded('supplier') ? $r->getRelation('supplier') : null;

            return [
                $r->part_number,
                $r->part_no_to_compare,
                $r->part_name,
                $r->model_name,
                (int) ($r->monthly_volume ?? 16500),
                $r->drawing_reference,
                $r->eci_number,
                $r->quotation_date ? (is_string($r->quotation_date) ? $r->quotation_date : $r->quotation_date->format('Y-m-d')) : null,
                $r->quarter,
                $r->year,
                $r->supplier_name ?: ($supplier?->name ?? '-'),
                $r->steel_spec ?: '-',
                (float) ($r->double_thickness ?? 0),
                $r->size,
                (float) ($r->wide ?? 0),
                (float) ($r->pitch ?? 0),
                (float) ($r->yield_rate ?? 1),
                (float) ($r->berat ?? 0),
                (float) ($r->exchange_rate ?? 0),
                (float) ($r->exchange_rate_fluctuation ?? 1),
                (float) ($r->coil_price ?? 0),
                (float) ($r->coil_price_idr ?? 0),
                (float) ($r->sheet_price ?? 0),
                (float) ($r->sheet_price_idr ?? 0),
                (float) ($r->scrap_weight ?? 0),
                (float) ($r->scrap_price_per_kg ?? -1500),
                (float) ($r->gross_material_cost ?? 0),
                (float) ($r->scrap_cost ?? 0),
                (float) ($r->material_total ?? 0),
                $r->process_name,
                (float) ($r->process_man_hour ?? 0),
                (float) ($r->process_charge ?? 0),
                (float) ($r->process_total ?? 0),
                (float) ($r->subtotal ?? 0),
                (float) ($r->overhead_supplied_percent ?? 11),
                (float) ($r->overhead_supplied_cost ?? 0),
                (float) ($r->overhead_self_percent ?? 4),
                (float) ($r->overhead_self_cost ?? 0),
                (float) ($r->transportation_cost ?? 0),
                (float) ($r->packing_cost ?? 0),
                (float) ($r->part_cost_excluding_tooling ?? 0),
                (float) ($r->tooling_depreciation ?? 0),
                (float) ($r->part_cost ?? 0),
                ! empty($r->tooling_details) ? json_encode($r->tooling_details, JSON_UNESCAPED_UNICODE) : '',
                $r->status ?: 'Active',
                $r->remarks,
                $r->comments,
                $r->alteration,
                $r->etc_contents,
                $r->notes,
            ];
        };

        $dateSuffix = date('Ymd_His');
        $baseName = "Stamping_Parts_Export_{$dateSuffix}";

        if ($records instanceof Builder) {
            return static::streamDownloadFromQuery($baseName, $headers, $records->with('supplier'), $mapper, $format);
        }

        return static::streamDownloadTable($baseName, $headers, static::mapIterableWithSupplier($records, $mapper), $format);
    }

    /**
     * Download template impor StampingPart
     */
    public static function downloadStampingPartTemplate(string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Part Number',
            'Part Number to Compare',
            'Part Name',
            'Model',
            'Monthly Volume',
            'Drawing Reference',
            'ECI Number',
            'Quotation Date',
            'Kuartal',
            'Tahun',
            'Supplier',
            'Spec Material',
            'Thickness',
            'Wide',
            'Pitch',
            'Yield Rate',
            'Kurs USD',
            'Coil ($)',
            'Sheet ($)',
            'Sheet (IDR)',
            'Scrap Weight',
            'Scrap Price',
            'Process Name',
            'SPH',
            'Charge',
            'OH Supplied %',
            'OH Self %',
            'Transportation Cost',
            'Packing Cost',
            'Tooling Depr',
            'Comments',
            'Remarks',
            'Alteration',
            'ETC Contents',
        ];

        $samples = [
            [
                '72388-X7V00-A',
                '72388-X7V00',
                'BRKT, SEAT BELT ANCHOR LH',
                'D26A',
                16500,
                'Mass Prod. Drwg',
                'F1225',
                '2025-04-01',
                'Q2',
                2025,
                'PT. POSMI STEEL INDONESIA',
                'SPH440-OD',
                2.6,
                390,
                148,
                2,
                14300,
                1.2320,
                1.2320,
                17617.60,
                0.278,
                -1500,
                '1. PROGRESSIVE 250T',
                9,
                45,
                11,
                4,
                0,
                0,
                0,
                '',
                '',
                '',
                '',
            ],
        ];

        return static::streamDownloadTable('Template_Import_Stamping_Part', $headers, $samples, $format);
    }

    /**
     * Export data Fastener (mendukung Eloquent Builder chunked atau iterable).
     *
     * @param  Builder<Fastener>|iterable<Fastener>  $records
     */
    public static function exportFastener(Builder|iterable $records, string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Kuartal',
            'Tahun',
            'Supplier',
            'Part Number',
            'Old Part No.',
            'Part Name',
            'Model',
            'Monthly Volume',
            'Drawing Reference',
            'ECI Number',
            'Quotation Date',
            'Spec Material',
            'Wire Spec',
            'Dia / Thick (mm)',
            'Old Kurs USD (Rp)',
            'Kurs USD (Rp)',
            'Exchg Fluctuation',
            'Wire ($/kg)',
            'Wire (Rp/kg)',
            'Nett Weight (gr)',
            'Gross Weight (gr)',
            'Wire Cost / pc (Rp)',
            'Mat Control (%)',
            'Material Control (Rp)',
            'Washer Weight (gr)',
            'Washer Price (Rp)',
            '(1) Material Total (Rp)',
            'Heading (Rp)',
            'Rolling (Rp)',
            'Washing Rate (Rp/kg)',
            'Washing (Rp)',
            'Plating Rate (Rp/kg)',
            'Plating (Rp)',
            'Final Inspection (Rp)',
            'Packing (Rp)',
            '(2) Process Total (Rp)',
            '(3) Subtotal (Rp)',
            'OH Supplied (%)',
            '(4) OH Supplied Cost (Rp)',
            'OH Self (%)',
            '(5) OH Self Cost (Rp)',
            '(6) Transportation (Rp)',
            '(7) Cost Down (Rp)',
            '(8) Part Cost Excl. Tooling (Rp)',
            '(9) Die & Jig Depr (Rp)',
            'TOTAL PART COST (Rp)',
            'Tooling Details (JSON)',
            'Status',
            'Remarks',
            'Comments',
            'Alteration',
            'ETC Contents',
            'Notes',
        ];

        $mapper = function (Fastener $r): array {
            $supplier = $r->relationLoaded('supplier') ? $r->getRelation('supplier') : null;

            return [
                $r->quarter,
                $r->year,
                $r->supplier_name ?: ($supplier?->name ?? '-'),
                $r->part_number ?: '-',
                $r->old_part_number ?: '',
                $r->part_name ?: '-',
                $r->model_name ?: 'D26A',
                (int) ($r->monthly_volume ?? 20000),
                $r->drawing_reference,
                $r->eci_number,
                $r->quotation_date ? (is_string($r->quotation_date) ? $r->quotation_date : $r->quotation_date->format('Y-m-d')) : '',
                $r->steel_spec ?: '-',
                $r->wire_spec ?: '-',
                (float) ($r->double_thickness ?: $r->diameter),
                (float) ($r->old_exchange_rate ?? 0),
                (float) $r->exchange_rate,
                (float) ($r->exchange_rate_fluctuation ?? 1),
                (float) $r->wire_price_usd_kg,
                (float) $r->wire_price_idr_kg,
                (float) $r->nett_weight_gr,
                (float) $r->gross_weight_gr,
                (float) $r->wire_price_pc,
                (float) ($r->material_control_percent ?? 5),
                (float) $r->material_control_cost,
                (float) $r->washer_weight_gr,
                (float) $r->washer_price,
                (float) $r->material_total,
                (float) $r->heading_cost,
                (float) $r->rolling_cost,
                (float) $r->washing_cost_kg,
                (float) $r->washing_cost,
                (float) $r->plating_cost_kg,
                (float) $r->plating_cost,
                (float) $r->inspection_cost,
                (float) $r->packing_cost,
                (float) $r->process_total,
                (float) $r->subtotal,
                (float) ($r->overhead_supplied_percent ?? 0),
                (float) ($r->overhead_supplied_cost ?? 0),
                (float) ($r->overhead_self_percent ?? 14.3),
                (float) $r->overhead_self_cost,
                (float) ($r->transportation_cost ?? 0),
                (float) ($r->cost_down ?? 0),
                (float) ($r->part_cost_excluding_tooling ?? ($r->part_cost - $r->tooling_depreciation)),
                (float) $r->tooling_depreciation,
                (float) $r->part_cost,
                ! empty($r->tooling_details) ? json_encode($r->tooling_details, JSON_UNESCAPED_UNICODE) : '',
                $r->status ?: 'Active',
                $r->remarks,
                $r->comments,
                $r->alteration,
                $r->etc_contents,
                $r->notes,
            ];
        };

        $dateSuffix = date('Ymd_His');
        $baseName = "Fasteners_Export_{$dateSuffix}";

        if ($records instanceof Builder) {
            return static::streamDownloadFromQuery($baseName, $headers, $records->with('supplier'), $mapper, $format);
        }

        return static::streamDownloadTable($baseName, $headers, static::mapIterableWithSupplier($records, $mapper), $format);
    }

    /**
     * Download template impor Fastener
     */
    public static function downloadFastenerTemplate(string $format = 'xlsx'): StreamedResponse
    {
        $headers = [
            'Kuartal',
            'Tahun',
            'Supplier',
            'Part Number',
            'Old Part No.',
            'Part Name',
            'Model',
            'Monthly Volume',
            'Drawing Reference',
            'ECI Number',
            'Quotation Date',
            'Spec Material',
            'Wire Spec',
            'Thickness',
            'Old Kurs USD (Rp)',
            'Kurs USD',
            'Wire ($/kg)',
            'Wire (Rp/kg)',
            'Nett Weight',
            'Gross Weight',
            'Mat Control (%)',
            'Washer Price',
            'Washer Weight',
            'Heading Cost',
            'Rolling Cost',
            'Washing Cost/kg',
            'Plating Cost/kg',
            'Inspection Cost',
            'Packing Cost',
            'OH Supplied %',
            'OH Self %',
            'Transportation Cost',
            'Cost Down',
            'Tooling Depr',
            'Status',
            'Comments',
            'Remarks',
            'Alteration',
            'ETC Contents',
        ];

        $samples = [
            [
                'Q1',
                2026,
                'PT. NITTO ALAM INDONESIA',
                '90041-19960',
                '',
                'BOLT,W/WASHER',
                'D26A',
                20000,
                'Official Dwg (supplier selection)',
                '90041-19960',
                '2025-12-02',
                'SWCH-45K',
                'WIRE OF RIVET [SWCH-45K](TAIWAN)',
                4.4,
                16321,
                16465,
                2.02,
                33259.30,
                4.40,
                4.84,
                5,
                198,
                4.5,
                48,
                76,
                500,
                10000,
                5,
                3,
                0,
                14.3,
                0,
                0,
                0,
                'Active',
                '',
                '',
                '',
                '',
            ],
        ];

        return static::streamDownloadTable('Template_Import_Fastener', $headers, $samples, $format);
    }
}
