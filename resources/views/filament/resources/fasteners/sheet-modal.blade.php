@php
    use App\Models\Fastener;

    // Helper untuk mengekstrak seluruh metrik Fastener
    $extractMetrics = function (?Fastener $model): ?array {
        if (! $model) {
            return null;
        }

        $r = (float) ($model->exchange_rate ?? 16000);
        $wUsd = (float) ($model->wire_price_usd_kg ?? 0);
        $wIdr = (float) ($model->wire_price_idr_kg ?? round($wUsd * $r, 2));
        $grossW = (float) ($model->gross_weight_gr ?? 0);
        $nettW = (float) ($model->nett_weight_gr ?? 0);
        $washerW = (float) ($model->washer_weight_gr ?? 0);
        $wCostPc = (float) ($model->wire_price_pc ?? round(($grossW / 1000) * $wIdr, 2));
        $matCtrlPct = (float) ($model->material_control_percent ?? 5);
        $matCtrlCost = (float) ($model->material_control_cost ?? round(($wCostPc * $matCtrlPct) / 100, 2));
        $washerPrice = (float) ($model->washer_price ?? 0);
        $matTot = (float) ($model->material_total ?? round($wCostPc + $matCtrlCost + $washerPrice, 2));

        $heading = (float) ($model->heading_cost ?? 48);
        $rolling = (float) ($model->rolling_cost ?? 76);
        $wash = (float) ($model->washing_cost ?? 0);
        $plate = (float) ($model->plating_cost ?? 0);
        $inspect = (float) ($model->inspection_cost ?? 5);
        $pack = (float) ($model->packing_cost ?? 3);
        $procTot = (float) ($model->process_total ?? round($heading + $rolling + $wash + $plate + $inspect + $pack, 2));

        $sub = (float) ($model->subtotal ?? round($matTot + $procTot, 2));
        $ohSelfPct = $model->overhead_self_percent !== null && $model->overhead_self_percent !== '' ? (float) $model->overhead_self_percent : 14.3;
        $ohSelf = (float) ($model->overhead_self_cost ?? round(($sub * $ohSelfPct) / 100, 2));
        $tooling = (float) ($model->tooling_depreciation ?? 0);
        $partCost = (float) ($model->part_cost ?? round($sub + $ohSelf + $tooling, 2));

        return [
            'record' => $model,
            'year' => $model->year,
            'quarter' => $model->quarter,
            'rate' => $r,
            'wire_price_usd' => $wUsd,
            'wire_price_idr' => $wIdr,
            'gross_weight' => $grossW,
            'nett_weight' => $nettW,
            'washer_weight' => $washerW,
            'wire_price_pc' => $wCostPc,
            'mat_ctrl_pct' => $matCtrlPct,
            'mat_ctrl_cost' => $matCtrlCost,
            'washer_price' => $washerPrice,
            'mat_total' => $matTot,
            'heading_cost' => $heading,
            'rolling_cost' => $rolling,
            'washing_cost' => $wash,
            'washing_cost_kg' => (float) ($model->washing_cost_kg ?: 500),
            'plating_cost' => $plate,
            'plating_cost_kg' => (float) ($model->plating_cost_kg ?: 10000),
            'inspection_cost' => $inspect,
            'packing_cost' => $pack,
            'process_total' => $procTot,
            'subtotal' => $sub,
            'oh_self_pct' => $ohSelfPct,
            'oh_self' => $ohSelf,
            'tooling' => $tooling,
            'part_cost' => $partCost,
        ];
    };

    // Skor kronologis untuk membandingkan urutan waktu: year * 10 + quarter (Q1=1, Q2=2, Q3=3, Q4=4)
    $qNum = fn ($q) => match (strtoupper(trim((string) $q))) {
        'Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4, default => 1
    };
    $scoreFn = fn ($y, $q) => ((int) $y * 10) + $qNum($q);
    $currentScore = $scoreFn($record->year, $record->quarter);

    // Cari seluruh record lain untuk part_number yang sama
    $otherQuarters = Fastener::where('part_number', $record->part_number)
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
        // Kasus 1: Membuka Q2 (ada Q1 sebelumnya) -> Left = Q1 (Lama), Right = Q2 (Baru [Aktif])
        $leftRecord = $earlierQuarter;
        $rightRecord = $record;
        $leftHeaderLabel = "Lama ({$leftRecord->quarter} {$leftRecord->year})";
        $rightHeaderLabel = "Baru ({$rightRecord->quarter} {$rightRecord->year}) [AKTIF]";
        $activeRecord = 'right';
    } elseif ($laterQuarter) {
        // Kasus 2: Membuka Q1 (ada Q2 berikutnya) -> Left = Q1 (Kuartal Berjalan [Aktif]), Right = Q2 (Kuartal Berikutnya)
        $leftRecord = $record;
        $rightRecord = $laterQuarter;
        $leftHeaderLabel = "Kuartal Berjalan ({$leftRecord->quarter} {$leftRecord->year}) [AKTIF]";
        $rightHeaderLabel = "Kuartal Berikutnya ({$rightRecord->quarter} {$rightRecord->year})";
        $activeRecord = 'left';
    } else {
        // Kasus 3: Kuartal tunggal tanpa pembanding
        $leftRecord = $record;
        $rightRecord = null;
        $leftHeaderLabel = "Kuartal Berjalan ({$leftRecord->quarter} {$leftRecord->year}) [AKTIF]";
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
            'formatted_with_pct' => "{$text} (" . ($isUp ? '+' : '') . "{$pct}%)",
            'color' => $isUp ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-emerald-600 dark:text-emerald-400 font-bold',
            'badge' => $isUp ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
            'icon' => $isUp ? '▲' : '▼',
        ];
    };

    $totalDiff = $diffFn($right ? $right['part_cost'] : null, $left ? $left['part_cost'] : null, true, 2);
@endphp

<div class="space-y-5 font-sans text-gray-950 dark:text-white antialiased p-1">
    <!-- Header Lembar Kerja Komparatif Formal Standar Excel -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800 gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-black uppercase tracking-wider text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300">
                        Official Comparative Quotation Sheet
                    </span>
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">ATTN: Purchasing Dept.</span>
                </div>
                <h2 class="text-xl font-black tracking-tight text-gray-950 dark:text-white mt-1">PT. FUJI SEAT INDONESIA</h2>
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Lembar Kalkulasi Biaya Komparasi 2 Kuartal Berdampingan (Side-by-Side dengan Kolom Difference)</p>
            </div>
            <div class="text-left md:text-right">
                <div class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950 px-3.5 py-1.5 text-xs font-black shadow-sm">
                    <span>{{ $left['quarter'] }} {{ $left['year'] }}</span>
                    @if($right)
                        <span class="text-gray-400 dark:text-gray-500 font-normal">vs</span>
                        <span class="text-emerald-300 dark:text-emerald-700">{{ $right['quarter'] }} {{ $right['year'] }}</span>
                    @endif
                </div>
                <div class="text-xs text-gray-800 dark:text-gray-200 mt-1.5 font-semibold">
                    Supplier: <strong class="text-gray-950 dark:text-white font-extrabold">{{ $record->supplier_name ?: 'PT. NITTO ALAM INDONESIA' }}</strong>
                </div>
            </div>
        </div>

        <!-- Metadata Grid Part & Kurs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-4 text-xs">
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Number</span>
                <span class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $record->part_number ?: '-' }}</span>
                @if($record->old_part_number)
                    <span class="text-[10px] text-gray-600 dark:text-gray-400 font-mono font-bold block mt-0.5">Old Part No: {{ $record->old_part_number }}</span>
                @endif
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Name</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 truncate block" title="{{ $record->part_name }}">{{ $record->part_name ?: '-' }}</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Model & Vol</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $record->model_name ?: 'D26A' }} / {{ number_format($record->monthly_volume ?: 20000, 0, ',', '.') }} pcs/bln</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Fluktuasi Kurs Acuan</span>
                <div class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 flex items-baseline justify-between">
                    <span>Rp {{ number_format($record->exchange_rate ?? 16000, 0, ',', '.') }}</span>
                    @if($right && $left)
                        @php $rateDiff = $diffFn($right['rate'], $left['rate'], true, 0); @endphp
                        <span class="text-[11px] {{ $rateDiff['color'] }}">{{ $rateDiff['formatted'] }}</span>
                    @endif
                </div>
                @if($record->exchange_rate_fluctuation && (float)$record->exchange_rate_fluctuation != 1.0)
                    <span class="text-[10px] text-gray-600 dark:text-gray-400 font-bold block mt-0.5">Exchg factor: {{ number_format((float) $record->exchange_rate_fluctuation, 6) }}</span>
                @endif
            </div>
        </div>

        <!-- Banner Ringkasan Komparasi Biaya (Top KPI) -->
        @if($right)
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-lg bg-gradient-to-r from-gray-50 to-emerald-50/60 border border-emerald-200 dark:from-gray-800/80 dark:to-emerald-950/40 dark:border-emerald-800">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400">{{ $leftHeaderLabel }}</span>
                    <div class="text-base font-black font-mono text-gray-900 dark:text-white mt-0.5">Rp {{ number_format($left['part_cost'], 2, ',', '.') }}</div>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">{{ $rightHeaderLabel }}</span>
                    <div class="text-base font-black font-mono text-emerald-900 dark:text-emerald-200 mt-0.5">Rp {{ number_format($right['part_cost'], 2, ',', '.') }}</div>
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
                <strong>Catatan Komparasi:</strong> Belum ada data kuartal pembanding untuk Part ini di database. Menampilkan rincian murni kuartal {{ $record->quarter }} {{ $record->year }}. Tambahkan kuartal lain untuk melihat selisih kenaikan/penurunan secara otomatis.
            </div>
        @endif
    </div>

    <!-- 1. MATERIAL BREAKDOWN (Side-by-Side dengan Difference) -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-black text-white shadow-sm">1</span>
                Material Breakdown (Wire, Washer & Material Control)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Komparasi Harga Bahan Baku</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Komponen / Spesifikasi</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-center">Parameter / Bobot</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right {{ $activeRecord === 'left' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200' : 'bg-gray-100/70 dark:bg-gray-800/80' }}">
                            {{ $leftHeaderLabel }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right {{ $activeRecord === 'right' ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200' : 'bg-gray-100/70 dark:bg-gray-800/80' }}">
                            {{ $rightHeaderLabel }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-200 dark:bg-gray-800 font-black">
                            Difference (Selisih)
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white font-medium">
                    <!-- Baris 1: Wire Base Price -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">
                            {{ $record->wire_spec ?: 'WIRE OF RIVET [SWCH-45K]' }}
                            <span class="block text-[11px] text-gray-600 dark:text-gray-400 font-normal">Harga Kawat Dasar per Kilogram</span>
                        </td>
                        <td class="p-3 text-center font-mono">
                            Gross: {{ number_format($left['gross_weight'], 2, ',', '.') }} gr | Nett: {{ number_format($left['nett_weight'], 2, ',', '.') }} gr
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            <div>$ {{ number_format($left['wire_price_usd'], 4) }} /kg</div>
                            <div class="text-[11px] text-gray-600 dark:text-gray-400 font-bold">Rp {{ number_format($left['wire_price_idr'], 2, ',', '.') }}</div>
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-blue-50/40 dark:bg-blue-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            @if($right)
                                <div>$ {{ number_format($right['wire_price_usd'], 4) }} /kg</div>
                                <div class="text-[11px] text-blue-700 dark:text-blue-300 font-bold">Rp {{ number_format($right['wire_price_idr'], 2, ',', '.') }}</div>
                            @else
                                -
                            @endif
                        </td>
                        @php $wireDiff = $diffFn($right ? $right['wire_price_idr'] : null, $left['wire_price_idr'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $wireDiff['color'] }}">
                            {{ $wireDiff['formatted'] }}
                        </td>
                    </tr>

                    <!-- Baris 2: Wire Cost per piece -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">
                            d. Wire Price / pc (b × c)
                            <span class="block text-[11px] text-gray-600 dark:text-gray-400 font-normal">(Gross Weight / 1000) × Wire IDR/kg</span>
                        </td>
                        <td class="p-3 text-center font-mono text-gray-600 dark:text-gray-400">
                            {{ number_format($left['gross_weight'], 2, ',', '.') }} gr / 1000
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            Rp {{ number_format($left['wire_price_pc'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-blue-50/40 dark:bg-blue-950/20 font-black text-gray-950 dark:text-white' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            {{ $right ? 'Rp ' . number_format($right['wire_price_pc'], 2, ',', '.') : '-' }}
                        </td>
                        @php $wirePcDiff = $diffFn($right ? $right['wire_price_pc'] : null, $left['wire_price_pc'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $wirePcDiff['color'] }}">
                            {{ $wirePcDiff['formatted'] }}
                        </td>
                    </tr>

                    <!-- Baris 3: Material Control -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">
                            e. Material Control ({{ $left['mat_ctrl_pct'] }}%)
                            <span class="block text-[11px] text-gray-600 dark:text-gray-400 font-normal">{{ $left['mat_ctrl_pct'] }}% dari Biaya Kawat per piece</span>
                        </td>
                        <td class="p-3 text-center font-mono text-gray-600 dark:text-gray-400">
                            {{ $left['mat_ctrl_pct'] }}%
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            Rp {{ number_format($left['mat_ctrl_cost'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-blue-50/40 dark:bg-blue-950/20 font-black text-gray-950 dark:text-white' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            {{ $right ? 'Rp ' . number_format($right['mat_ctrl_cost'], 2, ',', '.') : '-' }}
                        </td>
                        @php $matCtrlDiff = $diffFn($right ? $right['mat_ctrl_cost'] : null, $left['mat_ctrl_cost'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $matCtrlDiff['color'] }}">
                            {{ $matCtrlDiff['formatted'] }}
                        </td>
                    </tr>

                    <!-- Baris 4: Washer Component -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">
                            Washer Component (Ring)
                            <span class="block text-[11px] text-gray-600 dark:text-gray-400 font-normal">Komponen ring washer pengunci</span>
                        </td>
                        <td class="p-3 text-center font-mono text-gray-600 dark:text-gray-400">
                            Berat: {{ number_format($left['washer_weight'], 2, ',', '.') }} gr
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            Rp {{ number_format($left['washer_price'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-blue-50/40 dark:bg-blue-950/20 font-black text-gray-950 dark:text-white' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            {{ $right ? 'Rp ' . number_format($right['washer_price'], 2, ',', '.') : '-' }}
                        </td>
                        @php $washerDiff = $diffFn($right ? $right['washer_price'] : null, $left['washer_price'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $washerDiff['color'] }}">
                            {{ $washerDiff['formatted'] }}
                        </td>
                    </tr>

                    <!-- Baris Total Material -->
                    <tr class="bg-blue-100/90 dark:bg-blue-950/60 font-bold border-t-2 border-blue-400 dark:border-blue-700">
                        <td colspan="2" class="p-3 font-black uppercase text-gray-950 dark:text-white">
                            (1) : Material Total (Wire + Control + Washer)
                        </td>
                        <td class="p-3 text-right font-mono font-black text-gray-900 dark:text-gray-200">
                            Rp {{ number_format($left['mat_total'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-blue-950 dark:text-blue-100 text-sm">
                            {{ $right ? 'Rp ' . number_format($right['mat_total'], 2, ',', '.') : '-' }}
                        </td>
                        @php $matTotalDiff = $diffFn($right ? $right['mat_total'] : null, $left['mat_total'], true, 2); @endphp
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
                Process Breakdown (Heading, Rolling, Washing, Plating, Inspection, Packing)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Komparasi Tarif & Ongkos Manufaktur</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Tahap Proses</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Metode / Rumus Acuan</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right {{ $activeRecord === 'left' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200' : 'bg-gray-100/70 dark:bg-gray-800/80' }}">
                            {{ $leftHeaderLabel }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right {{ $activeRecord === 'right' ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-900 dark:text-purple-200' : 'bg-gray-100/70 dark:bg-gray-800/80' }}">
                            {{ $rightHeaderLabel }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-200 dark:bg-gray-800 font-black">
                            Difference (Selisih)
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white font-medium">
                    <!-- Heading -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">a. Heading</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">Tarif pembentukan kepala baut (Cold Heading)</td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            Rp {{ number_format($left['heading_cost'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-purple-50/40 dark:bg-purple-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            {{ $right ? 'Rp ' . number_format($right['heading_cost'], 2, ',', '.') : '-' }}
                        </td>
                        @php $headingDiff = $diffFn($right ? $right['heading_cost'] : null, $left['heading_cost'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $headingDiff['color'] }}">{{ $headingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Rolling -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">b. Rolling</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">Tarif roll drat pembentukan ulir baut</td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            Rp {{ number_format($left['rolling_cost'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-purple-50/40 dark:bg-purple-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            {{ $right ? 'Rp ' . number_format($right['rolling_cost'], 2, ',', '.') : '-' }}
                        </td>
                        @php $rollingDiff = $diffFn($right ? $right['rolling_cost'] : null, $left['rolling_cost'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $rollingDiff['color'] }}">{{ $rollingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Washing -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">c. Washing</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">({{ number_format($left['nett_weight'], 1) }}g + {{ number_format($left['washer_weight'], 1) }}g) / 1000 × Rp {{ number_format($left['washing_cost_kg']) }}/kg</td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            Rp {{ number_format($left['washing_cost'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-purple-50/40 dark:bg-purple-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            {{ $right ? 'Rp ' . number_format($right['washing_cost'], 2, ',', '.') : '-' }}
                        </td>
                        @php $washingDiff = $diffFn($right ? $right['washing_cost'] : null, $left['washing_cost'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $washingDiff['color'] }}">{{ $washingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Plating -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">d. Plating (MFZn-C)</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">({{ number_format($left['nett_weight'], 1) }}g + {{ number_format($left['washer_weight'], 1) }}g) / 1000 × Rp {{ number_format($left['plating_cost_kg']) }}/kg</td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            Rp {{ number_format($left['plating_cost'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-purple-50/40 dark:bg-purple-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            {{ $right ? 'Rp ' . number_format($right['plating_cost'], 2, ',', '.') : '-' }}
                        </td>
                        @php $platingDiff = $diffFn($right ? $right['plating_cost'] : null, $left['plating_cost'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $platingDiff['color'] }}">{{ $platingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Inspection -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">e. Final Inspection</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">Pemeriksaan kualitas & sortasi</td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            Rp {{ number_format($left['inspection_cost'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-purple-50/40 dark:bg-purple-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            {{ $right ? 'Rp ' . number_format($right['inspection_cost'], 2, ',', '.') : '-' }}
                        </td>
                        @php $inspectDiff = $diffFn($right ? $right['inspection_cost'] : null, $left['inspection_cost'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $inspectDiff['color'] }}">{{ $inspectDiff['formatted'] }}</td>
                    </tr>

                    <!-- Packing -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">f. Packing</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">Pengemasan & pelabelan box</td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/40 dark:bg-amber-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            Rp {{ number_format($left['packing_cost'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-purple-50/40 dark:bg-purple-950/20 font-black' : 'bg-gray-50/50 dark:bg-gray-800/40 font-bold' }}">
                            {{ $right ? 'Rp ' . number_format($right['packing_cost'], 2, ',', '.') : '-' }}
                        </td>
                        @php $packingDiff = $diffFn($right ? $right['packing_cost'] : null, $left['packing_cost'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $packingDiff['color'] }}">{{ $packingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Baris Total Proses -->
                    <tr class="bg-purple-100/90 dark:bg-purple-950/60 font-bold border-t-2 border-purple-400 dark:border-purple-700">
                        <td colspan="2" class="p-3 font-black uppercase text-gray-950 dark:text-white">
                            (2) : Process Total (Heading + Rolling + Wash + Plate + Inspect + Pack)
                        </td>
                        <td class="p-3 text-right font-mono font-black text-gray-900 dark:text-gray-200">
                            Rp {{ number_format($left['process_total'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-purple-950 dark:text-purple-100 text-sm">
                            {{ $right ? 'Rp ' . number_format($right['process_total'], 2, ',', '.') : '-' }}
                        </td>
                        @php $procTotalDiff = $diffFn($right ? $right['process_total'] : null, $left['process_total'], true, 2); @endphp
                        <td class="p-3 text-right font-mono font-black text-sm {{ $procTotalDiff['color'] }}">
                            {{ $procTotalDiff['formatted'] }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. REKAPITULASI BIAYA & SUB-TOTAL (Side-by-Side dengan Difference) -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-xs font-black text-white shadow-sm">3</span>
                Rekapitulasi Kalkulasi Akhir & Overhead (Cost Summary)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Struktur Harga Lengkap Sesuai Format Resmi</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Item Ringkasan Biaya</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right {{ $activeRecord === 'left' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200' : 'bg-gray-100/70 dark:bg-gray-800/80' }}">
                            {{ $leftHeaderLabel }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right {{ $activeRecord === 'right' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200' : 'bg-gray-100/70 dark:bg-gray-800/80' }}">
                            {{ $rightHeaderLabel }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-200 dark:bg-gray-800 font-black">
                            Difference (Selisih)
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white font-medium">
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(1) : Material Total</td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/30 dark:bg-amber-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            Rp {{ number_format($left['mat_total'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-emerald-50/30 dark:bg-emerald-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            {{ $right ? 'Rp ' . number_format($right['mat_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $matTotalDiff['color'] }}">{{ $matTotalDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(2) : Process Total</td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/30 dark:bg-amber-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            Rp {{ number_format($left['process_total'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-emerald-50/30 dark:bg-emerald-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            {{ $right ? 'Rp ' . number_format($right['process_total'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $procTotalDiff['color'] }}">{{ $procTotalDiff['formatted'] }}</td>
                    </tr>
                    <tr class="bg-gray-100/90 dark:bg-gray-800/90 font-bold border-y border-gray-300 dark:border-gray-700">
                        <td class="p-3 font-black text-gray-950 dark:text-white">(3) : Subtotal Direct Cost (1 + 2)</td>
                        <td class="p-3 text-right font-mono font-black text-gray-950 dark:text-white">
                            Rp {{ number_format($left['subtotal'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-gray-950 dark:text-white">
                            {{ $right ? 'Rp ' . number_format($right['subtotal'], 2, ',', '.') : '-' }}
                        </td>
                        @php $subtotalDiff = $diffFn($right ? $right['subtotal'] : null, $left['subtotal'], true, 2); @endphp
                        <td class="p-3 text-right font-mono font-black {{ $subtotalDiff['color'] }}">{{ $subtotalDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">
                            (5) : O/H & Profit self-procuring portion ({{ $left['oh_self_pct'] }}%)
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/30 dark:bg-amber-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            Rp {{ number_format($left['oh_self'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-emerald-50/30 dark:bg-emerald-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            {{ $right ? 'Rp ' . number_format($right['oh_self'], 2, ',', '.') : '-' }}
                        </td>
                        @php $ohSelfDiff = $diffFn($right ? $right['oh_self'] : null, $left['oh_self'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $ohSelfDiff['color'] }}">{{ $ohSelfDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(9) : Die & Jig depreciation (Tooling)</td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'left' ? 'bg-amber-50/30 dark:bg-amber-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            Rp {{ number_format($left['tooling'], 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $activeRecord === 'right' ? 'bg-emerald-50/30 dark:bg-emerald-950/20 font-bold' : 'bg-gray-50/50 dark:bg-gray-800/40' }}">
                            {{ $right ? 'Rp ' . number_format($right['tooling'], 2, ',', '.') : '-' }}
                        </td>
                        @php $toolingDiff = $diffFn($right ? $right['tooling'] : null, $left['tooling'], true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $toolingDiff['color'] }}">{{ $toolingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Total Termasuk Tooling -->
                    <tr class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white font-black text-sm">
                        <td class="p-4 uppercase tracking-wider text-white">
                            Total Including Tooling Cost = (8) + (9)
                        </td>
                        <td class="p-4 text-right font-mono text-emerald-100 font-black">
                            Rp {{ number_format($left['part_cost'], 2, ',', '.') }}
                        </td>
                        <td class="p-4 text-right font-mono text-white text-base font-black">
                            {{ $right ? 'Rp ' . number_format($right['part_cost'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-4 text-right font-mono text-white text-base font-black">
                            @if($right)
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

    <!-- 4. SIGN-OFF / APPROVAL WORKFLOW BOX (Standar Dokumen Formal Excel) -->
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
