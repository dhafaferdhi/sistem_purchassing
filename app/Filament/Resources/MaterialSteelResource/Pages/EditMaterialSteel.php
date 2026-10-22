<?php

namespace App\Filament\Resources\MaterialSteelResource\Pages;

use App\Filament\Resources\MaterialSteelResource;
use App\Models\Material;
use App\Models\MaterialSteel;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialSteel extends EditRecord
{
    protected static string $resource = MaterialSteelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Kembali');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $quarter = $data['quarter'] ?? 'Q1';
        $hasGap = isset($data['gap_steel_usd']) && (float) $data['gap_steel_usd'] != 0.0;

        $data['price_mode'] = ($quarter === 'Q1' && ! $hasGap) ? 'baseline' : 'adjustment';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Pastikan vendor_code dan supplier_name terisi atau buat supplier otomatis jika diinput manual
        if (empty($data['supplier_id']) && ! empty($data['supplier_name'])) {
            $vendorCode = ! empty($data['vendor_code']) ? $data['vendor_code'] : 'VND-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['supplier_name']), 0, 5)).'-'.rand(10, 99);
            $supplier = Supplier::firstOrCreate(
                ['name' => $data['supplier_name']],
                [
                    'code' => $vendorCode,
                    'category' => 'Steel',
                    'is_active' => true,
                ]
            );
            $data['supplier_id'] = $supplier->id;
            $data['vendor_code'] = $data['vendor_code'] ?? $supplier->code;
        } elseif (! empty($data['supplier_id'])) {
            $supplier = Supplier::find($data['supplier_id']);
            if ($supplier) {
                $data['vendor_code'] = $data['vendor_code'] ?? $supplier->code;
                $data['supplier_name'] = $data['supplier_name'] ?? $supplier->name;
            }
        }

        // Bersihkan satuan mm dari ketebalan
        if (! empty($data['double_thickness'])) {
            $data['double_thickness'] = trim(preg_replace('/[a-zA-Z\s]+/', '', (string) $data['double_thickness']));
        }

        $exchangeRate = (float) ($data['exchange_rate'] ?? 1);
        $coilPrice = (float) ($data['coil_price'] ?? 0);
        $sheetPrice = (float) ($data['sheet_price'] ?? 0);

        if (! isset($data['gap_steel_usd']) || $data['gap_steel_usd'] === null) {
            $data['gap_steel_usd'] = 0;
        }
        $data['gap_steel_idr'] = round((float) $data['gap_steel_usd'] * $exchangeRate, 2);

        $data['coil_price_idr'] = round($coilPrice * $exchangeRate, 2);
        $data['sheet_price_idr'] = round($sheetPrice * $exchangeRate, 2);

        $steelPrice = (float) ($data['steel_price'] ?? ($coilPrice ?: $sheetPrice ?: 0));
        $data['steel_price'] = $steelPrice;
        $data['steel_price_idr'] = round($steelPrice * $exchangeRate, 2);

        unset(
            $data['price_mode'],
            $data['prev_quarter_label'],
            $data['prev_record_found'],
            $data['prev_coil_price'],
            $data['prev_sheet_price'],
            $data['prev_rate'],
            $data['prev_quarter_info']
        );

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var MaterialSteel $record */
        $record = $this->record;

        // Jika dikaitkan dengan Master Material tertentu, perbarui harga dasar (basic_rate) material tersebut
        if ($record->material_id) {
            $material = Material::find($record->material_id);
            if ($material) {
                $material->basic_rate = $record->steel_price;
                $material->currency_id = $record->currency_id;
                $material->save();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
