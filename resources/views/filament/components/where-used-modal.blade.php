<div class="space-y-4">
    <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-lg border border-blue-200 dark:border-blue-900 text-sm">
        <p><strong>Material Spec:</strong> {{ $record->spec }} ({{ $record->main_category }} - {{ $record->sub_category }})</p>
        <p><strong>Basic Rate:</strong> {{ $record->currency?->symbol ?? '$' }} {{ number_format($record->basic_rate, 4) }} / {{ $record->unit }}</p>
        <p><strong>Supplier:</strong> {{ $record->supplier?->name ?? 'Belum ditentukan' }}</p>
    </div>

    @if($parts->isEmpty())
        <div class="text-center py-6 text-gray-500">
            <p>Belum ada Part Number yang terdaftar menggunakan material ini.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-100 dark:bg-gray-800 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">
                    <tr>
                        <th class="px-3 py-2">Part Number</th>
                        <th class="px-3 py-2">Nama Part</th>
                        <th class="px-3 py-2">Kategori</th>
                        <th class="px-3 py-2">Berat (kg)</th>
                        <th class="px-3 py-2">Induk Assembly</th>
                        <th class="px-3 py-2">Harga Terkini</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($parts as $part)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-3 py-2 font-mono font-bold text-primary-600">{{ $part->part_number }}</td>
                            <td class="px-3 py-2">{{ $part->part_name }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $part->main_category === 'Steel' ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $part->main_category }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ number_format($part->weight, 4) }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">
                                @forelse($part->parentBoms as $pb)
                                    <div>↳ {{ $pb->parentPart?->part_name }} ({{ $pb->parentPart?->part_number }})</div>
                                @empty
                                    <span class="text-gray-400">Standalone / Top</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-2 font-semibold text-emerald-600">
                                @php
                                    $activeQuo = $part->partQuotations->where('status', 'active')->first();
                                @endphp
                                {{ $activeQuo ? 'Rp ' . number_format($activeQuo->total_cost_idr, 2, ',', '.') : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">Total ditemukan: <strong>{{ $parts->count() }} Part Number</strong> yang menggunakan material ini.</p>
    @endif
</div>

