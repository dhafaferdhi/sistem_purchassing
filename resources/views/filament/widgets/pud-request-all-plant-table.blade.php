<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Request All Plant
        </x-slot>
        <x-slot name="description">
            Rincian request per Plant, Division, dan Month — Tahun {{ $year }}
        </x-slot>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800 mt-2">
            <table class="w-full text-xs text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                        <th class="py-2.5 px-3">Plant / Division</th>
                        @foreach($months as $m)
                            <th class="py-2.5 px-2 text-center">{{ $m }}</th>
                        @endforeach
                        <th class="py-2.5 px-3 text-center bg-gray-100 dark:bg-gray-800">Total 12 Bulan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-[11px]">
                    @foreach($plants as $plant)
                        @php
                            $pTotal = $plantTotals[$plant] ?? 0;
                            $pMonthCounts = $plantMonthCounts[$plant] ?? [];
                            $divs = $plantDivisions[$plant] ?? [];
                            sort($divs);
                        @endphp
                        {{-- Plant Summary Header Row --}}
                        <tr class="bg-blue-50/70 dark:bg-blue-950/30 font-bold text-primary-900 dark:text-primary-200">
                            <td class="py-2 px-3 flex items-center gap-1.5 font-bold">
                                <span>🏢</span>
                                <span>{{ $plant }}</span>
                            </td>
                            @for($m = 1; $m <= 12; $m++)
                                <td class="py-2 px-2 text-center font-bold">{{ $pMonthCounts[$m] ?? 0 }}</td>
                            @endfor
                            <td class="py-2 px-3 text-center font-extrabold bg-blue-100/60 dark:bg-blue-900/40 text-primary-800 dark:text-primary-300">
                                {{ $pTotal }}
                            </td>
                        </tr>

                        {{-- Division Rows for this Plant --}}
                        @foreach($divs as $div)
                            @php
                                $dTotal = $divisionTotals[$plant][$div] ?? 0;
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                                <td class="py-1.5 px-3 pl-7 text-gray-700 dark:text-gray-300">
                                    ↳ {{ $div }}
                                </td>
                                @for($m = 1; $m <= 12; $m++)
                                    @php
                                        $dCount = $divisionMonthCounts[$plant][$div][$m] ?? 0;
                                    @endphp
                                    <td class="py-1.5 px-2 text-center {{ $dCount > 0 ? 'font-bold text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-600' }}">
                                        {{ $dCount }}
                                    </td>
                                @endfor
                                <td class="py-1.5 px-3 text-center font-bold bg-gray-50/80 dark:bg-gray-800/40 {{ $dTotal > 0 ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400' }}">
                                    {{ $dTotal }}
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

