<div class="space-y-6">
    <div class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 rounded-xl border border-blue-200 dark:border-gray-700 shadow-sm">
        <div class="flex items-center space-x-3">
            <div class="p-2 bg-blue-600 text-white rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ $record->part_name }}</h3>
                <p class="text-xs text-gray-500 font-mono">Part Number: <strong>{{ $record->part_number }}</strong> | Tipe: {{ $record->type }} ({{ $record->main_category }})</p>
            </div>
        </div>
    </div>

    @php
        $tree = $record->getBomTreeArray();
    @endphp

    <div class="bom-hierarchy space-y-3 font-sans">
        {{-- Level 0: Top Assembly --}}
        <div class="border-2 border-blue-500 rounded-lg p-3 bg-white dark:bg-gray-800 shadow">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 bg-blue-600 text-white text-xs font-bold rounded">LEVEL 0 (Top Assembly)</span>
                    <span class="font-mono font-bold text-blue-700 dark:text-blue-400">{{ $tree['part_number'] }}</span>
                    <span class="text-gray-700 dark:text-gray-300 font-semibold">— {{ $tree['part_name'] }}</span>
                </div>
                <div class="text-xs text-gray-500">
                    Total Berat: <strong>{{ number_format($tree['weight'], 2) }} kg</strong>
                </div>
            </div>

            {{-- Children (Level 1) --}}
            @if(!empty($tree['children']))
                <div class="mt-4 pl-6 border-l-2 border-dashed border-blue-300 dark:border-blue-700 space-y-3">
                    @foreach($tree['children'] as $childL1)
                        <div class="border border-indigo-200 dark:border-indigo-800 rounded-lg p-3 bg-indigo-50/40 dark:bg-indigo-950/30">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 bg-indigo-600 text-white text-xs font-bold rounded">LEVEL 1</span>
                                    <span class="font-mono font-bold text-indigo-700 dark:text-indigo-400">{{ $childL1['part_number'] }}</span>
                                    <span class="text-gray-800 dark:text-gray-200 font-medium">— {{ $childL1['part_name'] }}</span>
                                    <span class="text-xs bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded font-bold">Qty: {{ (float)$childL1['quantity'] }} {{ $childL1['unit'] }}</span>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                    Kategori: <span class="font-semibold">{{ $childL1['main_category'] }} ({{ $childL1['sub_category'] }})</span>
                                </div>
                            </div>

                            {{-- Children (Level 2) --}}
                            @if(!empty($childL1['children']))
                                <div class="mt-3 pl-6 border-l-2 border-dashed border-indigo-300 dark:border-indigo-700 space-y-2">
                                    @foreach($childL1['children'] as $childL2)
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-2.5 bg-white dark:bg-gray-800 flex items-center justify-between hover:border-gray-400 transition">
                                            <div class="flex items-center space-x-2">
                                                <span class="px-1.5 py-0.5 bg-emerald-600 text-white text-[10px] font-bold rounded">LEVEL 2</span>
                                                <span class="font-mono font-bold text-emerald-700 dark:text-emerald-400 text-xs">{{ $childL2['part_number'] }}</span>
                                                <span class="text-xs text-gray-800 dark:text-gray-200 font-medium">— {{ $childL2['part_name'] }}</span>
                                                <span class="text-[11px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded text-gray-700 dark:text-gray-300 font-semibold">Qty: {{ (float)$childL2['quantity'] }}</span>
                                            </div>
                                            <div class="text-right text-xs">
                                                <div class="text-gray-500 text-[11px]">Material Dasar:</div>
                                                <div class="font-semibold text-primary-600 dark:text-primary-400">{{ $childL2['material_spec'] }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-2 text-xs text-gray-400 italic">
                    Part ini adalah komponen tunggal (tidak memiliki sub-komponen BOM).
                </div>
            @endif
        </div>
    </div>
</div>

