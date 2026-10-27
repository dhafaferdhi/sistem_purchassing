@php
    use App\Models\Fastener;

    // Ambil data kuartal berjalan
    $current = $record;
    $rate = (float) ($current->exchange_rate ?? 16000);
    $oldRate = (float) ($current->old_exchange_rate ?? $rate);
    $wirePriceUsd = (float) ($current->wire_price_usd_kg ?? 0);
    $wirePriceIdr = (float) ($current->wire_price_idr_kg ?? round($wirePriceUsd * $rate, 2));
    $wireCostPc = (float) ($current->wire_price_pc ?? 0);
    $matCtrlPct = (float) ($current->material_control_percent ?? 5);
    $matCtrlCost = (float) ($current->material_control_cost ?? 0);
    $washerPrice = (float) ($current->washer_price ?? 0);
    $washerWeight = (float) ($current->washer_weight_gr ?? 0);
    $nettWeight = (float) ($current->nett_weight_gr ?? 0);
    $grossWeight = (float) ($current->gross_weight_gr ?? 0);
    $totalWeightGr = $nettWeight + $washerWeight;

    $matTotal = (float) ($current->material_total ?? 0);
    $procTotal = (float) ($current->process_total ?? 0);
    $subtotal = (float) ($current->subtotal ?? ($matTotal + $procTotal));
    $ohSelf = (float) ($current->overhead_self_cost ?? 0);
    $tooling = (float) ($current->tooling_depreciation ?? 0);
    $partCost = (float) ($current->part_cost ?? ($subtotal + $ohSelf + $tooling));

    // Cari seluruh kuartal lain untuk Part Number yang sama
    $otherQuarters = Fastener::where('part_number', $current->part_number)
        ->where('id', '!=', $current->id)
        ->orderByDesc('year')
        ->orderByDesc('quarter')
        ->get();

    // Default kuartal acuan pembanding: cari kuartal persis sebelumnya
    $prevInfo = Fastener::getPreviousQuarterInfo($current->quarter, $current->year);
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
    $prevRate = $prev ? (float) ($prev->exchange_rate ?? 0) : null;
    $prevWireUsd = $prev ? (float) ($prev->wire_price_usd_kg ?? 0) : null;
    $prevWireIdr = $prev ? (float) ($prev->wire_price_idr_kg ?? 0) : null;
    $prevWirePc = $prev ? (float) ($prev->wire_price_pc ?? 0) : null;
    $prevMatCtrlCost = $prev ? (float) ($prev->material_control_cost ?? 0) : null;
    $prevWasherPrice = $prev ? (float) ($prev->washer_price ?? 0) : null;
    $prevMatTotal = $prev ? (float) ($prev->material_total ?? 0) : null;

    $prevHeading = $prev ? (float) ($prev->heading_cost ?? 48) : null;
    $prevRolling = $prev ? (float) ($prev->rolling_cost ?? 76) : null;
    $prevWashing = $prev ? (float) ($prev->washing_cost ?? 0) : null;
    $prevPlating = $prev ? (float) ($prev->plating_cost ?? 0) : null;
    $prevInspect = $prev ? (float) ($prev->inspection_cost ?? 5) : null;
    $prevPacking = $prev ? (float) ($prev->packing_cost ?? 3) : null;
    $prevProcTotal = $prev ? (float) ($prev->process_total ?? 0) : null;

    $prevSubtotal = $prev ? (float) ($prev->subtotal ?? 0) : null;
    $prevOhSelf = $prev ? (float) ($prev->overhead_self_cost ?? 0) : null;
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
                    <span>Kuartal Berjalan: {{ $current->year }} - {{ $current->quarter }}</span>
                    @if($prev)
                        <span class="text-gray-400 dark:text-gray-500 font-normal">vs</span>
                        <span class="text-emerald-300 dark:text-emerald-700">Acuan: {{ $prev->year }} - {{ $prev->quarter }}</span>
                    @endif
                </div>
                <div class="text-xs text-gray-800 dark:text-gray-200 mt-1.5 font-semibold">
                    Supplier: <strong class="text-gray-950 dark:text-white font-extrabold">{{ $current->supplier_name ?: 'PT. NITTO ALAM INDONESIA' }}</strong>
                </div>
            </div>
        </div>

        <!-- Metadata Grid Part & Kurs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-4 text-xs">
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Number</span>
                <span class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $current->part_number ?: '-' }}</span>
                @if($current->old_part_number)
                    <span class="text-[10px] text-gray-600 dark:text-gray-400 font-mono font-bold block mt-0.5">Old Part No: {{ $current->old_part_number }}</span>
                @endif
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Name</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 truncate block" title="{{ $current->part_name }}">{{ $current->part_name ?: '-' }}</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Model & Vol</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $current->model_name ?: 'D26A' }} / {{ number_format($current->monthly_volume ?: 20000, 0, ',', '.') }} pcs/bln</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Fluktuasi Kurs Acuan</span>
                <div class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 flex items-baseline justify-between">
                    <span>Rp {{ number_format($rate, 0, ',', '.') }}</span>
                    @if($prev && $prevRate)
                        @php $rateDiff = $diffFn($rate, $prevRate, true, 0); @endphp
                        <span class="text-[11px] {{ $rateDiff['color'] }}">{{ $rateDiff['formatted'] }}</span>
                    @endif
                </div>
                @if($current->exchange_rate_fluctuation && (float)$current->exchange_rate_fluctuation != 1.0)
                    <span class="text-[10px] text-gray-600 dark:text-gray-400 font-bold block mt-0.5">Exchg factor: {{ number_format((float) $current->exchange_rate_fluctuation, 6) }}</span>
                @endif
            </div>
        </div>

        <!-- Banner Ringkasan Komparasi Biaya (Top KPI) -->
        @if($prev)
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-lg bg-gradient-to-r from-gray-50 to-emerald-50/60 border border-emerald-200 dark:from-gray-800/80 dark:to-emerald-950/40 dark:border-emerald-800">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400">Harga Acuan ({{ $prev->quarter }} {{ $prev->year }})</span>
                    <div class="text-base font-black font-mono text-gray-900 dark:text-white mt-0.5">Rp {{ number_format($prevPartCost, 2, ',', '.') }}</div>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Harga Berjalan ({{ $current->quarter }} {{ $current->year }})</span>
                    <div class="text-base font-black font-mono text-emerald-900 dark:text-emerald-200 mt-0.5">Rp {{ number_format($partCost, 2, ',', '.') }}</div>
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
                    <!-- Baris 1: Wire Base Price -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">
                            {{ $current->wire_spec ?: 'WIRE OF RIVET [SWCH-45K]' }}
                            <span class="block text-[11px] text-gray-600 dark:text-gray-400 font-normal">Harga Kawat Dasar per Kilogram</span>
                        </td>
                        <td class="p-3 text-center font-mono">
                            Gross: {{ number_format($grossWeight, 2, ',', '.') }} gr | Nett: {{ number_format($nettWeight, 2, ',', '.') }} gr
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            @if($prev)
                                <div>$ {{ number_format((float) $prevWireUsd, 4) }} /kg</div>
                                <div class="text-[11px] text-gray-600 dark:text-gray-400 font-bold">Rp {{ number_format((float) $prevWireIdr, 2, ',', '.') }}</div>
                            @else
                                -
                            @endif
                        </td>
                        <td class="p-3 text-right font-mono bg-blue-50/40 dark:bg-blue-950/20 font-bold">
                            <div>$ {{ number_format($wirePriceUsd, 4) }} /kg</div>
                            <div class="text-[11px] text-blue-700 dark:text-blue-300 font-bold">Rp {{ number_format($wirePriceIdr, 2, ',', '.') }}</div>
                        </td>
                        @php $wireDiff = $diffFn($wirePriceIdr, $prevWireIdr, true, 2); @endphp
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
                            {{ number_format($grossWeight, 2, ',', '.') }} gr / 1000
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $prev ? 'Rp ' . number_format((float) $prevWirePc, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-blue-50/40 dark:bg-blue-950/20 font-black text-gray-950 dark:text-white">
                            Rp {{ number_format($wireCostPc, 2, ',', '.') }}
                        </td>
                        @php $wirePcDiff = $diffFn($wireCostPc, $prevWirePc, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $wirePcDiff['color'] }}">
                            {{ $wirePcDiff['formatted'] }}
                        </td>
                    </tr>

                    <!-- Baris 3: Material Control 5% -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">
                            e. Material Control ({{ $matCtrlPct }}%)
                            <span class="block text-[11px] text-gray-600 dark:text-gray-400 font-normal">{{ $matCtrlPct }}% dari Biaya Kawat per piece</span>
                        </td>
                        <td class="p-3 text-center font-mono text-gray-600 dark:text-gray-400">
                            {{ $matCtrlPct }}%
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $prev ? 'Rp ' . number_format((float) $prevMatCtrlCost, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-blue-50/40 dark:bg-blue-950/20 font-black text-gray-950 dark:text-white">
                            Rp {{ number_format($matCtrlCost, 2, ',', '.') }}
                        </td>
                        @php $matCtrlDiff = $diffFn($matCtrlCost, $prevMatCtrlCost, true, 2); @endphp
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
                            Berat: {{ number_format($washerWeight, 2, ',', '.') }} gr
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $prev ? 'Rp ' . number_format((float) $prevWasherPrice, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-blue-50/40 dark:bg-blue-950/20 font-black text-gray-950 dark:text-white">
                            Rp {{ number_format($washerPrice, 2, ',', '.') }}
                        </td>
                        @php $washerDiff = $diffFn($washerPrice, $prevWasherPrice, true, 2); @endphp
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
                    <!-- Heading -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">a. Heading</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">Tarif pembentukan kepala baut (Cold Heading)</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $prev ? 'Rp ' . number_format((float) $prevHeading, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-purple-50/40 dark:bg-purple-950/20 font-black">
                            Rp {{ number_format((float) ($current->heading_cost ?? 48), 2, ',', '.') }}
                        </td>
                        @php $headingDiff = $diffFn((float) ($current->heading_cost ?? 48), $prevHeading, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $headingDiff['color'] }}">{{ $headingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Rolling -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">b. Rolling</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">Tarif roll drat pembentukan ulir baut</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $prev ? 'Rp ' . number_format((float) $prevRolling, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-purple-50/40 dark:bg-purple-950/20 font-black">
                            Rp {{ number_format((float) ($current->rolling_cost ?? 76), 2, ',', '.') }}
                        </td>
                        @php $rollingDiff = $diffFn((float) ($current->rolling_cost ?? 76), $prevRolling, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $rollingDiff['color'] }}">{{ $rollingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Washing -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">c. Washing</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">({{ number_format($nettWeight, 1) }}g + {{ number_format($washerWeight, 1) }}g) / 1000 × Rp {{ number_format((float) ($current->washing_cost_kg ?: 500)) }}/kg</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $prev ? 'Rp ' . number_format((float) $prevWashing, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-purple-50/40 dark:bg-purple-950/20 font-black">
                            Rp {{ number_format((float) $current->washing_cost, 2, ',', '.') }}
                        </td>
                        @php $washingDiff = $diffFn((float) $current->washing_cost, $prevWashing, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $washingDiff['color'] }}">{{ $washingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Plating -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">d. Plating (MFZn-C)</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">({{ number_format($nettWeight, 1) }}g + {{ number_format($washerWeight, 1) }}g) / 1000 × Rp {{ number_format((float) ($current->plating_cost_kg ?: 10000)) }}/kg</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $prev ? 'Rp ' . number_format((float) $prevPlating, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-purple-50/40 dark:bg-purple-950/20 font-black">
                            Rp {{ number_format((float) $current->plating_cost, 2, ',', '.') }}
                        </td>
                        @php $platingDiff = $diffFn((float) $current->plating_cost, $prevPlating, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $platingDiff['color'] }}">{{ $platingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Inspection -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">e. Final Inspection</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">Pemeriksaan kualitas & sortasi</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $prev ? 'Rp ' . number_format((float) $prevInspect, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-purple-50/40 dark:bg-purple-950/20 font-black">
                            Rp {{ number_format((float) ($current->inspection_cost ?? 5), 2, ',', '.') }}
                        </td>
                        @php $inspectDiff = $diffFn((float) ($current->inspection_cost ?? 5), $prevInspect, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $inspectDiff['color'] }}">{{ $inspectDiff['formatted'] }}</td>
                    </tr>

                    <!-- Packing -->
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="p-3 font-bold">f. Packing</td>
                        <td class="p-3 text-gray-600 dark:text-gray-400">Pengemasan & pelabelan box</td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40 font-bold">
                            {{ $prev ? 'Rp ' . number_format((float) $prevPacking, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-purple-50/40 dark:bg-purple-950/20 font-black">
                            Rp {{ number_format((float) ($current->packing_cost ?? 3), 2, ',', '.') }}
                        </td>
                        @php $packingDiff = $diffFn((float) ($current->packing_cost ?? 3), $prevPacking, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $packingDiff['color'] }}">{{ $packingDiff['formatted'] }}</td>
                    </tr>

                    <!-- Baris Total Proses -->
                    <tr class="bg-purple-100/90 dark:bg-purple-950/60 font-bold border-t-2 border-purple-400 dark:border-purple-700">
                        <td colspan="2" class="p-3 font-black uppercase text-gray-950 dark:text-white">
                            (2) : Process Total (Heading + Rolling + Wash + Plate + Inspect + Pack)
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
                            (5) : O/H & Profit self-procuring portion ({{ $current->overhead_self_percent !== null && $current->overhead_self_percent !== '' ? $current->overhead_self_percent : 14.3 }}%)
                        </td>
                        <td class="p-3 text-right font-mono bg-gray-50/50 dark:bg-gray-800/40">
                            {{ $prev ? 'Rp ' . number_format((float) $prevOhSelf, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/20 font-bold">
                            Rp {{ number_format($ohSelf, 2, ',', '.') }}
                        </td>
                        @php $ohSelfDiff = $diffFn($ohSelf, $prevOhSelf, true, 2); @endphp
                        <td class="p-3 text-right font-mono {{ $ohSelfDiff['color'] }}">{{ $ohSelfDiff['formatted'] }}</td>
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
                    <tr class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white font-black text-sm">
                        <td class="p-4 uppercase tracking-wider text-white">
                            Total Including Tooling Cost = (8) + (9)
                        </td>
                        <td class="p-4 text-right font-mono text-emerald-100">
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
