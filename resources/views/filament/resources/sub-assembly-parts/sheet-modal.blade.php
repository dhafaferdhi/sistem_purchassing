@php
    use App\Models\SubAssemblyPart;

    // Data kuartal berjalan
    $current = $record;
    $rate = (float) ($current->exchange_rate ?? 16000);
    $matTotal = (float) ($current->material_total ?? 0);
    $procTotal = (float) ($current->process_total ?? 0);
    $subtotal = (float) ($current->subtotal ?? ($matTotal + $procTotal));
    $ohCost = (float) ($current->overhead_supplied_cost ?? 0);
    $profitCost = (float) ($current->profit_cost ?? 0);
    $packing = (float) ($current->packing_cost ?? 0);
    $tooling = (float) ($current->tooling_depreciation ?? 0);
    $partCost = (float) ($current->part_cost ?? ($subtotal + $ohCost + $profitCost + $packing + $tooling));

    $matBreakdown = is_array($current->material_breakdown) 
        ? $current->material_breakdown 
        : (json_decode($current->material_breakdown ?? '[]', true) ?: []);

    $procBreakdown = is_array($current->process_breakdown) 
        ? $current->process_breakdown 
        : (json_decode($current->process_breakdown ?? '[]', true) ?: []);

    // Cari seluruh kuartal lain untuk Part Number yang sama
    $otherQuarters = SubAssemblyPart::where('part_number', $current->part_number)
        ->where('id', '!=', $current->id)
        ->orderByDesc('year')
        ->orderByDesc('quarter')
        ->get();

    // Default kuartal acuan pembanding: cari kuartal persis sebelumnya
    $prevInfo = SubAssemblyPart::getPreviousQuarterInfo($current->quarter, $current->year);
    $prev = $otherQuarters->first(fn ($q) => (int)$q->year === (int)$prevInfo['prev_year'] && $q->quarter === $prevInfo['prev_quarter'])
        ?? $otherQuarters->first();

    // Helper formatting selisih (Difference)
    $diffFn = function (float $currVal, ?float $prevVal, bool $isPrice = true, int $decimals = 2): array {
        if ($prevVal === null) {
            return [
                'has_prev' => false,
                'diff' => 0.0,
                'formatted' => '-',
                'color' => 'text-gray-400',
                'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                'icon' => '—'
            ];
        }

        $diff = round($currVal - $prevVal, 4);
        $pct = $prevVal != 0.0 ? round(($diff / abs($prevVal)) * 100, 2) : 0.0;

        if (abs($diff) < 0.0001) {
            return [
                'has_prev' => true,
                'diff' => 0.0,
                'formatted' => $isPrice ? 'Rp 0' : '0',
                'color' => 'text-gray-500 dark:text-gray-400 font-semibold',
                'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                'icon' => '—'
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
            'icon' => $isUp ? '▲' : '▼'
        ];
    };

    // Precalculate data kuartal pembanding jika ada
    $prevMatBreakdown = [];
    $prevProcBreakdown = [];
    if ($prev) {
        $prevMatBreakdown = is_array($prev->material_breakdown) 
            ? $prev->material_breakdown 
            : (json_decode($prev->material_breakdown ?? '[]', true) ?: []);

        $prevProcBreakdown = is_array($prev->process_breakdown) 
            ? $prev->process_breakdown 
            : (json_decode($prev->process_breakdown ?? '[]', true) ?: []);
    }

    $prevRate = $prev ? (float) ($prev->exchange_rate ?? 0) : null;
    $prevMatTotal = $prev ? (float) ($prev->material_total ?? 0) : null;
    $prevProcTotal = $prev ? (float) ($prev->process_total ?? 0) : null;
    $prevSubtotal = $prev ? (float) ($prev->subtotal ?? 0) : null;
    $prevOhCost = $prev ? (float) ($prev->overhead_supplied_cost ?? 0) : null;
    $prevProfitCost = $prev ? (float) ($prev->profit_cost ?? 0) : null;
    $prevPacking = $prev ? (float) ($prev->packing_cost ?? 0) : null;
    $prevTooling = $prev ? (float) ($prev->tooling_depreciation ?? 0) : null;
    $prevPartCost = $prev ? (float) ($prev->part_cost ?? 0) : null;

    $totalDiff = $diffFn($partCost, $prevPartCost, true, 2);
@endphp

<div class="space-y-5 font-sans text-gray-950 dark:text-white antialiased p-1">
    <!-- Header Lembar Kerja Komparatif Formal Standar Excel -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800 gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md bg-indigo-100 px-2.5 py-0.5 text-xs font-black uppercase tracking-wider text-indigo-800 dark:bg-indigo-950/70 dark:text-indigo-300">
                        Official Comparative Quotation Sheet
                    </span>
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">ATTN: Purchasing Dept.</span>
                </div>
                <h2 class="text-xl font-black tracking-tight text-gray-950 dark:text-white mt-1">PT. FUJI SEAT INDONESIA</h2>
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Lembar Kalkulasi Biaya Sub-Assembly Part Komparasi 2 Kuartal Berdampingan (Side-by-Side dengan Kolom Difference)</p>
            </div>
            <div class="text-left md:text-right">
                <div class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950 px-3.5 py-1.5 text-xs font-black shadow-sm">
                    <span>Kuartal Berjalan: {{ $current->year }} - {{ $current->quarter }}</span>
                    @if($prev)
                        <span class="text-gray-400 dark:text-gray-500 font-normal">vs</span>
                        <span class="text-indigo-300 dark:text-indigo-600">Acuan: {{ $prev->year }} - {{ $prev->quarter }}</span>
                    @endif
                </div>
                <div class="text-xs text-gray-800 dark:text-gray-200 mt-1.5 font-semibold">
                    Supplier: <strong class="text-gray-950 dark:text-white font-extrabold">{{ $current->supplier_name ?: 'PT. TRI CENTRUM FORTUNA' }}</strong>
                </div>
            </div>
        </div>

        <!-- Metadata Grid Part & Kurs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-4 text-xs">
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Sub-Assy Part No</span>
                <span class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $current->part_number ?: '-' }}</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Name</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 truncate block" title="{{ $current->part_name }}">{{ $current->part_name ?: '-' }}</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Model & Vol</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $current->model_name ?: 'D26A' }} / {{ number_format($current->monthly_volume ?: 16500, 0, ',', '.') }} pcs/bln</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Kurs Acuan (IDR)</span>
                <div class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 flex items-baseline justify-between">
                    <span>Rp {{ number_format($rate, 0, ',', '.') }}</span>
                    @if($prev && $prevRate)
                        @php $rateDiff = $diffFn($rate, $prevRate, true, 0); @endphp
                        <span class="text-[11px] {{ $rateDiff['color'] }}">{{ $rateDiff['formatted'] }}</span>
                    @endif
                </div>
                <span class="text-[10px] text-gray-600 dark:text-gray-400 font-bold block mt-0.5">Drawing / ECI: {{ $current->drawing_reference ?: 'Mass Prod. Drwg' }} ({{ $current->eci_number ?: 'F1225' }})</span>
            </div>
        </div>

        <!-- Banner Ringkasan Komparasi Biaya (Top KPI) -->
        @if($prev)
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-lg bg-gradient-to-r from-gray-50 to-indigo-50/60 border border-indigo-200 dark:from-gray-800/80 dark:to-indigo-950/40 dark:border-indigo-800">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400">Harga Acuan ({{ $prev->quarter }} {{ $prev->year }})</span>
                    <div class="text-base font-black font-mono text-gray-900 dark:text-white mt-0.5">Rp {{ number_format($prevPartCost, 2, ',', '.') }}</div>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-800 dark:text-indigo-300">Harga Berjalan ({{ $current->quarter }} {{ $current->year }})</span>
                    <div class="text-base font-black font-mono text-indigo-900 dark:text-indigo-200 mt-0.5">Rp {{ number_format($partCost, 2, ',', '.') }}</div>
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
                Material Breakdown (Komponen Anak / BOM Child Parts)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Komparasi Harga Part Anak</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Part Number / Komponen</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-center">Qty</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-100/70 dark:bg-gray-800/80">
                            {{ $prev ? "Lama ({$prev->quarter} {$prev->year})" : "Acuan Lama" }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200">
                            {{ "Baru ({$current->quarter} {$current->year})" }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-200 dark:bg-gray-800 font-black">
                            Difference (Selisih)
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white font-medium">
                    @forelse($matBreakdown as $index => $item)
                        @php
                            $pNumber = $item['part_number'] ?? ($item['name'] ?? '');
                            $pName = $item['part_name'] ?? ($item['name'] ?? '-');
                            $qty = (float)($item['qty'] ?? ($item['quantity'] ?? 1));
                            $currPrice = isset($item['price']) && (float)$item['price'] > 0 ? (float)$item['price'] : round($qty * (float)($item['unit_price'] ?? 0), 2);

                            // Cari item yang bersesuaian di kuartal pembanding
                            $prevItem = null;
                            if (!empty($prevMatBreakdown)) {
                                foreach ($prevMatBreakdown as $pi) {
                                    $piNum = $pi['part_number'] ?? ($pi['name'] ?? '');
                                    if ($piNum && $pNumber && (str_contains($pNumber, $piNum) || str_contains($piNum, $pNumber))) {
                                        $prevItem = $pi;
                                        break;
                                    }
                                }
                                if (!$prevItem && isset($prevMatBreakdown[$index])) {
                                    $prevItem = $prevMatBreakdown[$index];
                                }
                            }

                            $prevPrice = $prevItem ? (isset($prevItem['price']) && (float)$prevItem['price'] > 0 ? (float)$prevItem['price'] : round((float)($prevItem['qty'] ?? 1) * (float)($prevItem['unit_price'] ?? 0), 2)) : null;
                            $itemDiff = $diffFn($currPrice, $prevPrice, true, 2);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="p-3 font-bold text-gray-950 dark:text-white">
                                {{ $pNumber ? "{$pNumber} — {$pName}" : $pName }}
                                @if(!empty($item['valuation']))
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 ml-1.5 font-mono">{{ $item['valuation'] }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-center font-mono font-bold">{{ $qty }} pcs</td>
                            <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                                {{ $prev && $prevPrice !== null ? 'Rp ' . number_format($prevPrice, 2, ',', '.') : '-' }}
                            </td>
                            <td class="p-3 text-right font-mono bg-blue-50/40 dark:bg-blue-950/20 font-black">
                                Rp {{ number_format($currPrice, 2, ',', '.') }}
                            </td>
                            <td class="p-3 text-right font-mono {{ $itemDiff['color'] }}">
                                {{ $itemDiff['formatted'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-3 text-center text-gray-500 italic">Tidak ada rincian child parts.</td>
                        </tr>
                    @endforelse

                    <!-- Baris Total Material -->
                    <tr class="bg-blue-100/90 dark:bg-blue-950/60 font-bold border-t-2 border-blue-400 dark:border-blue-700">
                        <td colspan="2" class="p-3 font-black uppercase text-gray-950 dark:text-white">
                            (1) : Material Total (Total Komponen Anak)
                        </td>
                        <td class="p-3 text-right font-mono font-black text-gray-900 dark:text-gray-200">
                            {{ $prev ? 'Rp ' . number_format((float) $prevMatTotal, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-blue-950 dark:text-blue-100 text-sm">
                            Rp {{ number_format($matTotal, 2, ',', '.') }}
                        </td>
                        @php $matTotalDiff = $diffFn($matTotal, $prevMatTotal, true, 2); @endphp
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
                Process Breakdown (Welding, Painting CED, Assembly)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Komparasi Tarif Ongkos Manufaktur Perakitan</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Tahap Proses</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-center">Man-hour & Charge</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-100/70 dark:bg-gray-800/80">
                            {{ $prev ? "Lama ({$prev->quarter} {$prev->year})" : "Acuan Lama" }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-purple-50 dark:bg-purple-950/40 text-purple-900 dark:text-purple-200">
                            {{ "Baru ({$current->quarter} {$current->year})" }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-200 dark:bg-gray-800 font-black">
                            Difference (Selisih)
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white font-medium">
                    @forelse($procBreakdown as $index => $item)
                        @php
                            $procName = $item['name'] ?? ($item['process_name'] ?? 'Proses');
                            $currPrice = isset($item['price']) && (float)$item['price'] > 0 ? (float)$item['price'] : round((float)($item['man_hour'] ?? 0) * (float)($item['charge'] ?? 0), 2);
                            
                            $prevItem = null;
                            if (!empty($prevProcBreakdown)) {
                                foreach ($prevProcBreakdown as $pi) {
                                    $piName = $pi['name'] ?? ($pi['process_name'] ?? '');
                                    if ($piName && $procName && (str_contains($procName, $piName) || str_contains($piName, $procName))) {
                                        $prevItem = $pi;
                                        break;
                                    }
                                }
                                if (!$prevItem && isset($prevProcBreakdown[$index])) {
                                    $prevItem = $prevProcBreakdown[$index];
                                }
                            }

                            $prevPrice = $prevItem ? (isset($prevItem['price']) && (float)$prevItem['price'] > 0 ? (float)$prevItem['price'] : round((float)($prevItem['man_hour'] ?? 0) * (float)($prevItem['charge'] ?? 0), 2)) : null;
                            $procItemDiff = $diffFn($currPrice, $prevPrice, true, 2);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="p-3 font-bold text-gray-950 dark:text-white">{{ $procName }}</td>
                            <td class="p-3 text-center font-mono text-gray-600 dark:text-gray-400">
                                @if(isset($item['man_hour']) && (float)$item['man_hour'] > 0)
                                    {{ $item['man_hour'] }} det × Rp {{ $item['charge'] ?? 27 }}
                                @else
                                    Fixed Tarif
                                @endif
                            </td>
                            <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                                {{ $prev && $prevPrice !== null ? 'Rp ' . number_format($prevPrice, 2, ',', '.') : '-' }}
                            </td>
                            <td class="p-3 text-right font-mono bg-purple-50/40 dark:bg-purple-950/20 font-black">
                                Rp {{ number_format($currPrice, 2, ',', '.') }}
                            </td>
                            <td class="p-3 text-right font-mono {{ $procItemDiff['color'] }}">
                                {{ $procItemDiff['formatted'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-3 text-center text-gray-500 italic">Tidak ada rincian proses.</td>
                        </tr>
                    @endforelse

                    <!-- Baris Total Proses -->
                    <tr class="bg-purple-100/90 dark:bg-purple-950/60 font-bold border-t-2 border-purple-400 dark:border-purple-700">
                        <td colspan="2" class="p-3 font-black uppercase text-gray-950 dark:text-white">
                            (2) : Process Total (Welding + CED Painting)
                        </td>
                        <td class="p-3 text-right font-mono font-black text-gray-900 dark:text-gray-200">
                            {{ $prev ? 'Rp ' . number_format((float) $prevProcTotal, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-purple-950 dark:text-purple-100 text-sm">
                            Rp {{ number_format($procTotal, 2, ',', '.') }}
                        </td>
                        @php $procTotalDiff = $diffFn($procTotal, $prevProcTotal, true, 2); @endphp
                        <td class="p-3 text-right font-mono font-black text-sm {{ $procTotalDiff['color'] }}">
                            {{ $procTotalDiff['formatted'] }}
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
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Struktur Harga Lengkap Sub-Assembly Part</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Item Ringkasan Biaya</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-gray-100/70 dark:bg-gray-800/80">
                            {{ $prev ? "Lama ({$prev->quarter} {$prev->year})" : "Acuan Lama" }}
                        </th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200">
                            {{ "Baru ({$current->quarter} {$current->year})" }}
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
                            {{ $prev ? 'Rp ' . number_format((float) $prevMatTotal, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            Rp {{ number_format($matTotal, 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $matTotalDiff['color'] }}">{{ $matTotalDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(2) : Process Total</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $prev ? 'Rp ' . number_format((float) $prevProcTotal, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            Rp {{ number_format($procTotal, 2, ',', '.') }}
                        </td>
                        <td class="p-3 text-right font-mono {{ $procTotalDiff['color'] }}">{{ $procTotalDiff['formatted'] }}</td>
                    </tr>
                    <tr class="bg-gray-100/90 dark:bg-gray-800/90 font-bold border-y border-gray-300 dark:border-gray-700">
                        <td class="p-3 font-black text-gray-950 dark:text-white">(3) : Subtotal Direct Cost (1 + 2)</td>
                        <td class="p-3 text-right font-mono font-black">
                            {{ $prev ? 'Rp ' . number_format((float) $prevSubtotal, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono font-black text-gray-950 dark:text-white">
                            Rp {{ number_format($subtotal, 2, ',', '.') }}
                        </td>
                        @php $subtotalDiff = $diffFn($subtotal, $prevSubtotal, true, 2); @endphp
                        <td class="p-3 text-right font-mono font-black {{ $subtotalDiff['color'] }}">{{ $subtotalDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">
                            (4) : O/H & Profit supplied portion ({{ $current->overhead_supplied_percent ?: 11 }}% dari Proses)
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $prev ? 'Rp ' . number_format((float) $prevOhCost, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            Rp {{ number_format($ohCost, 2, ',', '.') }}
                        </td>
                        @php $ohDiff = $diffFn($ohCost, $prevOhCost, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $ohDiff['color'] }}">{{ $ohDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">
                            (5) : Profit portion ({{ $current->profit_percent ?: 0 }}%)
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $prev ? 'Rp ' . number_format((float) $prevProfitCost, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            Rp {{ number_format($profitCost, 2, ',', '.') }}
                        </td>
                        @php $profitDiff = $diffFn($profitCost, $prevProfitCost, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $profitDiff['color'] }}">{{ $profitDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(7) : Packing Cost</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $prev ? 'Rp ' . number_format((float) $prevPacking, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            Rp {{ number_format($packing, 2, ',', '.') }}
                        </td>
                        @php $packDiff = $diffFn($packing, $prevPacking, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $packDiff['color'] }}">{{ $packDiff['formatted'] }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-semibold">(9) : Die & Jig depreciation (Tooling)</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $prev ? 'Rp ' . number_format((float) $prevTooling, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            Rp {{ number_format($tooling, 2, ',', '.') }}
                        </td>
                        @php $toolingDiff = $diffFn($tooling, $prevTooling, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $toolingDiff['color'] }}">{{ $toolingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Total Termasuk Tooling -->
                    <tr class="bg-gradient-to-r from-indigo-700 to-purple-800 text-white font-black text-sm">
                        <td class="p-4 uppercase tracking-wider text-white">
                            Total Part Cost = (3) + (4) + (5) + (7) + (9)
                        </td>
                        <td class="p-4 text-right font-mono text-indigo-100">
                            {{ $prev ? 'Rp ' . number_format((float) $prevPartCost, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-4 text-right font-mono text-white text-base font-black">
                            Rp {{ number_format($partCost, 2, ',', '.') }}
                        </td>
                        <td class="p-4 text-right font-mono text-white text-base font-black">
                            @if($prev)
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
