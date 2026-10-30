@php
    use App\Models\StampingPart;

    // Helper ekstrak seluruh metrik biaya dari suatu record StampingPart
    $extractMetrics = function (?StampingPart $model): ?array {
        if (! $model) {
            return null;
        }
        $r = (float) ($model->exchange_rate ?? 16000);
        $coil = (float) ($model->coil_price_idr ?? round((float) ($model->coil_price ?? 0) * $r));
        $sheet = (float) ($model->sheet_price_idr ?? round((float) ($model->sheet_price ?? 0) * $r));
        $steel = $sheet > 0 ? $sheet : ($coil > 0 ? $coil : round((float) ($model->sheet_price ?? $model->coil_price ?? 0) * $r));
        $berat = (float) ($model->berat ?? 0);
        $grossMat = (float) ($model->gross_material_cost ?? ($berat * $steel));
        $scrap = (float) ($model->scrap_cost ?? 0);
        $matTot = (float) ($model->material_total ?? ($grossMat + $scrap));
        $procTot = (float) ($model->process_total ?? 0);
        $sub = (float) ($model->subtotal ?? ($matTot + $procTot));
        $ohSup = (float) ($model->overhead_supplied_cost ?? 0);
        $ohSlf = (float) ($model->overhead_self_cost ?? 0);
        $trans = (float) ($model->transportation_cost ?? 0);
        $pack = (float) ($model->packing_cost ?? 0);
        $exclTool = (float) ($model->part_cost_excluding_tooling ?? ($sub + $ohSup + $ohSlf + $trans + $pack));
        $tool = (float) ($model->tooling_depreciation ?? 0);
        $cost = (float) ($model->part_cost ?? ($exclTool + $tool));

        return [
            'record' => $model,
            'year' => $model->year,
            'quarter' => $model->quarter,
            'rate' => $r,
            'sheet_price' => (float) ($model->sheet_price ?? 0),
            'steel_rate' => $steel,
            'berat' => $berat,
            'gross_mat' => $grossMat,
            'scrap_weight' => (float) ($model->scrap_weight ?? 0),
            'scrap_price' => (float) ($model->scrap_price_per_kg ?? -1500),
            'scrap_cost' => $scrap,
            'mat_total' => $matTot,
            'process_name' => $model->process_name,
            'process_man_hour' => (float) ($model->process_man_hour ?? 9),
            'process_charge' => (float) ($model->process_charge ?? 45),
            'yield_rate' => (float) ($model->yield_rate ?: 1),
            'proc_total' => $procTot,
            'subtotal' => $sub,
            'oh_supplied_pct' => (float) ($model->overhead_supplied_percent ?? 11),
            'oh_supplied' => $ohSup,
            'oh_self_pct' => (float) ($model->overhead_self_percent ?? 4),
            'oh_self' => $ohSlf,
            'trans_cost' => $trans,
            'pack_cost' => $pack,
            'excl_tooling' => $exclTool,
            'tooling' => $tool,
            'part_cost' => $cost,
        ];
    };

    // Skor kronologis untuk membandingkan urutan waktu: year * 10 + quarter (Q1=1, Q2=2, Q3=3, Q4=4)
    $qNum = fn ($q) => match (strtoupper(trim((string) $q))) {
        'Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4, default => 1
    };
    $scoreFn = fn ($y, $q) => ((int) $y * 10) + $qNum($q);
    $currentScore = $scoreFn($record->year, $record->quarter);

    // Cari seluruh record lain untuk part_number yang sama
    $otherQuarters = StampingPart::where('part_number', $record->part_number)
        ->where('id', '!=', $record->id)
        ->get();

    // Kuartal sebelum kuartal berjalan (Earlier / Lama)
    $earlierQuarter = $otherQuarters->filter(fn ($q) => $scoreFn($q->year, $q->quarter) < $currentScore)
        ->sortByDesc(fn ($q) => $scoreFn($q->year, $q->quarter))
        ->first();

    // Kuartal setelah kuartal berjalan (Later / Baru)
    $laterQuarter = $otherQuarters->filter(fn ($q) => $scoreFn($q->year, $q->quarter) > $currentScore)
        ->sortBy(fn ($q) => $scoreFn($q->year, $q->quarter))
        ->first();

    // Tentukan pasangan komparasi secara kronologis (Left = Lebih Awal/Lama, Right = Lebih Baru/Berikutnya)
    if ($earlierQuarter) {
        // Kasus 1: Membuka kuartal yang memiliki kuartal acuan sebelumnya (misal membuka Q2 dan ada Q1)
        $leftRecord = $earlierQuarter;
        $rightRecord = $record;
        $leftHeaderLabel = "Lama ({$leftRecord->quarter} {$leftRecord->year})";
        $rightHeaderLabel = "Baru ({$rightRecord->quarter} {$rightRecord->year})";
        $activeRecord = 'right';
    } elseif ($laterQuarter) {
        // Kasus 2: Membuka kuartal awal (misal Q1) dan ada kuartal berikutnya (Q2)
        // Kronologi tetap dijaga: Q1 di kiri sebagai awal, Q2 di kanan sebagai eskalasi berikutnya!
        $leftRecord = $record;
        $rightRecord = $laterQuarter;
        $leftHeaderLabel = "Kuartal Berjalan ({$leftRecord->quarter} {$leftRecord->year})";
        $rightHeaderLabel = "Kuartal Berikutnya ({$rightRecord->quarter} {$rightRecord->year})";
        $activeRecord = 'left';
    } else {
        // Kasus 3: Kuartal tunggal tanpa pembanding
        $leftRecord = $record;
        $rightRecord = null;
        $leftHeaderLabel = "Kuartal Berjalan ({$leftRecord->quarter} {$leftRecord->year})";
        $rightHeaderLabel = 'Acuan Pembanding';
        $activeRecord = 'left';
    }

    $left = $extractMetrics($leftRecord);
    $right = $extractMetrics($rightRecord);

    // Helper formatting selisih (Difference = Right - Left)
    $diffFn = function (?float $rightVal, ?float $leftVal, bool $isPrice = true, int $decimals = 2): array {
        if ($rightVal === null || $leftVal === null) {
            return [
                'has_prev' => false,
                'diff' => 0.0,
                'formatted' => '-',
                'color' => 'text-gray-400',
                'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                'icon' => '—',
            ];
        }

        $diff = round($rightVal - $leftVal, 4);
        $pct = $leftVal != 0.0 ? round(($diff / abs($leftVal)) * 100, 2) : 0.0;

        if (abs($diff) < 0.0001) {
            return [
                'has_prev' => true,
                'diff' => 0.0,
                'formatted' => $isPrice ? 'Rp 0' : '0',
                'color' => 'text-gray-500 dark:text-gray-400 font-semibold',
                'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                'icon' => '—',
            ];
        }

        $isUp = $diff > 0;
        $sign = $isUp ? '+ ' : '- ';
        $formattedNum = number_format(abs($diff), $decimals, ',', '.');
        $text = $isPrice ? "{$sign}Rp {$formattedNum}" : "{$sign}{$formattedNum}";

        return [
            'has_prev' => true,
            'diff' => $diff,
            'pct' => $pct,
            'formatted' => $text,
            'formatted_with_pct' => "{$text} (".($isUp ? '+' : '')."{$pct}%)",
            'color' => $isUp ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-emerald-600 dark:text-emerald-400 font-bold',
            'badge' => $isUp ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
            'icon' => $isUp ? '▲' : '▼',
        ];
    };

    $current = $record;
    $totalDiff = $diffFn($right['part_cost'] ?? null, $left['part_cost'] ?? null, true, 2);
    $exclToolingDiff = $diffFn($right['excl_tooling'] ?? null, $left['excl_tooling'] ?? null, true, 2);
    $transDiff = $diffFn($right['trans_cost'] ?? null, $left['trans_cost'] ?? null, true, 2);
    $packDiff = $diffFn($right['pack_cost'] ?? null, $left['pack_cost'] ?? null, true, 2);
@endphp

<div class="space-y-5 font-sans text-gray-950 dark:text-white antialiased p-1">
    <!-- Header Lembar Kerja Komparatif Formal Standar Excel -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800 gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md bg-blue-100 px-2.5 py-0.5 text-xs font-black uppercase tracking-wider text-blue-800 dark:bg-blue-950/70 dark:text-blue-300">
                        Official Comparative Quotation Sheet
                    </span>
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">ATTN: Purchasing Dept.</span>
                </div>
                <h2 class="text-xl font-black tracking-tight text-gray-950 dark:text-white mt-1">PT. FUJI SEAT INDONESIA</h2>
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Lembar Kalkulasi Biaya Part Stamping Komparasi 2 Kuartal Berdampingan (Side-by-Side dengan Kolom Difference)</p>
            </div>
            <div class="text-left md:text-right">
                <div class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950 px-3.5 py-1.5 text-xs font-black shadow-sm">
                    <span>Kuartal Terbuka: {{ $current->year }} - {{ $current->quarter }}</span>
                    @if($rightRecord && $leftRecord && $rightRecord->id !== $leftRecord->id)
                        <span class="text-gray-400 dark:text-gray-500 font-normal">|</span>
                        <span class="text-blue-300 dark:text-blue-600">Komparasi: {{ $leftRecord->quarter }} {{ $leftRecord->year }} vs {{ $rightRecord->quarter }} {{ $rightRecord->year }}</span>
                    @endif
                </div>
                <div class="text-xs text-gray-800 dark:text-gray-200 mt-1.5 font-semibold">
                    Supplier: <strong class="text-gray-950 dark:text-white font-extrabold">{{ $current->supplier_name ?: 'PT. POSMI STEEL INDONESIA' }}</strong>
                </div>
            </div>
        </div>

        <!-- Metadata Grid Part & Kurs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-4 text-xs">
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Number</span>
                <span class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $current->part_number ?: '-' }}</span>
                @if($current->part_no_to_compare)
                    <span class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold block mt-0.5">Comp: {{ $current->part_no_to_compare }}</span>
                @endif
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Name</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 truncate block" title="{{ $current->part_name }}">{{ $current->part_name ?: '-' }}</span>
                @if($current->quotation_date)
                    <span class="text-[10px] text-gray-600 dark:text-gray-400 block mt-0.5">Tgl Quotation: {{ $current->quotation_date->format('d/m/Y') }}</span>
                @endif
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Model & Vol</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $current->model_name ?: 'D26A' }} / {{ number_format($current->monthly_volume ?: 16500, 0, ',', '.') }} pcs/bln</span>
                <span class="text-[10px] text-gray-600 dark:text-gray-400 block mt-0.5">ECI: {{ $current->eci_number ?: '-' }}</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Kurs Acuan (IDR)</span>
                <div class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 flex items-baseline justify-between">
                    <span>Rp {{ number_format($current->exchange_rate ?: 16000, 0, ',', '.') }}</span>
                    @if($right && $left)
                        @php $rateDiff = $diffFn($right['rate'], $left['rate'], true, 0); @endphp
                        @if($rateDiff['has_prev'])
                            <span class="text-[11px] {{ $rateDiff['color'] }}">{{ $rateDiff['formatted'] }}</span>
                        @endif
                    @endif
                </div>
                <span class="text-[10px] text-gray-600 dark:text-gray-400 font-bold block mt-0.5">Drawing: {{ $current->drawing_reference ?: 'Mass Prod. Drwg' }}</span>
            </div>
        </div>

        <!-- Banner Ringkasan Komparasi Biaya (Top KPI) -->
        @if($right && $left && $rightRecord && $leftRecord && $rightRecord->id !== $leftRecord->id)
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-lg bg-gradient-to-r from-gray-50 to-blue-50/60 border border-blue-200 dark:from-gray-800/80 dark:to-blue-950/40 dark:border-blue-800">
                <div class="{{ $activeRecord === 'left' ? 'ring-2 ring-blue-500 rounded-lg p-2 bg-white dark:bg-gray-900 shadow-sm' : '' }}">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 flex items-center justify-between">
                        <span>{{ $leftHeaderLabel }}</span>
                        @if($activeRecord === 'left') <span class="text-[10px] px-1.5 py-0.2 bg-blue-100 text-blue-800 rounded font-black dark:bg-blue-900 dark:text-blue-200">AKTIF</span> @endif
                    </span>
                    <div class="text-base font-black font-mono text-gray-900 dark:text-white mt-0.5">Rp {{ number_format($left['part_cost'], 2, ',', '.') }}</div>
                </div>
                <div class="{{ $activeRecord === 'right' ? 'ring-2 ring-blue-500 rounded-lg p-2 bg-white dark:bg-gray-900 shadow-sm' : '' }}">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-blue-800 dark:text-blue-300 flex items-center justify-between">
                        <span>{{ $rightHeaderLabel }}</span>
                        @if($activeRecord === 'right') <span class="text-[10px] px-1.5 py-0.2 bg-blue-100 text-blue-800 rounded font-black dark:bg-blue-900 dark:text-blue-200">AKTIF</span> @endif
                    </span>
                    <div class="text-base font-black font-mono text-blue-900 dark:text-blue-200 mt-0.5">Rp {{ number_format($right['part_cost'], 2, ',', '.') }}</div>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Selisih Biaya (Difference)</span>
                    <div class="text-base font-black font-mono {{ $totalDiff['color'] }} mt-0.5 flex items-center gap-1.5">
                        <span>{{ $totalDiff['icon'] }}</span>
                        <span>{{ $totalDiff['formatted'] }}</span>
                        <span class="text-xs px-2 py-0.5 rounded {{ $totalDiff['badge'] }} font-sans font-extrabold">{{ ($totalDiff['diff'] > 0 ? '+' : '') . $totalDiff['pct'] }}%</span>
                    </div>
                </div>
            </div>
        @else
            <div class="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200 dark:bg-amber-950/40 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 font-medium">
                <strong>Catatan Komparasi:</strong> Belum ada data kuartal pembanding untuk Part ini di database. Menampilkan rincian murni kuartal {{ $current->quarter }} {{ $current->year }}. Tambahkan kuartal lain untuk melihat selisih kenaikan/penurunan secara otomatis.
            </div>
        @endif
    </div>

    <!-- 1. MATERIAL BREAKDOWN (Side-by-Side dengan Difference) -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-black text-white shadow-sm">1</span>
                Material Breakdown (Plat Baja & Scrap Recovery)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Komparasi Harga Bahan Baku Baja Plat</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Spesifikasi Material Baja</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-center">Parameter Potong & Dimensi</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-100/70 dark:bg-gray-800/80">
                            {{ $leftHeaderLabel }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200">
                            {{ $rightRecord ? $rightHeaderLabel : '-' }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-200 dark:bg-gray-800 font-black">
                            Difference (Selisih)
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white font-medium">
                    <!-- Baris 1: Spesifikasi & Harga Baja per kg -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">
                            {{ $current->steel_spec ?: 'SPH440-OD' }} ({{ $current->double_thickness }} mm)
                            <span class="block text-[11px] text-gray-600 dark:text-gray-400 font-normal">Harga Sheet Baja per Kilogram (IDR)</span>
                        </td>
                        <td class="p-3 text-center font-mono">
                            T: {{ $current->double_thickness }}mm | W: {{ $current->wide }}mm | P: {{ $current->pitch }}mm | Yield: {{ (int)($current->yield_rate ?: 1) }}
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            @if($left)
                                <div>$ {{ number_format($left['sheet_price'], 4) }} /kg</div>
                                <div class="text-[11px] text-gray-600 dark:text-gray-400 font-bold">Rp {{ number_format($left['steel_rate'], 2, ',', '.') }} /kg</div>
                            @else
                                -
                            @endif
                        </td>
                        <td class="p-3 text-right font-mono bg-blue-50/40 dark:bg-blue-950/20 font-bold">
                            @if($right)
                                <div>$ {{ number_format($right['sheet_price'], 4) }} /kg</div>
                                <div class="text-[11px] text-blue-700 dark:text-blue-300 font-bold">Rp {{ number_format($right['steel_rate'], 2, ',', '.') }} /kg</div>
                            @else
                                -
                            @endif
                        </td>
                        @php $steelDiff = $diffFn($right['steel_rate'] ?? null, $left['steel_rate'] ?? null, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $steelDiff['color'] }}">
                            {{ $steelDiff['formatted'] }}
                        </td>
                    </tr>

                    <!-- Baris 2: Berat & Biaya Kotor Material (Gross Material Cost) -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">
                            Berat Kotor Part (Gross Weight)
                            <span class="block text-[11px] text-gray-600 dark:text-gray-400 font-normal">Gross Material Cost = Berat (kg) × Harga Sheet IDR</span>
                        </td>
                        <td class="p-3 text-center font-mono font-bold">
                            {{ number_format((float) $current->berat, 4, ',', '.') }} kg / pcs
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $left ? 'Rp ' . number_format($left['gross_mat'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-blue-50/40 dark:bg-blue-950/20 font-black text-gray-950 dark:text-white">
                            {{ $right ? 'Rp ' . number_format($right['gross_mat'], 2, ',', '.') : '-' }}
                        </td>
                        @php $grossMatDiff = $diffFn($right['gross_mat'] ?? null, $left['gross_mat'] ?? null, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $grossMatDiff['color'] }}">
                            {{ $grossMatDiff['formatted'] }}
                        </td>
                    </tr>

                    <!-- Baris 3: Potongan Scrap Recovery -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold text-amber-900 dark:text-amber-300">
                            Scrap Recovery (Potongan Afval)
                            <span class="block text-[11px] text-gray-600 dark:text-gray-400 font-normal">Nilai pengembalian sisa potongan plat baja</span>
                        </td>
                        <td class="p-3 text-center font-mono text-gray-600 dark:text-gray-400">
                            Berat: {{ number_format((float) $current->scrap_weight, 4, ',', '.') }} kg @ Rp {{ number_format((float) $current->scrap_price_per_kg, 0) }}/kg
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold text-amber-800 dark:text-amber-400">
                            {{ $left ? 'Rp ' . number_format($left['scrap_cost'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-blue-50/40 dark:bg-blue-950/20 font-black text-amber-900 dark:text-amber-300">
                            {{ $right ? 'Rp ' . number_format($right['scrap_cost'], 2, ',', '.') : '-' }}
                        </td>
                        @php $scrapDiff = $diffFn($right['scrap_cost'] ?? null, $left['scrap_cost'] ?? null, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $scrapDiff['color'] }}">
                            {{ $scrapDiff['formatted'] }}
                        </td>
                    </tr>

                    <!-- Baris Total Material -->
                    <tr class="bg-blue-100/90 dark:bg-blue-950/60 font-bold border-t-2 border-blue-400 dark:border-blue-700">
                        <td colspan="2" class="p-3 font-black uppercase text-gray-950 dark:text-white">
                            (1) : Material Total (Gross Material Cost - Scrap Recovery)
                        </td>
                        <td class="p-3 text-right font-mono font-black text-gray-900 dark:text-gray-200">
                            {{ $left ? 'Rp ' . number_format($left['mat_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-blue-950 dark:text-blue-100 text-sm">
                            {{ $right ? 'Rp ' . number_format($right['mat_total'], 2, ',', '.') : '-' }}
                        </td>
                        @php $matTotalDiff = $diffFn($right['mat_total'] ?? null, $left['mat_total'] ?? null, true, 2); @endphp
                        <td class="p-3 text-right font-mono font-black text-sm {{ $matTotalDiff['color'] }}">
                            {{ $matTotalDiff['formatted'] }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. PROCESS BREAKDOWN (Side-by-Side dengan Difference) -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-purple-600 text-xs font-black text-white shadow-sm">2</span>
                Process Breakdown (Stamping & Pressing)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Komparasi Tarif Ongkos Mesin Stamping</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Tahap Proses</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Detail Mesin & Parameter</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-100/70 dark:bg-gray-800/80">
                            {{ $leftHeaderLabel }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-purple-50 dark:bg-purple-950/40 text-purple-900 dark:text-purple-200">
                            {{ $rightRecord ? $rightHeaderLabel : '-' }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-200 dark:bg-gray-800 font-black">
                            Difference (Selisih)
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white font-medium">
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">{{ $current->process_name ?: '1. PROGRESSIVE 250T' }}</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">
                            Man-hour: {{ $current->process_man_hour ?: 9 }} | Charge: {{ $current->process_charge ?: 45 }} | Yield: {{ (int)($current->yield_rate ?: 1) }}
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $left ? 'Rp ' . number_format($left['proc_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-purple-50/40 dark:bg-purple-950/20 font-black">
                            {{ $right ? 'Rp ' . number_format($right['proc_total'], 2, ',', '.') : '-' }}
                        </td>
                        @php $procDiff = $diffFn($right['proc_total'] ?? null, $left['proc_total'] ?? null, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $procDiff['color'] }}">{{ $procDiff['formatted'] }}</td>
                    </tr>

                    <!-- Baris Total Proses -->
                    <tr class="bg-purple-100/90 dark:bg-purple-950/60 font-bold border-t-2 border-purple-400 dark:border-purple-700">
                        <td colspan="2" class="p-3 font-black uppercase text-gray-950 dark:text-white">
                            (2) : Process Total
                        </td>
                        <td class="p-3 text-right font-mono font-black text-gray-900 dark:text-gray-200">
                            {{ $left ? 'Rp ' . number_format($left['proc_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-purple-950 dark:text-purple-100 text-sm">
                            {{ $right ? 'Rp ' . number_format($right['proc_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-sm {{ $procDiff['color'] }}">
                            {{ $procDiff['formatted'] }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. REKAPITULASI BIAYA & OVERHEAD (Side-by-Side dengan Difference) -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-xs font-black text-white shadow-sm">3</span>
                Rekapitulasi Kalkulasi Akhir & Overhead (Cost Summary)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Struktur Harga Lengkap Part Stamping</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Item Ringkasan Biaya</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-100/70 dark:bg-gray-800/80">
                            {{ $leftHeaderLabel }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200">
                            {{ $rightRecord ? $rightHeaderLabel : '-' }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-200 dark:bg-gray-800 font-black">
                            Difference (Selisih)
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white font-medium">
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(1) : Material Total</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $left ? 'Rp ' . number_format($left['mat_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            {{ $right ? 'Rp ' . number_format($right['mat_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $matTotalDiff['color'] }}">{{ $matTotalDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(2) : Process Total</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $left ? 'Rp ' . number_format($left['proc_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            {{ $right ? 'Rp ' . number_format($right['proc_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $procDiff['color'] }}">{{ $procDiff['formatted'] }}</td>
                    </tr>
                    <tr class="bg-gray-100/90 dark:bg-gray-800/90 font-bold border-y border-gray-300 dark:border-gray-700">
                        <td class="p-3 font-black text-gray-950 dark:text-white">(3) : Subtotal Direct Cost (1 + 2)</td>
                        <td class="p-3 text-right font-mono font-black">
                            {{ $left ? 'Rp ' . number_format($left['subtotal'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-gray-950 dark:text-white">
                            {{ $right ? 'Rp ' . number_format($right['subtotal'], 2, ',', '.') : '-' }}
                        </td>
                        @php $subtotalDiff = $diffFn($right['subtotal'] ?? null, $left['subtotal'] ?? null, true, 2); @endphp
                        <td class="p-3 text-right font-mono font-black {{ $subtotalDiff['color'] }}">{{ $subtotalDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">
                            (4) : O/H & Profit supplied portion ({{ $current->overhead_supplied_percent ?: 11 }}% dari Proses)
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $left ? 'Rp ' . number_format($left['oh_supplied'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            {{ $right ? 'Rp ' . number_format($right['oh_supplied'], 2, ',', '.') : '-' }}
                        </td>
                        @php $ohSuppliedDiff = $diffFn($right['oh_supplied'] ?? null, $left['oh_supplied'] ?? null, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $ohSuppliedDiff['color'] }}">{{ $ohSuppliedDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">
                            (5) : O/H & Profit self-procuring portion ({{ $current->overhead_self_percent ?: 4 }}% dari Material)
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $left ? 'Rp ' . number_format($left['oh_self'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            {{ $right ? 'Rp ' . number_format($right['oh_self'], 2, ',', '.') : '-' }}
                        </td>
                        @php $ohSelfDiff = $diffFn($right['oh_self'] ?? null, $left['oh_self'] ?? null, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $ohSelfDiff['color'] }}">{{ $ohSelfDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(6) : Transportation (Rp / pc)</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $left ? 'Rp ' . number_format($left['trans_cost'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            {{ $right ? 'Rp ' . number_format($right['trans_cost'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $transDiff['color'] }}">{{ $transDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(7) : Packing (Rp / pc)</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $left ? 'Rp ' . number_format($left['pack_cost'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            {{ $right ? 'Rp ' . number_format($right['pack_cost'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $packDiff['color'] }}">{{ $packDiff['formatted'] }}</td>
                    </tr>
                    <tr class="bg-amber-100/90 dark:bg-amber-950/60 font-bold border-y-2 border-amber-400 dark:border-amber-700">
                        <td class="p-3 font-black text-amber-950 dark:text-amber-100">
                            (8) : Part Cost (excluding tooling cost) = (3) + (4) + (5) + (6) + (7)
                        </td>
                        <td class="p-3 text-right font-mono font-black text-amber-900 dark:text-amber-200">
                            {{ $left ? 'Rp ' . number_format($left['excl_tooling'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-amber-950 dark:text-amber-100 text-sm">
                            {{ $right ? 'Rp ' . number_format($right['excl_tooling'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-sm {{ $exclToolingDiff['color'] }}">
                            {{ $exclToolingDiff['formatted'] }}
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(9) : Die & Jig depreciation (Tooling)</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $left ? 'Rp ' . number_format($left['tooling'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            {{ $right ? 'Rp ' . number_format($right['tooling'], 2, ',', '.') : '-' }}
                        </td>
                        @php $toolingDiff = $diffFn($right['tooling'] ?? null, $left['tooling'] ?? null, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $toolingDiff['color'] }}">{{ $toolingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Total Termasuk Tooling -->
                    <tr class="bg-gradient-to-r from-blue-700 to-indigo-800 text-white font-black text-sm">
                        <td class="p-4 uppercase tracking-wider text-white">
                            Total Including Tooling Cost = (8) + (9)
                        </td>
                        <td class="p-4 text-right font-mono text-blue-100">
                            {{ $left ? 'Rp ' . number_format($left['part_cost'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-4 text-right font-mono text-white text-base font-black">
                            {{ $right ? 'Rp ' . number_format($right['part_cost'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-4 text-right font-mono text-white text-base font-black">
                            @if($right && $left)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white/20 backdrop-blur-sm text-white font-black">
                                    {{ $totalDiff['icon'] }} {{ $totalDiff['formatted'] }}
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. RINCIAN TOOLING (DIE & JIG BREAKDOWN) JIKA ADA -->
    @if(!empty($current->tooling_details) && is_array($current->tooling_details))
        <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
                <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-cyan-600 text-xs font-black text-white shadow-sm">4</span>
                    Rincian Investasi & Amortisasi Tooling (Die & Jig Breakdown)
                </h3>
                <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Tabel Komponen Cetakan</span>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                    <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase">
                        <tr>
                            <th class="p-2.5">Contents / Item Die</th>
                            <th class="p-2.5 text-right">Total Cost (KRp)</th>
                            <th class="p-2.5 text-right">Monthly Volume (pcs)</th>
                            <th class="p-2.5 text-right">Depreciation / pc (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($current->tooling_details as $tool)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="p-2.5 font-bold">{{ $tool['contents'] ?? 'DIE STAMPING' }}</td>
                                <td class="p-2.5 text-right font-mono">{{ number_format((float) ($tool['total_cost_krp'] ?? 0), 0, ',', '.') }}</td>
                                <td class="p-2.5 text-right font-mono">{{ number_format((float) ($tool['monthly_volume'] ?? 16500), 0, ',', '.') }}</td>
                                <td class="p-2.5 text-right font-mono font-bold text-cyan-700 dark:text-cyan-300">Rp {{ number_format((float) ($tool['depreciation_per_pc'] ?? 0), 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- 5. FOOTER EXCEL: COMMENTS, REMARKS & ALTERATIONS -->
    @if($current->comments || $current->remarks || $current->alteration || $current->etc_contents)
        <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2 border-b border-gray-200 pb-3 dark:border-gray-800 mb-3">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-600 text-xs font-black text-white shadow-sm">5</span>
                Catatan Resmi & Ketentuan Komersial Penawaran (Excel Footer)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                @if($current->alteration)
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 border border-gray-200 dark:border-gray-700">
                        <strong class="text-gray-900 dark:text-white block font-bold uppercase text-[11px] mb-1">ALTERATION / ECI CHANGES:</strong>
                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $current->alteration }}</p>
                    </div>
                @endif
                @if($current->etc_contents)
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 border border-gray-200 dark:border-gray-700">
                        <strong class="text-gray-900 dark:text-white block font-bold uppercase text-[11px] mb-1">ETC. CONTENTS:</strong>
                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $current->etc_contents }}</p>
                    </div>
                @endif
                @if($current->comments)
                    <div class="rounded-lg bg-blue-50/60 dark:bg-blue-950/30 p-3 border border-blue-200 dark:border-blue-800">
                        <strong class="text-blue-900 dark:text-blue-200 block font-bold uppercase text-[11px] mb-1">COMMENTS:</strong>
                        <p class="text-blue-950 dark:text-blue-100 whitespace-pre-line">{{ $current->comments }}</p>
                    </div>
                @endif
                @if($current->remarks)
                    <div class="rounded-lg bg-purple-50/60 dark:bg-purple-950/30 p-3 border border-purple-200 dark:border-purple-800">
                        <strong class="text-purple-900 dark:text-purple-200 block font-bold uppercase text-[11px] mb-1">REMARKS:</strong>
                        <p class="text-purple-950 dark:text-purple-100 whitespace-pre-line">{{ $current->remarks }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- 6. RELASI SUB-ASSEMBLY JIKA DIGUNAKAN SEBAGAI CHILD PART -->
    @php $subAssies = $current->used_in_sub_assemblies; @endphp
    @if(!empty($subAssies))
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-800 dark:bg-indigo-950/40">
            <div class="flex items-center gap-2 text-xs font-bold text-indigo-900 dark:text-indigo-200 mb-2">
                <span>🧩</span> Komponen ini Terpasang Pada Sub-Assembly Berikut:
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                @foreach($subAssies as $sa)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-700 font-semibold text-gray-900 dark:text-white shadow-sm">
                        <span class="font-mono font-black text-indigo-600 dark:text-indigo-400">{{ $sa['part_number'] }}</span>
                        <span class="text-gray-500 dark:text-gray-400">({{ $sa['part_name'] ?? 'Sub-Assembly' }})</span>
                        <span class="text-[10px] bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 px-1.5 py-0.5 rounded font-bold">{{ $sa['model_name'] ?? 'Model' }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <!-- 7. SIGN-OFF / APPROVAL WORKFLOW BOX (Standar Dokumen Formal Excel) -->
    <div class="rounded-xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 block mb-2.5">Workflow Verifikasi & Persetujuan Dokumen (Sign-Off Box)</span>
        <div class="grid grid-cols-4 gap-3 text-center text-xs">
            <div class="rounded-lg border border-gray-300 dark:border-gray-700 p-2.5 bg-gray-50 dark:bg-gray-800">
                <span class="text-[10px] font-bold text-gray-500 uppercase block">Prepared</span>
                <span class="font-extrabold text-gray-950 dark:text-white block mt-3">Deddy</span>
                <span class="text-[10px] text-gray-400 block">Purchasing Staff</span>
            </div>
            <div class="rounded-lg border border-gray-300 dark:border-gray-700 p-2.5 bg-gray-50 dark:bg-gray-800">
                <span class="text-[10px] font-bold text-gray-500 uppercase block">Checked</span>
                <span class="font-extrabold text-gray-950 dark:text-white block mt-3">Doni</span>
                <span class="text-[10px] text-gray-400 block">Purchasing Spv</span>
            </div>
            <div class="rounded-lg border border-gray-300 dark:border-gray-700 p-2.5 bg-gray-50 dark:bg-gray-800">
                <span class="text-[10px] font-bold text-gray-500 uppercase block">Reviewed</span>
                <span class="font-extrabold text-gray-950 dark:text-white block mt-3">Kida</span>
                <span class="text-[10px] text-gray-400 block">Cost Planning Mgr</span>
            </div>
            <div class="rounded-lg border border-gray-300 dark:border-gray-700 p-2.5 bg-gray-50 dark:bg-gray-800">
                <span class="text-[10px] font-bold text-gray-500 uppercase block">Approved</span>
                <span class="font-extrabold text-gray-950 dark:text-white block mt-3">Kambayashi</span>
                <span class="text-[10px] text-gray-400 block">Purchasing Director</span>
            </div>
        </div>
    </div>
</div>
