<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use Filament\Forms;

class QuarterlyPullHelper
{
    /**
     * Kembalikan daftar tahun standar untuk pilihan periode Tarik Data Kuartal.
     *
     * @return array<int, int>
     */
    public static function getYearOptions(): array
    {
        $current = (int) date('Y');
        $years = range($current + 2, 2020);

        return array_combine($years, $years);
    }

    /**
     * Kembalikan opsi kuartal Q1-Q4 beserta keterangan bulannya.
     *
     * @return array<string, string>
     */
    public static function getQuarterOptions(): array
    {
        return [
            'Q1' => 'Q1 (Januari - Maret)',
            'Q2' => 'Q2 (April - Juni)',
            'Q3' => 'Q3 (Juli - September)',
            'Q4' => 'Q4 (Oktober - Desember)',
        ];
    }

    /**
     * Dapatkan kuartal sebelum kuartal berjalan.
     */
    public static function getPreviousQuarter(string $quarter): string
    {
        return match ($quarter) {
            'Q1' => 'Q4',
            'Q2' => 'Q1',
            'Q3' => 'Q2',
            'Q4' => 'Q3',
            default => 'Q4',
        };
    }

    /**
     * Dapatkan kuartal sesudah kuartal acuan.
     */
    public static function getNextQuarter(string $quarter): string
    {
        return match ($quarter) {
            'Q1' => 'Q2',
            'Q2' => 'Q3',
            'Q3' => 'Q4',
            'Q4' => 'Q1',
            default => 'Q2',
        };
    }

    /**
     * Cari kurs USD default dari tabel Currency berdasarkan tahun dan kuartal target.
     */
    public static function resolveTargetCurrencyRate(int $year, string $quarter, int $fallback = 16000): int
    {
        $rate = Currency::where('code', 'USD')->where('year', $year)->where('quarter', $quarter)->value('exchange_rate_to_idr')
            ?? Currency::where('code', 'USD')->where('year', $year)->value('exchange_rate_to_idr')
            ?? Currency::where('code', 'USD')->latest('year')->value('exchange_rate_to_idr')
            ?? $fallback;

        return (int) round((float) $rate);
    }

    /**
     * Bangun komponen pilihan Tahun & Kuartal Acuan serta Target untuk modal Tarik Data Kuartal.
     *
     * @return array<int, Forms\Components\Grid>
     */
    public static function buildPeriodSelectionGrids(callable $getPageData): array
    {
        return [
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('source_year')
                        ->label('Tahun Acuan')
                        ->options(fn () => static::getYearOptions())
                        ->default(function () use ($getPageData) {
                            $data = $getPageData();
                            $q = $data['quarter'] ?? 'Q1';
                            $y = (int) ($data['year'] ?? date('Y'));

                            return $q === 'Q1' ? $y - 1 : $y;
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('source_record_id', null)),

                    Forms\Components\Select::make('source_quarter')
                        ->label('Kuartal Acuan')
                        ->options(fn () => static::getQuarterOptions())
                        ->default(function () use ($getPageData) {
                            $data = $getPageData();

                            return static::getPreviousQuarter($data['quarter'] ?? 'Q1');
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('source_record_id', null)),
                ]),

            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('target_year')
                        ->label('Tahun yang Akan Diisi')
                        ->options(fn () => static::getYearOptions())
                        ->default(function () use ($getPageData) {
                            $data = $getPageData();

                            return (int) ($data['year'] ?? date('Y'));
                        })
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('target_quarter')
                        ->label('Kuartal yang Akan Diisi')
                        ->options(fn () => static::getQuarterOptions())
                        ->default(function (Forms\Get $get) use ($getPageData) {
                            $data = $getPageData();
                            $sq = $get('source_quarter') ?? ($data['quarter'] ?? 'Q1');

                            return static::getNextQuarter($sq);
                        })
                        ->required()
                        ->live(),
                ]),
        ];
    }

    /**
     * Komponen input Kurs USD tunggal yang bisa dipakai ulang lintas modul.
     */
    public static function buildRateField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('exchange_rate')
            ->label('Kurs USD')
            ->numeric()
            ->prefix('Rp')
            ->placeholder('Contoh: 16500')
            ->helperText('Kurs yang akan diterapkan untuk kuartal target (bisa diubah manual)')
            ->default(function (Forms\Get $get) {
                $targetY = (int) ($get('target_year') ?: date('Y'));
                $targetQ = $get('target_quarter') ?: 'Q1';

                return static::resolveTargetCurrencyRate($targetY, $targetQ);
            })
            ->live(onBlur: true);
    }

    /**
     * Bangun komponen input Kurs USD dan Gap HRC/CRC ($/MT) untuk simulasi baja lembaran (Stamping Part).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function buildAdjustmentFields(): array
    {
        return [
            static::buildRateField(),

            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('hr_steel_usd')
                        ->label('Gap HRC ($/MT)')
                        ->numeric()
                        ->prefix('$')
                        ->placeholder('Contoh: -57 atau 50')
                        ->helperText('Gunakan minus (-) jika harga turun (contoh: -57), positif jika naik')
                        ->default(0)
                        ->live(debounce: 400),

                    Forms\Components\TextInput::make('cr_steel_usd')
                        ->label('Gap CRC ($/MT)')
                        ->numeric()
                        ->prefix('$')
                        ->placeholder('Contoh: -57 atau 100')
                        ->helperText('Gunakan minus (-) jika harga turun (contoh: -57), positif jika naik')
                        ->default(0)
                        ->live(debounce: 400),
                ]),
        ];
    }

    /**
     * Bangun komponen input Kurs USD dan Harga Kawat / Wire ($/Kg) sesuai standar Excel Fastener (Nitto Alam).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function buildFastenerAdjustmentFields(): array
    {
        return [
            Forms\Components\Grid::make(2)
                ->schema([
                    static::buildRateField(),

                    Forms\Components\TextInput::make('wire_price_usd_kg')
                        ->label('Harga Wire / Kawat ($/Kg)')
                        ->numeric()
                        ->step(0.0001)
                        ->prefix('$')
                        ->placeholder('Contoh: 2.0200')
                        ->helperText('Harga kawat dalam USD/kg (terisi otomatis dari item acuan)')
                        ->live(debounce: 400),
                ]),
        ];
    }

    /**
     * Hitung total depresiasi tooling dari array rincian Repeater tooling_details.
     */
    public static function sumToolingDepreciation(mixed $toolingDetails): float
    {
        if (! is_array($toolingDetails) || empty($toolingDetails)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($toolingDetails as $tool) {
            $total += (float) ($tool['depreciation_per_pc'] ?? $tool['depreciation'] ?? 0);
        }

        return round($total, 4);
    }

    /**
     * Komponen Repeater rincian investasi Tooling (Die & Jig / Heading Die) dengan sinkronisasi live ke depresiasi per pc.
     */
    public static function buildToolingDetailsRepeater(string $itemPlaceholder = 'Misal: DIE STAMPING / WELD JIG', string $volumePlaceholder = '16500'): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('tooling_details')
            ->label('Rincian Investasi Tooling & Amortisasi (Die & Jig Breakdown)')
            ->schema([
                Forms\Components\TextInput::make('contents')
                    ->label('Contents / Item Die')
                    ->placeholder($itemPlaceholder)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('total_cost_krp')
                    ->label('Total Cost (KRp)')
                    ->numeric()
                    ->placeholder('0')
                    ->columnSpan(1),
                Forms\Components\TextInput::make('monthly_volume')
                    ->label('Monthly Volume (pcs)')
                    ->numeric()
                    ->placeholder($volumePlaceholder)
                    ->columnSpan(1),
                Forms\Components\TextInput::make('depreciation_per_pc')
                    ->label('Depr / pc (Rp)')
                    ->numeric()
                    ->placeholder('0')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                        $sum = static::sumToolingDepreciation($get('../../tooling_details'));
                        if ($sum > 0) {
                            $set('../../tooling_depreciation', $sum);
                        }
                    })
                    ->columnSpan(1),
            ])
            ->live()
            ->afterStateUpdated(function ($state, Forms\Set $set) {
                $sum = static::sumToolingDepreciation($state);
                if ($sum > 0) {
                    $set('tooling_depreciation', $sum);
                }
            })
            ->columns(5)
            ->collapsible()
            ->collapsed()
            ->columnSpanFull();
    }

    /**
     * Tentukan nilai selisih $/MT dan $/Kg yang diterapkan berdasarkan tipe baja (HR vs CR).
     *
     * @return array{applied_mt: float, applied_kg: float}
     */
    public static function resolveSteelGap(string $steelType, float $hrMt, float $crMt): array
    {
        if ($steelType === 'HR') {
            $appliedMt = $hrMt != 0.0 ? $hrMt : $crMt;
        } else {
            $appliedMt = $crMt != 0.0 ? $crMt : $hrMt;
        }

        return [
            'applied_mt' => $appliedMt,
            'applied_kg' => round($appliedMt / 1000, 4),
        ];
    }

    /**
     * Tentukan nilai kurs yang diterapkan: utamakan input modal, lalu fallback ke acuan.
     */
    public static function resolveAppliedRate(mixed $modalRate, ?float $recordRate = null, ?float $pageRate = null): float
    {
        if ($modalRate !== null && $modalRate !== '' && (float) $modalRate > 0) {
            return (float) $modalRate;
        }

        if ($recordRate !== null && $recordRate > 0) {
            return (float) $recordRate;
        }

        if ($pageRate !== null && $pageRate > 0) {
            return (float) $pageRate;
        }

        return 16000.0;
    }

    /**
     * Hitung simulasi biaya lengkap untuk Material Steel acuan yang dipilih di modal.
     *
     * @return array<string, float>
     */
    public static function computeMaterialSteelSimulation(MaterialSteel $rec, float $rate, float $appliedKg): array
    {
        $baseCoil = (float) $rec->coil_price;
        $baseSheet = (float) $rec->sheet_price;
        $newCoil = $baseCoil > 0 ? round($baseCoil + $appliedKg, 4) : 0.0;
        $newSheet = $baseSheet > 0 ? round($baseSheet + $appliedKg, 4) : 0.0;

        return [
            'base_coil' => $baseCoil,
            'base_sheet' => $baseSheet,
            'new_coil' => $newCoil,
            'new_sheet' => $newSheet,
            'coil_price_idr' => round($newCoil * $rate, 2),
            'sheet_price_idr' => round($newSheet * $rate, 2),
            'rate' => $rate,
        ];
    }

    /**
     * Hitung simulasi biaya 9 baris untuk Stamping Part berdasarkan kurs dan selisih baja baru.
     *
     * @return array<string, float>
     */
    public static function computeStampingSimulation(StampingPart $rec, float $rate, float $appliedKg): array
    {
        $baseCoil = (float) ($rec->coil_price ?? 0);
        $baseSheet = (float) ($rec->sheet_price ?? 0);
        $newCoil = $baseCoil > 0 ? round($baseCoil + $appliedKg, 4) : 0.0;
        $newSheet = $baseSheet > 0 ? round($baseSheet + $appliedKg, 4) : 0.0;

        $coilIdr = round($newCoil * $rate, 2);
        $sheetIdr = round($newSheet * $rate, 2);
        $steelRate = (float) ($sheetIdr > 0 ? $sheetIdr : $coilIdr);

        $thk = (float) ($rec->double_thickness ?? 0);
        $wide = (float) ($rec->wide ?? 0);
        $pitch = (float) ($rec->pitch ?? 0);
        $yieldRate = (float) ($rec->yield_rate ?: 1);
        $yieldRate = $yieldRate > 0 ? $yieldRate : 1;

        $berat = (float) ($rec->berat ?? 0);
        if ($berat <= 0.0 && $thk > 0 && $wide > 0 && $pitch > 0) {
            $berat = round(($thk * $wide * $pitch * 7.85) / (1000000 * $yieldRate), 4);
        }

        $grossMat = $berat > 0 && $steelRate > 0 ? round($berat * $steelRate, 2) : 0.0;
        $scrapWeight = $berat > 0 ? (float) ($rec->scrap_weight ?? 0) : 0.0;
        $scrapPrice = (float) ($rec->scrap_price_per_kg ?? -1500);
        $scrapCost = round($scrapWeight * $scrapPrice, 2);
        $matTotal = round($grossMat + $scrapCost, 2);

        $procMan = (float) ($rec->process_man_hour ?? 9);
        $procChg = (float) ($rec->process_charge ?? 45);
        $procTotal = round(($procMan * $procChg) / $yieldRate, 2);
        $subtotal = round($matTotal + $procTotal, 2);

        $ohSupp = round(((float) ($rec->overhead_supplied_percent ?? 11) / 100) * $procTotal, 2);
        $ohSelf = round(((float) ($rec->overhead_self_percent ?? 4) / 100) * $matTotal, 2);
        $transCost = (float) ($rec->transportation_cost ?? 0);
        $packCost = (float) ($rec->packing_cost ?? 0);
        $toolingDepr = (float) ($rec->tooling_depreciation ?? 0);

        $partCostExcl = round($subtotal + $ohSupp + $ohSelf + $transCost + $packCost, 4);
        $partCost = round($partCostExcl + $toolingDepr, 4);

        return [
            'base_coil' => $baseCoil,
            'base_sheet' => $baseSheet,
            'new_coil' => $newCoil,
            'new_sheet' => $newSheet,
            'coil_price_idr' => $coilIdr,
            'sheet_price_idr' => $sheetIdr,
            'berat' => $berat,
            'gross_material_cost' => $grossMat,
            'scrap_cost' => $scrapCost,
            'material_total' => $matTotal,
            'process_total' => $procTotal,
            'subtotal' => $subtotal,
            'overhead_supplied_cost' => $ohSupp,
            'overhead_self_cost' => $ohSelf,
            'part_cost_excluding_tooling' => $partCostExcl,
            'part_cost' => $partCost,
            'old_part_cost' => (float) ($rec->part_cost ?? 0),
            'rate' => $rate,
        ];
    }

    /**
     * Hitung simulasi biaya lengkap untuk Fastener berdasarkan kurs, harga kawat ($/kg), atau selisih baja baru.
     *
     * @return array<string, float>
     */
    public static function computeFastenerSimulation(Fastener $rec, float $rate, float $appliedKg = 0.0, ?float $overrideWireUsd = null): array
    {
        $baseCoil = (float) ($rec->coil_price ?? 0);
        $baseSheet = (float) ($rec->sheet_price ?? 0);
        $baseWire = (float) ($rec->wire_price_usd_kg ?: ($baseCoil > 0 ? $baseCoil : 1.66));

        $hasExplicitWire = $overrideWireUsd !== null && $overrideWireUsd > 0;
        $newWireUsd = $hasExplicitWire ? round($overrideWireUsd, 4) : round($baseWire + $appliedKg, 4);
        $deltaWireUsd = $hasExplicitWire ? round($newWireUsd - $baseWire, 4) : $appliedKg;

        $newCoil = $baseCoil > 0 ? round($baseCoil + $deltaWireUsd, 4) : 0.0;
        $newSheet = $baseSheet > 0 ? round($baseSheet + $deltaWireUsd, 4) : 0.0;

        $coilIdr = round($newCoil * $rate, 2);
        $sheetIdr = round($newSheet * $rate, 2);
        $wireIdrKg = round($newWireUsd * $rate, 2);

        $oldRate = (float) ($rec->exchange_rate ?: ($rec->old_exchange_rate ?: 16000));
        $fluctuation = ($oldRate > 0 && $rate > 0) ? round($rate / $oldRate, 6) : 1.0;

        $grossGr = (float) ($rec->gross_weight_gr ?? 4.84);
        $wirePc = round(($grossGr / 1000) * $wireIdrKg, 4);

        $ctrlPct = (float) ($rec->material_control_percent ?? 5);
        $ctrlCost = round(($ctrlPct / 100) * $wirePc, 4);
        $washerPrice = (float) ($rec->washer_price ?? 198);
        $matTotal = round($wirePc + $ctrlCost + $washerPrice, 4);

        $nettGr = (float) ($rec->nett_weight_gr ?? 4.4);
        $washerGr = (float) ($rec->washer_weight_gr ?? 4.5);
        $totalPartWeightKg = ($nettGr + $washerGr) / 1000;

        $washingCost = round($totalPartWeightKg * (float) ($rec->washing_cost_kg ?? 500), 4);
        $platingCost = round($totalPartWeightKg * (float) ($rec->plating_cost_kg ?? 10000), 4);
        $procTotal = round(
            (float) ($rec->heading_cost ?? 48)
            + (float) ($rec->rolling_cost ?? 76)
            + $washingCost
            + $platingCost
            + (float) ($rec->inspection_cost ?? 5)
            + (float) ($rec->packing_cost ?? 3),
            4
        );

        $subtotal = round($matTotal + $procTotal, 4);
        $ohSelfPct = (float) ($rec->overhead_self_percent ?? 14.3);
        $ohSelfCost = round(($ohSelfPct / 100) * $subtotal, 4);
        $toolingDepr = (float) ($rec->tooling_depreciation ?? 0);
        $partCost = round($subtotal + $ohSelfCost + $toolingDepr, 4);

        return [
            'base_coil' => $baseCoil,
            'base_sheet' => $baseSheet,
            'base_wire' => $baseWire,
            'new_coil' => $newCoil,
            'new_sheet' => $newSheet,
            'new_wire_usd' => $newWireUsd,
            'coil_price_idr' => $coilIdr,
            'sheet_price_idr' => $sheetIdr,
            'wire_price_idr_kg' => $wireIdrKg,
            'wire_price_pc' => $wirePc,
            'material_control_cost' => $ctrlCost,
            'material_total' => $matTotal,
            'washing_cost' => $washingCost,
            'plating_cost' => $platingCost,
            'process_total' => $procTotal,
            'subtotal' => $subtotal,
            'overhead_self_cost' => $ohSelfCost,
            'part_cost' => $partCost,
            'old_part_cost' => (float) ($rec->part_cost ?? 0),
            'old_rate' => $oldRate,
            'exchange_rate_fluctuation' => $fluctuation,
            'rate' => $rate,
        ];
    }

    /**
     * Render HTML pratinjau simulasi Material Steel di dalam modal Tarik Data Kuartal.
     */
    public static function renderMaterialSteelPreviewHtml(MaterialSteel $rec, string $targetQ, int $targetY, array $sim): string
    {
        $rateStr = number_format($sim['rate'], 0, ',', '.');
        $newCoilIdr = number_format(round($sim['coil_price_idr']), 0, ',', '.');
        $newSheetIdr = number_format(round($sim['sheet_price_idr']), 0, ',', '.');
        $thickStr = $rec->double_thickness !== null && $rec->double_thickness !== '' ? "{$rec->double_thickness} mm" : '-';

        return "
            <div class='p-4 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded-lg text-sm space-y-2'>
                <div class='font-semibold text-primary-900 dark:text-primary-100 flex items-center justify-between'>
                    <span class='text-base font-bold'>[Material Steel] {$rec->steel_spec}</span>
                    <span class='text-xs px-2 py-0.5 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 font-mono'>Acuan: {$rec->quarter} {$rec->year} ➔ Target: {$targetQ} {$targetY}</span>
                </div>
                <div class='text-gray-700 dark:text-gray-300'><strong>Supplier:</strong> {$rec->supplier_name} <span class='text-xs text-gray-500'>({$rec->vendor_code})</span> | <strong>Ketebalan:</strong> {$thickStr}</div>
                <div class='grid grid-cols-2 gap-2 pt-2 border-t border-primary-200 dark:border-primary-800/50 text-xs text-gray-700 dark:text-gray-300'>
                    <div><strong>Harga Coil Baru:</strong> <span class='font-bold text-primary-700 dark:text-primary-300'>$ {$sim['new_coil']}</span> <span class='text-gray-500'>(Rp {$newCoilIdr})</span></div>
                    <div><strong>Harga Sheet Baru:</strong> <span class='font-bold text-primary-700 dark:text-primary-300'>$ {$sim['new_sheet']}</span> <span class='text-gray-500'>(Rp {$newSheetIdr})</span></div>
                    <div><strong>Harga Dasar Acuan:</strong> Coil \${$sim['base_coil']} | Sheet \${$sim['base_sheet']}</div>
                    <div><strong>Kurs Diterapkan:</strong> Rp {$rateStr}</div>
                </div>
            </div>
        ";
    }

    /**
     * Render HTML pratinjau simulasi Stamping Part di dalam modal Tarik Data Kuartal.
     */
    public static function renderStampingPreviewHtml(StampingPart $rec, string $targetQ, int $targetY, array $sim): string
    {
        $rateStr = number_format($sim['rate'], 0, ',', '.');
        $oldCostStr = number_format($sim['old_part_cost'], 2, ',', '.');
        $newCostStr = number_format($sim['part_cost'], 2, ',', '.');
        $coilIdrStr = number_format(round($sim['coil_price_idr']), 0, ',', '.');
        $sheetIdrStr = number_format(round($sim['sheet_price_idr']), 0, ',', '.');
        $thickStr = $rec->double_thickness !== null && $rec->double_thickness !== '' ? "{$rec->double_thickness} mm" : '-';

        return "
            <div class='p-4 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded-lg text-sm space-y-2'>
                <div class='font-semibold text-primary-900 dark:text-primary-100 flex items-center justify-between gap-2'>
                    <span class='text-base font-bold'>{$rec->part_number}</span>
                    <span class='text-xs px-2 py-0.5 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 font-mono'>Acuan: {$rec->quarter} {$rec->year} ➔ Target: {$targetQ} {$targetY}</span>
                </div>
                <div class='text-gray-700 dark:text-gray-300'><strong>Nama Part:</strong> {$rec->part_name} <span class='text-xs text-gray-500'>({$rec->model_name})</span></div>
                <div class='text-gray-700 dark:text-gray-300'><strong>Supplier:</strong> {$rec->supplier_name} <span class='text-xs text-gray-500'>({$rec->vendor_code})</span> | <strong>Spec:</strong> {$rec->steel_spec} ({$thickStr})</div>
                <div class='grid grid-cols-2 gap-2 pt-2 border-t border-primary-200 dark:border-primary-800/50 text-xs text-gray-700 dark:text-gray-300'>
                    <div><strong>Part Cost Baru (Simulasi):</strong> <span class='font-bold text-emerald-700 dark:text-emerald-400'>Rp {$newCostStr}</span></div>
                    <div><strong>Part Cost Acuan:</strong> Rp {$oldCostStr}</div>
                    <div><strong>Harga Coil Baru:</strong> <span class='font-bold text-primary-700 dark:text-primary-300'>$ {$sim['new_coil']}</span> <span class='text-gray-500'>(Rp {$coilIdrStr})</span></div>
                    <div><strong>Harga Sheet Baru:</strong> <span class='font-bold text-primary-700 dark:text-primary-300'>$ {$sim['new_sheet']}</span> <span class='text-gray-500'>(Rp {$sheetIdrStr})</span></div>
                    <div><strong>Harga Dasar Acuan:</strong> Coil \${$sim['base_coil']} | Sheet \${$sim['base_sheet']}</div>
                    <div><strong>Kurs Diterapkan:</strong> Rp {$rateStr}</div>
                </div>
            </div>
        ";
    }

    /**
     * Render HTML pratinjau simulasi Fastener di dalam modal Tarik Data Kuartal.
     */
    public static function renderFastenerPreviewHtml(Fastener $rec, string $targetQ, int $targetY, array $sim): string
    {
        $rateStr = number_format($sim['rate'], 0, ',', '.');
        $oldRateStr = number_format($sim['old_rate'], 0, ',', '.');
        $oldCostStr = number_format($sim['old_part_cost'], 2, ',', '.');
        $newCostStr = number_format($sim['part_cost'], 2, ',', '.');
        $wireIdrStr = number_format(round($sim['wire_price_idr_kg']), 0, ',', '.');
        $matTotStr = number_format($sim['material_total'], 2, ',', '.');
        $procTotStr = number_format($sim['process_total'], 2, ',', '.');
        $thickStr = $rec->double_thickness !== null && $rec->double_thickness !== '' ? "{$rec->double_thickness} mm" : '-';

        return "
            <div class='p-4 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded-lg text-sm space-y-2'>
                <div class='font-semibold text-primary-900 dark:text-primary-100 flex items-center justify-between gap-2'>
                    <span class='text-base font-bold'>{$rec->part_number}</span>
                    <span class='text-xs px-2 py-0.5 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 font-mono'>Acuan: {$rec->quarter} {$rec->year} ➔ Target: {$targetQ} {$targetY}</span>
                </div>
                <div class='text-gray-700 dark:text-gray-300'><strong>Nama Item:</strong> {$rec->part_name}</div>
                <div class='text-gray-700 dark:text-gray-300'><strong>Supplier:</strong> {$rec->supplier_name} <span class='text-xs text-gray-500'>({$rec->vendor_code})</span> | <strong>Wire:</strong> {$rec->wire_spec} ({$thickStr})</div>
                <div class='grid grid-cols-2 gap-2 pt-2 border-t border-primary-200 dark:border-primary-800/50 text-xs text-gray-700 dark:text-gray-300'>
                    <div><strong>Part Cost Baru (Simulasi):</strong> <span class='font-bold text-emerald-700 dark:text-emerald-400'>Rp {$newCostStr}</span></div>
                    <div><strong>Part Cost Acuan:</strong> Rp {$oldCostStr}</div>
                    <div><strong>Harga Wire Baru:</strong> <span class='font-bold text-primary-700 dark:text-primary-300'>$ {$sim['new_wire_usd']} /kg</span> <span class='text-gray-500'>(Rp {$wireIdrStr})</span></div>
                    <div><strong>Material / Process:</strong> Rp {$matTotStr} / Rp {$procTotStr}</div>
                    <div><strong>Kurs Diterapkan:</strong> Rp {$rateStr} <span class='text-gray-500'>(Lama: Rp {$oldRateStr})</span></div>
                    <div><strong>Exchg Fluctuation:</strong> {$sim['exchange_rate_fluctuation']}x</div>
                </div>
            </div>
        ";
    }
}
