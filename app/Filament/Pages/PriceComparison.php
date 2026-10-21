<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Part;
use App\Models\PartQuotation;
use App\Models\PurchaseOrder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PriceComparison extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static bool $shouldRegisterNavigation = false;

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
        if (! $this->selectedPartId) {
            return null;
        }

        return Part::with(['material', 'partQuotations.supplier', 'partQuotations.currency'])->find($this->selectedPartId);
    }

    public function getQuotationsProperty(): Collection
    {
        if (! $this->selectedPart) {
            return collect();
        }

        return $this->selectedPart->partQuotations()->orderBy('total_cost_idr', 'asc')->get();
    }

    public function selectWinningSupplier(int $quotationId): void
    {
        if (! $this->selectedPartId) {
            return;
        }

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

    public function generatePurchaseOrder(int $quotationId): void
    {
        $winner = PartQuotation::with(['part', 'supplier', 'currency'])->find($quotationId);
        if (! $winner) {
            return;
        }

        // Otomatis tandai sebagai pemenang
        PartQuotation::where('part_id', $winner->part_id)->update(['is_selected' => false]);
        $winner->update(['is_selected' => true]);

        // Buat Purchase Order
        $po = PurchaseOrder::create([
            'supplier_id' => $winner->supplier_id,
            'user_id' => Auth::id() ?? 1,
            'order_date' => now(),
            'delivery_date' => now()->addDays(14),
            'payment_terms' => 'Net 30 Days',
            'currency_id' => $winner->currency_id,
            'exchange_rate' => $winner->exchange_rate_to_idr,
            'status' => 'Draft',
            'notes' => "Diterbitkan dari Komparasi Harga untuk Part Number {$winner->part?->part_number}",
        ]);

        // Cari mapping kode barang vendor jika ada
        $vendorItemCode = $winner->supplier?->vendorItems()
            ->where('part_id', $winner->part_id)
            ->first()?->vendor_item_code;

        $qty = 1000; // Batch standar pengadaan 1.000 pcs
        $unitPrice = $winner->total_cost_idr;
        $totalPrice = round($qty * $unitPrice, 2);

        $po->items()->create([
            'part_id' => $winner->part_id,
            'item_code' => $winner->part?->item_code ?? $winner->part?->part_number ?? 'PART-ITEM',
            'description' => $winner->part?->description ?? $winner->part?->part_name ?? 'Item Description',
            'vendor_item_code' => $vendorItemCode,
            'quantity' => $qty,
            'unit' => 'pcs',
            'unit_price_idr' => $unitPrice,
            'total_price_idr' => $totalPrice,
        ]);

        // Hitung Subtotal, PPN 11%, dan Grand Total
        $taxAmount = round($totalPrice * 0.11, 2);
        $grandTotal = round($totalPrice + $taxAmount, 2);

        $po->update([
            'subtotal_idr' => $totalPrice,
            'tax_percentage' => 11.0,
            'tax_amount_idr' => $taxAmount,
            'total_amount_idr' => $grandTotal,
        ]);

        Notification::make()
            ->title("PO Berhasil Dibuat: {$po->po_number}")
            ->body("Draft Purchase Order telah diterbitkan untuk {$winner->supplier?->name} dengan total Rp ".number_format($grandTotal, 2, ',', '.'))
            ->success()
            ->actions([
                Action::make('view_po')
                    ->label('Buka Draft PO')
                    ->url(PurchaseOrderResource::getUrl('edit', ['record' => $po->id])),
            ])
            ->send();
    }
}
