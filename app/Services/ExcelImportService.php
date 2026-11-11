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
            'hr_steel_usd' => ['hr_steel_usd', 'hr dolar/mt', 'hr_dolar_mt', 'hr dolar / mt', 'hr_dolar/mt', 'hr_mt', 'hr ($/mt)', 'hr $/mt', 'hr_usd_mt', 'hr dolar/ mt'],
            'cr_steel_usd' => ['cr_steel_usd', 'cr dolar/mt', 'cr_dolar_mt', 'cr dolar / mt', 'cr_dolar/mt', 'cr_mt', 'cr ($/mt)', 'cr $/mt', 'cr_usd_mt', 'cr dolar/ mt'],
            'gap_steel_usd' => ['gap_steel_usd', 'gap/mt', 'gap/mt(usd)', 'gap_mt', 'gap_mt_usd', 'gap_usd', 'gap/mt_usd', 'gap_steel_mt', 'gap_mt_usd'],
            'gap_steel_kg_usd' => ['gap_steel_kg_usd', 'gap/kg', 'gap/kg(usd)', 'gap_kg', 'gap_kg_usd', 'gap/kg_usd', 'gap_steel_kg'],
            'exchange_rate' => ['exchange_rate', 'ex_rate', 'ex_rate(idr)', 'kurs', 'kurs_idr', 'rate', 'ex_rate_idr', 'kurs_usd', 'ex_rate_kurs', 'kurs_usd_idr'],
            'coil_price' => ['coil_price', 'coil', 'coil(usd)', 'coil_usd', 'harga_coil', 'basic_rate', 'coil_price_usd', 'coil_usd_price', 'ar - coil ($)', 'ar - coil', 'ar_coil', 'ar_coil_usd', 'arcoilusd', 'arcoil', 'ap - coil ($)', 'ap - coil', 'ap_coil', 'ap_coil_usd', 'coil (usd)', 'coil($)'],
            'coil_price_idr' => ['coil_price_idr', 'coil(idr)', 'coil_idr', 'harga_coil_idr', 'ar - coil (idr)', 'ar_coil_idr', 'arcoilidr', 'ap - coil (idr)', 'ap_coil_idr', 'apcoilidr'],
            'sheet_price' => ['sheet_price', 'sheet', 'sheet(usd)', 'sheet_usd', 'harga_sheet', 'sheet_price_usd', 'sheet_usd_price', 'ar sheet ($)', 'ar - sheet ($)', 'ar sheet', 'ar_sheet', 'ar_sheet_usd', 'arsheetusd', 'arsheet', 'ap sheet ($)', 'ap - sheet ($)', 'ap sheet', 'ap_sheet', 'sheet (usd)', 'sheet($)'],
            'sheet_price_idr' => ['sheet_price_idr', 'sheet(idr)', 'sheet_idr', 'harga_sheet_idr', 'ar - sheet (idr)', 'ar_sheet_idr', 'arsheetidr', 'ap - sheet (idr)', 'ap_sheet_idr', 'apsheetidr', 'sheet (idr)'],
            'fluktuasi_jfe' => ['fluktuasi_jfe', 'jfe', 'fluktuasi', 'jfe_fluctuation'],
            'status' => ['status', 'status_kontrak'],
            'notes' => ['notes', 'catatan', 'keterangan'],
            'comments' => ['comments', 'comment', 'coments'],
            'remarks' => ['remarks', 'remark'],
            'alteration' => ['alteration', 'perubahan_eci'],
            'etc_contents' => ['etc_contents', 'etc', 'etc_content'],
            'part_number' => ['part_number', 'part_no', 'part_num', 'no_part', 'nomor_part', 'partno'],
            'old_part_number' => ['old_part_number', 'old_part_no', 'old_part', 'part_no_lama', 'no_part_lama'],
            'part_no_to_compare' => ['part_no_to_compare', 'part_number_to_compare', 'compare_part', 'part_no_compare', 'part_compare'],
            'part_name' => ['part_name', 'nama_part', 'deskripsi_part', 'item_name', 'nama_barang'],
            'model_name' => ['model_name', 'model', 'tipe_model', 'car_model'],
            'monthly_volume' => ['monthly_volume', 'monthly_vol', 'volume_bulanan'],
            'drawing_reference' => ['drawing_reference', 'drawing_ref', 'drawing_of_quotation'],
            'old_exchange_rate' => ['old_exchange_rate', 'old_rate', 'old_kurs', 'old_kurs_usd', 'old_kurs_usd_rp', 'kurs_lama'],
            'exchange_rate_fluctuation' => ['exchange_rate_fluctuation', 'exchg_fluctuation', 'exchg_flactuation', 'fluktuasi_kurs'],
            'eci_number' => ['eci_number', 'eci_no', 'eci'],
            'quotation_date' => ['quotation_date', 'tgl_quotation', 'tanggal_penawaran', 'date'],
            'wide' => ['wide', 'lebar', 'width', 'wide_mm'],
            'pitch' => ['pitch', 'panjang_pitch', 'pitch_mm'],
            'yield_rate' => ['yield_rate', 'yield', 'yield_stroke'],
            'scrap_weight' => ['scrap_weight', 'berat_scrap'],
            'scrap_price_per_kg' => ['scrap_price_per_kg', 'scrap_price', 'harga_scrap'],
            'process_name' => ['process_name', 'proses', 'nama_proses', 'machine_ton', 'tonnage'],
            'process_man_hour' => ['process_man_hour', 'sph', 'man_hour', 'cycle_time'],
            'process_charge' => ['process_charge', 'charge', 'line_charge', 'rate_proses'],
            'overhead_supplied_percent' => ['overhead_supplied_percent', 'oh_supplied', 'oh_supplied_percent', 'oh_supplied_pct'],
            'overhead_self_percent' => ['overhead_self_percent', 'oh_self', 'oh_self_percent', 'oh_self_pct'],
            'transportation_cost' => ['transportation_cost', 'transportation', 'transport', 'ongkir', 'biaya_angkut'],
            'cost_down' => ['cost_down', 'costdown'],
            'packing_cost' => ['packing_cost', 'packing', 'kemasan', 'biaya_packing'],
            'tooling_depreciation' => ['tooling_depreciation', 'tooling_depr', 'depresiasi_tooling', 'tooling', 'die_jig_depreciation', 'die_jig_depr', 'die_depr'],
            'tooling_details' => ['tooling_details', 'tooling_details_json', 'rincian_tooling'],
            'wire_spec' => ['wire_spec', 'spesifikasi_kawat', 'spesifikasi_wire'],
            'gross_weight_gr' => ['gross_weight_gr', 'gross_weight', 'gross_wt', 'berat_kotor'],
            'nett_weight_gr' => ['nett_weight_gr', 'nett_weight', 'nett_wt', 'berat_bersih'],
            'wire_price_usd_kg' => ['wire_price_usd_kg', 'wire_price_usd', 'wire_price', 'wire($/kg)', 'wire ($/kg)', 'wire_usd_kg', 'wire_usd', 'wire_usd_per_kg'],
            'wire_price_idr_kg' => ['wire_price_idr_kg', 'wire_idr_kg', 'wire_rp_kg', 'wire (rp/kg)', 'wire (idr/kg)', 'wire_idr_per_kg'],
            'material_control_percent' => ['material_control_percent', 'material_control_pct', 'mat_control_pct', 'mat_control', 'material_control'],
            'washer_price' => ['washer_price', 'washer_price_rp', 'harga_washer'],
            'washer_weight_gr' => ['washer_weight_gr', 'washer_weight', 'berat_washer'],
            'heading_cost' => ['heading_cost', 'heading', 'heading_rp', 'biaya_heading'],
            'rolling_cost' => ['rolling_cost', 'rolling', 'rolling_rp', 'biaya_rolling'],
            'washing_cost_kg' => ['washing_cost_kg', 'washing_cost', 'washing_rate_rp_kg', 'washing_rp_kg', 'washing', 'biaya_washing'],
            'plating_cost_kg' => ['plating_cost_kg', 'plating_cost', 'plating_rate_rp_kg', 'plating_rp_kg', 'plating', 'biaya_plating'],
            'inspection_cost' => ['inspection_cost', 'inspection', 'final_inspection', 'final_inspection_rp', 'pemeriksaan'],
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

                    // Cari Kurs di baris kuartal atau baris-baris sebelumnya di sekitar kolom ini
                    $rate = null;
                    for ($checkR = 0; $checkR <= $rIdx; $checkR++) {
                        for ($offset = 0; $offset <= 3; $offset++) {
                            $possibleRate = $grid[$checkR][$colIdx + $offset] ?? null;
                            if ($possibleRate !== null && is_numeric($possibleRate) && (float) $possibleRate >= 10000) {
                                $rate = (float) $possibleRate;
                                break 2;
                            }
                        }
                    }

                    // Cari penyesuaian HRC dan CRC dari teks header atau baris atasnya (seperti "HRC (HOT),,-158", "CRC (COLD),,-138")
                    $hrMt = null;
                    $crMt = null;

                    if (preg_match('/HR\s*([+-]?\d+(?:[.,]\d+)?)\s*\$\s*\/\s*MT/i', (string) $val, $hm)) {
                        $hrMt = (float) str_replace(',', '.', $hm[1]);
                    }
                    if (preg_match('/CR\s*([+-]?\d+(?:[.,]\d+)?)\s*\$\s*\/\s*MT/i', (string) $val, $cm)) {
                        $crMt = (float) str_replace(',', '.', $cm[1]);
                    }

                    // Jika belum ketemu di teks kuartal, cari di baris atas pada kolom ini
                    if ($hrMt === null || $crMt === null) {
                        for ($checkR = 0; $checkR < $rIdx; $checkR++) {
                            for ($offset = 0; $offset <= 3; $offset++) {
                                $cellVal = (string) ($grid[$checkR][$colIdx + $offset] ?? '');
                                if (stripos($cellVal, 'HRC') !== false || (stripos($cellVal, 'HR') !== false && stripos($cellVal, 'HOT') !== false)) {
                                    for ($vo = 1; $vo <= 3; $vo++) {
                                        $num = $grid[$checkR][$colIdx + $offset + $vo] ?? null;
                                        if ($num !== null && $num !== '' && is_numeric($num)) {
                                            $hrMt = (float) $num;
                                            break;
                                        }
                                    }
                                } elseif (stripos($cellVal, 'CRC') !== false || (stripos($cellVal, 'CR') !== false && stripos($cellVal, 'COLD') !== false)) {
                                    for ($vo = 1; $vo <= 3; $vo++) {
                                        $num = $grid[$checkR][$colIdx + $offset + $vo] ?? null;
                                        if ($num !== null && $num !== '' && is_numeric($num)) {
                                            $crMt = (float) $num;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $gapMt = 0.0;
                    if ($hrMt !== null) {
                        $gapMt = $hrMt;
                    } elseif (preg_match('/([+-]?\d+(?:[.,]\d+)?)\s*\$\s*\/\s*MT/i', (string) $val, $gm)) {
                        $gapMt = (float) str_replace(',', '.', $gm[1]);
                    }

                    $periods[] = [
                        'quarter' => $q,
                        'year' => $y,
                        'col_start' => $colIdx,
                        'header_row' => $rIdx,
                        'rate' => $rate,
                        'hr_mt' => $hrMt,
                        'cr_mt' => $crMt,
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

        $subHeaderRow = $selectedPeriod['header_row'] + 1;
        $orderIsSheetSecond = false;
        if (isset($grid[$subHeaderRow])) {
            $h1 = strtolower((string) ($grid[$subHeaderRow][$startCol + 1] ?? ''));
            if (str_contains($h1, 'sheet')) {
                $orderIsSheetSecond = true;
            }
        }

        $dataRows = [];
        $currentFamily = null;
        for ($r = $firstDataRow; $r <= count($grid); $r++) {
            if (! isset($grid[$r])) {
                continue;
            }

            $col0 = trim((string) ($grid[$r][0] ?? ''));
            if (! empty($col0) && ! is_numeric($col0) && strlen($col0) >= 3 && ! str_contains(strtolower($col0), 'note')) {
                $currentFamily = $col0;
            }

            // Cari spesifikasi baja di kolom 0, 1, atau 2 yang memuat nama spec baja standar (misal "SPH270C-OD T1.6")
            $candidateCols = [
                $grid[$r][2] ?? '',
                $grid[$r][1] ?? '',
                $grid[$r][0] ?? '',
            ];

            $rawSpec = '';
            foreach ($candidateCols as $cVal) {
                $strVal = trim((string) $cVal);
                if (preg_match('/(?:SP[HC]|NSC|SAPH)\d+/i', $strVal)) {
                    $rawSpec = $strVal;
                    break;
                }
            }

            // Fallback jika tidak ada regex match langsung tapi ada teks non-kategori
            if (empty($rawSpec)) {
                foreach ($candidateCols as $cVal) {
                    $strVal = trim((string) $cVal);
                    if (! empty($strVal) && ! is_numeric($strVal) && ! in_array(strtoupper($strVal), ['HOT', 'COLD', 'CAT', 'SPEC', 'SIZE', 'NOTE', 'NOTES'])) {
                        $rawSpec = $strVal;
                        break;
                    }
                }
            }

            if (empty($rawSpec) && $currentFamily && ! in_array(strtoupper($currentFamily), ['HOT', 'COLD', 'CAT', 'SPEC'])) {
                $rawSpec = $currentFamily;
            }

            if (empty($rawSpec) || in_array(strtoupper($rawSpec), ['HOT', 'COLD', 'CAT', 'SPEC'])) {
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

            // Ambil harga dari kolom target periode sesuai urutan subheader
            if ($orderIsSheetSecond) {
                $coilUsd = (float) static::cleanNumeric($grid[$r][$startCol] ?? 0);
                $sheetUsd = (float) static::cleanNumeric($grid[$r][$startCol + 1] ?? 0);
                $coilIdr = (float) static::cleanNumeric($grid[$r][$startCol + 2] ?? 0);
                $sheetIdr = (float) static::cleanNumeric($grid[$r][$startCol + 3] ?? 0);
            } else {
                $coilUsd = (float) static::cleanNumeric($grid[$r][$startCol] ?? 0);
                $coilIdr = (float) static::cleanNumeric($grid[$r][$startCol + 1] ?? 0);
                $sheetUsd = (float) static::cleanNumeric($grid[$r][$startCol + 2] ?? 0);
                $sheetIdr = (float) static::cleanNumeric($grid[$r][$startCol + 3] ?? 0);
            }

            if ($coilUsd <= 0 && $sheetUsd <= 0) {
                continue;
            }

            $code = trim((string) ($grid[$r][1] ?? ($grid[$r][2] ?? '')));
            $steelType = MaterialSteel::determineSteelType($spec);
            $periodHr = $selectedPeriod['hr_mt'] ?? null;
            $periodCr = $selectedPeriod['cr_mt'] ?? null;

            if ($steelType === 'HR') {
                $appliedGap = $periodHr !== null ? $periodHr : $selectedPeriod['gap_mt'];
            } else {
                $appliedGap = $periodCr !== null ? $periodCr : ($periodHr !== null ? $periodHr : $selectedPeriod['gap_mt']);
            }

            if ($appliedGap == 0.0 && $coilUsd > 0 && $sheetUsd > 0) {
                $appliedGap = round(($sheetUsd - $coilUsd) * 1000, 4);
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
                'hr_steel_usd' => ($steelType === 'HR') ? $appliedGap : $periodHr,
                'cr_steel_usd' => ($steelType === 'CR') ? $appliedGap : $periodCr,
                'gap_steel_usd' => $appliedGap,
                'gap_steel_kg_usd' => round($appliedGap / 1000, 4),
                'fluktuasi_jfe' => round($appliedGap / 1000, 4),
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
     * Parsing format sheet Comparative Quotation Stamping Part bertumpuk vertikal
     * dengan banyak panel periode horizontal (mis. Book1.xlsx: 73012-BZ010 Sub-Assy + 72388-X7V00-A Stamping Part).
     * Blok Sub-Assy otomatis dilewati karena tidak memiliki subheader Spec|Thickness|Wide|Pitch|yield rate|Weight.
     *
     * @param  array<int, array<int, string>>  $grid
     * @return array{header_row: int|null, column_map: array<string, int>, data_rows: array<int, array<string, mixed>>}|null
     */
    public static function detectComparativeQuotationStampingBlocks(array $grid, array $defaults = []): ?array
    {
        $rowIndices = array_keys($grid);
        if (empty($rowIndices)) {
            return null;
        }
        sort($rowIndices);
        $maxRow = end($rowIndices);
        $maxCol = 0;
        foreach ($grid as $cols) {
            if (! empty($cols)) {
                $maxCol = max($maxCol, max(array_keys($cols)));
            }
        }

        // Cari semua baris header material Stamping Part yang memuat kolom Spec + Thickness + Wide + Pitch
        $specHeaderRows = [];
        foreach ($grid as $rIdx => $cols) {
            $hasSpecHeader = false;
            foreach ($cols as $cIdx => $val) {
                if (strtolower(trim((string) $val)) === 'spec') {
                    $next1 = strtolower(trim((string) ($cols[$cIdx + 1] ?? '')));
                    $next2 = strtolower(trim((string) ($cols[$cIdx + 2] ?? '')));
                    $next3 = strtolower(trim((string) ($cols[$cIdx + 3] ?? '')));
                    if (str_contains($next1, 'thick') && str_contains($next2, 'wide') && str_contains($next3, 'pitch')) {
                        $hasSpecHeader = true;
                        break;
                    }
                }
            }
            if ($hasSpecHeader) {
                $specHeaderRows[] = (int) $rIdx;
            }
        }

        if (empty($specHeaderRows)) {
            return null;
        }

        $parseNum = static function ($raw): ?float {
            $str = trim((string) $raw);
            if ($str === '' || str_starts_with($str, '#') || strtoupper($str) === 'SUPPLY') {
                return null;
            }
            if (is_numeric($str)) {
                return (float) $str;
            }
            $cleaned = str_replace(['Rp', 'IDR', '$', ' '], '', $str);
            // Tangani format pemisah desimal & ribuan dengan aman
            if (preg_match('/^-?\d{1,3}(\.\d{3})+,\d+$/', $cleaned)) {
                $cleaned = str_replace(['.', ','], ['', '.'], $cleaned);
            } elseif (preg_match('/^-?\d{1,3}(,\d{3})+\.\d+$/', $cleaned)) {
                $cleaned = str_replace(',', '', $cleaned);
            } elseif (preg_match('/^-?\d+,\d+$/', $cleaned)) {
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                $cleaned = str_replace(',', '', $cleaned);
            }

            return is_numeric($cleaned) ? (float) $cleaned : null;
        };

        $parsePeriodString = static function (string $text, int $defaultYear, string $defaultQuarter): ?array {
            $t = trim($text);
            if ($t === '') {
                return null;
            }
            $lower = strtolower($t);
            $quarter = null;
            $year = null;

            if (preg_match('/\bq([1-4])\b/i', $t, $qm)) {
                $quarter = 'Q'.$qm[1];
            } elseif (preg_match('/\b(jan|feb|mar|maret)\b/i', $lower)) {
                $quarter = 'Q1';
            } elseif (preg_match('/\b(apr|april|mei|may|jun|june|juni)\b/i', $lower)) {
                $quarter = 'Q2';
            } elseif (preg_match('/\b(jul|juli|july|aug|agu|agustus|sep|sept|september)\b/i', $lower)) {
                $quarter = 'Q3';
            } elseif (preg_match('/\b(oct|okt|oktober|october|nov|november|dec|des|december|desember)\b/i', $lower)) {
                $quarter = 'Q4';
            }

            if (preg_match('/\b(20\d{2})\b/', $t, $ym)) {
                $year = (int) $ym[1];
            } elseif (preg_match('/[\'’]\s*(\d{2})\b/', $t, $ym)) {
                $year = 2000 + (int) $ym[1];
            }

            if ($quarter === null && $year === null) {
                return null;
            }

            return [
                'year' => $year ?? $defaultYear,
                'quarter' => $quarter ?? $defaultQuarter,
            ];
        };

        $parseDateValue = static function ($raw): ?string {
            $s = trim((string) $raw);
            if ($s === '') {
                return null;
            }
            if (is_numeric($s) && (float) $s >= 30000 && (float) $s <= 70000) {
                try {
                    return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) round((float) $s))->toDateString();
                } catch (\Throwable) {
                    return null;
                }
            }
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
                return "{$m[1]}-{$m[2]}-{$m[3]}";
            }
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            }

            return null;
        };

        $defaultYear = (int) ($defaults['year'] ?? now()->year);
        $defaultQuarter = (string) ($defaults['quarter'] ?? 'Q1');
        $dataRows = [];

        foreach ($specHeaderRows as $idx => $specRow) {
            $specRowCols = $grid[$specRow] ?? [];
            $panelAnchors = [];
            foreach ($specRowCols as $cIdx => $val) {
                if (strtolower(trim((string) $val)) === 'spec') {
                    $n1 = strtolower(trim((string) ($specRowCols[$cIdx + 1] ?? '')));
                    $n2 = strtolower(trim((string) ($specRowCols[$cIdx + 2] ?? '')));
                    if (str_contains($n1, 'thick') && str_contains($n2, 'wide')) {
                        $panelAnchors[] = (int) $cIdx;
                    }
                }
            }
            sort($panelAnchors);

            $prevSpecRow = $idx > 0 ? $specHeaderRows[$idx - 1] : 0;
            $nextSpecRow = $specHeaderRows[$idx + 1] ?? ($maxRow + 16);
            $minSearchRow = $idx > 0
                ? max($prevSpecRow + 15, $specRow - 18, 1)
                : max($specRow - 18, 1);
            $blockTopRow = $minSearchRow;
            for ($sr = $specRow - 2; $sr >= $minSearchRow; $sr--) {
                $firstAnchorVal = strtolower(trim((string) ($grid[$sr][$panelAnchors[0]] ?? '')));
                $secondVal = strtolower(trim((string) ($grid[$sr][$panelAnchors[0] + 1] ?? '')));
                if (
                    str_contains($firstAnchorVal, 'material breakdown')
                    || str_contains($firstAnchorVal, 'process breakdown')
                    || str_contains($firstAnchorVal, 'material total')
                    || str_contains($firstAnchorVal, 'process total')
                    || str_contains($firstAnchorVal, 'part cost')
                    || str_contains($firstAnchorVal, 'die & jig')
                    || str_contains($firstAnchorVal, 'coment')
                    || str_contains($firstAnchorVal, 'remark')
                    || str_contains($firstAnchorVal, 'alteration')
                    || str_contains($firstAnchorVal, 'etc content')
                    || str_contains($secondVal, 'drawing/eci')
                ) {
                    $blockTopRow = $sr + 1;
                    break;
                }
            }
            $blockBottomRow = min($nextSpecRow - 15, $maxRow);

            foreach ($panelAnchors as $pIdx => $anchor) {
                $panelEnd = isset($panelAnchors[$pIdx + 1]) ? ($panelAnchors[$pIdx + 1] - 1) : $maxCol;

                // Petakan kolom pada Material Breakdown & Spec row dalam batas panel ini
                $matRow = $specRow + 1;
                $unitPriceCol = $anchor + 7;
                $priceCol = $anchor + 8;
                $valuationCol = $anchor + 10;
                $thickCol = $anchor + 1;
                $wideCol = $anchor + 2;
                $pitchCol = $anchor + 3;
                $yieldCol = $anchor + 4;
                $weightCol = $anchor + 5;
                $qtyCol = $anchor + 6;

                foreach ($grid[$specRow] ?? [] as $c => $hVal) {
                    if ($c < $anchor || $c > $panelEnd) {
                        continue;
                    }
                    $hl = strtolower(trim((string) $hVal));
                    if (str_contains($hl, 'thick')) {
                        $thickCol = $c;
                    } elseif (str_contains($hl, 'wide')) {
                        $wideCol = $c;
                    } elseif (str_contains($hl, 'pitch')) {
                        $pitchCol = $c;
                    } elseif (str_contains($hl, 'yield')) {
                        $yieldCol = $c;
                    } elseif (str_contains($hl, 'weight')) {
                        $weightCol = $c;
                    }
                }

                foreach ($grid[$specRow - 1] ?? [] as $c => $hVal) {
                    if ($c < $anchor || $c > $panelEnd) {
                        continue;
                    }
                    $hl = strtolower(trim((string) $hVal));
                    if (str_contains($hl, 'quantity')) {
                        $qtyCol = $c;
                    } elseif (str_contains($hl, 'unit price')) {
                        $unitPriceCol = $c;
                    } elseif ($hl === 'price') {
                        $priceCol = $c;
                    } elseif (str_contains($hl, 'valuation')) {
                        $valuationCol = $c;
                    }
                }

                $steelSpec = trim((string) ($grid[$matRow][$anchor] ?? ''));
                if ($steelSpec === '') {
                    continue;
                }

                // Scan metadata di atas Spec row (Part No, Part Name, Period, Quotation Date, Supplier, Exchange Rate)
                $partNumber = '';
                $partName = '';
                $supplierName = '';
                $periodYear = $defaultYear;
                $periodQuarter = $defaultQuarter;
                $periodTitle = '';
                $quotationDate = null;
                $exchangeRate = null;
                $hasExplicitZeroRate = false;
                $modelName = '';
                $monthlyVolume = null;
                $drawingReference = '';
                $eciNo = '';

                for ($r = $blockTopRow; $r < $specRow; $r++) {
                    $rowCells = $grid[$r] ?? [];
                    foreach ($rowCells as $c => $cellVal) {
                        if ($c < $anchor || $c > $panelEnd) {
                            continue;
                        }
                        $cellStr = trim((string) $cellVal);
                        if ($cellStr === '') {
                            continue;
                        }
                        $cellLow = strtolower($cellStr);

                        // Period title (biasanya di kolom anchor)
                        if ($c === $anchor && $periodTitle === '') {
                            $parsedPeriod = $parsePeriodString($cellStr, $defaultYear, $defaultQuarter);
                            if ($parsedPeriod !== null) {
                                $periodTitle = $cellStr;
                                $periodYear = $parsedPeriod['year'];
                                $periodQuarter = $parsedPeriod['quarter'];
                                $quotationDate = $parseDateValue($grid[$r + 1][$anchor] ?? null);
                                $candSup = trim((string) ($grid[$r + 2][$anchor] ?? ''));
                                if ($candSup !== '' && ! preg_match('/^(model|drawing|part\s*no|exchange)/i', $candSup)) {
                                    $supplierName = $candSup;
                                }
                            }
                        }

                        if (preg_match('/^part\s*no\.?$/i', $cellStr) && $partNumber === '') {
                            for ($vc = $c + 1; $vc <= min($c + 4, $panelEnd); $vc++) {
                                $v = trim((string) ($rowCells[$vc] ?? ''));
                                if ($v !== '') {
                                    $partNumber = $v;
                                    break;
                                }
                            }
                        } elseif (preg_match('/^part\s*name$/i', $cellStr) && $partName === '') {
                            for ($vc = $c + 1; $vc <= min($c + 4, $panelEnd); $vc++) {
                                $v = trim((string) ($rowCells[$vc] ?? ''));
                                if ($v !== '') {
                                    $partName = $v;
                                    break;
                                }
                            }
                        } elseif ($cellLow === 'model' && $modelName === '') {
                            for ($vc = $c + 1; $vc <= min($c + 4, $panelEnd); $vc++) {
                                $v = trim((string) ($rowCells[$vc] ?? ''));
                                if ($v !== '' && ! str_starts_with($v, '(')) {
                                    $modelName = $v;
                                    break;
                                }
                            }
                        } elseif (str_contains($cellLow, 'monthly volume') && $monthlyVolume === null) {
                            for ($vc = $c + 1; $vc <= min($c + 5, $panelEnd); $vc++) {
                                $mv = $parseNum($rowCells[$vc] ?? null);
                                if ($mv !== null) {
                                    $monthlyVolume = (int) round($mv);
                                    break;
                                }
                            }
                        } elseif (str_contains($cellLow, 'drawing of quotation') && $drawingReference === '') {
                            for ($vc = $c + 1; $vc <= min($c + 4, $panelEnd); $vc++) {
                                $v = trim((string) ($rowCells[$vc] ?? ''));
                                if ($v !== '') {
                                    $drawingReference = $v;
                                    break;
                                }
                            }
                        } elseif (preg_match('/^eci\s*no/i', str_replace('Ｎ', 'N', $cellStr)) && $eciNo === '') {
                            for ($vc = $c + 1; $vc <= min($c + 4, $panelEnd); $vc++) {
                                $v = trim((string) ($rowCells[$vc] ?? ''));
                                if ($v !== '') {
                                    $eciNo = $v;
                                    break;
                                }
                            }
                        } elseif (str_contains($cellLow, 'new rate')) {
                            for ($vc = $c + 1; $vc <= min($c + 4, $panelEnd); $vc++) {
                                $vStr = trim((string) ($rowCells[$vc] ?? ''));
                                if ($vStr !== '' && is_numeric(str_replace(',', '', $vStr))) {
                                    $exchangeRate = (float) $parseNum($vStr);
                                    $hasExplicitZeroRate = ($exchangeRate == 0.0);
                                    break;
                                }
                            }
                        }
                    }
                }

                // Fallback exchange rate ke Qtn condition jika New Rate tidak terbaca
                if ($exchangeRate === null) {
                    for ($r = $blockTopRow; $r < $specRow; $r++) {
                        $rowCells = $grid[$r] ?? [];
                        foreach ($rowCells as $c => $cellVal) {
                            if ($c >= $anchor && $c <= $panelEnd && stripos((string) $cellVal, 'qtn condition') !== false) {
                                for ($vc = $c + 1; $vc <= min($c + 4, $panelEnd); $vc++) {
                                    $num = $parseNum($rowCells[$vc] ?? null);
                                    if ($num !== null) {
                                        $exchangeRate = $num;
                                        break 3;
                                    }
                                }
                            }
                        }
                    }
                }

                $thickness = $parseNum($grid[$matRow][$thickCol] ?? null) ?? 0.0;
                $wide = $parseNum($grid[$matRow][$wideCol] ?? null) ?? 0.0;
                $pitch = $parseNum($grid[$matRow][$pitchCol] ?? null) ?? 0.0;
                $yieldRate = $parseNum($grid[$matRow][$yieldCol] ?? null) ?: 1.0;
                $berat = $parseNum($grid[$matRow][$weightCol] ?? null) ?? 0.0;
                if ($berat <= 0 && $thickness > 0 && $wide > 0 && $pitch > 0) {
                    $berat = round(($thickness * $wide * $pitch * 7.85) / (1000000 * $yieldRate), 7);
                } else {
                    $berat = round($berat, 7);
                }

                $sheetIdr = $parseNum($grid[$matRow][$unitPriceCol] ?? null) ?? 0.0;
                $sheetUsd = $parseNum($grid[$matRow + 1][$unitPriceCol] ?? null) ?? 0.0;

                // Deteksi label sumber steel di kolom paling kanan panel (HSSI / USCI)
                $steelSourceTag = '';
                for ($vc = max($valuationCol, $priceCol + 1); $vc <= min($panelEnd, $valuationCol + 2); $vc++) {
                    $tag = trim((string) ($grid[$matRow][$vc] ?? ''));
                    if ($tag !== '' && ! is_numeric($tag) && ! in_array(strtoupper($tag), ['OK', 'NO'])) {
                        $steelSourceTag = $tag;
                        break;
                    }
                }

                // Scan ke bawah dari material row sampai blockBottomRow untuk SCRAP, Process Breakdown, dan komponen biaya (4)-(9)
                $scrapWeight = 0.0;
                $scrapPricePerKg = -1500.0;
                $processName = '';
                $processManHour = 0.0;
                $processCharge = 0.0;
                $ohSuppliedPct = 11.0;
                $ohSelfPct = 4.0;
                $transportCost = 0.0;
                $packingCost = 0.0;
                $toolingCost = 0.0;
                $inProcess = false;

                for ($r = $matRow + 1; $r <= $blockBottomRow; $r++) {
                    $rowCells = $grid[$r] ?? [];
                    $anchorLabel = trim((string) ($rowCells[$anchor] ?? ''));
                    $anchorLow = strtolower($anchorLabel);

                    // Cek SCRAP (biasanya di kolom yield/scrap sebelum Weight)
                    foreach ($rowCells as $c => $cellVal) {
                        if ($c >= $anchor && $c < $weightCol && strcasecmp(trim((string) $cellVal), 'SCRAP') === 0) {
                            $sw = $parseNum($rowCells[$weightCol] ?? null);
                            $sp = $parseNum($rowCells[$unitPriceCol] ?? null);
                            if ($sw !== null) {
                                $scrapWeight = $sw;
                            }
                            if ($sp !== null) {
                                $scrapPricePerKg = $sp;
                            }
                        }
                    }

                    if (str_contains($anchorLow, 'process breakdown')) {
                        $inProcess = true;
                        continue;
                    }

                    if (str_starts_with($anchorLabel, '(2)')) {
                        $inProcess = false;
                        continue;
                    }

                    if ($inProcess && $anchorLabel !== '' && $processName === '') {
                        $mh = $parseNum($rowCells[$qtyCol] ?? null);
                        $chg = $parseNum($rowCells[$unitPriceCol] ?? null);
                        if ($mh !== null || $chg !== null) {
                            $processName = $anchorLabel;
                            $processManHour = $mh ?? 0.0;
                            $processCharge = $chg ?? 0.0;
                        }
                    }

                    if (str_starts_with($anchorLabel, '(4)')) {
                        if (preg_match('/(\d+(?:\.\d+)?)\s*[％%]/u', $anchorLabel, $pm)) {
                            $ohSuppliedPct = (float) $pm[1];
                        }
                    } elseif (str_starts_with($anchorLabel, '(5)')) {
                        if (preg_match('/(\d+(?:\.\d+)?)\s*[％%]/u', $anchorLabel, $pm)) {
                            $ohSelfPct = (float) $pm[1];
                        }
                    } elseif (str_starts_with($anchorLabel, '(6)')) {
                        $val = $parseNum($rowCells[$priceCol] ?? null);
                        if ($val !== null) {
                            $transportCost = $val;
                        }
                    } elseif (str_starts_with($anchorLabel, '(7)')) {
                        $val = $parseNum($rowCells[$priceCol] ?? null);
                        if ($val !== null) {
                            $packingCost = $val;
                        }
                    } elseif (str_starts_with($anchorLabel, '(9)')) {
                        $val = $parseNum($rowCells[$priceCol] ?? null);
                        if ($val !== null) {
                            $toolingCost = $val;
                        }
                    }
                }

                $isDirectIdr = ($sheetUsd <= 0.0 && $sheetIdr > 0.0);
                $finalRate = $exchangeRate !== null
                    ? $exchangeRate
                    : ($isDirectIdr ? 0.0 : (float) ($defaults['exchange_rate'] ?? 0));

                $noteParts = array_filter([
                    $periodTitle ? "Periode: {$periodTitle}" : null,
                    $steelSourceTag ? "Steel Source: {$steelSourceTag}" : null,
                    $eciNo ? "ECI: {$eciNo}" : null,
                ]);

                $dataRows[] = [
                    'year' => $periodYear,
                    'quarter' => $periodQuarter,
                    'quotation_date' => $quotationDate,
                    'supplier_name' => $supplierName ?: ($steelSourceTag ?: ($defaults['supplier_name'] ?? 'PT. POSMI STEEL INDONESIA')),
                    'vendor_code' => $defaults['vendor_code'] ?? null,
                    'part_number' => $partNumber ?: null,
                    'part_name' => $partName ?: null,
                    'model_name' => $modelName ?: null,
                    'monthly_volume' => $monthlyVolume,
                    'drawing_reference' => $drawingReference ?: null,
                    'eci_number' => $eciNo ?: null,
                    'steel_spec' => $steelSpec,
                    'double_thickness' => $thickness,
                    'wide' => $wide,
                    'pitch' => $pitch,
                    'yield_rate' => $yieldRate,
                    'berat' => $berat,
                    'exchange_rate' => $hasExplicitZeroRate ? 0.0 : $finalRate,
                    'coil_price' => 0.0,
                    'coil_price_idr' => $sheetIdr,
                    'sheet_price' => $isDirectIdr ? 0.0 : $sheetUsd,
                    'sheet_price_idr' => $sheetIdr > 0 ? $sheetIdr : ($finalRate > 0 ? round($sheetUsd * $finalRate, 2) : 0.0),
                    'scrap_weight' => $scrapWeight,
                    'scrap_price_per_kg' => $scrapPricePerKg,
                    'process_name' => $processName ?: '1. PROGRESSIVE',
                    'process_man_hour' => $processManHour,
                    'process_charge' => $processCharge,
                    'overhead_supplied_percent' => $ohSuppliedPct,
                    'overhead_self_percent' => $ohSelfPct,
                    'transportation_cost' => $transportCost,
                    'packing_cost' => $packingCost,
                    'tooling_depreciation' => $toolingCost,
                    'status' => 'Active',
                    'notes' => ! empty($noteParts) ? implode(' | ', $noteParts) : 'Diimpor dari Comparative Quotation Sheet',
                ];
            }
        }

        if (empty($dataRows)) {
            return null;
        }

        return [
            'header_row' => $specHeaderRows[0],
            'column_map' => ['steel_spec' => 1],
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
            $usedCols = [];

            // Pass 1: exact normalized match lebih dulu agar header spesifik (seperti "Old Part No.", "Wire Spec", "OH Self %") tidak direbut alias pendek
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
                        if ($cleaned === $cleanedKw) {
                            $currentMap[$fieldKey] = $colIdx;
                            $usedCols[$colIdx] = true;
                            break 2;
                        }
                    }
                }
            }

            // Pass 2: substring match untuk kolom dan field yang belum terpetakan (prioritaskan alias terpanjang)
            foreach ($rowCols as $colIdx => $cellValue) {
                if (isset($usedCols[$colIdx])) {
                    continue;
                }

                $cleaned = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $cellValue));
                if (empty($cleaned)) {
                    continue;
                }

                $bestField = null;
                $bestLen = 0;

                foreach ($aliases as $fieldKey => $keywords) {
                    if (isset($currentMap[$fieldKey])) {
                        continue;
                    }

                    foreach ($keywords as $kw) {
                        $cleanedKw = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $kw));
                        if (strlen($cleanedKw) <= 2) {
                            continue;
                        }
                        if (str_contains($cleaned, $cleanedKw) && strlen($cleanedKw) > $bestLen) {
                            $bestField = $fieldKey;
                            $bestLen = strlen($cleanedKw);
                        }
                    }
                }

                if ($bestField !== null) {
                    $currentMap[$bestField] = $colIdx;
                    $usedCols[$colIdx] = true;
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

                if (
                    empty($rowRecord['steel_spec'])
                    && empty($rowRecord['wire_spec'])
                    && empty($rowRecord['part_number'])
                    && empty($rowRecord['coil_price'])
                    && empty($rowRecord['sheet_price'])
                    && empty($rowRecord['coil_price_idr'])
                    && empty($rowRecord['sheet_price_idr'])
                    && empty($rowRecord['wire_price_usd_kg'])
                    && empty($rowRecord['wire_price_idr_kg'])
                ) {
                    continue;
                }

                $cleanedRow = static::cleanRowValues($rowRecord, $defaults);
                if (
                    ! empty($cleanedRow['steel_spec'])
                    || ! empty($cleanedRow['wire_spec'])
                    || ! empty($cleanedRow['part_number'])
                    || $cleanedRow['coil_price'] > 0
                    || $cleanedRow['sheet_price'] > 0
                    || $cleanedRow['coil_price_idr'] > 0
                    || $cleanedRow['sheet_price_idr'] > 0
                    || (! empty($cleanedRow['wire_price_usd_kg']) && $cleanedRow['wire_price_usd_kg'] > 0)
                    || (! empty($cleanedRow['wire_price_idr_kg']) && $cleanedRow['wire_price_idr_kg'] > 0)
                ) {
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

        // Ambil Kurs Exchange Rate (izinkan 0 jika harga IDR langsung diisi & kurs memang ditulis 0)
        $hasDirectIdr = static::cleanNumeric($row['sheet_price_idr'] ?? null) > 0
            || static::cleanNumeric($row['coil_price_idr'] ?? null) > 0
            || static::cleanNumeric($row['wire_price_idr_kg'] ?? null) > 0;
        $hasExplicitRate = isset($row['exchange_rate']) && $row['exchange_rate'] !== '' && $row['exchange_rate'] !== null;
        $rate = static::cleanNumeric($row['exchange_rate'] ?? null);
        if ($rate <= 0 && ! ($hasDirectIdr && $hasExplicitRate)) {
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

        // Penyesuaian HR Dolar/MT & CR Dolar/MT serta fallback Gap USD per MT
        $steelType = MaterialSteel::determineSteelType($spec);

        $hasExplicitHr = isset($row['hr_steel_usd']) && $row['hr_steel_usd'] !== '' && $row['hr_steel_usd'] !== null;
        $hrUsd = $hasExplicitHr ? static::cleanNumeric($row['hr_steel_usd']) : null;

        $hasExplicitCr = isset($row['cr_steel_usd']) && $row['cr_steel_usd'] !== '' && $row['cr_steel_usd'] !== null;
        $crUsd = $hasExplicitCr ? static::cleanNumeric($row['cr_steel_usd']) : null;

        $hasExplicitGap = isset($row['gap_steel_usd']) && $row['gap_steel_usd'] !== '' && $row['gap_steel_usd'] !== null;
        $gapUsd = $hasExplicitGap ? static::cleanNumeric($row['gap_steel_usd']) : null;

        if ($gapUsd === null) {
            if ($steelType === 'HR' && $hrUsd !== null) {
                $gapUsd = $hrUsd;
            } elseif ($steelType === 'CR' && $crUsd !== null) {
                $gapUsd = $crUsd;
            }
        }
        if ($hrUsd === null && $steelType === 'HR' && $gapUsd !== null) {
            $hrUsd = $gapUsd;
        }
        if ($crUsd === null && $steelType === 'CR' && $gapUsd !== null) {
            $crUsd = $gapUsd;
        }

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
        $isFastener = ($defaults['module'] ?? null) === 'fastener';

        $quotationDate = null;
        if (! empty($row['quotation_date'])) {
            $rawDate = trim((string) $row['quotation_date']);
            if (is_numeric($rawDate) && (float) $rawDate > 25000) {
                $quotationDate = gmdate('Y-m-d', (int) (((float) $rawDate - 25569) * 86400));
            } else {
                $quotationDate = $rawDate;
            }
        }

        $cleaned = [
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
            'hr_steel_usd' => $hrUsd,
            'cr_steel_usd' => $crUsd,
            'gap_steel_usd' => $gapUsd,
            'gap_steel_kg_usd' => $gapKgUsd,
            'fluktuasi_jfe' => static::cleanNumeric($row['fluktuasi_jfe'] ?? $gapKgUsd),
            'status' => ! empty($row['status']) ? trim((string) $row['status']) : 'Active',
            'notes' => ! empty($row['notes']) ? trim((string) $row['notes']) : 'Diimpor otomatis via Excel',
            'part_number' => trim((string) ($row['part_number'] ?? '')),
            'part_no_to_compare' => trim((string) ($row['part_no_to_compare'] ?? '')),
            'part_name' => trim((string) ($row['part_name'] ?? '')),
            'model_name' => trim((string) ($row['model_name'] ?? ($row['model'] ?? ''))),
            'monthly_volume' => isset($row['monthly_volume']) && $row['monthly_volume'] !== '' ? (int) static::cleanNumeric($row['monthly_volume']) : ($isFastener ? 20000 : 16500),
            'drawing_reference' => ! empty($row['drawing_reference']) ? trim((string) $row['drawing_reference']) : ($isFastener ? 'Official Dwg (supplier selection)' : 'Mass Prod. Drwg'),
            'eci_number' => trim((string) ($row['eci_number'] ?? '')),
            'quotation_date' => $quotationDate,
            'exchange_rate_fluctuation' => isset($row['exchange_rate_fluctuation']) && $row['exchange_rate_fluctuation'] !== '' ? static::cleanNumeric($row['exchange_rate_fluctuation']) : 1.0,
            'comments' => trim((string) ($row['comments'] ?? '')),
            'remarks' => trim((string) ($row['remarks'] ?? '')),
            'alteration' => trim((string) ($row['alteration'] ?? '')),
            'etc_contents' => trim((string) ($row['etc_contents'] ?? '')),
            'wide' => isset($row['wide']) ? static::cleanNumeric($row['wide']) : null,
            'pitch' => isset($row['pitch']) ? static::cleanNumeric($row['pitch']) : null,
            'yield_rate' => isset($row['yield_rate']) ? static::cleanNumeric($row['yield_rate']) : 1,
            'scrap_weight' => isset($row['scrap_weight']) ? static::cleanNumeric($row['scrap_weight']) : 0,
            'scrap_price_per_kg' => isset($row['scrap_price_per_kg']) ? static::cleanNumeric($row['scrap_price_per_kg']) : -1500,
            'process_name' => ! empty($row['process_name']) ? trim((string) $row['process_name']) : '1. PROGRESSIVE 250T',
            'process_man_hour' => isset($row['process_man_hour']) ? static::cleanNumeric($row['process_man_hour']) : 9,
            'process_charge' => isset($row['process_charge']) ? static::cleanNumeric($row['process_charge']) : 45,
            'overhead_supplied_percent' => isset($row['overhead_supplied_percent']) && $row['overhead_supplied_percent'] !== '' ? static::cleanNumeric($row['overhead_supplied_percent']) : ($isFastener ? 0 : 11),
            'overhead_self_percent' => isset($row['overhead_self_percent']) && $row['overhead_self_percent'] !== '' ? static::cleanNumeric($row['overhead_self_percent']) : ($isFastener ? 14.3 : 4),
            'transportation_cost' => isset($row['transportation_cost']) && $row['transportation_cost'] !== '' ? static::cleanNumeric($row['transportation_cost']) : 0,
            'packing_cost' => isset($row['packing_cost']) && $row['packing_cost'] !== '' ? static::cleanNumeric($row['packing_cost']) : ($isFastener ? 3 : 0),
            'tooling_depreciation' => isset($row['tooling_depreciation']) && $row['tooling_depreciation'] !== '' ? static::cleanNumeric($row['tooling_depreciation']) : 0,
        ];

        // Teruskan field khusus Fastener jika tersedia di baris input
        if (! empty($row['old_part_number'])) {
            $cleaned['old_part_number'] = trim((string) $row['old_part_number']);
        }
        if (isset($row['old_exchange_rate']) && $row['old_exchange_rate'] !== '') {
            $cleaned['old_exchange_rate'] = static::cleanNumeric($row['old_exchange_rate']);
        }
        if (! empty($row['wire_spec'])) {
            $cleaned['wire_spec'] = trim((string) $row['wire_spec']);
        }
        foreach ([
            'nett_weight_gr', 'gross_weight_gr', 'wire_price_usd_kg', 'wire_price_idr_kg',
            'material_control_percent', 'washer_price', 'washer_weight_gr',
            'heading_cost', 'rolling_cost', 'washing_cost_kg', 'plating_cost_kg',
            'inspection_cost', 'cost_down',
        ] as $fNumKey) {
            if (isset($row[$fNumKey]) && $row[$fNumKey] !== '') {
                $cleaned[$fNumKey] = static::cleanNumeric($row[$fNumKey]);
            }
        }
        if (! empty($row['tooling_details'])) {
            if (is_array($row['tooling_details'])) {
                $cleaned['tooling_details'] = $row['tooling_details'];
            } elseif (is_string($row['tooling_details'])) {
                $decoded = json_decode($row['tooling_details'], true);
                if (is_array($decoded)) {
                    $cleaned['tooling_details'] = $decoded;
                }
            }
        }

        return $cleaned;
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

        $detection = (($defaults['module'] ?? 'stamping_part') === 'stamping_part'
            ? static::detectComparativeQuotationStampingBlocks($grid, $defaults)
            : null) ?? static::detectColumnsAndData($grid, $defaults);
        $rows = $detection['data_rows'];

        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $idx => $row) {
            try {
                if (empty($row['steel_spec']) && empty($row['part_number'])) {
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

                // Cek apakah data sudah ada (cocokkan via part_number atau steel_spec)
                $query = StampingPart::where('year', $row['year'])
                    ->where('quarter', $row['quarter']);

                if (! empty($row['part_number'])) {
                    $query->where('part_number', $row['part_number']);
                } else {
                    $query->where('steel_spec', $row['steel_spec'])
                        ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId));
                }

                $existing = $query->first();

                if ($existing) {
                    if ($updateExisting) {
                        $updateData = array_filter($row, fn ($val) => $val !== null && $val !== '');
                        $existing->update($updateData);
                        $updated++;
                    }
                } else {
                    $createData = array_filter($row, fn ($val) => $val !== null && $val !== '');
                    StampingPart::create($createData);
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

        $detection = static::detectColumnsAndData($grid, array_merge($defaults, ['module' => 'fastener']));
        $rows = $detection['data_rows'];

        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $idx => $row) {
            try {
                if (empty($row['steel_spec']) && empty($row['wire_spec']) && empty($row['part_number'])) {
                    continue;
                }

                if (empty($row['steel_spec']) && ! empty($row['wire_spec'])) {
                    if (preg_match('/\[([^\]]+)\]/', (string) $row['wire_spec'], $m)) {
                        $row['steel_spec'] = Fastener::cleanSteelSpec($m[1]);
                    } else {
                        $row['steel_spec'] = Fastener::cleanSteelSpec((string) $row['wire_spec']);
                    }
                } elseif (empty($row['steel_spec'])) {
                    $row['steel_spec'] = 'SWCH';
                }

                // Auto Gross Weight = Nett Weight * 1.1 sesuai Excel R16 jika hanya Nett yang diisi
                if (empty($row['gross_weight_gr']) && ! empty($row['nett_weight_gr']) && (float) $row['nett_weight_gr'] > 0) {
                    $row['gross_weight_gr'] = round((float) $row['nett_weight_gr'] * 1.1, 4);
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

                // Cek apakah data sudah ada (berdasarkan part_number jika ada, atau steel_spec)
                $query = Fastener::where('year', $row['year'])
                    ->where('quarter', $row['quarter']);
                if (! empty($row['part_number'])) {
                    $query->where('part_number', $row['part_number']);
                } else {
                    $query->where('steel_spec', $row['steel_spec']);
                }
                if ($supplierId) {
                    $query->where('supplier_id', $supplierId);
                }
                $existing = $query->first();

                if ($existing) {
                    if ($updateExisting) {
                        $updateData = [
                            'vendor_code' => $row['vendor_code'] ?? $existing->vendor_code,
                            'supplier_name' => $row['supplier_name'] ?? $existing->supplier_name,
                            'double_thickness' => $row['double_thickness'] ?: $existing->double_thickness,
                            'diameter' => $row['diameter'] ?: $existing->diameter,
                            'exchange_rate' => $row['exchange_rate'] ?? $existing->exchange_rate,
                            'coil_price' => $row['coil_price'] ?? $existing->coil_price,
                            'coil_price_idr' => $row['coil_price_idr'] ?? $existing->coil_price_idr,
                            'sheet_price' => $row['sheet_price'] ?? $existing->sheet_price,
                            'sheet_price_idr' => $row['sheet_price_idr'] ?? $existing->sheet_price_idr,
                            'gap_steel_usd' => $row['gap_steel_usd'] ?? $existing->gap_steel_usd,
                            'gap_steel_kg_usd' => $row['gap_steel_kg_usd'] ?? $existing->gap_steel_kg_usd,
                            'fluktuasi_jfe' => $row['fluktuasi_jfe'] ?? $existing->fluktuasi_jfe,
                        ];

                        foreach ([
                            'part_number', 'old_part_number', 'part_name', 'model_name', 'monthly_volume',
                            'drawing_reference', 'eci_number', 'quotation_date', 'old_exchange_rate',
                            'exchange_rate_fluctuation', 'wire_spec', 'gross_weight_gr', 'nett_weight_gr',
                            'wire_price_usd_kg', 'wire_price_idr_kg', 'material_control_percent',
                            'washer_price', 'washer_weight_gr', 'heading_cost', 'rolling_cost',
                            'washing_cost_kg', 'plating_cost_kg', 'inspection_cost', 'packing_cost',
                            'overhead_supplied_percent', 'overhead_self_percent', 'transportation_cost',
                            'cost_down', 'tooling_depreciation', 'tooling_details',
                            'status', 'notes', 'comments', 'remarks', 'alteration', 'etc_contents',
                        ] as $fKey) {
                            if (array_key_exists($fKey, $row) && $row[$fKey] !== null && $row[$fKey] !== '') {
                                $updateData[$fKey] = $row[$fKey];
                            }
                        }

                        $existing->update($updateData);
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

        $detection = (($defaults['module'] ?? 'stamping_part') === 'stamping_part'
            ? static::detectComparativeQuotationStampingBlocks($grid, $defaults)
            : null) ?? static::detectColumnsAndData($grid, $defaults);
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
            'HR Dolar/MT',
            'CR Dolar/MT',
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
                '0',
                '-20',
                '17796',
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
                '-20',
                '0',
                '17796',
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
