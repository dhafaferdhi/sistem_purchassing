<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Selector Part --}}
        <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                Pilih Part Number untuk Komparasi:
            </label>
            <select wire:model.live="selectedPartId" class="w-full md:w-1/2 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @foreach(\App\Models\Part::all() as $part)
                    <option value="{{ $part->id }}">
                        {{ $part->part_number }} — {{ $part->part_name }} ({{ $part->main_category }} - {{ $part->sub_category }})
                    </option>
                @endforeach
            </select>
        </div>

        @if($this->selectedPart)
            {{-- Header Part Info --}}
            <div class="p-5 bg-gradient-to-r from-blue-900 to-indigo-900 text-white rounded-xl shadow-md">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="px-2.5 py-1 bg-blue-500 text-xs font-bold rounded-md uppercase tracking-wide">
                            {{ $this->selectedPart->main_category }} • {{ $this->selectedPart->sub_category }}
                        </span>
                        <h2 class="text-2xl font-black mt-2 font-mono">{{ $this->selectedPart->part_number }}</h2>
                        <p class="text-blue-200 text-sm font-medium">{{ $this->selectedPart->part_name }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <div class="text-blue-200">Raw Material Spec:</div>
                        <div class="font-bold text-lg text-white">{{ $this->selectedPart->material?->spec ?? 'Assembly / Multi-Material' }}</div>
                        <div class="text-xs text-blue-300 mt-1">Berat Komponen: <strong>{{ number_format($this->selectedPart->weight, 4) }} kg</strong></div>
                    </div>
                </div>
            </div>

            @php
                $quotes = $this->quotations;
                $lowestCost = $quotes->min('total_cost_idr');
            @endphp

            @if($quotes->isEmpty())
                <div class="p-8 text-center bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                    <p class="text-gray-500">Belum ada penawaran harga supplier yang tercatat untuk part ini.</p>
                </div>
            @else
                {{-- Comparison Grid / Matrix --}}
                <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700">
                                <th class="p-4 w-1/4 font-bold uppercase text-xs tracking-wider text-gray-500">
                                    Komponen Biaya (Cost Breakdown)
                                </th>
                                @foreach($quotes as $q)
                                    <th class="p-4 text-center border-l border-gray-200 dark:border-gray-700 {{ $q->total_cost_idr == $lowestCost ? 'bg-emerald-50/60 dark:bg-emerald-950/40' : '' }}">
                                        <div class="font-bold text-base text-gray-900 dark:text-white">{{ $q->supplier?->name }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $q->supplier?->code }}</div>
                                        @if($q->total_cost_idr == $lowestCost)
                                            <div class="mt-2 inline-block px-2 py-0.5 bg-emerald-600 text-white text-[11px] font-extrabold rounded-full animate-pulse shadow-sm">
                                                ★ BEST PRICE (TERMURAH)
                                            </div>
                                        @endif
                                        @if($q->is_selected)
                                            <div class="mt-1 text-xs text-blue-600 dark:text-blue-400 font-bold">
                                                ✓ Supplier Terpilih
                                            </div>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            {{-- Mata Uang & Kurs --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="p-3 font-semibold text-gray-600 dark:text-gray-300">Mata Uang & Kurs (IDR)</td>
                                @foreach($quotes as $q)
                                    <td class="p-3 text-center border-l border-gray-200 dark:border-gray-700 font-mono text-xs">
                                        {{ $q->currency?->code }} (1 {{ $q->currency?->code }} = Rp {{ number_format($q->exchange_rate_to_idr, 0, ',', '.') }})
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Material Cost --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="p-3 font-semibold text-gray-600 dark:text-gray-300">
                                    1. Biaya Material (Bahan Baku Dasar)
                                </td>
                                @foreach($quotes as $q)
                                    <td class="p-3 text-center border-l border-gray-200 dark:border-gray-700">
                                        <div class="font-bold text-gray-900 dark:text-gray-100">Rp {{ number_format($q->material_cost_idr, 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-400">({{ $q->currency?->symbol }} {{ number_format($q->material_cost_original, 4) }})</div>
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Process / Machine Cost --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="p-3 font-semibold text-gray-600 dark:text-gray-300">
                                    2. Biaya Mesin / Proses (Machine Rate)
                                </td>
                                @foreach($quotes as $q)
                                    <td class="p-3 text-center border-l border-gray-200 dark:border-gray-700">
                                        <div class="font-bold text-gray-900 dark:text-gray-100">Rp {{ number_format($q->machine_cost_idr, 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-400">({{ $q->currency?->symbol }} {{ number_format($q->machine_cost_original, 4) }})</div>
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Manpower Cost --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="p-3 font-semibold text-gray-600 dark:text-gray-300">
                                    3. Biaya Tenaga Kerja (Manpower Operator)
                                </td>
                                @foreach($quotes as $q)
                                    <td class="p-3 text-center border-l border-gray-200 dark:border-gray-700 font-medium">
                                        Rp {{ number_format($q->manpower_cost_idr, 2, ',', '.') }}
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Packaging Cost --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="p-3 font-semibold text-gray-600 dark:text-gray-300">
                                    4. Packaging (Kemasan / Palet / Plastik)
                                </td>
                                @foreach($quotes as $q)
                                    <td class="p-3 text-center border-l border-gray-200 dark:border-gray-700">
                                        Rp {{ number_format($q->packaging_cost_idr, 2, ',', '.') }}
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Transport Cost --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="p-3 font-semibold text-gray-600 dark:text-gray-300">
                                    5. Transport (Pengiriman Logistik)
                                </td>
                                @foreach($quotes as $q)
                                    <td class="p-3 text-center border-l border-gray-200 dark:border-gray-700">
                                        Rp {{ number_format($q->transport_cost_idr, 2, ',', '.') }}
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Tooling / Mold / Dies --}}
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 bg-amber-50/30 dark:bg-amber-950/20">
                                <td class="p-3 font-semibold text-gray-700 dark:text-gray-200">
                                    6. Tooling (Mold / Dies) & Amortisasi
                                </td>
                                @foreach($quotes as $q)
                                    <td class="p-3 text-center border-l border-gray-200 dark:border-gray-700">
                                        @if($q->tooling_cost_total > 0)
                                            <div class="text-xs font-semibold text-amber-700 dark:text-amber-400">
                                                {{ $q->tooling_type }} (Rp {{ number_format($q->tooling_cost_total, 0, ',', '.') }})
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $q->tooling_payment_type === 'Amortisasi' ? 'Amortisasi: Rp ' . number_format($q->tooling_cost_per_piece, 2, ',', '.') . ' / pcs' : 'Dibayar Lumpsum di Awal' }}
                                            </div>
                                        @else
                                            <span class="text-gray-400 text-xs">Tidak ada tooling</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>

                            {{-- GRAND TOTAL COST --}}
                            <tr class="bg-gray-100 dark:bg-gray-900 border-t-2 border-gray-300 dark:border-gray-600">
                                <td class="p-4 font-black text-gray-900 dark:text-white text-base">
                                    TOTAL HARGA PER PIECE (HPP IDR)
                                </td>
                                @foreach($quotes as $q)
                                    <td class="p-4 text-center border-l border-gray-200 dark:border-gray-700 {{ $q->total_cost_idr == $lowestCost ? 'bg-emerald-100/70 dark:bg-emerald-950/70' : '' }}">
                                        <div class="text-xl font-black {{ $q->total_cost_idr == $lowestCost ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-900 dark:text-white' }}">
                                            Rp {{ number_format($q->total_cost_idr, 2, ',', '.') }}
                                        </div>
                                        @if($q->total_cost_idr > $lowestCost)
                                            <div class="text-xs text-rose-500 font-semibold mt-1">
                                                + Rp {{ number_format($q->total_cost_idr - $lowestCost, 2, ',', '.') }} (+{{ number_format((($q->total_cost_idr - $lowestCost) / $lowestCost) * 100, 1) }}%)
                                            </div>
                                        @else
                                            <div class="text-xs text-emerald-600 font-semibold mt-1">
                                                Penghematan Terbaik
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Action Pilih Pemenang & Terbitkan PO --}}
                            <tr class="bg-white dark:bg-gray-800">
                                <td class="p-3 font-semibold text-gray-500 text-xs">Keputusan Purchasing</td>
                                @foreach($quotes as $q)
                                    <td class="p-3 text-center border-l border-gray-200 dark:border-gray-700">
                                        <div class="flex flex-col items-center gap-2">
                                            @if($q->is_selected)
                                                <button disabled class="w-full px-3 py-1 bg-blue-600 text-white text-xs font-bold rounded-lg shadow-sm cursor-default">
                                                    ✓ Supplier Terpilih
                                                </button>
                                            @else
                                                <button wire:click="selectWinningSupplier({{ $q->id }})" wire:loading.attr="disabled" class="w-full px-3 py-1 bg-gray-100 hover:bg-emerald-600 hover:text-white text-gray-700 text-xs font-bold rounded-lg transition border border-gray-300 hover:border-emerald-600">
                                                    Pilih Supplier Ini
                                                </button>
                                            @endif

                                            <button wire:click="generatePurchaseOrder({{ $q->id }})" wire:loading.attr="disabled" class="w-full px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition flex items-center justify-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                Terbitkan PO
                                            </button>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

    </div>
</x-filament-panels::page>

