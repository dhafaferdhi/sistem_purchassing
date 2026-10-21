<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Status Overview (Month)
        </x-slot>
        <x-slot name="description">
            Distribusi status request pada bulan berjalan ({{ now()->translatedFormat('F Y') }}) per Plant
        </x-slot>

        @php
            $plants = \App\Models\PudRequest::PLANTS;
            $statuses = [
                ['name' => 'Delivered to PUD', 'color' => 'bg-slate-500'],
                ['name' => 'Request Quotation', 'color' => 'bg-amber-500'],
                ['name' => 'Review & Negotiation', 'color' => 'bg-orange-500'],
                ['name' => 'Item Code Created', 'color' => 'bg-blue-600'],
                ['name' => 'Closed', 'color' => 'bg-emerald-600'],
            ];
            $month = (int) now()->format('m');
            $year = (int) now()->format('Y');

            $monthRequests = \App\Models\PudRequest::where('is_deleted', false)
                ->whereMonth('request_date', $month)
                ->whereYear('request_date', $year)
                ->get();
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
            @foreach($plants as $plant)
                <div class="border border-gray-200 dark:border-gray-800 rounded-xl p-4 bg-white dark:bg-gray-900 shadow-sm">
                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-100 dark:border-gray-800">
                        <span class="font-bold text-xs uppercase tracking-wider text-primary-700 dark:text-primary-400">
                            {{ $plant }}
                        </span>
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                            Total: {{ $monthRequests->where('plant', $plant)->count() }}
                        </span>
                    </div>
                    <div class="space-y-1.5 text-xs">
                        @foreach($statuses as $st)
                            @php
                                $count = $monthRequests->where('plant', $plant)->where('status', $st['name'])->count();
                            @endphp
                            <div class="flex items-center justify-between py-1 border-b border-gray-50 dark:border-gray-800/40 last:border-0">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $st['color'] }} inline-block"></span>
                                    <span class="text-gray-600 dark:text-gray-400 text-[11px]">{{ $st['name'] }}</span>
                                </div>
                                <span class="font-bold text-gray-800 dark:text-gray-200 text-xs">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

