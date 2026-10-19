<?php

namespace App\Filament\Pages;

use App\Models\Currency;
use App\Models\Part;
use App\Models\PartQuotation;
use App\Models\Supplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class PriceComparison extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Kalkulator & Purchasing';

    protected static ?string $navigationLabel = 'Komparasi Harga Supplier';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.price-comparison';

    public ?int $selectedPartId = null;

    public function mount(): void
    {
        // Default ke part pertama yang memiliki quotation
        $firstPartWithQuo = Part::whereHas('partQuotations')->first();
        if ($firstPartWithQuo) {
            $this->selectedPartId = $firstPartWithQuo->id;
        } else {
            $this->selectedPartId = Part::first()?->id;
        }
    }

    public function getSelectedPartProperty(): ?Part
    {
        if (!$this->selectedPartId) {
            return null;
        }

        return Part::with(['material', 'partQuotations.supplier', 'partQuotations.currency'])->find($this->selectedPartId);
    }

    public function getQuotationsProperty(): Collection
    {
        if (!$this->selectedPart) {
            return collect();
        }

        return $this->selectedPart->partQuotations()->orderBy('total_cost_idr', 'asc')->get();
    }

    public function selectWinningSupplier(int $quotationId): void
    {
        if (!$this->selectedPartId) return;

        // Reset previous selected
        PartQuotation::where('part_id', $this->selectedPartId)->update(['is_selected' => false]);

        // Set new winner
        $winner = PartQuotation::find($quotationId);
        if ($winner) {
            $winner->update(['is_selected' => true]);
            Notification::make()
                ->title("{$winner->supplier?->name} berhasil dipilih sebagai supplier utama.")
                ->success()
                ->send();
        }
    }
}

