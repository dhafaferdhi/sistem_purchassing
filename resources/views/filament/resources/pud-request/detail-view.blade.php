@php
    $r = null;
    if (isset($record)) {
        $r = is_callable($record) ? $record() : $record;
    }
    if (! $r && isset($getRecord)) {
        $r = is_callable($getRecord) ? $getRecord() : $getRecord;
    }
    if (! $r && isset($entry) && method_exists($entry, 'getRecord')) {
        $r = $entry->getRecord();
    }
    if (is_callable($r)) {
        $r = $r();
    }

    $statuses = [
        'Delivered to PUD',
        'Request Quotation',
        'Review & Negotiation',
        'Item Code Created',
        'Closed'
    ];
    $statusIndex = ($r && !empty($r->status)) ? array_search($r->status, $statuses) : false;
    if ($statusIndex === false) {
        $statusIndex = 0;
    }
    $isCancelled = $r && ($r->status === \App\Models\PudRequest::STATUS_CANCELLED);
@endphp

@if (! $r)
    <div class="p-6 text-center text-sm text-gray-500 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
        Data permintaan tidak ditemukan.
    </div>
@else
<div class="space-y-6">
    {{-- 3-Column Top Layout: Identity | Item Detail | Photo --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
        {{-- Card 1: Requestor Identity --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-blue-50/50 to-white dark:from-gray-800/50 dark:to-gray-900">
                <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100">Requestor Identity</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Identitas pemohon barang</p>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800 text-sm flex-1">
                <div class="px-5 py-3">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Request ID</span>
                    <span class="font-bold text-primary-600 dark:text-primary-400 text-base">{{ $r->request_number }}</span>
                </div>
                <div class="px-5 py-3">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">User Name</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $r->user_name }}</span>
                </div>
                <div class="px-5 py-3">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Division</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $r->division }}</span>
                </div>
                <div class="px-5 py-3">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Plant</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                        {{ $r->plant }}
                    </span>
                </div>
                <div class="px-5 py-3">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Request Date</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $r->request_date?->format('d/m/Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Card 2: Request Item Detail --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-blue-50/50 to-white dark:from-gray-800/50 dark:to-gray-900 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100">Request Item Detail</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Spesifikasi & detail part</p>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold 
                        @if($r->status === 'Closed') bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300
                        @elseif($r->status === 'Cancelled by User') bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300
                        @elseif($r->status === 'Item Code Created') bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300
                        @elseif($r->status === 'Review & Negotiation') bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300
                        @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 @endif">
                        {{ $r->status }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                        @if($r->sla_progress === 'On Track') bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300
                        @elseif($r->sla_progress === 'Attention') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300
                        @elseif($r->sla_progress === 'Due Soon') bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300
                        @elseif($r->sla_progress === 'Overdue') bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300
                        @else bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 @endif">
                        {{ $r->sla_progress }}
                    </span>
                </div>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800 text-sm flex-1">
                <div class="px-5 py-2.5 grid grid-cols-2 gap-4">
                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Part Name</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100">{{ $r->part_name }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Brand</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $r->brand ?: '—' }}</span>
                    </div>
                </div>
                <div class="px-5 py-2.5 grid grid-cols-2 gap-4">
                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Type / Model</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $r->type ?: '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Quantity</span>
                        <span class="font-bold text-primary-600 dark:text-primary-400">{{ number_format((float) $r->qty, 0, ',', '.') }} {{ $r->unit }}</span>
                    </div>
                </div>
                <div class="px-5 py-2.5">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Use / Purpose</span>
                    <p class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $r->purpose }}</p>
                </div>
                <div class="px-5 py-2.5">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Specification</span>
                    <p class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-mono">{{ $r->spec }}</p>
                </div>
            </div>
        </div>

        {{-- Card 3: Photo Barang --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-blue-50/50 to-white dark:from-gray-800/50 dark:to-gray-900">
                <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100">Photo Barang</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Dokumentasi fisik barang</p>
            </div>
            <div class="p-4 flex-1 flex items-center justify-center bg-gray-50 dark:bg-gray-800/40">
                @if($r->photo)
                    <a href="{{ asset('storage/' . $r->photo) }}" target="_blank" title="Klik untuk membuka ukuran penuh">
                        <img src="{{ asset('storage/' . $r->photo) }}" alt="Photo {{ $r->part_name }}" class="max-h-60 max-w-full rounded-lg object-contain shadow-sm border border-gray-200 dark:border-gray-700 hover:scale-105 transition duration-200" />
                    </a>
                @else
                    <div class="text-center py-10 text-gray-400 dark:text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="block mt-2 text-xs">Belum ada foto yang diunggah.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Result Section --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800/40 dark:to-gray-900">
            <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100">Hasil Pemrosesan PUD</h3>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg border border-gray-100 dark:border-gray-800">
                <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Supplier Terpilih</span>
                <span class="font-bold text-gray-900 dark:text-gray-100 text-sm">{{ $r->selected_supplier ?: '— (Belum ditentukan)' }}</span>
            </div>
            <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg border border-gray-100 dark:border-gray-800">
                <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Item Code</span>
                <span class="font-bold text-primary-600 dark:text-primary-400 text-sm font-mono">{{ $r->item_code ?: '— (Menunggu pembuatan code)' }}</span>
            </div>
            <div class="col-span-full bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg border border-gray-100 dark:border-gray-800">
                <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Feedback / Respon PUD untuk Pemohon</span>
                <p class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $r->feedback ?: 'Belum ada catatan feedback dari PUD.' }}</p>
            </div>
            @if($r->internal_notes)
            <div class="col-span-full bg-amber-50/50 dark:bg-amber-950/20 p-4 rounded-lg border border-amber-200/50 dark:border-amber-800/50">
                <span class="block text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider mb-1">Internal Notes PUD</span>
                <p class="text-xs text-amber-900 dark:text-amber-200 whitespace-pre-wrap">{{ $r->internal_notes }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Supplier & Quotation Comparison (if available) --}}
    @if($r->supplier_1_name || $r->supplier_2_name || $r->supplier_3_name)
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800/40 dark:to-gray-900">
            <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100">Perbandingan Supplier & Quotation (Internal PUD)</h3>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
            @for($i = 1; $i <= 3; $i++)
                @php
                    $sName = $r->{"supplier_{$i}_name"};
                    $sP1 = $r->{"supplier_{$i}_price_1"};
                    $sP2 = $r->{"supplier_{$i}_price_2"};
                    $sC1 = $r->{"supplier_{$i}_currency_1"} ?: 'Rp';
                    $sC2 = $r->{"supplier_{$i}_currency_2"} ?: 'Rp';
                    $sFile = $r->{"supplier_{$i}_quotation_file"};
                    $isSelected = $sName && $r->selected_supplier === $sName;
                @endphp
                <div class="border rounded-xl p-4 {{ $isSelected ? 'border-primary-500 bg-primary-50/20 dark:bg-primary-950/20 shadow-sm' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900' }}">
                    <div class="flex justify-between items-start mb-3">
                        <h4 class="font-bold text-xs text-gray-700 dark:text-gray-300">Supplier {{ $i }}</h4>
                        @if($isSelected)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-primary-600 text-white">✓ Terpilih</span>
                        @endif
                    </div>
                    <div class="space-y-2 text-xs">
                        <div>
                            <span class="block text-gray-400 text-[10px] uppercase font-semibold">Nama Vendor</span>
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ $sName ?: '—' }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="block text-gray-400 text-[10px] uppercase font-semibold">Price 01</span>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $sP1 ? $sC1 . ' ' . number_format((float) $sP1, 0, ',', '.') : '—' }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-gray-400 text-[10px] uppercase font-semibold">Price 02 (Final)</span>
                                <span class="font-bold text-green-600 dark:text-green-400">
                                    {{ $sP2 ? $sC2 . ' ' . number_format((float) $sP2, 0, ',', '.') : '—' }}
                                </span>
                            </div>
                        </div>
                        @if($sFile)
                        <div class="pt-2">
                            <a href="{{ asset('storage/' . $sFile) }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-primary-600 hover:text-primary-700 font-medium underline">
                                📎 Preview Quotation File
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            @endfor
        </div>
    </div>
    @endif

    {{-- Timeline Process --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden p-5">
        <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100 mb-4">Process Timeline</h3>
        
        @if($isCancelled)
            <div class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800/40 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-xs">
                <span class="w-3 h-3 rounded-full bg-red-500 flex-shrink-0"></span>
                <span><strong>Request ini telah dibatalkan</strong> pada {{ $r->cancelled_at?->format('d/m/Y H:i') }}.</span>
            </div>
        @else
            <div class="grid grid-cols-5 gap-2 text-center text-xs">
                @foreach($statuses as $idx => $st)
                    @php $isPastOrCurrent = $statusIndex >= $idx; $isCurrent = $statusIndex === $idx; @endphp
                    <div class="flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs mb-2 transition-all
                            {{ $isCurrent ? 'bg-primary-600 text-white ring-4 ring-primary-100 dark:ring-primary-900/40' : ($isPastOrCurrent ? 'bg-green-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-400') }}">
                            {{ $isPastOrCurrent && !$isCurrent ? '✓' : ($idx + 1) }}
                        </div>
                        <span class="font-semibold {{ $isCurrent ? 'text-primary-600 dark:text-primary-400 font-bold' : ($isPastOrCurrent ? 'text-gray-800 dark:text-gray-200' : 'text-gray-400') }}">
                            {{ $st }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- History Audit Trail --}}
    @if(!empty($r->history) && is_array($r->history))
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden p-5">
        <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100 mb-3">Audit Trail & Riwayat Aktivitas</h3>
        <div class="space-y-2">
            @foreach(array_reverse($r->history) as $h)
                <div class="text-xs p-3 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800 flex justify-between items-center">
                    <div>
                        <span class="font-bold text-gray-800 dark:text-gray-200">{{ $h['action'] ?? 'Aksi' }}</span>
                        <span class="text-gray-500">oleh</span>
                        <span class="font-semibold text-primary-600">{{ $h['by'] ?? 'User' }}</span>
                    </div>
                    <span class="text-gray-400 font-mono">{{ $h['time'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endif

