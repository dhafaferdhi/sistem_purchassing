<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Top Info Banner -->
        <div class="rounded-xl border border-primary-200 bg-gradient-to-r from-primary-50 to-primary-100/50 p-4 shadow-sm dark:border-primary-800 dark:from-primary-950/40 dark:to-primary-900/20">
            <div class="flex items-start gap-3.5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white shadow">
                    <x-heroicon-o-document-duplicate class="h-6 w-6" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                        Menu Salin & Update Bulanan (Monthly Rollover & Copy Data)
                    </h3>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        Fitur ini digunakan setiap 1 bulan sekali (atau kuartalan) untuk menduplikasi seluruh data <strong>Kurs Mata Uang (Currency)</strong>, <strong>Material Steel</strong>, dan <strong>Stamping Part</strong> dari periode acuan ke periode baru, serta memperbarui nilai tukar (kurs $) dan rumus harga secara serentak tanpa perlu input manual satu per satu.
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-gray-800">
            <button
                type="button"
                wire:click="setTab('all')"
                class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition {{ $activeTab === 'all' ? 'bg-primary-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' }}"
            >
                <x-heroicon-o-bolt class="h-4 w-4" />
                <span>Salin Semua Sekaligus (All-in-One)</span>
            </button>

            <button
                type="button"
                wire:click="setTab('currency')"
                class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition {{ $activeTab === 'currency' ? 'bg-primary-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' }}"
            >
                <x-heroicon-o-currency-dollar class="h-4 w-4" />
                <span>Kurs Mata Uang</span>
            </button>

            <button
                type="button"
                wire:click="setTab('material_steel')"
                class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition {{ $activeTab === 'material_steel' ? 'bg-primary-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' }}"
            >
                <x-heroicon-o-cube class="h-4 w-4" />
                <span>Material Steel</span>
            </button>

            <button
                type="button"
                wire:click="setTab('stamping_part')"
                class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition {{ $activeTab === 'stamping_part' ? 'bg-primary-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' }}"
            >
                <x-heroicon-o-wrench-screwdriver class="h-4 w-4" />
                <span>Stamping Part</span>
            </button>

            <button
                type="button"
                wire:click="setTab('history')"
                class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition {{ $activeTab === 'history' ? 'bg-primary-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' }}"
            >
                <x-heroicon-o-clock class="h-4 w-4" />
                <span>Riwayat Salinan (Log)</span>
            </button>
        </div>

        <!-- TAB 1: ALL-IN-ONE ROLLOVER -->
        @if ($activeTab === 'all')
            <div class="space-y-6">
                <!-- Status Preview Cards -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Sumber: Material Steel</span>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-gray-900 dark:text-white">{{ $this->sourceCounts['material_steels'] }}</span>
                            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $sourceYear }} - {{ $sourceQuarter }}</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Sumber: Stamping Part</span>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-gray-900 dark:text-white">{{ $this->sourceCounts['stamping_parts'] }}</span>
                            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $sourceYear }} - {{ $sourceQuarter }}</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Target: Sudah Ada di Target</span>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-black text-gray-900 dark:text-white">{{ $this->targetCounts['material_steels'] + $this->targetCounts['stamping_parts'] }}</span>
                            <span class="text-xs font-semibold text-gray-500">{{ $targetYear }} - {{ $targetQuarter }}</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Kurs Target ($ ke IDR)</span>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-xl font-black text-success-600 dark:text-success-400">Rp {{ number_format((float) ($usdRate ?? 0), 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Form Panel All-in-One -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 pb-4 dark:border-gray-800">
                        <h4 class="text-base font-bold text-gray-900 dark:text-white">Pengaturan Salin Semua Sekaligus</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Pilih periode acuan dan periode target. Sistem akan membuat / memperbarui kurs $, menyalin semua spesifikasi material steel dan stamping part secara otomatis.</p>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Left: Periode Sumber -->
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">1. Periode Acuan (Sumber Data)</span>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tahun Sumber</label>
                                    <select wire:model.live="sourceYear" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        @foreach ($this->getAvailableYears() as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Kuartal Sumber</label>
                                    <select wire:model.live="sourceQuarter" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        <option value="Q1">Q1 (Kuartal 1)</option>
                                        <option value="Q2">Q2 (Kuartal 2)</option>
                                        <option value="Q3">Q3 (Kuartal 3)</option>
                                        <option value="Q4">Q4 (Kuartal 4)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Periode Target -->
                        <div class="rounded-lg border border-primary-200 bg-primary-50/30 p-4 dark:border-primary-800/60 dark:bg-primary-950/20">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary-700 dark:text-primary-400">2. Periode Tujuan (Target Salinan)</span>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tahun Target</label>
                                    <select wire:model.live="targetYear" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        @foreach ($this->getAvailableYears() as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Kuartal Target</label>
                                    <select wire:model.live="targetQuarter" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        <option value="Q1">Q1 (Kuartal 1)</option>
                                        <option value="Q2">Q2 (Kuartal 2)</option>
                                        <option value="Q3">Q3 (Kuartal 3)</option>
                                        <option value="Q4">Q4 (Kuartal 4)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parameter Update Kurs & Harga -->
                    <div class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">3. Parameter Nilai Tukar & Penyesuaian Harga</span>
                        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Kurs $ Baru ($ ke IDR)</label>
                                <div class="relative mt-1">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-gray-500">Rp</span>
                                    <input
                                        type="number"
                                        step="0.0001"
                                        wire:model="usdRate"
                                        placeholder="Contoh: 16200"
                                        class="w-full rounded-lg border-gray-300 pl-9 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>
                                <span class="text-[10px] text-gray-500">Akan diterapkan ke Currency, Material Steel, dan Stamping Part</span>
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Penyesuaian Fluktuasi Coil Price (%)</label>
                                <div class="relative mt-1">
                                    <input
                                        type="number"
                                        step="0.01"
                                        wire:model="priceAdjustmentPercent"
                                        placeholder="0 (Tetap), misal +2.5 atau -1.5"
                                        class="w-full rounded-lg border-gray-300 pr-7 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    />
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-gray-500">%</span>
                                </div>
                                <span class="text-[10px] text-gray-500">Kosongkan / isi 0 jika harga coil dasar tetap sama</span>
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tetapkan Gap Steel / MT Baru ($) (Opsional)</label>
                                <div class="relative mt-1">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-gray-500">$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        wire:model="newGapSteelUsd"
                                        placeholder="Biarkan kosong jika tetap"
                                        class="w-full rounded-lg border-gray-300 pl-7 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>
                                <span class="text-[10px] text-gray-500">Mengubah Gap MT dan otomatis Gap Kg = Gap MT ÷ 1.000</span>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Jika Data di Periode Target Sudah Ada:</label>
                                <select wire:model="duplicateHandling" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                    <option value="overwrite">Timpa / Perbarui Data yang Sudah Ada (Direkomendasikan)</option>
                                    <option value="skip">Lewati (Hanya salin item yang belum ada)</option>
                                    <option value="duplicate">Tetap Tambah sebagai Rekaman Baru</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Status Data Hasil Salinan:</label>
                                <select wire:model="status" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                    <option value="Active">Active (Langsung Aktif)</option>
                                    <option value="Draft">Draft (Dalam Pengajuan / Review)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Catatan Rollover (Opsional):</label>
                            <input
                                type="text"
                                wire:model="notes"
                                placeholder="Contoh: Kesepakatan harga bulanan berdasarkan rapat procurement 20 Oktober..."
                                class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button
                            type="button"
                            wire:click="executeAllRollover"
                            wire:loading.attr="disabled"
                            wire:confirm="Yakin ingin menyalin seluruh data Currency, Material Steel, dan Stamping Part dari {{ $sourceYear }} {{ $sourceQuarter }} ke {{ $targetYear }} {{ $targetQuarter }}?"
                            class="flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="executeAllRollover" class="flex items-center gap-1.5">
                                <x-heroicon-o-bolt class="h-4 w-4" />
                                Jalankan Salin & Update Bulanan (All-in-One)
                            </span>
                            <span wire:loading wire:target="executeAllRollover" class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Sedang Memproses Rollover...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 2: CURRENCY ONLY -->
        @if ($activeTab === 'currency')
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 pb-4 dark:border-gray-800">
                        <h4 class="text-base font-bold text-gray-900 dark:text-white">Salin & Update Kurs Mata Uang</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Salin daftar mata uang dari tahun acuan ke tahun target atau perbarui kurs $ secara instan.</p>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tahun Sumber</label>
                            <select wire:model.live="currSourceYear" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                @foreach ($this->getAvailableYears() as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tahun Target</label>
                            <select wire:model.live="currTargetYear" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                @foreach ($this->getAvailableYears() as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Nilai Kurs $ Baru (ke IDR)</label>
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-gray-500">Rp</span>
                                <input
                                    type="number"
                                    step="0.0001"
                                    wire:model="currUsdRate"
                                    placeholder="Contoh: 16500"
                                    class="w-full rounded-lg border-gray-300 pl-9 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button
                            type="button"
                            wire:click="executeCurrencyRollover"
                            wire:loading.attr="disabled"
                            wire:confirm="Yakin ingin menyalin & memperbarui kurs ke tahun {{ $currTargetYear }}?"
                            class="flex items-center gap-1.5 rounded-xl bg-primary-600 px-5 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-primary-700 disabled:opacity-50"
                        >
                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                            <span>Salin & Terapkan Kurs Baru</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 3: MATERIAL STEEL ONLY -->
        @if ($activeTab === 'material_steel')
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 pb-4 dark:border-gray-800">
                        <h4 class="text-base font-bold text-gray-900 dark:text-white">Salin & Update Data Material Steel</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Duplikasi seluruh harga spesifikasi baja dari satu kuartal ke kuartal lain dengan penyesuaian kurs dan gap.</p>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tahun Sumber</label>
                            <select wire:model.live="steelSourceYear" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                @foreach ($this->getAvailableYears() as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Kuartal Sumber</label>
                            <select wire:model.live="steelSourceQuarter" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="Q1">Q1</option>
                                <option value="Q2">Q2</option>
                                <option value="Q3">Q3</option>
                                <option value="Q4">Q4</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tahun Target</label>
                            <select wire:model.live="steelTargetYear" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                @foreach ($this->getAvailableYears() as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Kuartal Target</label>
                            <select wire:model.live="steelTargetQuarter" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="Q1">Q1</option>
                                <option value="Q2">Q2</option>
                                <option value="Q3">Q3</option>
                                <option value="Q4">Q4</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Kurs $ Baru (Rp)</label>
                            <input
                                type="number"
                                step="0.0001"
                                wire:model="steelExchangeRate"
                                placeholder="Contoh: 16000"
                                class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Penyesuaian Coil Price (%)</label>
                            <input
                                type="number"
                                step="0.01"
                                wire:model="steelAdjustmentPercent"
                                placeholder="0 (Tetap)"
                                class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Filter Supplier (Opsional)</label>
                            <select wire:model="steelSupplierId" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="">Semua Supplier</option>
                                @foreach ($this->suppliers as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }} ({{ $sup->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button
                            type="button"
                            wire:click="executeMaterialSteelRollover"
                            wire:loading.attr="disabled"
                            wire:confirm="Yakin ingin menyalin data Material Steel ke {{ $steelTargetYear }} {{ $steelTargetQuarter }}?"
                            class="flex items-center gap-1.5 rounded-xl bg-primary-600 px-5 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-primary-700 disabled:opacity-50"
                        >
                            <x-heroicon-o-cube class="h-4 w-4" />
                            <span>Salin Data Material Steel</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 4: STAMPING PART ONLY -->
        @if ($activeTab === 'stamping_part')
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 pb-4 dark:border-gray-800">
                        <h4 class="text-base font-bold text-gray-900 dark:text-white">Salin & Update Data Stamping Part</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Duplikasi data stamping part kuartal acuan ke kuartal target, dengan opsi mengambil acuan harga terbaru dari Material Steel.</p>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tahun Sumber</label>
                            <select wire:model.live="stampSourceYear" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                @foreach ($this->getAvailableYears() as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Kuartal Sumber</label>
                            <select wire:model.live="stampSourceQuarter" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="Q1">Q1</option>
                                <option value="Q2">Q2</option>
                                <option value="Q3">Q3</option>
                                <option value="Q4">Q4</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tahun Target</label>
                            <select wire:model.live="stampTargetYear" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                @foreach ($this->getAvailableYears() as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Kuartal Target</label>
                            <select wire:model.live="stampTargetQuarter" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="Q1">Q1</option>
                                <option value="Q2">Q2</option>
                                <option value="Q3">Q3</option>
                                <option value="Q4">Q4</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Kurs $ Baru (Rp)</label>
                            <input
                                type="number"
                                step="0.0001"
                                wire:model="stampExchangeRate"
                                placeholder="Contoh: 16000"
                                class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Filter Supplier (Opsional)</label>
                            <select wire:model="stampSupplierId" class="mt-1 w-full rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="">Semua Supplier</option>
                                @foreach ($this->suppliers as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }} ({{ $sup->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="stampSyncFromSteel" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span>Sinkronkan harga Coil & Sheet secara otomatis dari Material Steel periode target jika ada</span>
                        </label>
                    </div>

                    <div class="mt-6 flex items-center justify-end border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button
                            type="button"
                            wire:click="executeStampingPartRollover"
                            wire:loading.attr="disabled"
                            wire:confirm="Yakin ingin menyalin data Stamping Part ke {{ $stampTargetYear }} {{ $stampTargetQuarter }}?"
                            class="flex items-center gap-1.5 rounded-xl bg-primary-600 px-5 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-primary-700 disabled:opacity-50"
                        >
                            <x-heroicon-o-wrench-screwdriver class="h-4 w-4" />
                            <span>Salin Data Stamping Part</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 5: HISTORY / LOG -->
        @if ($activeTab === 'history')
            <div class="space-y-4">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 p-4 dark:border-gray-800">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white">Riwayat Aktivitas Salinan Bulanan (Rollover Logs)</h4>
                        <p class="text-xs text-gray-500">Mencatat tanggal, user pelaksana, periode sumber, periode tujuan, dan jumlah data yang disalin.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 uppercase tracking-wider text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Waktu</th>
                                    <th class="px-4 py-3">Modul</th>
                                    <th class="px-4 py-3">Periode Sumber</th>
                                    <th class="px-4 py-3">Periode Target</th>
                                    <th class="px-4 py-3 text-right">Kurs $</th>
                                    <th class="px-4 py-3 text-center">Baja Disalin</th>
                                    <th class="px-4 py-3 text-center">Stamping Disalin</th>
                                    <th class="px-4 py-3">Pelaksana</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($this->recentLogs as $log)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            {{ $log->created_at->format('d M Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold
                                                {{ $log->type === 'all' ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300' : '' }}
                                                {{ $log->type === 'currency' ? 'bg-warning-50 text-warning-700 dark:bg-warning-950/60 dark:text-warning-300' : '' }}
                                                {{ $log->type === 'material_steel' ? 'bg-info-50 text-info-700 dark:bg-info-950/60 dark:text-info-300' : '' }}
                                                {{ $log->type === 'stamping_part' ? 'bg-success-50 text-success-700 dark:bg-success-950/60 dark:text-success-300' : '' }}
                                            ">
                                                {{ match($log->type) {
                                                    'all' => 'All-in-One',
                                                    'currency' => 'Currency',
                                                    'material_steel' => 'Material Steel',
                                                    'stamping_part' => 'Stamping Part',
                                                    default => $log->type
                                                } }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-gray-600 dark:text-gray-300">{{ $log->source_period }}</td>
                                        <td class="px-4 py-3 font-bold text-primary-600 dark:text-primary-400">{{ $log->target_period }}</td>
                                        <td class="px-4 py-3 text-right font-medium">
                                            {{ $log->exchange_rate ? 'Rp ' . number_format((float) $log->exchange_rate, 2, ',', '.') : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="rounded bg-gray-100 px-2 py-0.5 font-bold text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                                {{ $log->material_steels_count }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="rounded bg-gray-100 px-2 py-0.5 font-bold text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                                {{ $log->stamping_parts_count }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                            {{ $log->user?->name ?? 'Admin' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="p-8 text-center text-xs text-gray-400">
                                            Belum ada aktivitas salinan bulanan yang tercatat.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>

