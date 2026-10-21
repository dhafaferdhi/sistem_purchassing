<?php

namespace App\Filament\Resources\GoodsReceiptResource\Pages;

use App\Filament\Resources\GoodsReceiptResource;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
        ];
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Kembali');
    }

    public function mount(): void
    {
        parent::mount();

        $poId = request()->query('po_id');
        if ($poId) {
            $po = PurchaseOrder::with('items')->find($poId);
            if ($po) {
                $itemsData = [];
                foreach ($po->items as $item) {
                    $rem = max(0, (float) ($item->quantity - $item->quantity_received));
                    $itemsData[] = [
                        'purchase_order_item_id' => $item->id,
                        'part_id' => $item->part_id,
                        'item_code' => $item->item_code,
                        'description' => $item->description,
                        'quantity_ordered' => (float) $item->quantity,
                        'quantity_previously_received' => (float) $item->quantity_received,
                        'quantity_remaining' => $rem,
                        'quantity_received' => $rem,
                        'quantity_rejected' => 0,
                        'unit' => $item->unit,
                        'notes' => null,
                    ];
                }

                $this->form->fill([
                    'purchase_order_id' => $po->id,
                    'supplier_id' => $po->supplier_id,
                    'received_date' => now()->format('Y-m-d'),
                    'status' => 'Verified',
                    'items' => $itemsData,
                ]);
            }
        }
    }
}
