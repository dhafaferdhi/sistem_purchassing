<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Top Bar: Part Selector & Basic Info --}}
        <div class="p-5 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="w-full md:w-1/2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                        Pilih Part Number / Dokumen Quotation:
                    </label>
                    <select wire:model.live="selectedPartNumber" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm text-sm font-semibold text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-primary-500">
                        @foreach($this->availableParts as $pNo => $label)
                            <option value="{{ $pNo }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status Meta --}}
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="px-3 py-1.5 bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 rounded-lg font-semibold border border-blue-200 dark:border-blue-800">
                        Model: <strong>{{ $this->matrix['model_name'] }}</strong>
                    </span>
                    <span class="px-3 py-1.5 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 rounded-lg font-semibold border border-indigo-200 dark:border-indigo-800">
                        Monthly Vol: <strong>{{ number_format($this->matrix['monthly_volume']) }} pcs</strong>
                    </span>
                    <span class="px-3 py-1.5 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 rounded-lg font-semibold border border-emerald-200 dark:border-emerald-800">
                        Supplier: <strong>{{ $this->matrix['supplier_name'] }}</strong>
                    </span>
                </div>
            </div>
        </div>

        {{-- Summary Metric Cards --}}
        @php
            $metrics = $this->summaryMetrics;
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Harga Awal (Baseline)</div>
                <div class="text-xl font-black text-gray-900 dark:text-white mt-1">
                    Rp {{ number_format($metrics['initial_price'], 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-gray-400 mt-0.5">Periode pertama tercatat</div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Harga Terakhir (Active)</div>
                <div class="text-xl font-black text-primary-600 dark:text-primary-400 mt-1">
                    Rp {{ number_format($metrics['latest_price'], 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-gray-400 mt-0.5">Kuartal berjalan</div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Rentang Harga (Min - Max)</div>
                <div class="text-base font-bold text-gray-900 dark:text-white mt-1">
                    Rp {{ number_format($metrics['min_price'], 0, ',', '.') }} – {{ number_format($metrics['max_price'], 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-gray-400 mt-0.5">Fluktuasi multi-kuartal</div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Akumulasi Perubahan</div>
                <div class="text-xl font-black {{ $metrics['total_change'] >= 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }} mt-1">
                    {{ $metrics['total_change'] >= 0 ? '+' : '' }}Rp {{ number_format($metrics['total_change'], 0, ',', '.') }}
                    <span class="text-xs font-semibold">({{ $metrics['total_change_percent'] }}%)</span>
                </div>
                <div class="text-[11px] text-gray-400 mt-0.5">Dari baseline awal</div>
            </div>
        </div>

        {{-- The Comparative Quotation Matrix Sheet --}}
        @php
            $periods = $this->matrix['periods'];
        @endphp

        @if($periods->isEmpty())
            <div class="p-12 text-center bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                <p class="text-gray-500">Belum ada data lembar komparatif kuartalan untuk part ini.</p>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

                {{-- Official Sheet Title Header --}}
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold text-primary-600 dark:text-primary-400 uppercase tracking-wider">
                            Calculation Sheet for Comparative Q'tn.
                        </div>
                        <h2 class="text-xl font-black text-gray-900 dark:text-white mt-0.5">
                            {{ $this->matrix['part_number'] }} — {{ $this->matrix['part_name'] }}
                        </h2>
                    </div>
                    <div class="text-right text-xs text-gray-500">
                        <div>Buyer: <strong class="text-gray-700 dark:text-gray-300">{{ $this->matrix['buyer_name'] }}</strong></div>
                        <div>Supplier: <strong class="text-gray-700 dark:text-gray-300">{{ $this->matrix['supplier_name'] }}</strong></div>
                        <div>Drawing: <span class="font-mono text-gray-600 dark:text-gray-400">{{ $this->matrix['drawing_reference'] }}</span></div>
                    </div>
                </div>

                {{-- Table Horizontal Scroll Container --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse min-w-[900px]">
                        <thead>
                            {{-- Baris Periode Kuartal --}}
                            <tr class="bg-blue-900 text-white border-b border-blue-950 font-bold">
                                <th class="p-3 w-72 sticky left-0 z-10 bg-blue-900 border-r border-blue-800 shadow-[2px_0_5px_rgba(0,0,0,0.1)]">
                                    Periode / Kuartal
                                </th>
                                @foreach($periods as $p)
                                    <th class="p-3 text-center min-w-[170px] border-r border-blue-800">
                                        <div class="text-xs font-extrabold uppercase">{{ $p->period_label }}</div>
                                        <div class="text-[10px] text-blue-200 font-normal mt-0.5">Seq #{{ $p->sequence }} ({{ $p->quarter }} {{ $p->year }})</div>
                                    </th>
                                @endforeach
                            </tr>

                            {{-- Baris Kurs (Exchange Rate) --}}
                            <tr class="bg-blue-50/80 dark:bg-blue-950/40 text-blue-950 dark:text-blue-200 border-b border-blue-200 dark:border-blue-900 font-semibold">
                                <th class="p-2.5 sticky left-0 z-10 bg-blue-100/90 dark:bg-blue-950 border-r border-blue-200 dark:border-blue-800">
                                    Exchange Rate (Kurs IDR)
                                </th>
                                @foreach($periods as $p)
                                    <td class="p-2.5 text-center border-r border-blue-200 dark:border-blue-900 font-mono">
                                        Rp {{ number_format((float) $p->new_exchange_rate, 0, ',', '.') }}
                                        @if($p->old_exchange_rate && (float) $p->old_exchange_rate != (float) $p->new_exchange_rate)
                                            <div class="text-[10px] text-blue-600 dark:text-blue-400">
                                                (Old: Rp {{ number_format((float) $p->old_exchange_rate, 0, ',', '.') }})
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono">

                            {{-- SECTION 1: MATERIAL BREAKDOWN --}}
                            <tr class="bg-gray-100/80 dark:bg-gray-900 font-sans font-bold text-gray-700 dark:text-gray-300">
                                <td colspan="{{ $periods->count() + 1 }}" class="p-2.5 uppercase tracking-wide text-[11px] sticky left-0 bg-gray-100 dark:bg-gray-900">
                                    1. Material Breakdown (Komposisi Bahan Baku / Child Parts)
                                </td>
                            </tr>

                            @foreach($this->matrix['material_rows'] as $matRow)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/50">
                                    <td class="p-2.5 font-sans font-medium text-gray-800 dark:text-gray-200 sticky left-0 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 shadow-[2px_0_5px_rgba(0,0,0,0.03)]">
                                        {{ $matRow['name'] }}
                                    </td>
                                    @foreach($periods as $p)
                                        @php
                                            $item = $matRow['periods'][$p->id] ?? null;
                                        @endphp
                                        <td class="p-2.5 text-right border-r border-gray-200 dark:border-gray-700">
                                            @if($item)
                                                <div class="font-bold text-gray-900 dark:text-white">
                                                    Rp {{ number_format((float) ($item['price'] ?? 0), 2, ',', '.') }}
                                                </div>
                                                @if(!empty($item['valuation']))
                                                    <div class="text-[10px] text-gray-400 font-sans">
                                                        Val: {{ $item['valuation'] }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach

                            {{-- Material Total --}}
                            <tr class="bg-amber-50/60 dark:bg-amber-950/20 font-bold border-t-2 border-b-2 border-amber-200 dark:border-amber-900 text-amber-950 dark:text-amber-200">
                                <td class="p-2.5 font-sans sticky left-0 bg-amber-100/90 dark:bg-amber-950 border-r border-amber-200 dark:border-amber-800">
                                    (1) : Material Total
                                </td>
                                @foreach($periods as $p)
                                    <td class="p-2.5 text-right border-r border-amber-200 dark:border-amber-900 text-sm">
                                        Rp {{ number_format((float) $p->material_total, 2, ',', '.') }}
                                    </td>
                                @endforeach
                            </tr>

                            {{-- SECTION 2: PROCESS BREAKDOWN --}}
                            <tr class="bg-gray-100/80 dark:bg-gray-900 font-sans font-bold text-gray-700 dark:text-gray-300">
                                <td colspan="{{ $periods->count() + 1 }}" class="p-2.5 uppercase tracking-wide text-[11px] sticky left-0 bg-gray-100 dark:bg-gray-900">
                                    2. Process Breakdown (Biaya Proses Manufaktur & Perakitan)
                                </td>
                            </tr>

                            @foreach($this->matrix['process_rows'] as $procRow)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/50">
                                    <td class="p-2.5 font-sans font-medium text-gray-800 dark:text-gray-200 sticky left-0 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 shadow-[2px_0_5px_rgba(0,0,0,0.03)]">
                                        {{ $procRow['name'] }}
                                    </td>
                                    @foreach($periods as $p)
                                        @php
                                            $item = $procRow['periods'][$p->id] ?? null;
                                        @endphp
                                        <td class="p-2.5 text-right border-r border-gray-200 dark:border-gray-700">
                                            @if($item)
                                                <div class="font-bold text-gray-900 dark:text-white">
                                                    Rp {{ number_format((float) ($item['price'] ?? 0), 2, ',', '.') }}
                                                </div>
                                                @if(isset($item['man_hour']) && (float) $item['man_hour'] > 0)
                                                    <div class="text-[10px] text-gray-400 font-sans">
                                                        MH: {{ $item['man_hour'] }}s × Rp {{ $item['charge'] ?? 0 }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach

                            {{-- Process Total --}}
                            <tr class="bg-sky-50/60 dark:bg-sky-950/20 font-bold border-t-2 border-b-2 border-sky-200 dark:border-sky-900 text-sky-950 dark:text-sky-200">
                                <td class="p-2.5 font-sans sticky left-0 bg-sky-100/90 dark:bg-sky-950 border-r border-sky-200 dark:border-sky-800">
                                    (2) : Process Total
                                </td>
                                @foreach($periods as $p)
                                    <td class="p-2.5 text-right border-r border-sky-200 dark:border-sky-900 text-sm">
                                        Rp {{ number_format((float) $p->process_total, 2, ',', '.') }}
                                    </td>
                                @endforeach
                            </tr>

                            {{-- (3) Subtotal = (1) + (2) --}}
                            <tr class="bg-indigo-50/70 dark:bg-indigo-950/30 font-bold border-b border-indigo-200 dark:border-indigo-900 text-indigo-950 dark:text-indigo-200">
                                <td class="p-2.5 font-sans sticky left-0 bg-indigo-100/90 dark:bg-indigo-950 border-r border-indigo-200 dark:border-indigo-800">
                                    (3) : Subtotal = (1) + (2)
                                </td>
                                @foreach($periods as $p)
                                    <td class="p-2.5 text-right border-r border-indigo-200 dark:border-indigo-900 text-sm font-black">
                                        Rp {{ number_format((float) $p->subtotal, 2, ',', '.') }}
                                    </td>
                                @endforeach
                            </tr>

                            {{-- (4) O/H & Profit supplied portion --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="p-2.5 font-sans sticky left-0 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
                                    (4) : O/H & Profit supplied portion
                                </td>
                                @foreach($periods as $p)
                                    <td class="p-2.5 text-right border-r border-gray-200 dark:border-gray-700">
                                        Rp {{ number_format((float) $p->overhead_supplied_cost, 2, ',', '.') }}
                                        @if((float) $p->overhead_supplied_percent > 0)
                                            <div class="text-[10px] text-gray-400 font-sans">({{ $p->overhead_supplied_percent }}%)</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>

                            {{-- (5) Profit / Self-procuring portion --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="p-2.5 font-sans sticky left-0 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
                                    (5) : O/H & Profit self portion / Profit
                                </td>
                                @foreach($periods as $p)
                                    <td class="p-2.5 text-right border-r border-gray-200 dark:border-gray-700">
                                        @php
                                            $selfCost = (float) $p->profit_cost > 0 ? (float) $p->profit_cost : (float) $p->overhead_self_cost;
                                            $selfPct = (float) $p->profit_percent > 0 ? $p->profit_percent : $p->overhead_self_percent;
                                        @endphp
                                        Rp {{ number_format($selfCost, 2, ',', '.') }}
                                        @if((float) $selfPct > 0)
                                            <div class="text-[10px] text-gray-400 font-sans">({{ $selfPct }}%)</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>

                            {{-- (7) Packing --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="p-2.5 font-sans sticky left-0 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
                                    (7) : Packing
                                </td>
                                @foreach($periods as $p)
                                    <td class="p-2.5 text-right border-r border-gray-200 dark:border-gray-700">
                                        Rp {{ number_format((float) $p->packing_cost, 2, ',', '.') }}
                                    </td>
                                @endforeach
                            </tr>

                            {{-- (8) Part Cost (Excluding tooling) --}}
                            <tr class="bg-emerald-50/80 dark:bg-emerald-950/30 font-bold border-t-2 border-b-2 border-emerald-300 dark:border-emerald-800 text-emerald-950 dark:text-emerald-200">
                                <td class="p-3 font-sans sticky left-0 bg-emerald-100 dark:bg-emerald-950 border-r border-emerald-300 dark:border-emerald-800 text-sm">
                                    (8) : Part Cost (Final Price / Pcs)
                                </td>
                                @foreach($periods as $p)
                                    <td class="p-3 text-right border-r border-emerald-300 dark:border-emerald-800 text-base font-black">
                                        Rp {{ number_format((float) $p->part_cost, 2, ',', '.') }}
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Difference vs Previous Quarter --}}
                            <tr class="bg-gray-50 dark:bg-gray-900 font-semibold border-b border-gray-200 dark:border-gray-700">
                                <td class="p-2.5 font-sans sticky left-0 bg-gray-100 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700">
                                    Difference (Selisih vs Kuartal Lalu)
                                </td>
                                @foreach($periods as $idx => $p)
                                    <td class="p-2.5 text-right border-r border-gray-200 dark:border-gray-700">
                                        @if($idx === 0 || (float) $p->cost_difference == 0.0)
                                            <span class="text-gray-400 font-normal">Baseline</span>
                                        @elseif((float) $p->cost_difference > 0)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                +Rp {{ number_format((float) $p->cost_difference, 2, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                -Rp {{ number_format(abs((float) $p->cost_difference), 2, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>

                        </tbody>
                    </table>
                </div>

                {{-- Table Footer Remarks --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-900/60 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-700 dark:text-gray-300">Catatan Resmi:</span>
                        <span>Seluruh perhitungan matriks eskalasi kuartalan tersinkronisasi otomatis dengan pergerakan kurs dan fluktuasi bahan baku baja.</span>
                    </div>
                </div>

            </div>
        @endif

    </div>
</x-filament-panels::page>
