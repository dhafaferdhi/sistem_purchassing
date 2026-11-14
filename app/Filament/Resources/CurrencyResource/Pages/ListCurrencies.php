<?php

namespace App\Filament\Resources\CurrencyResource\Pages;

use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\FastenerResource;
use App\Filament\Resources\MaterialSteelApResource;
use App\Filament\Resources\MaterialSteelArResource;
use App\Filament\Resources\StampingPartResource;
use App\Filament\Resources\SubAssemblyPartResource;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\SubAssemblyPart;
use App\Services\CurrencyRecalculationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;

    protected static string $view = 'filament.resources.currency-resource.pages.list-currencies';

    public int $selectedYear = 2026;

    public string $selectedQuarter = 'Q1';

    public ?float $rateUsd = 16000.0;

    public ?float $gapApUsd = 0.0;

    public ?float $gapCrcApUsd = 0.0;

    public ?float $gapArUsd = 0.0;

    public ?string $lastSavedAt = null;

    public string $selectedMasterTab = 'all';

    public string $searchMaster = '';

    public function getMasterDataCountsProperty(): array
    {
        $y = $this->selectedYear;
        $q = $this->selectedQuarter;

        $steelApCount = MaterialSteel::where('year', $y)->where('quarter', $q)
            ->where(fn ($query) => $query->where('price_type', 'AP')->orWhereNull('price_type'))
            ->count();

        $steelArCount = MaterialSteel::where('year', $y)->where('quarter', $q)
            ->where('price_type', 'AR')
            ->count();

        $stampingCount = StampingPart::where('year', $y)->where('quarter', $q)->count();
        $fastenerCount = Fastener::where('year', $y)->where('quarter', $q)->count();
        $subAssemblyCount = SubAssemblyPart::where('year', $y)->where('quarter', $q)->count();

        $total = $steelApCount + $steelArCount + $stampingCount + $fastenerCount + $subAssemblyCount;

        return [
            'all' => $total,
            'steel_ap' => $steelApCount,
            'steel_ar' => $steelArCount,
            'stamping' => $stampingCount,
            'fastener' => $fastenerCount,
            'sub_assembly' => $subAssemblyCount,
        ];
    }

    public function getMasterDataRecordsProperty(): \Illuminate\Support\Collection
    {
        $y = $this->selectedYear;
        $q = $this->selectedQuarter;
        $tab = $this->selectedMasterTab;
        $search = strtolower(trim($this->searchMaster));

        $records = collect();

        // 1. Material Steel AP
        if ($tab === 'all' || $tab === 'steel_ap') {
            $steelsAp = MaterialSteel::where('year', $y)->where('quarter', $q)
                ->where(fn ($query) => $query->where('price_type', 'AP')->orWhereNull('price_type'))
                ->get();

            foreach ($steelsAp as $item) {
                try {
                    $url = MaterialSteelApResource::getUrl('edit', ['record' => $item->id]);
                } catch (\Throwable) {
                    $url = url('/admin/material-steel-aps/' . $item->id . '/edit');
                }

                $records->push([
                    'id' => 'steel_ap_' . $item->id,
                    'category' => 'Material Steel AP',
                    'category_slug' => 'steel_ap',
                    'badge_color' => 'blue',
                    'code_spec' => $item->steel_spec ?: '-',
                    'steel_category' => $item->steel_category ?: ((MaterialSteel::determineSteelType($item->steel_spec) === 'HR') ? 'HRC' : 'CRC'),
                    'name_desc' => $item->steel_spec . ($item->double_thickness ? ' (' . $item->double_thickness . ' mm)' : ''),
                    'thickness_size' => $item->double_thickness ? $item->double_thickness . ' mm' : ($item->size ?: '-'),
                    'supplier' => $item->supplier_name ?: ($item->vendor_code ?: '-'),
                    'rate' => (float) ($item->exchange_rate ?: $this->rateUsd),
                    'gap' => $item->gap_steel_usd !== null ? (float) $item->gap_steel_usd : null,
                    'price_usd' => (float) ($item->coil_price > 0 ? $item->coil_price : ($item->sheet_price > 0 ? $item->sheet_price : $item->steel_price)),
                    'price_idr' => (float) ($item->coil_price_idr > 0 ? $item->coil_price_idr : ($item->sheet_price_idr > 0 ? $item->sheet_price_idr : $item->steel_price_idr)),
                    'status' => $item->status ?: 'Active',
                    'view_url' => $url,
                ]);
            }
        }

        // 2. Material Steel AR
        if ($tab === 'all' || $tab === 'steel_ar') {
            $steelsAr = MaterialSteel::where('year', $y)->where('quarter', $q)
                ->where('price_type', 'AR')
                ->get();

            foreach ($steelsAr as $item) {
                try {
                    $url = MaterialSteelArResource::getUrl('edit', ['record' => $item->id]);
                } catch (\Throwable) {
                    $url = url('/admin/material-steel-ars/' . $item->id . '/edit');
                }

                $records->push([
                    'id' => 'steel_ar_' . $item->id,
                    'category' => 'Material Steel AR',
                    'category_slug' => 'steel_ar',
                    'badge_color' => 'amber',
                    'code_spec' => $item->steel_spec ?: '-',
                    'steel_category' => $item->steel_category ?: ((MaterialSteel::determineSteelType($item->steel_spec) === 'HR') ? 'HRC' : 'CRC'),
                    'name_desc' => $item->steel_spec . ($item->double_thickness ? ' (' . $item->double_thickness . ' mm)' : ''),
                    'thickness_size' => $item->double_thickness ? $item->double_thickness . ' mm' : ($item->size ?: '-'),
                    'supplier' => $item->supplier_name ?: ($item->vendor_code ?: '-'),
                    'rate' => (float) ($item->exchange_rate ?: $this->rateUsd),
                    'gap' => $item->gap_steel_usd !== null ? (float) $item->gap_steel_usd : null,
                    'price_usd' => (float) ($item->coil_price > 0 ? $item->coil_price : ($item->sheet_price > 0 ? $item->sheet_price : $item->steel_price)),
                    'price_idr' => (float) ($item->coil_price_idr > 0 ? $item->coil_price_idr : ($item->sheet_price_idr > 0 ? $item->sheet_price_idr : $item->steel_price_idr)),
                    'status' => $item->status ?: 'Active',
                    'view_url' => $url,
                ]);
            }
        }

        // 3. Stamping Part
        if ($tab === 'all' || $tab === 'stamping') {
            $stampings = StampingPart::where('year', $y)->where('quarter', $q)->get();

            foreach ($stampings as $item) {
                $costUsd = (float) ($item->part_cost_excluding_tooling ?: $item->material_total);
                $costIdr = (float) ($costUsd * ($this->rateUsd ?: 16000));

                try {
                    $url = StampingPartResource::getUrl('edit', ['record' => $item->id]);
                } catch (\Throwable) {
                    $url = url('/admin/stamping-parts/' . $item->id . '/edit');
                }

                $records->push([
                    'id' => 'stamping_' . $item->id,
                    'category' => 'Stamping Part',
                    'category_slug' => 'stamping',
                    'badge_color' => 'purple',
                    'code_spec' => $item->part_number ?: ($item->steel_spec ?: '-'),
                    'name_desc' => $item->part_name ?: ($item->model_name ?: ($item->steel_spec ?: '-')),
                    'thickness_size' => $item->double_thickness ? $item->double_thickness . ' mm' : ($item->size ?: '-'),
                    'supplier' => $item->supplier_name ?: ($item->vendor_code ?: '-'),
                    'rate' => (float) ($this->rateUsd ?: 16000),
                    'gap' => null,
                    'price_usd' => $costUsd,
                    'price_idr' => $costIdr,
                    'status' => 'Active',
                    'view_url' => $url,
                ]);
            }
        }

        // 4. Fastener
        if ($tab === 'all' || $tab === 'fastener') {
            $fasteners = Fastener::where('year', $y)->where('quarter', $q)->get();

            foreach ($fasteners as $item) {
                $costUsd = (float) ($item->wire_price_usd_kg ?: ($item->part_cost_excluding_tooling ?: 0));
                $costIdr = (float) ($item->wire_price_idr_kg ?: ($costUsd * ($this->rateUsd ?: 16000)));

                try {
                    $url = FastenerResource::getUrl('edit', ['record' => $item->id]);
                } catch (\Throwable) {
                    $url = url('/admin/fasteners/' . $item->id . '/edit');
                }

                $records->push([
                    'id' => 'fastener_' . $item->id,
                    'category' => 'Fastener',
                    'category_slug' => 'fastener',
                    'badge_color' => 'emerald',
                    'code_spec' => $item->part_number ?: ($item->wire_spec ?: ($item->steel_spec ?: '-')),
                    'name_desc' => $item->part_name ?: ($item->model_name ?: '-'),
                    'thickness_size' => $item->steel_spec ?: '-',
                    'supplier' => $item->supplier_name ?: ($item->vendor_code ?: '-'),
                    'rate' => (float) ($this->rateUsd ?: 16000),
                    'gap' => null,
                    'price_usd' => $costUsd,
                    'price_idr' => $costIdr,
                    'status' => 'Active',
                    'view_url' => $url,
                ]);
            }
        }

        // 5. Sub Assembly Part
        if ($tab === 'all' || $tab === 'sub_assembly') {
            $subAssemblies = SubAssemblyPart::where('year', $y)->where('quarter', $q)->get();

            foreach ($subAssemblies as $item) {
                $costUsd = (float) ($item->part_cost ?: ($item->material_total ?: 0));
                $costIdr = (float) ($costUsd * ($item->exchange_rate ?: ($this->rateUsd ?: 16000)));

                try {
                    $url = SubAssemblyPartResource::getUrl('edit', ['record' => $item->id]);
                } catch (\Throwable) {
                    $url = url('/admin/sub-assembly-parts/' . $item->id . '/edit');
                }

                $records->push([
                    'id' => 'sub_assembly_' . $item->id,
                    'category' => 'Sub Assembly Part',
                    'category_slug' => 'sub_assembly',
                    'badge_color' => 'indigo',
                    'code_spec' => $item->part_number ?: '-',
                    'name_desc' => $item->part_name ?: ($item->model_name ?: '-'),
                    'thickness_size' => '-',
                    'supplier' => $item->supplier_name ?: ($item->vendor_code ?: '-'),
                    'rate' => (float) ($item->exchange_rate ?: ($this->rateUsd ?: 16000)),
                    'gap' => null,
                    'price_usd' => $costUsd,
                    'price_idr' => $costIdr,
                    'status' => $item->status ?: 'Active',
                    'view_url' => $url,
                ]);
            }
        }

        // Filter by search query jika ada input pencarian
        if ($search !== '') {
            $records = $records->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['category']), $search)
                    || str_contains(strtolower($item['code_spec']), $search)
                    || str_contains(strtolower($item['name_desc']), $search)
                    || str_contains(strtolower($item['supplier']), $search);
            });
        }

        return $records->values();
    }

    public function getYearsProperty(): array
    {
        $curYear = (int) date('Y');
        $dbYears = array_merge(
            Currency::distinct()->pluck('year')->filter()->toArray(),
            MaterialSteel::distinct()->pluck('year')->filter()->toArray()
        );
        $range = range($curYear - 3, $curYear + 3);
        $all = array_unique(array_merge($dbYears, $range));
        rsort($all);

        return array_values($all);
    }

    public function mount(): void
    {
        parent::mount();

        // Otomatis sinkronkan semua item MaterialSteel AP <-> AR yang ada di database
        MaterialSteel::syncAllMissingCounterparts();

        $currentYear = (int) date('Y');
        // Prioritaskan tahun/kuartal terbaru dari MaterialSteel jika ada, atau dari Currency
        $latestSteel = MaterialSteel::orderBy('year', 'desc')->orderBy('quarter', 'desc')->first(['year', 'quarter']);
        $latestCurrency = Currency::where('code', 'USD')->orderBy('year', 'desc')->orderBy('quarter', 'desc')->first(['year', 'quarter']);

        if ($latestSteel && (! $latestCurrency || $latestSteel->year >= $latestCurrency->year)) {
            $this->selectedYear = (int) $latestSteel->year;
            $this->selectedQuarter = $latestSteel->quarter ?: 'Q1';
        } elseif ($latestCurrency) {
            $this->selectedYear = (int) $latestCurrency->year;
            $this->selectedQuarter = $latestCurrency->quarter ?: 'Q1';
        } else {
            $this->selectedYear = $currentYear;
            $this->selectedQuarter = 'Q1';
        }

        $this->loadCurrentValues();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadCurrentValues();
        $this->resetPage();
    }

    public function updatedSelectedQuarter(): void
    {
        $this->loadCurrentValues();
        $this->resetPage();
    }

    public function updatedRateUsd(): void
    {
        $this->autoSave();
    }

    public function updatedGapApUsd(): void
    {
        $this->autoSave();
    }

    public function updatedGapCrcApUsd(): void
    {
        $this->autoSave();
    }

    public function updatedGapArUsd(): void
    {
        $this->autoSave();
    }

    public function loadCurrentValues(): void
    {
        $month = match ($this->selectedQuarter) {
            'Q1' => 1,
            'Q2' => 4,
            'Q3' => 7,
            'Q4' => 10,
            default => 1,
        };

        // 1. Sinkronkan dulu data AP/AR yang belum berpasangan di kuartal/tahun ini
        MaterialSteel::syncAllMissingCounterparts($this->selectedYear, $this->selectedQuarter);

        // 2. Periksa apakah sudah ada Currency record untuk kuartal ini
        $record = Currency::where('code', 'USD')
            ->where('year', $this->selectedYear)
            ->where('quarter', $this->selectedQuarter)
            ->first();

        // 3. Periksa data Material Steel di kuartal ini untuk sinkronisasi otomatis
        $steelQuery = MaterialSteel::where('year', $this->selectedYear)->where('quarter', $this->selectedQuarter);
        $hasSteels = $steelQuery->exists();

        $steelRate = null;
        $steelGapAp = null;
        $steelGapAr = null;

        if ($hasSteels) {
            $steelRate = (float) MaterialSteel::where('year', $this->selectedYear)
                ->where('quarter', $this->selectedQuarter)
                ->where('exchange_rate', '>', 0)
                ->latest('id')
                ->value('exchange_rate');

            $steelGapAp = MaterialSteel::where('year', $this->selectedYear)
                ->where('quarter', $this->selectedQuarter)
                ->where(fn ($q) => $q->where('price_type', 'AP')->orWhereNull('price_type'))
                ->whereNotNull('gap_steel_usd')
                ->latest('id')
                ->value('gap_steel_usd');

            $steelGapAr = MaterialSteel::where('year', $this->selectedYear)
                ->where('quarter', $this->selectedQuarter)
                ->where('price_type', 'AR')
                ->whereNotNull('gap_steel_usd')
                ->latest('id')
                ->value('gap_steel_usd');
        }

        // 4. Jika belum ada Currency, inisialisasi dari data Material Steel atau rate terbaru
        if (! $record) {
            $latestRate = Currency::where('code', 'USD')->where('exchange_rate_to_idr', '>', 0)->latest('year')->latest('quarter')->value('exchange_rate_to_idr');
            $initialRate = ($steelRate && $steelRate > 0) ? $steelRate : ($latestRate ?: 16000.0);

            $record = Currency::create([
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'year' => $this->selectedYear,
                'quarter' => $this->selectedQuarter,
                'month' => $month,
                'exchange_rate_to_idr' => $initialRate,
                'gap_ap_usd' => $steelGapAp !== null ? (float) $steelGapAp : 0.0,
                'gap_ar_usd' => $steelGapAr !== null ? (float) $steelGapAr : 0.0,
                'gap_hrc_usd' => $steelGapAp !== null ? (float) $steelGapAp : 0.0,
                'gap_crc_usd' => $steelGapAp !== null ? (float) $steelGapAp : 0.0,
                'gap_hrc_ar_usd' => $steelGapAr !== null ? (float) $steelGapAr : 0.0,
                'gap_crc_ar_usd' => $steelGapAr !== null ? (float) $steelGapAr : 0.0,
            ]);
        } else {
            // Jika record sudah ada tetapi masih bernilai default (16000 dan gap 0) dan di Material Steel ada data spesifik:
            $needsSync = false;
            if ($steelRate && $steelRate > 0 && ((float) $record->exchange_rate_to_idr == 16000 || (float) $record->exchange_rate_to_idr <= 0)) {
                $record->exchange_rate_to_idr = $steelRate;
                $needsSync = true;
            }
            if ($steelGapAp !== null && (float) ($record->gap_ap_usd ?? 0) == 0 && (float) $steelGapAp != 0) {
                $record->gap_ap_usd = (float) $steelGapAp;
                $record->gap_hrc_usd = (float) $steelGapAp;
                $record->gap_crc_usd = (float) $steelGapAp;
                $needsSync = true;
            }
            if ($steelGapAr !== null && (float) ($record->gap_ar_usd ?? 0) == 0 && (float) $steelGapAr != 0) {
                $record->gap_ar_usd = (float) $steelGapAr;
                $record->gap_hrc_ar_usd = (float) $steelGapAr;
                $record->gap_crc_ar_usd = (float) $steelGapAr;
                $needsSync = true;
            }
            if ($needsSync) {
                $record->saveQuietly();
            }
        }

        $this->rateUsd = (float) $record->exchange_rate_to_idr;
        $this->gapApUsd = (float) ($record->gap_ap_usd ?? $record->gap_hrc_usd ?? 0);
        $this->gapCrcApUsd = (float) ($record->gap_crc_usd ?? 0);
        $this->gapArUsd = (float) ($record->gap_ar_usd ?? 0);
    }

    public function autoSave(): void
    {
        $this->validate([
            'selectedYear' => 'required|integer',
            'selectedQuarter' => 'required|string',
            'rateUsd' => 'required|numeric|min:1',
            'gapApUsd' => 'nullable|numeric',
            'gapCrcApUsd' => 'nullable|numeric',
            'gapArUsd' => 'nullable|numeric',
        ]);

        $year = (int) $this->selectedYear;
        $quarter = $this->selectedQuarter;
        $rate = (float) $this->rateUsd;
        $gapAp = (float) ($this->gapApUsd ?? 0);
        $gapCrcAp = (float) ($this->gapCrcApUsd ?? 0);
        $gapAr = (float) ($this->gapArUsd ?? 0);

        $month = match ($quarter) {
            'Q1' => 1,
            'Q2' => 4,
            'Q3' => 7,
            'Q4' => 10,
            default => 1,
        };

        $currency = Currency::updateOrCreate(
            [
                'code' => 'USD',
                'year' => $year,
                'quarter' => $quarter,
            ],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate_to_idr' => $rate,
                'month' => $month,
                'gap_ap_usd' => $gapAp,
                'gap_ar_usd' => $gapAr,
                'gap_hrc_usd' => $gapAp,
                'gap_crc_usd' => $gapCrcAp,
                'gap_hrc_ar_usd' => $gapAr,
                'gap_crc_ar_usd' => $gapAr,
            ]
        );

        CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($currency, true);
        CurrencyRecalculationService::recalculateForCurrency($currency);

        $this->lastSavedAt = now()->format('H:i');

        Notification::make()
            ->title("Tersimpan Otomatis ({$quarter} {$year})")
            ->body("Kurs USD Rp " . number_format($rate, 0, ',', '.') . " serta Gap AP ({$gapAp}) & Gap CRC AP ({$gapCrcAp}) berhasil diperbarui dan disinkronkan.")
            ->success()
            ->send();
    }

    public function submitRates(): void
    {
        $this->autoSave();
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if ($this->selectedYear) {
            $query->where('year', $this->selectedYear);
        }

        if ($this->selectedQuarter) {
            $query->where('quarter', $this->selectedQuarter);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [];
    }
}
