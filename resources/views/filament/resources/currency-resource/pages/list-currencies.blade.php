<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        <!-- Home Card: Periode & Patokan Kurs Mata Uang (USD) & Gap AP -->
        <div class="bg-white dark:bg-gray-900 border-2 border-blue-500 dark:border-blue-600 rounded-2xl p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-4">
                    <!-- Pilih Tahun -->
                    <div class="w-44 sm:w-48">
                        <label class="block text-sm font-semibold text-gray-900 dark:text-gray-200 mb-1.5">
                            Pilih Tahun
                        </label>
                        <select 
                            wire:model.live="selectedYear"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm font-bold shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2.5 px-3"
                        >
                            @foreach ($this->years as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Kuartal -->
                    <div class="w-36 sm:w-40">
                        <label class="block text-sm font-semibold text-gray-900 dark:text-gray-200 mb-1.5">
                            Kuartal
                        </label>
                        <select 
                            wire:model.live="selectedQuarter"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm font-bold shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2.5 px-3"
                        >
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>
                    </div>
                </div>

                @if ($lastSavedAt)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 self-start sm:self-center">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        <span>Tersimpan Otomatis ({{ $lastSavedAt }})</span>
                    </span>
                @endif
            </div>

            <!-- Row 2: Gap AP ($/MT), Gap CRC AP ($/MT), Kurs USD (Rp) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <!-- Gap AP ($/MT) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-900 dark:text-gray-200 mb-1.5">
                        Gap AP ($/MT)
                    </label>
                    <input 
                        type="number"
                        step="0.0001"
                        wire:model.blur="gapApUsd"
                        placeholder="0"
                        class="block w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-base font-bold shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2.5 px-3.5"
                    />
                </div>

                <!-- Gap CRC AP ($/MT) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-900 dark:text-gray-200 mb-1.5">
                        Gap CRC AP ($/MT)
                    </label>
                    <input 
                        type="number"
                        step="0.0001"
                        wire:model.blur="gapCrcApUsd"
                        placeholder="0"
                        class="block w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-base font-bold shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2.5 px-3.5"
                    />
                </div>

                <!-- Kurs USD (Rp) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-900 dark:text-gray-200 mb-1.5">
                        Kurs USD (Rp)
                    </label>
                    <input 
                        type="number"
                        step="0.0001"
                        wire:model.blur="rateUsd"
                        placeholder="16517"
                        required
                        class="block w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-base font-bold shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2.5 px-3.5"
                    />
                </div>
            </div>
        </div>

        <!-- Master Data Table Card for Selected Year & Quarter -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
            <div class="mb-5 border-b border-gray-100 dark:border-gray-800 pb-3">
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-table-cells class="w-5 h-5 text-primary-600" />
                    <span>Rincian Data Master Periode {{ $selectedQuarter }} {{ $selectedYear }}</span>
                </h3>
            </div>

            <!-- Filter Bar / Kolom: Semua & Nama Bagian Master Data -->
            <div class="flex items-center gap-2.5 overflow-x-auto pb-3 mb-4 border-b border-gray-100 dark:border-gray-800 scrollbar-thin">
                @php
                    $counts = $this->masterDataCounts;
                    $tabs = [
                        'all' => ['label' => 'Semua', 'count' => $counts['all']],
                        'steel_ap' => ['label' => 'Material Steel AP', 'count' => $counts['steel_ap']],
                        'steel_ar' => ['label' => 'Material Steel AR', 'count' => $counts['steel_ar']],
                        'stamping' => ['label' => 'Stamping Part', 'count' => $counts['stamping']],
                        'fastener' => ['label' => 'Fastener', 'count' => $counts['fastener']],
                        'sub_assembly' => ['label' => 'Sub Assembly Part', 'count' => $counts['sub_assembly']],
                    ];
                @endphp

                @foreach ($tabs as $key => $tab)
                    <button
                        type="button"
                        wire:click="$set('selectedMasterTab', '{{ $key }}')"
                        @class([
                            'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all whitespace-nowrap',
                            'bg-primary-600 text-white shadow-sm ring-1 ring-primary-600' => $selectedMasterTab === $key,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $selectedMasterTab !== $key,
                        ])
                    >
                        <span>{{ $tab['label'] }}</span>
                        <span @class([
                            'px-2.5 py-0.5 rounded-full text-xs font-bold',
                            'bg-white/20 text-white' => $selectedMasterTab === $key,
                            'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300' => $selectedMasterTab !== $key,
                        ])>
                            {{ $tab['count'] }}
                        </span>
                    </button>
                @endforeach
            </div>

            <!-- Data Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/60 font-semibold text-gray-700 dark:text-gray-200 text-sm">
                        <tr>
                            <th scope="col" class="py-3.5 px-3.5 text-center w-12 font-semibold">No</th>
                            <th scope="col" class="py-3.5 px-3.5 font-semibold">Master Data</th>
                            <th scope="col" class="py-3.5 px-3.5 font-semibold">Part No / Spec</th>
                            <th scope="col" class="py-3.5 px-3.5 font-semibold">Nama Part / Deskripsi</th>
                            <th scope="col" class="py-3.5 px-3.5 font-semibold">Ukuran / Thickness</th>
                            <th scope="col" class="py-3.5 px-3.5 font-semibold">Supplier</th>
                            <th scope="col" class="py-3.5 px-3.5 text-right font-semibold">Kurs ($)</th>
                            <th scope="col" class="py-3.5 px-3.5 text-right font-semibold">Gap ($/MT)</th>
                            <th scope="col" class="py-3.5 px-3.5 text-right font-semibold">Harga ($)</th>
                            <th scope="col" class="py-3.5 px-3.5 text-right font-semibold">Harga (Rp)</th>
                            <th scope="col" class="py-3.5 px-3.5 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 text-sm">
                        @php
                            $records = $this->masterDataRecords;
                        @endphp
                        @forelse ($records as $index => $row)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40 transition-colors">
                                <td class="py-3.5 px-3.5 text-center text-gray-400 font-mono text-sm">{{ $index + 1 }}</td>
                                <td class="py-3.5 px-3.5">
                                    <span @class([
                                        'inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold',
                                        'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800' => $row['badge_color'] === 'blue',
                                        'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800' => $row['badge_color'] === 'amber',
                                        'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800' => $row['badge_color'] === 'purple',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' => $row['badge_color'] === 'emerald',
                                        'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800' => $row['badge_color'] === 'indigo',
                                    ])>
                                        {{ $row['category'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-3.5 font-semibold text-gray-900 dark:text-white text-sm">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span>{{ $row['code_spec'] }}</span>
                                        @if (! empty($row['steel_category']))
                                            <span @class([
                                                'px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' => $row['steel_category'] === 'HRC',
                                                'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/60 dark:text-cyan-300' => $row['steel_category'] === 'CRC',
                                            ])>
                                                {{ $row['steel_category'] }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3.5 px-3.5 text-gray-800 dark:text-gray-200 text-sm">{{ $row['name_desc'] }}</td>
                                <td class="py-3.5 px-3.5 text-gray-600 dark:text-gray-300 font-mono text-sm">{{ $row['thickness_size'] }}</td>
                                <td class="py-3.5 px-3.5 font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $row['supplier'] }}</td>
                                <td class="py-3.5 px-3.5 text-right font-mono text-gray-900 dark:text-gray-100 text-sm">Rp {{ number_format($row['rate'], 0, ',', '.') }}</td>
                                <td class="py-3.5 px-3.5 text-right font-mono text-sm">
                                    @if ($row['gap'] !== null)
                                        <span class="{{ $row['gap'] < 0 ? 'text-red-600 dark:text-red-400 font-medium' : ($row['gap'] > 0 ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-gray-500') }}">
                                            ${{ number_format($row['gap'], 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-3.5 text-right font-mono font-semibold text-gray-900 dark:text-white text-sm">
                                    @if ($row['price_usd'] > 0)
                                        ${{ number_format($row['price_usd'], 4) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-3.5 text-right font-mono font-bold text-primary-700 dark:text-primary-300 text-sm">
                                    @if ($row['price_idr'] > 0)
                                        Rp {{ number_format($row['price_idr'], 0, ',', '.') }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-3.5 text-center">
                                    @if (! empty($row['view_url']))
                                        <a 
                                            href="{{ $row['view_url'] }}" 
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-600 hover:text-primary-700 hover:underline py-1 px-2 rounded hover:bg-primary-50 dark:hover:bg-primary-950/40 transition-colors"
                                        >
                                            <span>Buka</span>
                                            <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="py-10 text-center text-gray-400">
                                    <x-heroicon-o-inbox class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-2.5" />
                                    <p class="font-medium text-sm text-gray-500">Tidak ada data master pada periode {{ $selectedQuarter }} {{ $selectedYear }}.</p>
                                    <p class="text-xs text-gray-400 mt-1">Data yang dibuat di menu master data terkait akan otomatis muncul di sini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
