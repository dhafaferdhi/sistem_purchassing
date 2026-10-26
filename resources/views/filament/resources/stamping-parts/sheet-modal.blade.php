@php
    $rate = (float) ($record->exchange_rate ?? 16000);
    $coilIdr = (float) ($record->coil_price_idr ?? round((float) ($record->coil_price ?? 0) * $rate));
    $sheetIdr = (float) ($record->sheet_price_idr ?? round((float) ($record->sheet_price ?? 0) * $rate));
    $steelRate = $sheetIdr > 0 ? $sheetIdr : ($coilIdr > 0 ? $coilIdr : round((float) ($record->sheet_price ?? $record->coil_price ?? 0) * $rate));

    $matTotal = (float) ($record->material_total ?? 0);
    $procTotal = (float) ($record->process_total ?? 0);
    $subtotal = (float) ($record->subtotal ?? ($matTotal + $procTotal));
    $ohSupplied = (float) ($record->overhead_supplied_cost ?? 0);
    $ohSelf = (float) ($record->overhead_self_cost ?? 0);
    $tooling = (float) ($record->tooling_depreciation ?? 0);
    $partCost = (float) ($record->part_cost ?? ($subtotal + $ohSupplied + $ohSelf + $tooling));
@endphp

<div class="space-y-5 font-sans text-gray-950 dark:text-white antialiased p-1">
    <!-- Header Lembar Kerja Formal -->
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800 gap-3">
            <div>
                <span class="text-xs font-black uppercase tracking-wider text-blue-700 dark:text-blue-400">Official Cost Estimation Sheet</span>
                <h2 class="text-xl font-black tracking-tight text-gray-950 dark:text-white mt-0.5">PT. FUJI SEAT INDONESIA</h2>
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Lembar Rincian Struktur Biaya Part Stamping (Standar Excel Formal)</p>
            </div>
            <div class="text-left md:text-right">
                <span class="inline-flex items-center rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950 px-3 py-1 text-xs font-black tracking-wide shadow-sm">
                    Kuartal: {{ $record->year }} - {{ $record->quarter }}
                </span>
                <div class="text-xs text-gray-800 dark:text-gray-200 mt-1 font-semibold">
                    Supplier: <strong class="text-gray-950 dark:text-white font-extrabold">{{ $record->supplier_name ?: 'PT. POSMI STEEL INDONESIA' }}</strong>
                </div>
            </div>
        </div>

        <!-- Metadata Grid yang Rapi dan Sangat Jelas -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-4 text-xs">
            <div class="rounded-lg bg-gray-100 p-3 border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300 block">Part Number</span>
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
                Rincian Biaya Material (Material Cost)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Bahan Baku Plat Baja</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold text-xs uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Spesifikasi Material</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Thickness</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Wide</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Pitch</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-center">Yield/Stroke</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Berat Gross</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Harga Baja (IDR/Kg)</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Gross Mat Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white">
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">{{ $record->steel_spec ?: '-' }}</td>
                        <td class="p-3 text-right font-mono font-bold">{{ $record->double_thickness !== null && $record->double_thickness !== '' ? number_format((float)$record->double_thickness, 2).' mm' : '-' }}</td>
                        <td class="p-3 text-right font-mono font-bold">{{ $record->wide !== null && $record->wide !== '' ? number_format((float) $record->wide, 1).' mm' : '-' }}</td>
                        <td class="p-3 text-right font-mono font-bold">{{ $record->pitch !== null && $record->pitch !== '' ? number_format((float) $record->pitch, 1).' mm' : '-' }}</td>
                        <td class="p-3 text-center font-extrabold">{{ $record->yield_rate !== null && $record->yield_rate !== '' ? (int) $record->yield_rate : 1 }} pcs</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">{{ number_format((float) $record->berat, 4, ',', '.') }} kg</td>
                        <td class="p-3 text-right font-mono font-bold">Rp {{ number_format($steelRate, 2, ',', '.') }}</td>
                        <td class="p-3 text-right font-mono font-extrabold text-gray-950 dark:text-white">Rp {{ number_format((float) $record->gross_material_cost, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="bg-amber-100/70 dark:bg-amber-950/40 border-t border-amber-300 dark:border-amber-700">
                        <td colspan="5" class="p-3 font-medium text-xs text-gray-950 dark:text-gray-100">
                            <strong class="font-extrabold text-amber-900 dark:text-amber-300">Scrap Recovery:</strong> Berat {{ number_format((float) $record->scrap_weight, 4, ',', '.') }} kg × Harga Rp {{ number_format((float) $record->scrap_price_per_kg, 0, ',', '.') }}/kg
                        </td>
                        <td colspan="2" class="p-3 text-right font-black text-xs uppercase text-gray-950 dark:text-white">Potongan Scrap:</td>
                        <td class="p-3 text-right font-mono font-black text-amber-900 dark:text-amber-300">Rp {{ number_format((float) $record->scrap_cost, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="bg-blue-100/80 dark:bg-blue-950/50 font-bold border-t-2 border-blue-400 dark:border-blue-700">
                        <td colspan="7" class="p-3 text-xs font-black uppercase tracking-wide text-gray-950 dark:text-white">
                            (1) TOTAL BIAYA MATERIAL (Gross Material + Scrap Recovery)
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
                Rincian Biaya Proses Mesin (Process Cost)
            </h3>
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Progressive Press & Stamping</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-200 text-gray-950 dark:bg-gray-800 dark:text-white font-extrabold text-xs uppercase tracking-wide border-b-2 border-gray-300 dark:border-gray-700">
                    <tr>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700">Nama Proses / Mesin Stamping</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Cycle Time (SPH)</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">Tarif Mesin (Rp)</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-center">Yield / Stroke</th>
                        <th class="p-3 border-b border-gray-300 dark:border-gray-700 text-right">(2) Total Biaya Proses</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-950 dark:text-white">
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/60 font-medium">
                        <td class="p-3 font-bold text-gray-950 dark:text-white">{{ $record->process_name ?: 'PROGRESSIVE 250T' }}</td>
                        <td class="p-3 text-right font-mono font-bold text-gray-950 dark:text-white">{{ $record->process_man_hour !== null && $record->process_man_hour !== '' ? number_format((float) $record->process_man_hour, 1).' SPH' : '0.0 SPH' }}</td>
                        <td class="p-3 text-right font-mono font-bold text-gray-950 dark:text-white">Rp {{ number_format((float) ($record->process_charge !== null && $record->process_charge !== '' ? $record->process_charge : 45), 0, ',', '.') }}</td>
                        <td class="p-3 text-center font-extrabold text-gray-950 dark:text-white">{{ $record->yield_rate !== null && $record->yield_rate !== '' ? (int) $record->yield_rate : 1 }} pcs</td>
                        <td class="p-3 text-right font-mono font-black text-purple-900 dark:text-purple-200 text-base">Rp {{ number_format($procTotal, 2, ',', '.') }}</td>
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
            <span class="text-xs text-gray-700 dark:text-gray-300 font-semibold">Total Harga Akhir Part</span>
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
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(4) O/H Material Supplied ({{ $record->overhead_supplied_percent !== null && $record->overhead_supplied_percent !== '' ? $record->overhead_supplied_percent : 11 }}% dari Proses):</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($ohSupplied, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center py-2.5 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(5) O/H Material Self ({{ $record->overhead_self_percent !== null && $record->overhead_self_percent !== '' ? $record->overhead_self_percent : 4 }}% dari Material):</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($ohSelf, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center py-2.5 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-900 dark:text-gray-100 font-semibold">(6) Die & Jig Depreciation / pc:</span>
                <span class="font-mono font-black text-gray-950 dark:text-white">Rp {{ number_format($tooling, 2, ',', '.') }}</span>
            </div>
            
            <!-- Banner Total Akhir: Kontras Maksimal dengan Tulisan Putih Tebal -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 text-white shadow-md mt-4 gap-3">
                <div>
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-100 block">(8) TOTAL PART COST (EXCEL MATCH)</span>
                    <span class="text-sm font-bold text-white mt-0.5 block">Harga Satuan Bersih Termasuk Seluruh Komponen Biaya & Overhead</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black font-mono text-white tracking-tight drop-shadow-sm">
                    Rp {{ number_format($partCost, 2, ',', '.') }}
                </div>
            </div>
        </div>
    </div>
</div>
