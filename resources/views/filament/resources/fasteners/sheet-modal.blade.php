@php
    $rate = (float) ($record->exchange_rate ?? 16000);
    $oldRate = (float) ($record->old_exchange_rate ?? $rate);
    $wirePriceUsd = (float) ($record->wire_price_usd_kg ?? 0);
    $wirePriceIdr = (float) ($record->wire_price_idr_kg ?? round($wirePriceUsd * $rate, 2));
    $wireCostPc = (float) ($record->wire_price_pc ?? 0);
    $matCtrlPct = (float) ($record->material_control_percent ?? 5);
    $matCtrlCost = (float) ($record->material_control_cost ?? 0);
    $washerPrice = (float) ($record->washer_price ?? 0);
    $washerWeight = (float) ($record->washer_weight_gr ?? 0);
    $nettWeight = (float) ($record->nett_weight_gr ?? 0);
    $grossWeight = (float) ($record->gross_weight_gr ?? 0);
    $totalWeightGr = $nettWeight + $washerWeight;

    $matTotal = (float) ($record->material_total ?? 0);
    $procTotal = (float) ($record->process_total ?? 0);
    $subtotal = (float) ($record->subtotal ?? ($matTotal + $procTotal));
    $ohSelf = (float) ($record->overhead_self_cost ?? 0);
    $tooling = (float) ($record->tooling_depreciation ?? 0);
    $partCost = (float) ($record->part_cost ?? ($subtotal + $ohSelf + $tooling));
@endphp

<div class="space-y-5 font-sans text-gray-950 dark:text-white antialiased p-1">
    <!-- Header Lembar Kerja Formal -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800 gap-3">
            <div>
                <span class="text-xs font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Official Cost Estimation Sheet</span>
                <h2 class="text-xl font-black tracking-tight text-gray-950 dark:text-white mt-0.5">PT. FUJI SEAT INDONESIA</h2>
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Lembar Rincian Struktur Biaya Fasteners & Baut (Standar Excel Formal)</p>
            </div>
            <div class="text-left md:text-right">
                <span class="inline-flex items-center rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950 px-3 py-1 text-xs font-black tracking-wide shadow-sm">
                    Kuartal: {{ $record->year }} - {{ $record->quarter }}
                </span>
                <div class="text-xs text-gray-800 dark:text-gray-200 mt-1 font-semibold">
                    Supplier: <strong class="text-gray-950 dark:text-white font-extrabold">{{ $record->supplier_name ?: 'PT. NITTO ALAM INDONESIA' }}</strong>
                </div>
            </div>
        </div>

        <!-- Metadata Grid yang Rapi dan Sangat Jelas -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-4 text-xs">
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Number</span>
                <span class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $record->part_number ?: '-' }}</span>
                @if($record->old_part_number)
                    <span class="text-[10px] text-gray-600 dark:text-gray-400 font-mono font-bold block mt-0.5">Old: {{ $record->old_part_number }}</span>
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
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Kurs Acuan (IDR)</span>
                <span class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 block">
                    Rp {{ number_format($rate, 0, ',', '.') }}
                    @if($record->exchange_rate_fluctuation && (float)$record->exchange_rate_fluctuation != 1.0)
                        <span class="text-[11px] text-emerald-700 dark:text-emerald-300 font-bold ml-1">({{ number_format((float) $record->exchange_rate_fluctuation, 4) }})</span>
                    @endif
                </span>
            </div>
        </div>
    </div>

    <!-- 1. MATERIAL SECTION -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-black text-white shadow-sm">1</span>
                Rincian Biaya Material (Wire, Washer & Material Control)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Bahan Baku Kawat & Ring</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold text-xs uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Spesifikasi Wire</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Nett Weight</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Gross Weight</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Wire USD/Kg</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Wire IDR/Kg</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Wire Cost / pc</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white">
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">{{ $record->wire_spec ?: 'WIRE OF RIVET [SWCH-45K]' }}</td>
                        <td class="p-3 text-right font-mono font-bold">{{ number_format($nettWeight, 2, ',', '.') }} gr</td>
                        <td class="p-3 text-right font-mono font-bold">{{ number_format($grossWeight, 2, ',', '.') }} gr</td>
                        <td class="p-3 text-right font-mono font-bold">${{ number_format($wirePriceUsd, 4) }}</td>
                        <td class="p-3 text-right font-mono font-bold">Rp {{ number_format($wirePriceIdr, 2, ',', '.') }}</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format($wireCostPc, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td colspan="4" class="p-3 text-xs text-gray-950 dark:text-gray-100">
                            <strong class="font-extrabold text-gray-950 dark:text-white">Washer Component:</strong> Ring Washer Berat <span class="font-mono font-bold">{{ number_format($washerWeight, 2, ',', '.') }} gr</span>
                        </td>
                        <td class="p-3 text-right font-black text-xs uppercase text-gray-950 dark:text-white">Biaya Washer:</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format($washerPrice, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td colspan="4" class="p-3 text-xs text-gray-950 dark:text-gray-100">
                            <strong class="font-extrabold text-gray-950 dark:text-white">Material Control Cost:</strong> {{ $matCtrlPct }}% dari Biaya Kawat
                        </td>
                        <td class="p-3 text-right font-black text-xs uppercase text-gray-950 dark:text-white">Control Cost:</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format($matCtrlCost, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="bg-blue-100/80 dark:bg-blue-950/50 font-bold border-t-2 border-blue-400 dark:border-blue-700">
                        <td colspan="5" class="p-3 text-xs font-black uppercase tracking-wide text-gray-950 dark:text-white">
                            (1) TOTAL BIAYA MATERIAL (Wire Cost + Washer + Material Control)
                        </td>
                        <td class="p-3 text-right font-mono text-base text-blue-900 dark:text-blue-200 font-black">
                            Rp {{ number_format($matTotal, 2, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. PROCESS SECTION -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-purple-600 text-xs font-black text-white shadow-sm">2</span>
                Rincian Biaya Proses Fastener (Process Cost)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">6 Tahap Pembentukan Baut</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold text-xs uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Tahap Proses</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Metode / Formula Acuan</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Tarif Acuan</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Biaya / pc (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white">
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">1. Heading</td>
                        <td class="p-3 text-gray-700 dark:text-gray-300 text-xs font-medium">Tarif pembentukan kepala baut (Cold Heading)</td>
                        <td class="p-3 text-right font-mono text-gray-500 font-bold">-</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format((float) ($record->heading_cost !== null && $record->heading_cost !== '' ? $record->heading_cost : 48), 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">2. Rolling</td>
                        <td class="p-3 text-gray-700 dark:text-gray-300 text-xs font-medium">Tarif roll drat / pembentukan ulir</td>
                        <td class="p-3 text-right font-mono text-gray-500 font-bold">-</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format((float) ($record->rolling_cost !== null && $record->rolling_cost !== '' ? $record->rolling_cost : 76), 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">3. Washing</td>
                        <td class="p-3 text-gray-700 dark:text-gray-300 text-xs font-medium">({{ number_format($nettWeight, 1) }}g + {{ number_format($washerWeight, 1) }}g) / 1000 × Rp {{ number_format((float) ($record->washing_cost_kg ?: 500)) }}/kg</td>
                        <td class="p-3 text-right font-mono text-xs font-bold text-gray-800 dark:text-gray-200">Rp {{ number_format((float) ($record->washing_cost_kg ?: 500)) }}/kg</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format((float) $record->washing_cost, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">4. Plating (MFZn-C)</td>
                        <td class="p-3 text-gray-700 dark:text-gray-300 text-xs font-medium">({{ number_format($nettWeight, 1) }}g + {{ number_format($washerWeight, 1) }}g) / 1000 × Rp {{ number_format((float) ($record->plating_cost_kg ?: 10000)) }}/kg</td>
                        <td class="p-3 text-right font-mono text-xs font-bold text-gray-800 dark:text-gray-200">Rp {{ number_format((float) ($record->plating_cost_kg ?: 10000)) }}/kg</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format((float) $record->plating_cost, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">5. Inspection</td>
                        <td class="p-3 text-gray-700 dark:text-gray-300 text-xs font-medium">Pemeriksaan kualitas, sortasi & dimensi</td>
                        <td class="p-3 text-right font-mono text-gray-500 font-bold">-</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format((float) ($record->inspection_cost !== null && $record->inspection_cost !== '' ? $record->inspection_cost : 5), 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">6. Packing</td>
                        <td class="p-3 text-gray-700 dark:text-gray-300 text-xs font-medium">Pengemasan standar supplier & pelabelan</td>
                        <td class="p-3 text-right font-mono text-gray-500 font-bold">-</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format((float) ($record->packing_cost !== null && $record->packing_cost !== '' ? $record->packing_cost : 3), 2, ',', '.') }}</td>
                    </tr>
                    <tr class="bg-purple-100/80 dark:bg-purple-950/50 font-bold border-t-2 border-purple-400 dark:border-purple-700">
                        <td colspan="3" class="p-3 text-xs font-black uppercase tracking-wide text-gray-950 dark:text-white">
                            (2) TOTAL BIAYA PROSES MANUFAKTUR FASTENER
                        </td>
                        <td class="p-3 text-right font-mono text-base text-purple-900 dark:text-purple-200 font-black">
                            Rp {{ number_format($procTotal, 2, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. SUMMARY COST RECAP -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-xs font-black text-white shadow-sm">3</span>
                Rekapitulasi Kalkulasi Akhir & Overhead (Cost Summary)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Total Harga Akhir Fastener</span>
        </div>

        <div class="mt-4 space-y-2.5 text-sm">
            <div class="flex justify-between items-center py-2.5 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(1) Total Biaya Material:</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($matTotal, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center py-2.5 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(2) Total Biaya Proses:</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($procTotal, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center py-3 bg-gray-100 dark:bg-gray-800 px-4 rounded-lg font-bold text-gray-950 dark:text-white border border-gray-300 dark:border-gray-700">
                <span class="font-extrabold text-base">(3) Subtotal Direct Cost (1 + 2):</span>
                <span class="font-mono text-base font-black">Rp {{ number_format($subtotal, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center py-2.5 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(5) Overhead Self ({{ $record->overhead_self_percent !== null && $record->overhead_self_percent !== '' ? $record->overhead_self_percent : 14.3 }}% dari Subtotal):</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($ohSelf, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center py-2.5 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(6) Tooling Depreciation / pc:</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($tooling, 2, ',', '.') }}</span>
            </div>
            
            <!-- Banner Total Akhir: Kontras Maksimal dengan Tulisan Putih Tebal -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 text-white shadow-md mt-4 gap-3">
                <div>
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-100 block">(8) TOTAL FASTENER COST (EXCEL MATCH)</span>
                    <span class="text-sm font-bold text-white mt-0.5 block">Harga Satuan Bersih Termasuk Wire, Washer, 6 Proses & Overhead</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black font-mono text-white tracking-tight drop-shadow-sm">
                    Rp {{ number_format($partCost, 2, ',', '.') }}
                </div>
            </div>
        </div>
    </div>
</div>
