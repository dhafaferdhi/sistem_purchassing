@php
    $getter = null;
    if (isset($get) && is_callable($get)) {
        $getter = $get;
    } elseif (isset($component) && method_exists($component, 'getContainer')) {
        $getter = fn ($field) => $component->getContainer()->get($field);
    } elseif (isset($field) && method_exists($field, 'getContainer')) {
        $getter = fn ($f) => $field->getContainer()->get($f);
    }

    $currentRecord = $record ?? null;
    $getValue = function (string $key, $default = null) use ($getter, $currentRecord) {
        if ($getter) {
            $val = $getter($key);
            if ($val !== null && $val !== '') {
                return $val;
            }
        }
        if ($currentRecord) {
            return $currentRecord->{$key} ?? $default;
        }
        return $default;
    };

    $m1Label = $getValue('prev_month_1_label', 'Bulan M-1');
    $m1Price = (float) $getValue('prev_month_1_price', 0);
    $m1Rate = (float) $getValue('prev_month_1_rate', 0);
    $m1Total = $m1Price * $m1Rate;

    $m2Label = $getValue('prev_month_2_label', 'Bulan M-2');
    $m2Price = (float) $getValue('prev_month_2_price', 0);
    $m2Rate = (float) $getValue('prev_month_2_rate', 0);
    $m2Total = $m2Price * $m2Rate;

    $m3Label = $getValue('prev_month_3_label', 'Bulan M-3');
    $m3Price = (float) $getValue('prev_month_3_price', 0);
    $m3Rate = (float) $getValue('prev_month_3_rate', 0);
    $m3Total = $m3Price * $m3Rate;

    $prices = array_filter([$m1Price, $m2Price, $m3Price], fn($p) => $p > 0);
    $rates = array_filter([$m1Rate, $m2Rate, $m3Rate], fn($r) => $r > 0);

    $avgPrice = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;
    $avgRate = count($rates) > 0 ? array_sum($rates) / count($rates) : 0;
    $avgTotalIdr = $avgPrice * $avgRate;

    $currentPrice = (float) $getValue('coil_price', 0);
    if ($currentPrice <= 0) {
        $currentPrice = (float) $getValue('steel_price', $getValue('sheet_price', 0));
    }
    $currentRate = (float) $getValue('exchange_rate', 0);
    $currentTotalIdr = $currentPrice * $currentRate;

    $currencyCode = $getValue('currency_code', 'USD');

    $variance = 0;
    if ($avgPrice > 0 && $currentPrice > 0) {
        $variance = round((($currentPrice - $avgPrice) / $avgPrice) * 100, 2);
    }
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3 dark:border-gray-800">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </span>
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Analisis Tren Harga Baja & Kurs (3 Bulan ke Belakang)</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">Perbandingan harga kuartal baru terhadap riwayat pergerakan 3 bulan sebelumnya</p>
            </div>
        </div>

        @if ($variance > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-950/50 dark:text-red-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                Kenaikan: +{{ number_format($variance, 2) }}%
            </span>
        @elseif ($variance < 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                Penurunan / Diskon: {{ number_format($variance, 2) }}%
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                Stabil (0.00%)
            </span>
        @endif
    </div>

    <!-- 3 Months Historical Cards -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Bulan M-3 -->
        <div class="rounded-lg border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Bulan M-3 (3 Bulan Lalu)</span>
            <div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $m3Label }}</div>
            <div class="mt-2 space-y-1 text-xs">
                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                    <span>Harga:</span>
                    <span class="font-mono font-medium">{{ $currencyCode }} {{ number_format($m3Price, 4) }}</span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                    <span>Kurs:</span>
                    <span class="font-mono font-medium">Rp {{ number_format($m3Rate, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between border-t border-gray-200/60 pt-1 font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                    <span>Total IDR:</span>
                    <span class="font-mono">Rp {{ number_format($m3Total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Bulan M-2 -->
        <div class="rounded-lg border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Bulan M-2 (2 Bulan Lalu)</span>
            <div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $m2Label }}</div>
            <div class="mt-2 space-y-1 text-xs">
                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                    <span>Harga:</span>
                    <span class="font-mono font-medium">{{ $currencyCode }} {{ number_format($m2Price, 4) }}</span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                    <span>Kurs:</span>
                    <span class="font-mono font-medium">Rp {{ number_format($m2Rate, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between border-t border-gray-200/60 pt-1 font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                    <span>Total IDR:</span>
                    <span class="font-mono">Rp {{ number_format($m2Total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Bulan M-1 -->
        <div class="rounded-lg border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Bulan M-1 (1 Bulan Lalu)</span>
            <div class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $m1Label }}</div>
            <div class="mt-2 space-y-1 text-xs">
                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                    <span>Harga:</span>
                    <span class="font-mono font-medium">{{ $currencyCode }} {{ number_format($m1Price, 4) }}</span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                    <span>Kurs:</span>
                    <span class="font-mono font-medium">Rp {{ number_format($m1Rate, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between border-t border-gray-200/60 pt-1 font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                    <span>Total IDR:</span>
                    <span class="font-mono">Rp {{ number_format($m1Total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Rata-rata 3 Bulan Lalu -->
        <div class="rounded-lg border border-primary-100 bg-primary-50/50 p-3.5 dark:border-primary-900 dark:bg-primary-950/30">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Rata-rata 3 Bulan Lalu</span>
            <div class="mt-1 text-sm font-bold text-primary-900 dark:text-primary-100">Benchmark Historis</div>
            <div class="mt-2 space-y-1 text-xs">
                <div class="flex justify-between text-primary-800 dark:text-primary-200">
                    <span>Rata-rata:</span>
                    <span class="font-mono font-medium">{{ $currencyCode }} {{ number_format($avgPrice, 4) }}</span>
                </div>
                <div class="flex justify-between text-primary-800 dark:text-primary-200">
                    <span>Rata-rata Kurs:</span>
                    <span class="font-mono font-medium">Rp {{ number_format($avgRate, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between border-t border-primary-200/60 pt-1 font-bold text-primary-950 dark:border-primary-800 dark:text-primary-50">
                    <span>Total IDR:</span>
                    <span class="font-mono">Rp {{ number_format($avgTotalIdr, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Perbandingan Kuartal Baru vs Rata-rata 3 Bulan Lalu -->
    @if ($currentPrice > 0)
        <div class="mt-4 rounded-lg bg-gray-50 p-3.5 text-xs dark:bg-gray-800/60">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-700 dark:text-gray-300">Ringkasan Kuartal Terpilih:</span>
                    <span class="font-mono font-bold text-gray-900 dark:text-white">{{ $currencyCode }} {{ number_format($currentPrice, 4) }} (Kurs Rp {{ number_format($currentRate, 0, ',', '.') }}) = Rp {{ number_format($currentTotalIdr, 2, ',', '.') }}/kg</span>
                </div>
                <div>
                    @if ($avgTotalIdr > 0)
                        @php
                            $diffIdr = $currentTotalIdr - $avgTotalIdr;
                        @endphp
                        <span class="font-medium {{ $diffIdr > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            Selisih vs 3 bln lalu: {{ $diffIdr > 0 ? '+Rp ' : '-Rp ' }}{{ number_format(abs($diffIdr), 2, ',', '.') }}/kg
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
