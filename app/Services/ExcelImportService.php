<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use ZipArchive;

class ExcelImportService
{
    /**
     * Resolusi Supplier berdasarkan kode atau nama, atau buat baru secara aman
     */
    public static function resolveSupplier(string $name, ?string $code = null, string $category = 'Steel'): Supplier
    {
        $supplier = null;
        if (! empty($code)) {
            $supplier = Supplier::where('code', $code)->first();
        }
        if (! $supplier && ! empty($name)) {
            $supplier = Supplier::where('name', $name)->first();
        }
        if (! $supplier) {
            $gen = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 5));
            $finalCode = ! empty($code) ? $code : 'VND-'.($gen ?: 'SUP');
            if (Supplier::where('code', $finalCode)->exists()) {
                $finalCode .= '-'.rand(10, 99);
            }

            $supplier = Supplier::create([
                'name' => $name,
                'code' => $finalCode,
                'category' => $category,
                'is_active' => true,
            ]);
        }

        return $supplier;
    }

    /**
     * Parsing file Excel (.xlsx) atau .csv menjadi matrix baris dan kolom.
     *
     * @return array<int, array<int, string>>
     */
    public static function parseFileToGrid(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            return static::parseCsvToGrid($filePath);
        }

        // Default asumsikan .xlsx
        return static::parseXlsxToGrid($filePath);
    }

    /**
     * Parse file format OpenXML .xlsx murni dengan ZipArchive & SimpleXML
     *
     * @return array<int, array<int, string>>
     */
    public static function parseXlsxToGrid(string $filePath): array
    {
        $grid = [];
        $zip = new ZipArchive;

        if ($zip->open($filePath) !== true) {
            return [];
        }

        // 1. Ekstrak shared strings jika ada
        $sharedStrings = [];
        if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $content = $zip->getFromIndex($idx);
            if ($content) {
                $xml = simplexml_load_string($content);
                if ($xml && isset($xml->si)) {
                    foreach ($xml->si as $si) {
                        $val = '';
                        if (isset($si->t)) {
                            $val = (string) $si->t;
                        } elseif (isset($si->r)) {
                            foreach ($si->r as $r) {
                                $val .= (string) ($r->t ?? '');
                            }
                        }
                        $sharedStrings[] = $val;
                    }
                }
            }
        }

        // 2. Baca sheet pertama (sheet1.xml)
        $sheetIndex = $zip->locateName('xl/worksheets/sheet1.xml');
        if ($sheetIndex === false) {
            // Coba cari nama sheet pertama apa saja di xl/worksheets/
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                    $sheetIndex = $i;
                    break;
                }
            }
        }

        if ($sheetIndex !== false) {
            $sheetContent = $zip->getFromIndex($sheetIndex);
            if ($sheetContent) {
                $xml = simplexml_load_string($sheetContent);
                if ($xml && isset($xml->sheetData->row)) {
                    foreach ($xml->sheetData->row as $row) {
                        $rNum = (int) $row['r'];
                        foreach ($row->c as $c) {
                            $ref = (string) $c['r'];
                            $type = (string) $c['t'];
                            $val = (string) $c->v;

                            if ($type === 's' && isset($sharedStrings[(int) $val])) {
                                $val = $sharedStrings[(int) $val];
                            } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                                $val = (string) $c->is->t;
                            }

                            [$r, $colIdx] = static::coordinateToIndexes($ref);
                            $grid[$r][$colIdx] = trim($val);
                        }
                    }
                }
            }
        }

        $zip->close();
        ksort($grid);

        return $grid;
    }

    /**
     * Parse file CSV dengan deteksi otomatis delimiter (koma, titik koma, tab)
     *
     * @return array<int, array<int, string>>
     */
    public static function parseCsvToGrid(string $filePath): array
    {
        $grid = [];
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        // Deteksi delimiter dari baris pertama
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ',';
        if ($firstLine) {
            $semicolons = substr_count($firstLine, ';');
            $commas = substr_count($firstLine, ',');
            $tabs = substr_count($firstLine, "\t");
            if ($semicolons > $commas && $semicolons > $tabs) {
                $delimiter = ';';
            } elseif ($tabs > $commas && $tabs > $semicolons) {
                $delimiter = "\t";
            }
        }

        $rowIdx = 1;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            foreach ($data as $colIdx => $val) {
                $grid[$rowIdx][$colIdx] = trim((string) $val);
            }
            $rowIdx++;
        }
        fclose($handle);

        return $grid;
    }

    /**
     * Konversi koordinat sel Excel (misal "B2", "AA5") ke indeks [row, col]
     *
     * @return array{0: int, 1: int}
     */
    public static function coordinateToIndexes(string $cellRef): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', strtoupper($cellRef), $matches);
        $colStr = $matches[1] ?? 'A';
        $row = (int) ($matches[2] ?? 1);

        $col = 0;
        for ($i = 0; $i < strlen($colStr); $i++) {
            $col = $col * 26 + (ord($colStr[$i]) - 64);
        }

        return [$row, $col - 1];
    }

    /**
     * Pemetaan alias header kolom untuk deteksi otomatis yang sangat fleksibel
     *
     * @return array<string, array<string>>
     */
    public static function getColumnAliases(): array
    {
        return [
            'quarter' => ['quarter', 'kuartal', 'q', 'periode', 'period', 'quartal'],
            'year' => ['year', 'tahun', 'thn', 'fiscal_year', 'tahun_kurs'],
            'vendor_code' => ['vendor_code', 'kode_vendor', 'supplier_code', 'kode_supplier', 'code', 'vendor', 'kode'],
            'supplier_name' => ['supplier_name', 'nama_supplier', 'supplier', 'suplier', 'vendor_name', 'nama_vendor', 'company', 'perusahaan'],
            'steel_spec' => ['steel_spec', 'spec', 'spesifikasi', 'material_spec', 'material', 'spesifikasi_baja', 'part_spec', 'spesifikasi_material', 'nama_spesifikasi'],
            'double_thickness' => ['double_thickness', 'thickness', 'thickness(mm)', 'thickness_mm', 'ketebalan', 'tebal', 'thick', 'tikhnes', 't', 'thickness_mm', 'ketebalan_mm'],
            'size' => ['size', 'ukuran', 'dimensi', 'dimension', 'ukuran_size', 'size_material'],
            'berat' => ['berat', 'weight', 'berat_kg', 'weight_kg', 'berat(kg)', 'weight(kg)', 'berat_part'],
            'gap_steel_usd' => ['gap_steel_usd', 'gap/mt', 'gap/mt(usd)', 'gap_mt', 'gap_mt_usd', 'gap_usd', 'gap/mt_usd', 'gap_steel_mt', 'gap_mt_usd'],
            'gap_steel_kg_usd' => ['gap_steel_kg_usd', 'gap/kg', 'gap/kg(usd)', 'gap_kg', 'gap_kg_usd', 'gap/kg_usd', 'gap_steel_kg'],
            'exchange_rate' => ['exchange_rate', 'ex_rate', 'ex_rate(idr)', 'kurs', 'kurs_idr', 'rate', 'ex_rate_idr', 'kurs_usd', 'ex_rate_kurs', 'kurs_usd_idr'],
            'coil_price' => ['coil_price', 'coil', 'coil(usd)', 'coil_usd', 'harga_coil', 'basic_rate', 'coil_price_usd', 'coil_usd_price', 'ar - coil ($)', 'ar - coil', 'ar_coil', 'ar_coil_usd', 'arcoilusd', 'arcoil', 'ap - coil ($)', 'ap - coil', 'ap_coil', 'ap_coil_usd', 'coil (usd)'],
            'coil_price_idr' => ['coil_price_idr', 'coil(idr)', 'coil_idr', 'harga_coil_idr', 'ar - coil (idr)', 'ar_coil_idr', 'arcoilidr', 'ap - coil (idr)', 'ap_coil_idr', 'apcoilidr'],
            'sheet_price' => ['sheet_price', 'sheet', 'sheet(usd)', 'sheet_usd', 'harga_sheet', 'sheet_price_usd', 'sheet_usd_price', 'ar sheet ($)', 'ar - sheet ($)', 'ar sheet', 'ar_sheet', 'ar_sheet_usd', 'arsheetusd', 'arsheet', 'ap sheet ($)', 'ap - sheet ($)', 'ap sheet', 'ap_sheet', 'sheet (usd)'],
            'sheet_price_idr' => ['sheet_price_idr', 'sheet(idr)', 'sheet_idr', 'harga_sheet_idr', 'ar - sheet (idr)', 'ar_sheet_idr', 'arsheetidr', 'ap - sheet (idr)', 'ap_sheet_idr', 'apsheetidr'],
            'fluktuasi_jfe' => ['fluktuasi_jfe', 'jfe', 'fluktuasi', 'jfe_fluctuation'],
            'status' => ['status', 'status_kontrak'],
            'notes' => ['notes', 'catatan', 'keterangan', 'remark', 'remarks'],
        ];
    }

    /**
     * Deteksi khusus format matriks eskalasi harga berkala / multi-kuartal
     * (format master purchasing PT HSSI / perusahan dengan kolom Q1-2020 s.d Q4-2026 ke samping)
     *
     * @param  array<int, array<int, string>>  $grid
     * @param  array<string, mixed>  $defaults
     * @return array{header_row: int|null, column_map: array<string, int>, data_rows: array<int, array<string, mixed>>}|null
     */
    public static function detectQuarterlyMatrixLayout(array $grid, array $defaults = []): ?array
    {
        // Cari blok periode kuartal di baris 1-6
        $periods = [];
        foreach (array_slice($grid, 0, 6, true) as $rIdx => $cols) {
            foreach ($cols as $colIdx => $val) {
                if (preg_match('/(Q[1-4])[-_\s]*(\d{4})/i', (string) $val, $m)) {
                    $q = strtoupper($m[1]);
                    $y = (int) $m[2];

                    // Cari Kurs di baris atasnya atau sekitarnya
                    $rate = null;
                    for ($checkR = 1; $checkR < $rIdx; $checkR++) {
                        $possibleRate = $grid[$checkR][$colIdx] ?? ($grid[$checkR][$colIdx + 1] ?? null);
                        if ($possibleRate && is_numeric($possibleRate) && (float) $possibleRate >= 10000) {
                            $rate = (float) $possibleRate;
                            break;
                        }
                    }

                    // Cari Gap MT dari teks header, misal "HR -20$/MT"
                    $gapMt = 0.0;
                    if (preg_match('/([+-]?\d+(?:[.,]\d+)?)\s*\$\s*\/\s*MT/i', (string) $val, $gm)) {
                        $gapMt = (float) str_replace(',', '.', $gm[1]);
                    }

                    $periods[] = [
                        'quarter' => $q,
                        'year' => $y,
                        'col_start' => $colIdx,
                        'header_row' => $rIdx,
                        'rate' => $rate,
                        'gap_mt' => $gapMt,
                        'raw_title' => $val,
                    ];
                }
            }
        }

        // Jika tidak ditemukan minimal 2 blok kuartal di header, bukan layout matriks
        if (count($periods) < 2) {
            return null;
        }

        // Cari supplier dari baris 1-4
        $supplierName = $defaults['supplier_name'] ?? null;
        if (! $supplierName) {
            $hasHssi = false;
            $foundSupplier = null;
            foreach (array_slice($grid, 0, 4, true) as $r => $cols) {
                foreach ($cols as $val) {
                    if (stripos((string) $val, 'HSSI') !== false) {
                        $hasHssi = true;
                        break 2;
                    } elseif (stripos((string) $val, 'USCI') !== false) {
                        $foundSupplier = 'PT USCI';
                    } elseif (! $foundSupplier && stripos((string) $val, 'KENAIKAN') !== false && preg_match('/KENAIKAN\s+([A-Z0-9]+)/i', (string) $val, $m)) {
                        $cand = strtoupper($m[1]);
                        if ($cand !== 'ADM') {
                            $foundSupplier = 'PT '.$cand;
                        }
                    }
                }
            }
            $supplierName = $hasHssi ? 'PT HSSI' : ($foundSupplier ?? 'PT HSSI');
        }

        // Tentukan periode mana yang diambil
        $targetQuarter = $defaults['quarter'] ?? null;
        $targetYear = ! empty($defaults['year']) ? (int) $defaults['year'] : null;

        $selectedPeriod = null;
        if ($targetQuarter && $targetYear) {
            foreach ($periods as $p) {
                if ($p['quarter'] === $targetQuarter && $p['year'] === $targetYear) {
                    $selectedPeriod = $p;
                    break;
                }
            }
        }

        // Jika tidak ditemukan atau tidak ditentukan, ambil periode paling akhir (terbaru)
        if (! $selectedPeriod) {
            $selectedPeriod = end($periods);
        }

        $startCol = $selectedPeriod['col_start'];
        $rate = $selectedPeriod['rate'] ?? ($defaults['exchange_rate'] ?? 17796);

        // Cari baris pertama yang memuat spec baja di kolom 0 atau 1
        $firstDataRow = null;
        for ($r = 1; $r <= count($grid); $r++) {
            if (! isset($grid[$r])) {
                continue;
            }
            $c0 = (string) ($grid[$r][0] ?? '');
            $c1 = (string) ($grid[$r][1] ?? '');

            if (preg_match('/SP[HC]\d+/i', $c0) || preg_match('/SP[HC]\d+/i', $c1) || preg_match('/NSC\d+/i', $c1)) {
                $firstDataRow = $r;
                break;
            }
        }

        if (! $firstDataRow) {
            $firstDataRow = 8;
        }

        $dataRows = [];
        for ($r = $firstDataRow; $r <= count($grid); $r++) {
            if (! isset($grid[$r])) {
                continue;
            }
            $rawSpec = trim((string) ($grid[$r][1] ?? ''));
            if (empty($rawSpec)) {
                $rawSpec = trim((string) ($grid[$r][0] ?? ''));
            }

            if (empty($rawSpec)) {
                continue;
            }

            // Hilangkan keterangan seperti "==> New Spec"
            $rawSpec = preg_replace('/\s*==>.*$/', '', $rawSpec);

            // Ekstrak ketebalan: misal "SPH270C-OD T1.6" -> spec="SPH270C-OD", thickness="1.6"
            $thickness = '';
            $spec = $rawSpec;
            if (preg_match('/^(.*?)\s+T(\d+(?:\.\d+)?)/i', $rawSpec, $sm)) {
                $spec = trim($sm[1]);
                $thickness = $sm[2];
            } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:mm)?$/i', $rawSpec, $sm)) {
                $thickness = $sm[1];
            }

            // Ambil harga dari kolom target periode
            $coilUsd = (float) static::cleanNumeric($grid[$r][$startCol] ?? 0);
            $coilIdr = (float) static::cleanNumeric($grid[$r][$startCol + 1] ?? 0);
            $sheetUsd = (float) static::cleanNumeric($grid[$r][$startCol + 2] ?? 0);
            $sheetIdr = (float) static::cleanNumeric($grid[$r][$startCol + 3] ?? 0);

            if ($coilUsd <= 0 && $sheetUsd <= 0) {
                continue;
            }

            $code = trim((string) ($grid[$r][2] ?? ''));
            $gapMt = $selectedPeriod['gap_mt'];
            if ($gapMt == 0.0 && $coilUsd > 0 && $sheetUsd > 0) {
                $gapMt = round(($sheetUsd - $coilUsd) * 1000, 4);
            }

            $size = ! empty($thickness) ? "{$thickness} x 1219 x C" : '';

            $dataRows[] = [
                'year' => $selectedPeriod['year'],
                'quarter' => $selectedPeriod['quarter'],
                'supplier_name' => $supplierName,
                'vendor_code' => ! empty($code) ? $code : 'VND-HSSI',
                'steel_spec' => $spec,
                'double_thickness' => $thickness,
                'diameter' => $thickness,
                'size' => $size,
                'exchange_rate' => $rate,
                'coil_price' => $coilUsd,
                'coil_price_idr' => $coilIdr > 0 ? $coilIdr : round($coilUsd * $rate, 2),
                'sheet_price' => $sheetUsd,
                'sheet_price_idr' => $sheetIdr > 0 ? $sheetIdr : round($sheetUsd * $rate, 2),
                'gap_steel_usd' => $gapMt,
                'gap_steel_kg_usd' => round($gapMt / 1000, 4),
                'fluktuasi_jfe' => round($gapMt / 1000, 4),
                'status' => 'Active',
                'notes' => "Diimpor dari Excel: {$selectedPeriod['quarter']}-{$selectedPeriod['year']} ({$selectedPeriod['raw_title']})",
            ];
        }

        return [
            'header_row' => $selectedPeriod['header_row'],
            'column_map' => [
                'steel_spec' => 1,
                'vendor_code' => 2,
                'coil_price' => $startCol,
                'coil_price_idr' => $startCol + 1,
                'sheet_price' => $startCol + 2,
                'sheet_price_idr' => $startCol + 3,
            ],
            'data_rows' => $dataRows,
        ];
    }

    /**
     * Deteksi posisi baris header dan pemetaan kolom dari matriks Excel
     *
     * @param  array<int, array<int, string>>  $grid
     * @return array{header_row: int|null, column_map: array<string, int>, data_rows: array<int, array<string, mixed>>}
     */
    public static function detectColumnsAndData(array $grid, array $defaults = []): array
    {
        // 1. Cek apakah format merupakan matriks eskalasi kuartalan berkala
        $matrixResult = static::detectQuarterlyMatrixLayout($grid, $defaults);
        if ($matrixResult !== null && ! empty($matrixResult['data_rows'])) {
            return $matrixResult;
        }

        $aliases = static::getColumnAliases();
        $bestHeaderRow = null;
        $bestMatchCount = 0;
        $bestColumnMap = [];

        // Scan 15 baris pertama untuk menemukan baris header terbaik dengan kunci atribut UNIK terbanyak
        foreach (array_slice($grid, 0, 15, true) as $rIdx => $rowCols) {
            $currentMap = [];

            foreach ($rowCols as $colIdx => $cellValue) {
                $cleaned = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $cellValue));
                if (empty($cleaned)) {
                    continue;
                }

                foreach ($aliases as $fieldKey => $keywords) {
                    if (isset($currentMap[$fieldKey])) {
                        continue;
                    }

                    foreach ($keywords as $kw) {
                        $cleanedKw = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $kw));
                        // Keyword pendek (<= 2 karakter seperti 't' atau 'q') harus exact match
                        if (strlen($cleanedKw) <= 2) {
                            if ($cleaned === $cleanedKw) {
                                $currentMap[$fieldKey] = $colIdx;
                                break;
                            }
                        } else {
                            if ($cleaned === $cleanedKw || str_contains($cleaned, $cleanedKw)) {
                                $currentMap[$fieldKey] = $colIdx;
                                break;
                            }
                        }
                    }
                }
            }

            // Hitung jumlah field unik yang cocok
            $distinctMatchCount = count($currentMap);
            if ($distinctMatchCount > $bestMatchCount) {
                $bestMatchCount = $distinctMatchCount;
                $bestHeaderRow = $rIdx;
                $bestColumnMap = $currentMap;
            }
        }

        // Jika baris header standar memiliki minimal 3 atribut berbeda (misal Spec, Harga, Kurs/Vendor), gunakan tabel horizontal
        if ($bestMatchCount >= 3) {
            $dataRows = [];
            $lastSpec = null;
            $lastVendor = null;

            foreach ($grid as $rIdx => $rowCols) {
                if ($rIdx <= $bestHeaderRow) {
                    continue;
                }

                $rowRecord = [];
                $hasData = false;

                foreach ($bestColumnMap as $fieldKey => $colIdx) {
                    $val = $rowCols[$colIdx] ?? '';
                    if ($val !== '') {
                        $hasData = true;
                    }
                    $rowRecord[$fieldKey] = $val;
                }

                if (! $hasData) {
                    continue;
                }

                // Carry forward spec jika merged-cell (spec diisi di baris pertama saja untuk beberapa ketebalan)
                if (! empty($rowRecord['steel_spec'])) {
                    $lastSpec = $rowRecord['steel_spec'];
                } elseif (! empty($lastSpec) && (! empty($rowRecord['double_thickness']) || ! empty($rowRecord['coil_price']) || ! empty($rowRecord['sheet_price']))) {
                    $rowRecord['steel_spec'] = $lastSpec;
                }

                if (! empty($rowRecord['supplier_name'])) {
                    $lastVendor = $rowRecord['supplier_name'];
                } elseif (! empty($lastVendor)) {
                    $rowRecord['supplier_name'] = $lastVendor;
                }

                if (empty($rowRecord['steel_spec']) && empty($rowRecord['coil_price']) && empty($rowRecord['sheet_price'])) {
                    continue;
                }

                $cleanedRow = static::cleanRowValues($rowRecord, $defaults);
                if (! empty($cleanedRow['steel_spec']) || $cleanedRow['coil_price'] > 0 || $cleanedRow['sheet_price'] > 0) {
                    $dataRows[] = $cleanedRow;
                }
            }

            return [
                'header_row' => $bestHeaderRow,
                'column_map' => $bestColumnMap,
                'data_rows' => $dataRows,
            ];
        }

        // Jika tabel horizontal tidak terdeteksi, gunakan ekstraksi cerdas layout kolom/transposed (seperti kalkulasi JFE)
        return static::extractFromKeyValueLayout($grid, $defaults);
    }

    /**
     * Ekstraksi cerdas jika format Excel bukan tabel datar, melainkan formulir berkolom / blok kalkulasi
     *
     * @param  array<int, array<int, string>>  $grid
     * @return array{header_row: int|null, column_map: array<string, int>, data_rows: array<int, array<string, mixed>>}
     */
    protected static function extractFromKeyValueLayout(array $grid, array $defaults = []): array
    {
        $detectedData = [];
        $flatCells = [];

        foreach ($grid as $r => $cols) {
            foreach ($cols as $c => $val) {
                $flatCells[$r][$c] = trim((string) $val);
            }
        }

        $commonRate = null;
        $jfe = null;

        // Cari global exchange rate & JFE di baris awal
        foreach ($flatCells as $r => $cols) {
            foreach ($cols as $c => $val) {
                $lower = strtolower($val);
                if (str_contains($lower, 'rate') || str_contains($lower, 'kurs')) {
                    $nextVal = $cols[$c + 1] ?? ($flatCells[$r + 1][$c] ?? null);
                    if ($nextVal && is_numeric(static::cleanNumeric($nextVal)) && (float) static::cleanNumeric($nextVal) > 1000) {
                        $commonRate = (float) static::cleanNumeric($nextVal);
                    }
                }
                if (str_contains($lower, 'jfe')) {
                    $nextVal = $cols[$c + 1] ?? ($flatCells[$r + 1][$c] ?? null);
                    if ($nextVal && is_numeric(static::cleanNumeric($nextVal))) {
                        $jfe = (float) static::cleanNumeric($nextVal);
                    }
                }
            }
        }

        // Cari blok kolom spesifikasi baja
        foreach ($flatCells as $r => $cols) {
            foreach ($cols as $c => $val) {
                $lower = strtolower($val);
                if ($lower === 'spec' || $lower === 'spesifikasi') {
                    $spec = $cols[$c + 1] ?? null;
                    if ($spec && strlen($spec) > 2) {
                        $item = [
                            'steel_spec' => $spec,
                            'exchange_rate' => $commonRate ?? ($defaults['exchange_rate'] ?? 16000),
                            'fluktuasi_jfe' => $jfe ?? 0,
                            'gap_steel_kg_usd' => $jfe ?? 0,
                        ];

                        // Cari thickness di baris-baris terdekat di kolom yang sama
                        for ($offset = 1; $offset <= 8; $offset++) {
                            $checkRow = $r + $offset;
                            if (isset($flatCells[$checkRow][$c])) {
                                $label = strtolower($flatCells[$checkRow][$c]);
                                $targetVal = $flatCells[$checkRow][$c + 1] ?? null;

                                if (str_contains($label, 'tikhnes') || str_contains($label, 'thick') || str_contains($label, 'tebal')) {
                                    $item['double_thickness'] = $targetVal;
                                } elseif (str_contains($label, 'basic rate') || str_contains($label, 'coil')) {
                                    $item['coil_price'] = $targetVal;
                                } elseif (str_contains($label, 'sheet')) {
                                    $item['sheet_price'] = $targetVal;
                                } elseif (str_contains($label, 'rate') && empty($item['exchange_rate'])) {
                                    $item['exchange_rate'] = $targetVal;
                                }
                            }
                        }

                        $detectedData[] = static::cleanRowValues($item, $defaults);
                    }
                }
            }
        }

        return [
            'header_row' => 1,
            'column_map' => [],
            'data_rows' => $detectedData,
        ];
    }

    /**
     * Pembersihan dan validasi nilai tiap kolom agar siap disimpan ke Database
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function cleanRowValues(array $row, array $defaults = []): array
    {
        $year = ! empty($row['year']) ? (int) preg_replace('/\D/', '', (string) $row['year']) : (int) ($defaults['year'] ?? date('Y'));
        $quarter = ! empty($row['quarter']) ? strtoupper(trim((string) $row['quarter'])) : ($defaults['quarter'] ?? 'Q1');

        // Normalisasi kuartal jika terisi "1" -> "Q1"
        if (preg_match('/^[1-4]$/', $quarter)) {
            $quarter = 'Q'.$quarter;
        } elseif (! in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'])) {
            if (preg_match('/Q[1-4]/i', $quarter, $m)) {
                $quarter = strtoupper($m[0]);
            } else {
                $quarter = $defaults['quarter'] ?? 'Q1';
            }
        }

        $supplierName = trim((string) ($row['supplier_name'] ?? ($defaults['supplier_name'] ?? '')));
        $vendorCode = trim((string) ($row['vendor_code'] ?? ($defaults['vendor_code'] ?? '')));

        if (empty($vendorCode) && ! empty($supplierName)) {
            $vendorCode = 'VND-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $supplierName), 0, 5));
        }

        $spec = trim((string) ($row['steel_spec'] ?? ''));

        // Bersihkan ketebalan mm
        $thickness = trim((string) ($row['double_thickness'] ?? ''));
        $thickness = preg_replace('/[a-zA-Z\s]+/', '', $thickness);

        // Jika thickness kosong tapi ada di spec (misal SPCC-SD 1.2mm, SPH270C-OD T1.6), ekstrak ketebalan dari spec
        if (empty($thickness) && preg_match('/(?:T|\s+|-)\s*(\d+(?:\.\d+)?)\s*(?:mm)?\s*$/i', $spec, $m)) {
            $thickness = $m[1];
        }

        $size = trim((string) ($row['size'] ?? ($row['ukuran'] ?? '')));
        if (empty($thickness) && ! empty($size) && preg_match('/^(\d+(?:\.\d+)?)/', $size, $sm)) {
            $thickness = $sm[1];
        }

        if (empty($size) && ! empty($thickness)) {
            $size = "{$thickness} x 1219 x C";
        }

        // Bersihkan angka ketebalan dan satuan mm dari spec
        $spec = MaterialSteel::cleanSteelSpec($spec);

        // Ambil Kurs Exchange Rate
        $rate = static::cleanNumeric($row['exchange_rate'] ?? null);
        if ($rate <= 0) {
            $rate = (float) ($defaults['exchange_rate'] ?? 16000);
            if ($rate <= 0) {
                $rate = (float) (Currency::where('code', 'USD')->where('year', $year)->value('exchange_rate_to_idr') ?? 16000);
            }
        }

        $coilPrice = static::cleanNumeric($row['coil_price'] ?? null);
        $sheetPrice = static::cleanNumeric($row['sheet_price'] ?? null);

        // Jika sheet kosong tapi coil ada, beri default
        if ($sheetPrice <= 0 && $coilPrice > 0) {
            $sheetPrice = $coilPrice;
        } elseif ($coilPrice <= 0 && $sheetPrice > 0) {
            $coilPrice = $sheetPrice;
        }

        // Gap USD per MT & per KG (bisa bernilai negatif, misal -20$/MT)
        $hasExplicitGap = isset($row['gap_steel_usd']) && $row['gap_steel_usd'] !== '' && $row['gap_steel_usd'] !== null;
        $gapUsd = $hasExplicitGap ? static::cleanNumeric($row['gap_steel_usd']) : null;

        $hasExplicitGapKg = isset($row['gap_steel_kg_usd']) && $row['gap_steel_kg_usd'] !== '' && $row['gap_steel_kg_usd'] !== null;
        $gapKgUsd = $hasExplicitGapKg ? static::cleanNumeric($row['gap_steel_kg_usd']) : null;

        if ($gapUsd === null && $coilPrice > 0 && $sheetPrice > 0) {
            $gapUsd = round(($sheetPrice - $coilPrice) * 1000, 4);
        } elseif ($gapUsd === null) {
            $gapUsd = 0.0;
        }

        if ($gapKgUsd === null && $gapUsd != 0.0) {
            $gapKgUsd = round($gapUsd / 1000, 4);
        } elseif ($gapKgUsd === null) {
            $gapKgUsd = 0.0;
        }

        $coilPriceIdr = static::cleanNumeric($row['coil_price_idr'] ?? null);
        if ($coilPriceIdr <= 0 && $coilPrice > 0) {
            $coilPriceIdr = round($coilPrice * $rate, 2);
        }

        $sheetPriceIdr = static::cleanNumeric($row['sheet_price_idr'] ?? null);
        if ($sheetPriceIdr <= 0 && $sheetPrice > 0) {
            $sheetPriceIdr = round($sheetPrice * $rate, 2);
        }

        $steelPrice = (float) ($row['steel_price'] ?? ($coilPrice ?: $sheetPrice ?: 0));
        $berat = static::cleanNumeric($row['berat'] ?? $row['weight'] ?? null);

        return [
            'year' => $year,
            'quarter' => $quarter,
            'supplier_name' => $supplierName,
            'vendor_code' => $vendorCode,
            'steel_spec' => $spec,
            'double_thickness' => $thickness,
            'diameter' => $thickness,
            'size' => $size,
            'berat' => $berat,
            'currency_code' => 'USD',
            'exchange_rate' => $rate,
            'steel_price' => $steelPrice,
            'steel_price_idr' => round($steelPrice * $rate, 2),
            'coil_price' => $coilPrice,
            'coil_price_idr' => $coilPriceIdr,
            'sheet_price' => $sheetPrice,
            'sheet_price_idr' => $sheetPriceIdr,
            'gap_steel_usd' => $gapUsd,
            'gap_steel_kg_usd' => $gapKgUsd,
            'fluktuasi_jfe' => static::cleanNumeric($row['fluktuasi_jfe'] ?? $gapKgUsd),
            'status' => ! empty($row['status']) ? trim((string) $row['status']) : 'Active',
            'notes' => trim((string) ($row['notes'] ?? 'Diimpor otomatis via Excel')),
        ];
    }

    /**
     * Membersihkan string angka dari karakter mata uang, koma desimal, dsb.
     */
    public static function cleanNumeric(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);
        // Hapus simbol mata uang dan huruf
        $str = preg_replace('/[Rp$USD¥\s]/i', '', $str);

        // Jika terdapat koma dan titik (misal 16.000,50 atau 16,000.50)
        if (str_contains($str, ',') && str_contains($str, '.')) {
            $lastComma = strrpos($str, ',');
            $lastDot = strrpos($str, '.');
            if ($lastComma > $lastDot) {
                // 16.000,50 -> koma adalah desimal
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                // 16,000.50 -> titik adalah desimal
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            // Hanya ada koma (misal 2,15 atau 16,000)
            $parts = explode(',', $str);
            if (count($parts) === 2 && strlen($parts[1]) <= 4) {
                // Desimal (2,15)
                $str = str_replace(',', '.', $str);
            } else {
                // Ribuan (16,000)
                $str = str_replace(',', '', $str);
            }
        }

        return (float) $str;
    }

    /**
     * Impor data file Excel ke tabel MaterialSteel
     *
     * @return array{imported: int, updated: int, total: int, errors: array<string>}
     */
    public static function importMaterialSteel(string $filePath, array $defaults = [], bool $updateExisting = true): array
    {
        $grid = static::parseFileToGrid($filePath);
        if (empty($grid)) {
            return ['imported' => 0, 'updated' => 0, 'total' => 0, 'errors' => ['File tidak dapat dibaca atau kosong.']];
        }

        $detection = static::detectColumnsAndData($grid, $defaults);
        $rows = $detection['data_rows'];

        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $idx => $row) {
            try {
                if (empty($row['steel_spec'])) {
                    continue;
                }

                // Cari atau buat Supplier otomatis
                $supplierId = null;
                if (! empty($row['supplier_name']) || ! empty($row['vendor_code'])) {
                    $supplierName = $row['supplier_name'] ?: ($row['vendor_code'] ?? 'Unknown');
                    $supplier = static::resolveSupplier($supplierName, $row['vendor_code'] ?? null, 'Steel');
                    $supplierId = $supplier->id;
                    $row['supplier_id'] = $supplierId;
                    $row['vendor_code'] = $supplier->code;
                    $row['supplier_name'] = $supplier->name;
                }

                // Cek apakah data sudah ada berdasarkan (year, quarter, steel_spec, double_thickness, supplier_id)
                $existing = MaterialSteel::where('year', $row['year'])
                    ->where('quarter', $row['quarter'])
                    ->where('steel_spec', $row['steel_spec'])
                    ->when(! empty($row['double_thickness']), fn ($q) => $q->where('double_thickness', $row['double_thickness']))
                    ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
                    ->first();

                if ($existing) {
                    if ($updateExisting) {
                        $existing->update([
                            'vendor_code' => $row['vendor_code'] ?: $existing->vendor_code,
                            'supplier_name' => $row['supplier_name'] ?: $existing->supplier_name,
                            'double_thickness' => $row['double_thickness'] ?: $existing->double_thickness,
                            'diameter' => $row['diameter'] ?: $existing->diameter,
                            'size' => ! empty($row['size']) ? $row['size'] : $existing->size,
                            'exchange_rate' => $row['exchange_rate'] > 0 ? $row['exchange_rate'] : $existing->exchange_rate,
                            'steel_price' => $row['steel_price'] > 0 ? $row['steel_price'] : $existing->steel_price,
                            'steel_price_idr' => $row['steel_price_idr'] > 0 ? $row['steel_price_idr'] : $existing->steel_price_idr,
                            'coil_price' => $row['coil_price'] > 0 ? $row['coil_price'] : $existing->coil_price,
                            'coil_price_idr' => $row['coil_price_idr'] > 0 ? $row['coil_price_idr'] : $existing->coil_price_idr,
                            'sheet_price' => $row['sheet_price'] > 0 ? $row['sheet_price'] : $existing->sheet_price,
                            'sheet_price_idr' => $row['sheet_price_idr'] > 0 ? $row['sheet_price_idr'] : $existing->sheet_price_idr,
                            'gap_steel_usd' => $row['gap_steel_usd'],
                            'gap_steel_kg_usd' => $row['gap_steel_kg_usd'],
                            'fluktuasi_jfe' => $row['fluktuasi_jfe'],
                            'status' => $row['status'] ?: $existing->status,
                            'notes' => $row['notes'] ?: $existing->notes,
                        ]);
                        $updated++;
                    }
                } else {
                    if (empty($row['currency_id'])) {
                        $currency = Currency::where('code', 'USD')->first();
                        if ($currency) {
                            $row['currency_id'] = $currency->id;
                        }
                    }
                    MaterialSteel::create($row);
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Baris #'.($idx + 1).': '.$e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'total' => $imported + $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Impor data file Excel ke tabel StampingPart
     *
     * @return array{imported: int, updated: int, total: int, errors: array<string>}
     */
    public static function importStampingPart(string $filePath, array $defaults = [], bool $updateExisting = true): array
    {
        $grid = static::parseFileToGrid($filePath);
        if (empty($grid)) {
            return ['imported' => 0, 'updated' => 0, 'total' => 0, 'errors' => ['File tidak dapat dibaca atau kosong.']];
        }

        $detection = static::detectColumnsAndData($grid, $defaults);
        $rows = $detection['data_rows'];

        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $idx => $row) {
            try {
                if (empty($row['steel_spec'])) {
                    continue;
                }

                // Cari atau buat Supplier otomatis
                $supplierId = null;
                if (! empty($row['supplier_name']) || ! empty($row['vendor_code'])) {
                    $supplierName = $row['supplier_name'] ?: ($row['vendor_code'] ?? 'Unknown');
                    $supplier = static::resolveSupplier($supplierName, $row['vendor_code'] ?? null, 'Steel');
                    $supplierId = $supplier->id;
                    $row['supplier_id'] = $supplierId;
                    $row['vendor_code'] = $supplier->code;
                    $row['supplier_name'] = $supplier->name;
                }

                // Cek apakah data sudah ada
                $existing = StampingPart::where('year', $row['year'])
                    ->where('quarter', $row['quarter'])
                    ->where('steel_spec', $row['steel_spec'])
                    ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
                    ->first();

                if ($existing) {
                    if ($updateExisting) {
                        $existing->update([
                            'vendor_code' => $row['vendor_code'],
                            'supplier_name' => $row['supplier_name'],
                            'double_thickness' => $row['double_thickness'],
                            'size' => ! empty($row['size']) ? $row['size'] : $existing->size,
                            'berat' => ! empty($row['berat']) ? $row['berat'] : $existing->berat,
                            'exchange_rate' => $row['exchange_rate'],
                            'coil_price' => $row['coil_price'],
                            'coil_price_idr' => $row['coil_price_idr'],
                            'sheet_price' => $row['sheet_price'],
                            'sheet_price_idr' => $row['sheet_price_idr'],
                            'gap_steel_usd' => $row['gap_steel_usd'],
                            'gap_steel_kg_usd' => $row['gap_steel_kg_usd'],
                            'fluktuasi_jfe' => $row['fluktuasi_jfe'],
                            'status' => $row['status'],
                            'notes' => $row['notes'],
                        ]);
                        $updated++;
                    }
                } else {
                    StampingPart::create($row);
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Baris #'.($idx + 1).': '.$e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'total' => $imported + $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Impor data file Excel ke tabel Fastener
     *
     * @return array{imported: int, updated: int, total: int, errors: array<string>}
     */
    public static function importFastener(string $filePath, array $defaults = [], bool $updateExisting = true): array
    {
        $grid = static::parseFileToGrid($filePath);
        if (empty($grid)) {
            return ['imported' => 0, 'updated' => 0, 'total' => 0, 'errors' => ['File tidak dapat dibaca atau kosong.']];
        }

        $detection = static::detectColumnsAndData($grid, $defaults);
        $rows = $detection['data_rows'];

        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $idx => $row) {
            try {
                if (empty($row['steel_spec'])) {
                    continue;
                }

                // Cari atau buat Supplier otomatis
                $supplierId = null;
                if (! empty($row['supplier_name']) || ! empty($row['vendor_code'])) {
                    $supplierName = $row['supplier_name'] ?: ($row['vendor_code'] ?? 'Unknown');
                    $supplier = static::resolveSupplier($supplierName, $row['vendor_code'] ?? null, 'Fastener');
                    $supplierId = $supplier->id;
                    $row['supplier_id'] = $supplierId;
                    $row['vendor_code'] = $supplier->code;
                    $row['supplier_name'] = $supplier->name;
                }

                $row['diameter'] = $row['double_thickness'] ?? null;

                // Cek apakah data sudah ada
                $existing = Fastener::where('year', $row['year'])
                    ->where('quarter', $row['quarter'])
                    ->where('steel_spec', $row['steel_spec'])
                    ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
                    ->first();

                if ($existing) {
                    if ($updateExisting) {
                        $existing->update([
                            'vendor_code' => $row['vendor_code'],
                            'supplier_name' => $row['supplier_name'],
                            'double_thickness' => $row['double_thickness'],
                            'diameter' => $row['diameter'],
                            'exchange_rate' => $row['exchange_rate'],
                            'coil_price' => $row['coil_price'],
                            'coil_price_idr' => $row['coil_price_idr'],
                            'sheet_price' => $row['sheet_price'],
                            'sheet_price_idr' => $row['sheet_price_idr'],
                            'gap_steel_usd' => $row['gap_steel_usd'],
                            'gap_steel_kg_usd' => $row['gap_steel_kg_usd'],
                            'fluktuasi_jfe' => $row['fluktuasi_jfe'],
                            'status' => $row['status'],
                            'notes' => $row['notes'],
                        ]);
                        $updated++;
                    }
                } else {
                    Fastener::create($row);
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Baris #'.($idx + 1).': '.$e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'total' => $imported + $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Dapatkan data baris pertama dari file Excel untuk mengisi formulir Create secara otomatis
     *
     * @return array<string, mixed>|null
     */
    public static function detectFirstRowData(string $filePath, array $defaults = []): ?array
    {
        $grid = static::parseFileToGrid($filePath);
        if (empty($grid)) {
            return null;
        }

        $detection = static::detectColumnsAndData($grid, $defaults);
        $rows = $detection['data_rows'];

        return $rows[0] ?? null;
    }

    /**
     * Hasilkan isi file template CSV contoh untuk diunduh pengguna
     */
    public static function generateSampleCsv(string $type = 'material_steel'): string
    {
        $headers = [
            'Kuartal',
            'Tahun',
            'Vendor Code',
            'Supplier Name',
            'Spec',
            'Thickness(mm)',
            'Gap/MT (USD)',
            'Gap/Kg (USD)',
            'Ex Rate (IDR)',
            'Coil (USD)',
            'Sheet (USD)',
            'Status',
        ];

        $sampleRows = [
            [
                'Q1',
                date('Y'),
                'VND-HSSI',
                'PT HSSI',
                'SPCC-SD 1.2mm',
                '1.2',
                '100',
                '0.1',
                '16000',
                '1.95',
                '2.05',
                'Active',
            ],
            [
                'Q1',
                date('Y'),
                'VND-USCI',
                'PT USCI',
                'SPHC 2.0mm',
                '2.0',
                '100',
                '0.1',
                '16000',
                '2.00',
                '2.10',
                'Active',
            ],
        ];

        if ($type === 'fastener') {
            $headers[5] = 'Diameter/Thickness(mm)';
            $sampleRows = [
                [
                    'Q1',
                    date('Y'),
                    'VND-TOHO',
                    'PT TOHO FASTENER',
                    'SWMB 3.0mm',
                    '3.0',
                    '100',
                    '0.1',
                    '16000',
                    '1.85',
                    '1.95',
                    'Active',
                ],
                [
                    'Q1',
                    date('Y'),
                    'VND-MOON',
                    'PT MOON FASTENER',
                    'SWRCH10A 4.0mm',
                    '4.0',
                    '100',
                    '0.1',
                    '16000',
                    '1.90',
                    '2.00',
                    'Active',
                ],
            ];
        }

        $out = fopen('php://memory', 'r+');
        fputcsv($out, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return (string) $csv;
    }

    /**
     * Hasilkan file template CSV resmi untuk Material Steel sesuai format master purchasing
     */
    public static function generateMaterialSteelTemplateCsv(): string
    {
        $headers = [
            'Kuartal',
            'Tahun',
            'Vendor Code',
            'Nama Supplier',
            'Spesifikasi Baja',
            'Ketebalan (mm)',
            'Ukuran (Size)',
            'AR Coil (USD)',
            'AR Sheet (USD)',
            'Kurs USD',
            'Gap MT (USD)',
            'Status',
            'Catatan',
        ];

        $sampleRows = [
            ['Q4', '2026', '27016', 'PT HSSI', 'SPH270C-OD', '1.6', '1.6 x 1219 x C', '1.1206', '1.1456', '17796', '-20', 'Active', 'Master Pricing Q4 2026'],
            ['Q4', '2026', '27018', 'PT HSSI', 'SPH270C-OD', '1.8', '1.8 x 1219 x C', '1.1146', '1.1396', '17796', '-20', 'Active', 'Master Pricing Q4 2026'],
            ['Q4', '2026', '27020', 'PT HSSI', 'SPH270C-OD', '2.0', '2.0 x 1219 x C', '1.1106', '1.1356', '17796', '-20', 'Active', 'Master Pricing Q4 2026'],
            ['Q4', '2026', '44016', 'PT HSSI', 'SPH440-OD', '1.6', '1.6 x 1219 x C', '1.1556', '1.2306', '17796', '-20', 'Active', 'Master Pricing Q4 2026'],
            ['Q4', '2026', '59016', 'PT HSSI', 'SPC590', '1.6', '1.6 x 1219 x C', '1.2506', '1.3106', '17796', '-20', 'Active', 'Master Pricing Q4 2026'],
        ];

        $out = fopen('php://memory', 'r+');
        // UTF-8 BOM agar Excel membukanya langsung rapi tanpa karakter berantakan
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return (string) $csv;
    }
}
