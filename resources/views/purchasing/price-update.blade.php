<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyesuaian Harga Material Baja (HR & CR)</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen p-6">
    <div class="max-w-4xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Penyesuaian Harga Material Baja</h1>
                <p class="text-sm text-slate-500 mt-1">Form input penyesuaian harga Hot Rolled (HR) & Cold Rolled (CR) dalam satuan USD/MT dan Kurs IDR.</p>
            </div>
            <a href="{{ url('/admin/materials') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-lg transition">
                &larr; Kembali ke Master Material
            </a>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm font-medium">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4">
                <h4 class="font-semibold text-sm mb-1">Terjadi kesalahan validasi:</h4>
                <ul class="list-disc list-inside text-sm space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Form Input -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <form action="{{ route('purchasing.price-update.submit') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Kurs IDR -->
                    <div>
                        <label for="kurs_idr" class="block text-sm font-semibold text-slate-700 mb-2">
                            Nilai Tukar Rupiah (Kurs IDR) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-sm font-medium">Rp</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                id="kurs_idr" 
                                name="kurs_idr" 
                                value="{{ old('kurs_idr', 17796) }}" 
                                placeholder="Contoh: 17796"
                                required
                                class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-slate-900 font-medium"
                            >
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5">Nilai tukar 1 USD terhadap Rupiah saat ini.</p>
                    </div>

                    <!-- Penyesuaian HR (USD/MT) -->
                    <div>
                        <label for="hr_adj_mt_usd" class="block text-sm font-semibold text-slate-700 mb-2">
                            Penyesuaian HR (USD / MT) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-sm font-medium">$</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                id="hr_adj_mt_usd" 
                                name="hr_adj_mt_usd" 
                                value="{{ old('hr_adj_mt_usd', -20) }}" 
                                placeholder="Contoh: -20 atau 15"
                                required
                                class="w-full pl-8 pr-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-slate-900 font-medium"
                            >
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5">Untuk Hot Rolled (seri SPH, SAPH). Konversi: /1000 ke $/kg.</p>
                    </div>

                    <!-- Penyesuaian CR (USD/MT) -->
                    <div>
                        <label for="cr_adj_mt_usd" class="block text-sm font-semibold text-slate-700 mb-2">
                            Penyesuaian CR (USD / MT) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-sm font-medium">$</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                id="cr_adj_mt_usd" 
                                name="cr_adj_mt_usd" 
                                value="{{ old('cr_adj_mt_usd', -20) }}" 
                                placeholder="Contoh: -20 atau 15"
                                required
                                class="w-full pl-8 pr-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-slate-900 font-medium"
                            >
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5">Untuk Cold Rolled (seri SPC). Konversi: /1000 ke $/kg.</p>
                    </div>
                </div>

                <!-- Rumus Info Card -->
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-xs text-slate-600 space-y-1">
                    <p class="font-semibold text-slate-800">Rumus Kalkulasi Otomatis:</p>
                    <p>• <strong>Satuan $/kg</strong>: Penyesuaian ($/MT) ÷ 1.000</p>
                    <p>• <strong>Harga Baru ($)</strong>: Harga Dasar ($/kg) + Penyesuaian ($/kg)</p>
                    <p>• <strong>Harga Akhir (Rp)</strong>: Harga Baru ($/kg) × Kurs IDR</p>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                    <button 
                        type="submit" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg shadow-sm transition inline-flex items-center gap-2"
                        onclick="return confirm('Apakah Anda yakin ingin memperbarui seluruh harga material baja aktif dengan parameter ini?');"
                    >
                        <span>Update Harga Material Baja</span>
                    </button>
                </div>
            </form>
        </div>

        @if(session('summary'))
            <!-- Hasil Update Terakhir -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
                <h3 class="text-lg font-bold text-slate-900">Rincian Hasil Pembaruan Terakhir</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 border border-slate-200 rounded-lg">
                        <thead class="bg-slate-100 text-slate-700 text-xs uppercase font-semibold">
                            <tr>
                                <th class="p-3 border-b">ID</th>
                                <th class="p-3 border-b">Spesifikasi Material</th>
                                <th class="p-3 border-b">Tipe</th>
                                <th class="p-3 border-b text-right">Harga Lama ($/kg)</th>
                                <th class="p-3 border-b text-right">Harga Baru ($/kg)</th>
                                <th class="p-3 border-b text-right">Harga Baru (IDR/kg)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach(session('summary')['details'] ?? [] as $item)
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 font-mono text-xs">{{ $item['id'] }}</td>
                                    <td class="p-3 font-medium text-slate-900">{{ $item['spec'] }}</td>
                                    <td class="p-3">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold {{ $item['category_type'] === 'HR' ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">
                                            {{ $item['category_type'] }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right font-mono">${{ number_format($item['harga_dasar_usd'], 4) }}</td>
                                    <td class="p-3 text-right font-mono font-semibold text-indigo-700">${{ number_format($item['harga_baru_usd'], 4) }}</td>
                                    <td class="p-3 text-right font-mono font-semibold text-emerald-700">Rp {{ number_format($item['harga_baru_idr'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</body>
</html>
