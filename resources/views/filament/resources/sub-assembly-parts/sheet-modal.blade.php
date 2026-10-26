@php
    $rate = (float) ($record->exchange_rate ?? 16000);
    $matTotal = (float) ($record->material_total ?? 0);
    $procTotal = (float) ($record->process_total ?? 0);
    $subtotal = (float) ($record->subtotal ?? ($matTotal + $procTotal));
    $ohCost = (float) ($record->overhead_supplied_cost ?? 0);
    $profitCost = (float) ($record->profit_cost ?? 0);
    $packing = (float) ($record->packing_cost ?? 0);
    $tooling = (float) ($record->tooling_depreciation ?? 0);
    $partCost = (float) ($record->part_cost ?? ($subtotal + $ohCost + $profitCost + $packing + $tooling));

    $matBreakdown = is_array($record->material_breakdown) 
        ? $record->material_breakdown 
        : (json_decode($record->material_breakdown ?? '[]', true) ?: []);

    $procBreakdown = is_array($record->process_breakdown) 
        ? $record->process_breakdown 
        : (json_decode($record->process_breakdown ?? '[]', true) ?: []);
@endphp

<div class="space-y-5 font-sans text-gray-950 dark:text-white antialiased p-1">
    <!-- Header Lembar Kerja Formal -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800 gap-3">
            <div>
                <span class="text-xs font-black uppercase tracking-wider text-indigo-700 dark:text-indigo-400">Official Cost Estimation Sheet</span>
                <h2 class="text-xl font-black tracking-tight text-gray-950 dark:text-white mt-0.5">PT. FUJI SEAT INDONESIA</h2>
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Lembar Rincian Struktur Biaya Sub-Assembly Part (Standar Excel Formal)</p>
            </div>
            <div class="text-left md:text-right">
                <span class="inline-flex items-center rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950 px-3 py-1 text-xs font-black tracking-wide shadow-sm">
                    Kuartal: {{ $record->year }} - {{ $record->quarter }}
                </span>
                <div class="text-xs text-gray-800 dark:text-gray-200 mt-1 font-semibold">
                    Supplier: <strong class="text-gray-950 dark:text-white font-extrabold">{{ $record->supplier_name ?: 'PT. TRI CENTRUM FORTUNA' }}</strong>
                </div>
            </div>
        </div>

        <!-- Metadata Grid yang Rapi dan Sangat Jelas -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-4 text-xs">
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Sub-Assy Part No</span>
                <span class="font-mono font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $record->part_number ?: '-' }}</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Name</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 truncate block" title="{{ $record->part_name }}">{{ $record->part_name ?: '-' }}</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Model & Vol</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $record->model_name ?: 'D26A' }} / {{ number_format($record->monthly_volume ?: 16500, 0, ',', '.') }} pcs/bln</span>
            </div>
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Drawing / ECI</span>
                <span class="font-black text-gray-950 dark:text-white text-sm mt-0.5 block">{{ $record->drawing_reference ?: 'Mass Prod. Drwg' }} ({{ $record->eci_number ?: 'F1225' }})</span>
            </div>
        </div>
    </div>

    <!-- 1. MATERIAL SECTION -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <h3 class="text-sm font-black text-gray-950 dark:text-white flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-black text-white shadow-sm">1</span>
                Rincian Komponen Pembentuk Sub-Assembly (BOM Child Parts)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Bahan Baku & Part Anak</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold text-xs uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 w-12 text-center">No</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Nama Komponen / Child Part</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Spesifikasi / Dimensi</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-center">Qty</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Harga Satuan (Rp)</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Total Biaya (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white">
                    @forelse($matBreakdown as $index => $item)
                    @php
                        $qty = (float)($item['qty'] ?? 1);
                        $up = (float)($item['unit_price'] ?? 0);
                        $pr = isset($item['price']) && (float)$item['price'] > 0 ? (float)$item['price'] : round($qty * $up, 2);
                    @endphp
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 text-center text-gray-700 dark:text-gray-300 font-bold">{{ $index + 1 }}</td>
                        <td class="p-3 font-bold text-gray-950 dark:text-white">{{ $item['part_name'] ?? '-' }}</td>
                        <td class="p-3 text-gray-700 dark:text-gray-300 text-xs font-medium">{{ $item['spec'] ?? '-' }}</td>
                        <td class="p-3 text-center font-extrabold text-gray-950 dark:text-white">{{ $qty }}</td>
                        <td class="p-3 text-right font-mono font-bold">Rp {{ number_format($up, 2, ',', '.') }}</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format($pr, 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-4 text-center text-gray-600 dark:text-gray-400 font-semibold italic">Tidak ada rincian child parts.</td>
                    </tr>
                    @endforelse
                    <tr class="bg-blue-100/80 dark:bg-blue-950/50 font-bold border-t-2 border-blue-400 dark:border-blue-700">
                        <td colspan="5" class="p-3 text-xs font-black uppercase tracking-wide text-gray-950 dark:text-white">
                            (1) TOTAL BIAYA MATERIAL SUB-ASSEMBLY (Child Parts)
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
                Rincian Biaya Proses Perakitan (Assembly Process)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Welding, Riveting & Perakitan</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold text-xs uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 w-12 text-center">No</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Nama Proses / Stasiun Kerja</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Cycle Time (SPH / Detik)</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Tarif Mesin (Rp/dtk)</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Total Biaya Proses (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white">
                    @forelse($procBreakdown as $index => $proc)
                    @php
                        $sph = (float)($proc['man_hour'] ?? 0);
                        $charge = (float)($proc['rate_per_sph'] ?? 0);
                        $prc = isset($proc['price']) && (float)$proc['price'] > 0 ? (float)$proc['price'] : round($sph * $charge, 2);
                    @endphp
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 text-center text-gray-700 dark:text-gray-300 font-bold">{{ $index + 1 }}</td>
                        <td class="p-3 font-bold text-gray-950 dark:text-white">{{ $proc['process_name'] ?? '-' }}</td>
                        <td class="p-3 text-right font-mono font-bold">{{ number_format($sph, 1) }} SPH</td>
                        <td class="p-3 text-right font-mono font-bold">Rp {{ number_format($charge, 0, ',', '.') }}</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format($prc, 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-4 text-center text-gray-600 dark:text-gray-400 font-semibold italic">Tidak ada rincian proses perakitan.</td>
                    </tr>
                    @endforelse
                    <tr class="bg-purple-100/80 dark:bg-purple-950/50 font-bold border-t-2 border-purple-400 dark:border-purple-700">
                        <td colspan="4" class="p-3 text-xs font-black uppercase tracking-wide text-gray-950 dark:text-white">
                            (2) TOTAL BIAYA PROSES PERAKITAN SUB-ASSEMBLY
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
                Rekapitulasi Kalkulasi Akhir, Overhead & Margin (Cost Summary)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Total Harga Akhir Sub-Assembly</span>
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
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(4) O/H Mat'l Supplied ({{ $record->overhead_supplied_percent !== null && $record->overhead_supplied_percent !== '' ? $record->overhead_supplied_percent : 11 }}% dari Proses):</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($ohCost, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center py-2.5 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(5) Profit Margin ({{ $record->profit_percent !== null && $record->profit_percent !== '' ? $record->profit_percent : 0 }}% dari Subtotal):</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($profitCost, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center py-2.5 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(6) Packaging Cost / pc:</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($packing, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center py-2.5 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(7) Tooling & Jig Depreciation:</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($tooling, 2, ',', '.') }}</span>
            </div>
            
            <!-- Banner Total Akhir: Kontras Maksimal dengan Tulisan Putih Tebal -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-5 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-700 text-white shadow-md mt-4 gap-3">
                <div>
                    <span class="text-xs font-black uppercase tracking-wider text-indigo-100 block">(8) TOTAL SUB-ASSEMBLY COST (EXCEL MATCH)</span>
                    <span class="text-sm font-bold text-white mt-0.5 block">Harga Satuan Bersih Termasuk Child Parts, Perakitan, O/H & Margin</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black font-mono text-white tracking-tight drop-shadow-sm">
                    Rp {{ number_format($partCost, 2, ',', '.') }}
                </div>
            </div>
        </div>
    </div>
</div>
