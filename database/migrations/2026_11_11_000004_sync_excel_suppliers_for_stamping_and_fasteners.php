<?php

use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Hindari menyisipkan data fixture saat database kosong (mis. RefreshDatabase pada unit test)
        if (Supplier::count() === 0 && MaterialSteel::count() === 0 && StampingPart::count() === 0 && Fastener::count() === 0) {
            return;
        }

        $excelSuppliers = [
            [
                'code' => 'VND-POSMI',
                'name' => 'PT. POSMI STEEL INDONESIA',
                'category' => 'Stamping & Steel',
                'contact_person' => 'Marketing PT Posmi Steel',
            ],
            [
                'code' => 'VND-TCF',
                'name' => 'PT. TRI CENTRUM FORTUNA',
                'category' => 'Component',
                'contact_person' => 'Marketing PT TCF',
            ],
            [
                'code' => 'VND-NITTO',
                'name' => 'PT. NITTO ALAM INDONESIA',
                'category' => 'Fastener',
                'contact_person' => 'Marketing PT Nitto Alam',
            ],
            [
                'code' => 'VND-MEIDOH',
                'name' => 'PT. MEIDOH INDONESIA',
                'category' => 'Fastener',
                'contact_person' => 'Marketing PT Meidoh Indonesia',
            ],
        ];

        foreach ($excelSuppliers as $item) {
            $existing = Supplier::where('code', $item['code'])
                ->orWhere('name', $item['name'])
                ->first();

            if ($existing) {
                $existing->update([
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'is_active' => true,
                ]);
            } else {
                Supplier::create([
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'contact_person' => $item['contact_person'],
                    'is_active' => true,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive supplier sync
    }
};
