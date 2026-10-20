<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Selector Part --}}
        <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                Pilih Part Number untuk Analisis Tren Harga:
            </label>
            <select wire:model.live="selectedPartId" class="w-full md:w-1/2 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                @foreach(\App\Models\Part::all() as $part)
                    <option value="{{ $part->id }}">
                        {{ $part->item_code ?? $part->part_number }} — {{ $part->description ?? $part->part_name }} ({{ $part->main_category }} - {{ $part->sub_category }})
                    </option>
                @endforeach
            </select>
        </div>

        @if($this->selectedPart)
            {{-- Header Part Info --}}
            <div class="p-5 bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-900 text-white rounded-xl shadow-md">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="px-2.5 py-1 bg-indigo-500 text-xs font-bold rounded-md uppercase tracking-wide">
                            {{ $this->selectedPart->main_category }} • {{ $this->selectedPart->sub_category }}
                        </span>
                        <h2 class="text-2xl font-black mt-2 font-mono">{{ $this->selectedPart->item_code ?? $this->selectedPart->part_number }}</h2>
                        <p class="text-indigo-200 text-sm font-medium">{{ $this->selectedPart->description ?? $this->selectedPart->part_name }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <div class="text-indigo-200">Spesifikasi Material:</div>
                        <div class="font-bold text-lg text-white">{{ $this->selectedPart->material?->spec ?? 'Assembly / Komponen Khusus' }}</div>
                        <div class="text-xs text-indigo-300 mt-1">Berat Komponen: <strong>{{ number_format($this->selectedPart->weight, 4) }} kg</strong></div>
                    </div>
                </div>
            </div>

            @php
                $metrics = $this->metrics;
                $histories = $this->priceHistories;
            @endphp

            @if(!$metrics['has_data'])
                <div class="p-8 text-center bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                    <p class="text-gray-500">Belum ada catatan riwayat harga untuk part ini. Riwayat harga akan tercatat otomatis saat Purchase Order diterbitkan.</p>
                </div>
            @else
                {{-- KPI Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                        <span class="text-xs font-bold text-gray-500 uppercase">Harga Pembelian Terakhir</span>
                        <div class="text-2xl font-black text-gray-900 dark:text-white mt-1">
                            Rp {{ number_format($metrics['latest_price'], 2, ',', '.') }}
                        </div>
                        <span class="text-xs text-gray-400">Harga aktif saat ini</span>
                    </div>

                    <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase">Harga Terendah (Best Price)</span>
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
                            Rp {{ number_format($metrics['min_price'], 2, ',', '.') }}
                        </div>
                        <span class="text-xs text-gray-400">Paling efisien pernah dicapai</span>
                    </div>

                    <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                        <span class="text-xs font-bold text-rose-600 dark:text-rose-400 uppercase">Harga Tertinggi (Peak)</span>
                        <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">
                            Rp {{ number_format($metrics['max_price'], 2, ',', '.') }}
                        </div>
                        <span class="text-xs text-gray-400">Titik harga tertinggi tercatat</span>
                    </div>

                    <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                        <span class="text-xs font-bold text-gray-500 uppercase">Fluktuasi Sejak Awal</span>
                        <div class="text-2xl font-black {{ $metrics['overall_change'] > 0 ? 'text-rose-500' : ($metrics['overall_change'] < 0 ? 'text-emerald-500' : 'text-blue-500') }} mt-1">
                            {{ $metrics['overall_change'] > 0 ? '+' : '' }}{{ number_format($metrics['overall_change'], 2) }}%
                        </div>
                        <span class="text-xs text-gray-400">{{ $metrics['overall_change'] > 0 ? 'Kenaikan total' : ($metrics['overall_change'] < 0 ? 'Penurunan / Efisiensi' : 'Stabil') }}</span>
                    </div>
                </div>

                {{-- Visual Trend Chart (SVG Line Graph) --}}
                <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                        Grafik Garis Tren Pergerakan Harga Beli (Rp)
                    </h3>

                    @php
                        $count = $histories->count();
                        $minP = max(1, $metrics['min_price'] * 0.9);
                        $maxP = max(2, $metrics['max_price'] * 1.1);
                        $range = max(1, $maxP - $minP);
                        $width = 700;
                        $height = 200;
                        $points = [];

                        foreach ($histories as $idx => $h) {
                            $x = $count > 1 ? round(($idx / ($count - 1)) * ($width - 80)) + 40 : ($width / 2);
                            $y = round($height - 30 - ((($h->unit_price_idr - $minP) / $range) * ($height - 60)));
                            $points[] = ['x' => $x, 'y' => $y, 'price' => $h->unit_price_idr, 'date' => $h->price_date->format('d/m/y'), 'source' => $h->source_type];
                        }

                        $polyPoints = implode(' ', array_map(fn($p) => "{$p['x']},{$p['y']}", $points));
                    @endphp

                    <div class="overflow-x-auto">
                        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full h-56 font-sans">
                            <!-- Background Grid Lines -->
                            <line x1="40" y1="20" x2="{{ $width - 40 }}" y2="20" stroke="#e2e8f0" stroke-dasharray="3,3" />
                            <line x1="40" y1="{{ $height / 2 }}" x2="{{ $width - 40 }}" y2="{{ $height / 2 }}" stroke="#e2e8f0" stroke-dasharray="3,3" />
                            <line x1="40" y1="{{ $height - 30 }}" x2="{{ $width - 40 }}" y2="{{ $height - 30 }}" stroke="#cbd5e1" stroke-width="1.5" />

                            <!-- Trend Line -->
                            @if(count($points) > 1)
                                <polyline fill="none" stroke="#6366f1" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="{{ $polyPoints }}" />
                            @endif

                            <!-- Data Points & Labels -->
                            @foreach($points as $pt)
                                <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="5" fill="#4f46e5" stroke="#ffffff" stroke-width="2" />
                                <text x="{{ $pt['x'] }}" y="{{ $pt['y'] - 10 }}" text-anchor="middle" font-size="11" font-weight="bold" fill="#334155">
                                    Rp {{ number_format($pt['price'], 0, ',', '.') }}
                                </text>
                                <text x="{{ $pt['x'] }}" y="{{ $height - 12 }}" text-anchor="middle" font-size="10" fill="#64748b">
                                    {{ $pt['date'] }}
                                </text>
                            @endforeach
                        </svg>
                    </div>
                </div>

                {{-- Detail Table Log --}}
                <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 font-bold text-sm text-gray-700 dark:text-gray-200">
                        Buku Log Riwayat Perubahan Harga (Audit Trail)
                    </div>
                    <table class="w-full text-sm text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300 text-xs uppercase border-b border-gray-200 dark:border-gray-700">
                                <th class="p-3">Tanggal</th>
                                <th class="p-3">Sumber / Dokumen</th>
                                <th class="p-3">No. Referensi</th>
                                <th class="p-3">Supplier / Vendor</th>
                                <th class="p-3 text-right">Harga Satuan (IDR)</th>
                                <th class="p-3 text-center">Perubahan (%)</th>
                                <th class="p-3">Catatan / Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($histories->reverse() as $h)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                    <td class="p-3 font-medium">{{ $h->price_date->format('d M Y') }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $h->source_type === 'Purchase Order' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $h->source_type }}
                                        </span>
                                    </td>
                                    <td class="p-3 font-mono font-bold">{{ $h->reference_number ?? '-' }}</td>
                                    <td class="p-3">{{ $h->supplier?->name ?? 'Internal / Estimasi' }}</td>
                                    <td class="p-3 text-right font-bold text-gray-900 dark:text-white">
                                        Rp {{ number_format($h->unit_price_idr, 2, ',', '.') }}
                                    </td>
                                    <td class="p-3 text-center">
                                        @if(is_null($h->price_change_percentage))
                                            <span class="text-xs px-2 py-0.5 rounded bg-blue-50 text-blue-600 font-medium">Harga Dasar</span>
                                        @elseif($h->price_change_percentage > 0)
                                            <span class="text-xs px-2 py-0.5 rounded bg-rose-50 text-rose-600 font-bold">
                                                ▲ +{{ number_format($h->price_change_percentage, 2) }}%
                                            </span>
                                        @elseif($h->price_change_percentage < 0)
                                            <span class="text-xs px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 font-bold">
                                                ▼ {{ number_format($h->price_change_percentage, 2) }}%
                                            </span>
                                        @else
                                            <span class="text-xs px-2 py-0.5 rounded bg-gray-50 text-gray-600 font-medium">0.00%</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-xs text-gray-500">{{ $h->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

    </div>
</x-filament-panels::page>

