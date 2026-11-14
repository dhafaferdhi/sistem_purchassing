<?php

namespace Tests\Feature;

use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\MaterialSteelApResource;
use App\Filament\Resources\MaterialSteelArResource;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CurrencyRecalculationService;
use App\Services\ExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialSteelApArBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->supplier = Supplier::create([
            'code' => 'VND-POSCO',
            'name' => 'PT Posco Steel Indonesia',
            'category' => 'Steel',
        ]);
    }

    public function test_currency_auto_calculates_kg_and_idr_for_gap_hrc_and_gap_crc(): void
    {
        $currency = Currency::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
            'gap_hrc_usd' => -57.0, // $/MT
            'gap_crc_usd' => 100.0, // $/MT
        ]);

        $currency->refresh();

        // Gap HRC: -57 / 1000 = -0.057 $/kg, in IDR: -0.057 * 16000 = -912 IDR
        $this->assertEquals(-0.057, (float) $currency->gap_hrc_kg_usd);
        $this->assertEquals(-912.0, (float) $currency->gap_hrc_idr);

        // Gap CRC: 100 / 1000 = 0.1 $/kg, in IDR: 0.1 * 16000 = 1600 IDR
        $this->assertEquals(0.1, (float) $currency->gap_crc_kg_usd);
        $this->assertEquals(1600.0, (float) $currency->gap_crc_idr);
    }

    public function test_material_steel_ap_and_ar_are_segregated_by_price_type(): void
    {
        $steelAp = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '2.0',
            'coil_price' => 1.25,
            'price_type' => 'AP',
        ]);

        $steelAr = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPH270C-OD',
            'double_thickness' => '2.0',
            'coil_price' => 1.45,
            'price_type' => 'AR',
        ]);

        $apQuery = MaterialSteelApResource::getEloquentQuery()->pluck('id')->all();
        $arQuery = MaterialSteelArResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($steelAp->id, $apQuery);
        $this->assertNotContains($steelAr->id, $apQuery);

        $this->assertContains($steelAr->id, $arQuery);
        $this->assertNotContains($steelAp->id, $arQuery);
    }

    public function test_quarterly_benchmark_syncs_strictly_to_same_quarter_without_affecting_other_quarters(): void
    {
        $currencyQ1 = Currency::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'code' => 'USD',
            'name' => 'US Dollar',
            'exchange_rate_to_idr' => 16000.0,
            'gap_hrc_usd' => -50.0,
            'gap_crc_usd' => 50.0,
        ]);

        // Record Q1
        $steelQ1 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD', // Hot Rolled
            'coil_price' => 1.2000,
            'sheet_price' => 1.3000,
            'exchange_rate' => 15000.0,
            'price_type' => 'AP',
        ]);

        // Record Q2 (different quarter)
        $steelQ2 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD',
            'coil_price' => 1.5000,
            'sheet_price' => 1.6000,
            'exchange_rate' => 15500.0,
            'price_type' => 'AP',
        ]);

        // Sync benchmark for Q1
        CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($currencyQ1, true);

        $steelQ1->refresh();
        $steelQ2->refresh();

        // Q1 is updated with new rate and gap formula (Harga Baru = Harga Kuartal Lalu/Dasar + Gap)
        $this->assertEquals(16000.0, (float) $steelQ1->exchange_rate);
        $this->assertEquals(-50.0, (float) $steelQ1->gap_steel_usd);
        $this->assertEquals(-0.05, (float) $steelQ1->gap_steel_kg_usd);
        $this->assertEquals(round((1.2000 - 0.05) * 16000.0, 2), (float) $steelQ1->coil_price_idr);

        // Q2 is UNTOUCHED
        $this->assertEquals(15500.0, (float) $steelQ2->exchange_rate);
        $this->assertEquals(1.5000, (float) $steelQ2->coil_price);
    }

    public function test_quarterly_pull_respects_price_type(): void
    {
        MaterialSteel::$disableApArSync = true;
        try {
            // Acuan Q1 AP
            $apQ1 = MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q1',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'coil_price' => 1.2000,
                'sheet_price' => 1.2500,
                'price_type' => 'AP',
            ]);

            // Acuan Q1 AR (harga berbeda)
            $arQ1 = MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q1',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'coil_price' => 1.4000,
                'sheet_price' => 1.4500,
                'price_type' => 'AR',
            ]);

            // Cari acuan untuk Q2 AP
            $foundAp = MaterialSteel::findPreviousQuarterData('Q2', 2026, $this->supplier->id, 'SPH270C-OD', null, 'AP');
            $this->assertNotNull($foundAp);
            $this->assertEquals(1.2000, (float) $foundAp['coil_price']);
            $this->assertEquals('AP', $foundAp['price_type']);

            // Cari acuan untuk Q2 AR
            $foundAr = MaterialSteel::findPreviousQuarterData('Q2', 2026, $this->supplier->id, 'SPH270C-OD', null, 'AR');
            $this->assertNotNull($foundAr);
            $this->assertEquals(1.4000, (float) $foundAr['coil_price']);
            $this->assertEquals('AR', $foundAr['price_type']);
        } finally {
            MaterialSteel::$disableApArSync = false;
        }
    }

    public function test_creating_or_updating_material_steel_ap_automatically_syncs_to_ar_and_vice_versa(): void
    {
        // 1. Create AP -> AR harus otomatis dibuat dengan data yang sama
        $ap = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-SYNC-TEST',
            'double_thickness' => '1.2',
            'coil_price' => 1.3500,
            'sheet_price' => 1.4000,
            'exchange_rate' => 16000.0,
            'price_type' => 'AP',
        ]);

        $ar = MaterialSteel::where('year', 2026)
            ->where('quarter', 'Q1')
            ->where('price_type', 'AR')
            ->where('steel_spec', 'SPCC-SYNC-TEST')
            ->where('double_thickness', '1.2')
            ->first();

        $this->assertNotNull($ar, 'Record Material Steel AR harus otomatis dibuat saat data AP disimpan.');
        $this->assertEquals(1.3500, (float) $ar->coil_price);
        $this->assertEquals(1.4000, (float) $ar->sheet_price);
        $this->assertEquals(16000.0, (float) $ar->exchange_rate);
        $this->assertEquals($this->supplier->id, $ar->supplier_id);

        // 2. Update AR -> AP harus otomatis ikut terupdate (begitu juga sebaliknya)
        $ar->update([
            'coil_price' => 1.5500,
        ]);

        $ap->refresh();
        $this->assertEquals(1.5500, (float) $ap->coil_price, 'Harga AP harus otomatis tersinkron saat AR diperbarui.');

        // 3. Update AP -> AR harus otomatis ikut terupdate
        $ap->update([
            'sheet_price' => 1.6500,
        ]);

        $ar->refresh();
        $this->assertEquals(1.6500, (float) $ar->sheet_price, 'Harga AR harus otomatis tersinkron saat AP diperbarui.');

        // 4. Delete AP -> AR harus otomatis ikut terdelete
        $apId = $ap->id;
        $arId = $ar->id;
        $ap->delete();
        $this->assertDatabaseMissing('material_steels', ['id' => $apId]);
        $this->assertDatabaseMissing('material_steels', ['id' => $arId]);
    }

    public function test_deleting_material_steel_ar_automatically_deletes_ap_counterpart(): void
    {
        $ar = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-DEL-AR-TEST',
            'double_thickness' => '1.4',
            'coil_price' => 1.4500,
            'sheet_price' => 1.5000,
            'exchange_rate' => 16000.0,
            'price_type' => 'AR',
        ]);

        $ap = MaterialSteel::where('year', 2026)
            ->where('quarter', 'Q2')
            ->where('price_type', 'AP')
            ->where('steel_spec', 'SPCC-DEL-AR-TEST')
            ->first();

        $this->assertNotNull($ap);

        $apId = $ap->id;
        $arId = $ar->id;

        // Delete AR -> AP harus otomatis ikut terdelete
        $ar->delete();
        $this->assertDatabaseMissing('material_steels', ['id' => $arId]);
        $this->assertDatabaseMissing('material_steels', ['id' => $apId]);
    }

    public function test_deleting_ap_from_filament_table_deletes_both_ap_and_ar(): void
    {
        $ap = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q3',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-FILAMENT-DEL',
            'double_thickness' => '1.6',
            'coil_price' => 1.2500,
            'sheet_price' => 1.3000,
            'exchange_rate' => 16000.0,
            'price_type' => 'AP',
        ]);

        $ar = MaterialSteel::where('year', 2026)
            ->where('quarter', 'Q3')
            ->where('price_type', 'AR')
            ->where('steel_spec', 'SPCC-FILAMENT-DEL')
            ->first();
        $this->assertNotNull($ar);

        $apId = $ap->id;
        $arId = $ar->id;

        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\MaterialSteelApResource\Pages\ListMaterialSteelAps::class)
            ->callTableAction(\Filament\Tables\Actions\DeleteAction::class, $ap);

        $this->assertDatabaseMissing('material_steels', ['id' => $apId]);
        $this->assertDatabaseMissing('material_steels', ['id' => $arId]);
    }

    public function test_bulk_deleting_material_steel_ap_deletes_corresponding_ar_records(): void
    {
        $ap1 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-BULK-DEL-A',
            'double_thickness' => '1.0',
            'coil_price' => 1.2000,
            'sheet_price' => 1.2500,
            'exchange_rate' => 16000.0,
            'price_type' => 'AP',
        ]);

        $ap2 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-BULK-DEL-B',
            'double_thickness' => '1.2',
            'coil_price' => 1.2200,
            'sheet_price' => 1.2700,
            'exchange_rate' => 16000.0,
            'price_type' => 'AP',
        ]);

        $ar1 = MaterialSteel::where('steel_spec', 'SPCC-BULK-DEL-A')->where('price_type', 'AR')->first();
        $ar2 = MaterialSteel::where('steel_spec', 'SPCC-BULK-DEL-B')->where('price_type', 'AR')->first();
        $this->assertNotNull($ar1);
        $this->assertNotNull($ar2);

        $ap1Id = $ap1->id;
        $ap2Id = $ap2->id;
        $ar1Id = $ar1->id;
        $ar2Id = $ar2->id;

        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\MaterialSteelApResource\Pages\ListMaterialSteelAps::class)
            ->callTableBulkAction(\Filament\Tables\Actions\DeleteBulkAction::class, [$ap1, $ap2]);

        $this->assertDatabaseMissing('material_steels', ['id' => $ap1Id]);
        $this->assertDatabaseMissing('material_steels', ['id' => $ap2Id]);
        $this->assertDatabaseMissing('material_steels', ['id' => $ar1Id]);
        $this->assertDatabaseMissing('material_steels', ['id' => $ar2Id]);
    }

    public function test_editing_material_steel_ap_saves_successfully_and_syncs_counterpart(): void
    {
        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-EDIT-TEST',
            'double_thickness' => '1.2',
            'coil_price' => 1.2000,
            'sheet_price' => 1.2500,
            'exchange_rate' => 16000.0,
            'price_type' => 'AP',
        ]);

        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\MaterialSteelApResource\Pages\EditMaterialSteelAp::class, [
                'record' => $steel->getKey(),
            ])
            ->fillForm([
                'coil_price' => 1.3000,
                'sheet_price' => 1.3500,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $steel->refresh();
        $this->assertEquals(1.3000, (float) $steel->coil_price);
        $this->assertEquals(1.3500, (float) $steel->sheet_price);

        $ar = MaterialSteel::where('steel_spec', 'SPCC-EDIT-TEST')->where('price_type', 'AR')->first();
        $this->assertNotNull($ar);
        $this->assertEquals(1.3000, (float) $ar->coil_price);
        $this->assertEquals(1.3500, (float) $ar->sheet_price);
    }

    public function test_editing_material_steel_ar_saves_successfully(): void
    {
        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-EDIT-AR-TEST',
            'double_thickness' => '1.4',
            'coil_price' => 1.4000,
            'sheet_price' => 1.4500,
            'exchange_rate' => 16000.0,
            'price_type' => 'AR',
        ]);

        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\MaterialSteelArResource\Pages\EditMaterialSteelAr::class, [
                'record' => $steel->getKey(),
            ])
            ->fillForm([
                'coil_price' => 1.5000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $steel->refresh();
        $this->assertEquals(1.5000, (float) $steel->coil_price);
    }

    public function test_currency_auto_calculates_gap_ap_and_gap_ar_formulas(): void
    {
        $currency = Currency::create([
            'year' => 2027,
            'quarter' => 'Q1',
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
            'gap_ap_usd' => -57.0, // $/MT
            'gap_ar_usd' => -50.0, // $/MT
        ]);

        $currency->refresh();

        // Rumus: Gap ($/Kg) = Gap ($/MT) / 1000
        // Rumus: Gap (IDR)  = Gap ($/Kg) * Kurs Dollar
        // Gap AP: -57 / 1000 = -0.057 $/kg, in IDR: -0.057 * 16000 = -912 IDR
        $this->assertEquals(-0.0570, (float) $currency->gap_ap_kg_usd);
        $this->assertEquals(-912.0, (float) $currency->gap_ap_idr);

        // Gap AR: -50 / 1000 = -0.050 $/kg, in IDR: -0.050 * 16000 = -800 IDR
        $this->assertEquals(-0.0500, (float) $currency->gap_ar_kg_usd);
        $this->assertEquals(-800.0, (float) $currency->gap_ar_idr);
    }

    public function test_changing_q1_2027_rate_updates_q1_2027_data_without_modifying_q4_2026_data(): void
    {
        MaterialSteel::$disableApArSync = true;
        try {
            // 1. Setup Periode Q4 2026
            $currQ4_2026 = Currency::create([
                'year' => 2026,
                'quarter' => 'Q4',
                'code' => 'USD',
                'exchange_rate_to_idr' => 15500.0,
                'gap_ap_usd' => -20.0,
                'gap_ar_usd' => -15.0,
            ]);

            $steelApQ4 = MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q4',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'coil_price' => 1.0000,
                'exchange_rate' => 15500.0,
                'coil_price_idr' => 15500.0,
                'gap_steel_usd' => -20.0,
                'gap_steel_kg_usd' => -0.02,
                'price_type' => 'AP',
            ]);

            $steelArQ4 = MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q4',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'coil_price' => 1.2000,
                'exchange_rate' => 15500.0,
                'coil_price_idr' => 18600.0,
                'gap_steel_usd' => -15.0,
                'gap_steel_kg_usd' => -0.015,
                'price_type' => 'AR',
            ]);

            // 2. Setup Periode Q1 2027
            $currQ1_2027 = Currency::create([
                'year' => 2027,
                'quarter' => 'Q1',
                'code' => 'USD',
                'exchange_rate_to_idr' => 16000.0,
                'gap_ap_usd' => -57.0,
                'gap_ar_usd' => -50.0,
            ]);

            $steelApQ1 = MaterialSteel::create([
                'year' => 2027,
                'quarter' => 'Q1',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'coil_price' => 1.0000,
                'exchange_rate' => 16000.0,
                'coil_price_idr' => 16000.0,
                'gap_steel_usd' => -57.0,
                'gap_steel_kg_usd' => -0.057,
                'price_type' => 'AP',
            ]);

            $steelArQ1 = MaterialSteel::create([
                'year' => 2027,
                'quarter' => 'Q1',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'coil_price' => 1.2000,
                'exchange_rate' => 16000.0,
                'coil_price_idr' => 19200.0,
                'gap_steel_usd' => -50.0,
                'gap_steel_kg_usd' => -0.05,
                'price_type' => 'AR',
            ]);

            // 3. User mengubah Kurs Dollar Q1 2027 dari 16000 menjadi 16500,
            // Gap AP dari -57 menjadi -60, Gap AR dari -50 menjadi -45
            $currQ1_2027->update([
                'exchange_rate_to_idr' => 16500.0,
                'gap_ap_usd' => -60.0,
                'gap_ar_usd' => -45.0,
            ]);

            // 4. Verifikasi bahwa data Q1 2027 otomatis terupdate
            $steelApQ1->refresh();
            $steelArQ1->refresh();

            $this->assertEquals(16500.0, (float) $steelApQ1->exchange_rate);
            $this->assertEquals(round((1.0000 - 0.06) * 16500.0, 2), (float) $steelApQ1->coil_price_idr);
            $this->assertEquals(-60.0, (float) $steelApQ1->gap_steel_usd);
            $this->assertEquals(-0.06, (float) $steelApQ1->gap_steel_kg_usd);

            $this->assertEquals(16500.0, (float) $steelArQ1->exchange_rate);
            $this->assertEquals(round((1.2000 - 0.045) * 16500.0, 2), (float) $steelArQ1->coil_price_idr);
            $this->assertEquals(-45.0, (float) $steelArQ1->gap_steel_usd);
            $this->assertEquals(-0.045, (float) $steelArQ1->gap_steel_kg_usd);

            // 5. Verifikasi bahwa data Q4 2026 SAMA SEKALI TIDAK BERUBAH (Strict Isolation)
            $steelApQ4->refresh();
            $steelArQ4->refresh();
            $currQ4_2026->refresh();

            $this->assertEquals(15500.0, (float) $steelApQ4->exchange_rate, 'Kurs Steel AP Q4 2026 tidak boleh berubah');
            $this->assertEquals(15500.0, (float) $steelApQ4->coil_price_idr, 'Harga IDR Steel AP Q4 2026 tidak boleh berubah');
            $this->assertEquals(-20.0, (float) $steelApQ4->gap_steel_usd, 'Gap AP Q4 2026 tidak boleh berubah');

            $this->assertEquals(15500.0, (float) $steelArQ4->exchange_rate, 'Kurs Steel AR Q4 2026 tidak boleh berubah');
            $this->assertEquals(18600.0, (float) $steelArQ4->coil_price_idr, 'Harga IDR Steel AR Q4 2026 tidak boleh berubah');
            $this->assertEquals(-15.0, (float) $steelArQ4->gap_steel_usd, 'Gap AR Q4 2026 tidak boleh berubah');

            $this->assertEquals(15500.0, (float) $currQ4_2026->exchange_rate_to_idr, 'Kurs Q4 2026 Currency tidak boleh berubah');
        } finally {
            MaterialSteel::$disableApArSync = false;
        }
    }

    public function test_sync_quarterly_benchmark_assigns_gap_ap_to_ap_and_gap_ar_to_ar(): void
    {
        $currency = Currency::create([
            'year' => 2027,
            'quarter' => 'Q2',
            'code' => 'USD',
            'exchange_rate_to_idr' => 16200.0,
            'gap_ap_usd' => -57.0, // Gap AP
            'gap_ar_usd' => 80.0,  // Gap AR
        ]);

        $steelAp = MaterialSteel::create([
            'year' => 2027,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD',
            'coil_price' => 1.25,
            'price_type' => 'AP',
        ]);

        $steelAr = MaterialSteel::create([
            'year' => 2027,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD',
            'coil_price' => 1.45,
            'price_type' => 'AR',
        ]);

        CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($currency, true);

        $steelAp->refresh();
        $steelAr->refresh();

        // AP mengambil Gap AP (-57 $/MT -> -0.057 $/kg)
        $this->assertEquals(-57.0, (float) $steelAp->gap_steel_usd);
        $this->assertEquals(-0.057, (float) $steelAp->gap_steel_kg_usd);
        $this->assertEquals(round(-0.057 * 16200.0, 2), (float) $steelAp->gap_steel_idr);
        $this->assertEquals(16200.0, (float) $steelAp->exchange_rate);

        // AR mengambil Gap AR (80 $/MT -> 0.08 $/kg)
        $this->assertEquals(80.0, (float) $steelAr->gap_steel_usd);
        $this->assertEquals(0.08, (float) $steelAr->gap_steel_kg_usd);
        $this->assertEquals(round(0.08 * 16200.0, 2), (float) $steelAr->gap_steel_idr);
        $this->assertEquals(16200.0, (float) $steelAr->exchange_rate);
    }

    public function test_currency_resource_create_and_edit_pages_render_and_save_gap_ap_and_gap_ar(): void
    {
        // 1. Test Create Page
        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\CurrencyResource\Pages\CreateCurrency::class)
            ->fillForm([
                'year' => 2027,
                'quarter' => 'Q3',
                'kurs_usd' => 16300.0,
                'kurs_jpy' => 108.0,
                'gap_ap_usd' => -55.0,
                'gap_ar_usd' => -48.0,
                'notes' => 'Test Q3 2027',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $saved = Currency::where('year', 2027)->where('quarter', 'Q3')->where('code', 'USD')->first();
        $this->assertNotNull($saved);
        $this->assertEquals(16300.0, (float) $saved->exchange_rate_to_idr);
        $this->assertEquals(-55.0, (float) $saved->gap_ap_usd);
        $this->assertEquals(-0.055, (float) $saved->gap_ap_kg_usd);
        $this->assertEquals(round(-0.055 * 16300.0, 2), (float) $saved->gap_ap_idr);
        $this->assertEquals(-48.0, (float) $saved->gap_ar_usd);
        $this->assertEquals(-0.048, (float) $saved->gap_ar_kg_usd);
        $this->assertEquals(round(-0.048 * 16300.0, 2), (float) $saved->gap_ar_idr);

        // 2. Test Edit Page
        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\CurrencyResource\Pages\EditCurrency::class, ['record' => $saved->getKey()])
            ->assertFormFieldExists('gap_ap_usd')
            ->assertFormFieldExists('gap_ar_usd')
            ->fillForm([
                'kurs_usd' => 16400.0,
                'gap_ap_usd' => -58.0,
                'gap_ar_usd' => 25.0,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved->refresh();
        $this->assertEquals(16400.0, (float) $saved->exchange_rate_to_idr);
        $this->assertEquals(-58.0, (float) $saved->gap_ap_usd);
        $this->assertEquals(-0.058, (float) $saved->gap_ap_kg_usd);
        $this->assertEquals(round(-0.058 * 16400.0, 2), (float) $saved->gap_ap_idr);
        $this->assertEquals(25.0, (float) $saved->gap_ar_usd);
        $this->assertEquals(0.025, (float) $saved->gap_ar_kg_usd);
        $this->assertEquals(round(0.025 * 16400.0, 2), (float) $saved->gap_ar_idr);
    }

    public function test_changing_gap_ar_on_currency_does_not_modify_material_steel_ap_data_and_vice_versa(): void
    {
        // Setup Currency untuk 2025 Q2 dengan Gap AP 10 $/MT dan Gap AR 20 $/MT
        $currency = Currency::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'month' => 4,
            'code' => 'USD',
            'exchange_rate_to_idr' => 16500.0,
            'gap_ap_usd' => 10.0,
            'gap_ar_usd' => 20.0,
        ]);

        $ap = MaterialSteel::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-ISOLATION-TEST',
            'double_thickness' => '1.0',
            'coil_price' => 1.2000,
            'sheet_price' => 1.3000,
            'exchange_rate' => 16500.0,
            'gap_steel_usd' => 10.0,
            'price_type' => 'AP',
        ]);

        $ar = MaterialSteel::where('year', 2025)
            ->where('quarter', 'Q2')
            ->where('price_type', 'AR')
            ->where('steel_spec', 'SPCC-ISOLATION-TEST')
            ->first();

        $this->assertNotNull($ar);
        // AR mengambil gap AR (20) dari Currency
        $this->assertEquals(20.0, (float) $ar->gap_steel_usd);
        $this->assertEquals(10.0, (float) $ap->gap_steel_usd);

        // 1. Ubah Gap AR di Currency menjadi 50 $/MT
        $currency->update([
            'gap_ar_usd' => 50.0,
            'gap_hrc_ar_usd' => 50.0,
            'gap_crc_ar_usd' => 50.0,
        ]);
        CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($currency, true);

        $ap->refresh();
        $ar->refresh();

        // AR berubah menjadi 50 $/MT
        $this->assertEquals(50.0, (float) $ar->gap_steel_usd);
        // AP HARUS TETAP 10 $/MT (TIDAK BOLEH IKUT BERUBAH)
        $this->assertEquals(10.0, (float) $ap->gap_steel_usd, 'Gap Material Steel AP tidak boleh ikut berubah saat Gap AR diubah di Kurs');
        $this->assertEquals(1.2000, (float) $ap->coil_price);
        $this->assertEquals(1.3000, (float) $ap->sheet_price);

        // 2. Ubah Gap AP di Currency menjadi -35 $/MT
        $currency->update([
            'gap_ap_usd' => -35.0,
            'gap_hrc_usd' => -35.0,
            'gap_crc_usd' => -35.0,
        ]);
        CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($currency, true);

        $ap->refresh();
        $ar->refresh();

        // AP berubah menjadi -35 $/MT
        $this->assertEquals(-35.0, (float) $ap->gap_steel_usd);
        // AR HARUS TETAP 50 $/MT (TIDAK BOLEH IKUT BERUBAH)
        $this->assertEquals(50.0, (float) $ar->gap_steel_usd, 'Gap Material Steel AR tidak boleh ikut berubah saat Gap AP diubah di Kurs');
    }

    public function test_list_currencies_page_auto_syncs_and_loads_data_from_material_steel_when_year_and_quarter_selected(): void
    {
        // User membuat item di Material Steel AP untuk 2025 Q2 dengan Kurs 16800 dan Gap -45
        MaterialSteel::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-2025-Q2-TEST',
            'double_thickness' => '0.8',
            'coil_price' => 1.1500,
            'sheet_price' => 1.2500,
            'exchange_rate' => 16800.0,
            'gap_steel_usd' => -45.0,
            'price_type' => 'AP',
        ]);

        // Uji halaman ListCurrencies Livewire ketika memilih 2025 dan Q2
        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\CurrencyResource\Pages\ListCurrencies::class)
            ->set('selectedYear', 2025)
            ->set('selectedQuarter', 'Q2')
            ->assertSet('rateUsd', 16800.0)
            ->assertSet('gapApUsd', -45.0);

        // Verifikasi AR juga otomatis ada dan tersinkron
        $ar = MaterialSteel::where('year', 2025)->where('quarter', 'Q2')->where('price_type', 'AR')->first();
        $this->assertNotNull($ar, 'Item Material Steel AR harus otomatis tersedia untuk 2025 Q2');
        $this->assertEquals(1.1500, (float) $ar->coil_price);
        $this->assertEquals(16800.0, (float) $ar->exchange_rate);
    }

    public function test_list_currencies_renders_master_data_table_and_tabs_under_gap_form(): void
    {
        // Buat data Material Steel AP & AR untuk 2025 Q3
        MaterialSteel::create([
            'year' => 2025,
            'quarter' => 'Q3',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-TAB-TEST',
            'double_thickness' => '1.2',
            'coil_price' => 1.3000,
            'sheet_price' => 1.4000,
            'exchange_rate' => 16500.0,
            'price_type' => 'AP',
        ]);

        // Buat Stamping Part untuk 2025 Q3
        StampingPart::create([
            'year' => 2025,
            'quarter' => 'Q3',
            'supplier_id' => $this->supplier->id,
            'part_number' => 'STAMP-2025-Q3',
            'part_name' => 'Bracket Arm',
            'steel_spec' => 'SPCC-TAB-TEST',
            'double_thickness' => '1.2',
            'part_cost_excluding_tooling' => 2.5000,
        ]);

        // Uji komponen Livewire
        $test = \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\CurrencyResource\Pages\ListCurrencies::class)
            ->set('selectedYear', 2025)
            ->set('selectedQuarter', 'Q3');

        // Pastikan counter master data terhitung benar
        $counts = $test->instance()->masterDataCounts;
        $this->assertEquals(1, $counts['steel_ap']);
        $this->assertEquals(1, $counts['steel_ar']);
        $this->assertEquals(1, $counts['stamping']);
        $this->assertEquals(3, $counts['all']);

        // Pastikan data 'all' berisi 3 item
        $records = $test->instance()->masterDataRecords;
        $this->assertCount(3, $records);

        // Filter ke tab 'steel_ap'
        $test->set('selectedMasterTab', 'steel_ap');
        $filtered = $test->instance()->masterDataRecords;
        $this->assertCount(1, $filtered);
        $this->assertEquals('Material Steel AP', $filtered->first()['category']);

        // Cek fungsionalitas pencarian
        $test->set('selectedMasterTab', 'all')
            ->set('searchMaster', 'Bracket');
        $searchResult = $test->instance()->masterDataRecords;
        $this->assertCount(1, $searchResult);
        $this->assertEquals('Bracket Arm', $searchResult->first()['name_desc']);
    }

    public function test_updating_gap_ar_on_list_currencies_page_recalculates_material_steel_ar_prices_and_leaves_ap_untouched(): void
    {
        // 1. Data kuartal sebelumnya (2026 Q1)
        $apQ1 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-GAP-RECALC',
            'double_thickness' => '1.0',
            'coil_price' => 1.2000,
            'sheet_price' => 1.3000,
            'exchange_rate' => 16000.0,
            'price_type' => 'AP',
        ]);

        $arQ1 = MaterialSteel::where('year', 2026)->where('quarter', 'Q1')->where('price_type', 'AR')->where('steel_spec', 'SPCC-GAP-RECALC')->first();
        $this->assertNotNull($arQ1);

        // 2. Data kuartal 2026 Q2 (dibuat sebelum Gap AR diisi)
        $apQ2 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPCC-GAP-RECALC',
            'double_thickness' => '1.0',
            'coil_price' => 1.2000,
            'sheet_price' => 1.3000,
            'exchange_rate' => 16000.0,
            'price_type' => 'AP',
        ]);

        $arQ2 = MaterialSteel::where('year', 2026)->where('quarter', 'Q2')->where('price_type', 'AR')->where('steel_spec', 'SPCC-GAP-RECALC')->first();
        $this->assertNotNull($arQ2);

        // 3. Pada halaman Kurs dan Gap Kuartalan, user memilih 2026 Q2 dan mengubah Gap Baja AR menjadi -50 ($/MT)
        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\CurrencyResource\Pages\ListCurrencies::class)
            ->set('selectedYear', 2026)
            ->set('selectedQuarter', 'Q2')
            ->set('rateUsd', 16000.0)
            ->set('gapApUsd', 0.0)
            ->set('gapArUsd', -50.0) // Gap AR diubah ke -50
            ->call('autoSave');

        // 4. Verifikasi Material Steel AR 2026 Q2: Harga coil USD, sheet USD, coil Rp, sheet Rp otomatis berubah
        $arQ2->refresh();
        $this->assertEquals(-50.0, (float) $arQ2->gap_steel_usd);
        $this->assertEquals(-0.0500, (float) $arQ2->gap_steel_kg_usd);
        $this->assertEquals(1.1500, (float) $arQ2->coil_price, 'Coil price USD AR harus terhitung: 1.2000 + (-0.0500) = 1.1500');
        $this->assertEquals(1.2500, (float) $arQ2->sheet_price, 'Sheet price USD AR harus terhitung: 1.3000 + (-0.0500) = 1.2500');
        $this->assertEquals(18400.0, (float) $arQ2->coil_price_idr, 'Coil price IDR AR harus terhitung: 1.1500 * 16000 = 18400');
        $this->assertEquals(20000.0, (float) $arQ2->sheet_price_idr, 'Sheet price IDR AR harus terhitung: 1.2500 * 16000 = 20000');

        // 5. Verifikasi Material Steel AP 2026 Q2: DATA TIDAK BOLEH BERUBAH karena Gap AP tidak dirubah!
        $apQ2->refresh();
        $this->assertEquals(1.2000, (float) $apQ2->coil_price, 'Coil price AP harus tetap terisolasi dan tidak berubah.');
        $this->assertEquals(1.3000, (float) $apQ2->sheet_price, 'Sheet price AP harus tetap terisolasi dan tidak berubah.');
        $this->assertEquals(19200.0, (float) $apQ2->coil_price_idr);
        $this->assertEquals(20800.0, (float) $apQ2->sheet_price_idr);
    }

    public function test_updating_gap_ar_when_no_previous_quarter_exists_recalculates_ar_and_leaves_ap_untouched(): void
    {
        // Setup Currency untuk 2025 Q2 dengan Gap AP -57 $/MT dan Gap AR awal 0
        $currency = Currency::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
            'gap_ap_usd' => -57.0,
            'gap_ar_usd' => 0.0,
        ]);

        // Skenario nyata: User langsung membuat master data di 2025 Q2 tanpa pernah membuat 2025 Q1.
        $ap = MaterialSteel::create([
            'year' => 2025,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-INIT-Q2',
            'double_thickness' => '2.0',
            'coil_price' => 1.2000,
            'sheet_price' => 1.2250,
            'exchange_rate' => 16000.0,
            'gap_steel_usd' => -57.0,
            'gap_steel_kg_usd' => -0.0570,
            'price_type' => 'AP',
        ]);

        $ar = MaterialSteel::where('year', 2025)->where('quarter', 'Q2')->where('price_type', 'AR')->where('steel_spec', 'SPH270C-INIT-Q2')->first();
        $this->assertNotNull($ar);

        // 1. Pada Kurs dan Gap Kuartalan, user mengubah Gap AR menjadi +20 ($/MT = +0.0200 $/Kg)
        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\CurrencyResource\Pages\ListCurrencies::class)
            ->set('selectedYear', 2025)
            ->set('selectedQuarter', 'Q2')
            ->set('rateUsd', 16000.0)
            ->set('gapApUsd', -57.0)
            ->set('gapArUsd', 20.0)
            ->call('autoSave');

        // 2. Verifikasi Material Steel AR 2025 Q2: Otomatis berubah meskipun Q1 tidak ada!
        $ar->refresh();
        $this->assertEquals(20.0, (float) $ar->gap_steel_usd);
        $this->assertEquals(0.0200, (float) $ar->gap_steel_kg_usd);
        $this->assertEquals(1.2200, (float) $ar->coil_price, 'Harga Coil AR harus terhitung dari base AP + Gap AR: 1.2000 + 0.0200 = 1.2200');
        $this->assertEquals(1.2450, (float) $ar->sheet_price, 'Harga Sheet AR harus terhitung dari base AP + Gap AR: 1.2250 + 0.0200 = 1.2450');
        $this->assertEquals(19520.0, (float) $ar->coil_price_idr, 'Harga Coil Rp AR harus: 1.2200 * 16000 = 19520');
        $this->assertEquals(19920.0, (float) $ar->sheet_price_idr, 'Harga Sheet Rp AR harus: 1.2450 * 16000 = 19920');

        // 3. Verifikasi Material Steel AP 2025 Q2: Tetap terisolasi ketat!
        $ap->refresh();
        $this->assertEquals(1.2000, (float) $ap->coil_price);
        $this->assertEquals(1.2250, (float) $ap->sheet_price);
        $this->assertEquals(19200.0, (float) $ap->coil_price_idr);
        $this->assertEquals(19600.0, (float) $ap->sheet_price_idr);

        // 4. User kemudian mengubah Gap AR menjadi -10 ($/MT = -0.0100 $/Kg)
        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\CurrencyResource\Pages\ListCurrencies::class)
            ->set('selectedYear', 2025)
            ->set('selectedQuarter', 'Q2')
            ->set('rateUsd', 16000.0)
            ->set('gapApUsd', -57.0)
            ->set('gapArUsd', -10.0)
            ->call('autoSave');

        $ar->refresh();
        $this->assertEquals(-10.0, (float) $ar->gap_steel_usd);
        $this->assertEquals(-0.0100, (float) $ar->gap_steel_kg_usd);
        $this->assertEquals(1.1900, (float) $ar->coil_price, 'Harga Coil AR harus terhitung: 1.2000 + (-0.0100) = 1.1900');
        $this->assertEquals(1.2150, (float) $ar->sheet_price, 'Harga Sheet AR harus terhitung: 1.2250 + (-0.0100) = 1.2150');
        $this->assertEquals(19040.0, (float) $ar->coil_price_idr, 'Harga Coil Rp AR harus: 1.1900 * 16000 = 19040');
        $this->assertEquals(19440.0, (float) $ar->sheet_price_idr, 'Harga Sheet Rp AR harus: 1.2150 * 16000 = 19440');

        $ap->refresh();
        $this->assertEquals(1.2000, (float) $ap->coil_price);
        $this->assertEquals(19200.0, (float) $ap->coil_price_idr);
    }

    public function test_material_steel_category_dropdown_and_mutual_gap_exclusion(): void
    {
        // 1. Simpan Material Steel dengan kategori HRC
        $steelHrc = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD',
            'steel_category' => 'HRC',
            'hr_steel_usd' => 70.0,
            'cr_steel_usd' => -2.0, // Harus di-reset jadi 0 karena kategori HRC
            'coil_price' => 1.2846,
            'sheet_price' => 1.3096,
            'exchange_rate' => 15556.0,
            'price_type' => 'AP',
        ]);

        $this->assertEquals('HRC', $steelHrc->steel_category);
        $this->assertEquals(70.0, (float) $steelHrc->hr_steel_usd);
        $this->assertEquals(0.0, (float) $steelHrc->cr_steel_usd);
        $this->assertEquals(70.0, (float) $steelHrc->gap_steel_usd);
        $this->assertEquals(0.07, (float) $steelHrc->gap_steel_kg_usd);

        // 2. Simpan Material Steel dengan kategori CRC
        $steelCrc = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPC270C',
            'steel_category' => 'CRC',
            'hr_steel_usd' => 70.0, // Harus di-reset jadi 0 karena kategori CRC
            'cr_steel_usd' => -2.0,
            'coil_price' => 1.3946,
            'sheet_price' => 1.4196,
            'exchange_rate' => 15556.0,
            'price_type' => 'AP',
        ]);

        $this->assertEquals('CRC', $steelCrc->steel_category);
        $this->assertEquals(0.0, (float) $steelCrc->hr_steel_usd);
        $this->assertEquals(-2.0, (float) $steelCrc->cr_steel_usd);
        $this->assertEquals(-2.0, (float) $steelCrc->gap_steel_usd);
        $this->assertEquals(-0.002, (float) $steelCrc->gap_steel_kg_usd);
    }

    public function test_currency_kurs_and_kuartal_gap_ap_and_gap_ar_use_identical_formulas(): void
    {
        MaterialSteel::$disableApArSync = true;
        try {
            // Setup kuartal sebelumnya (Q1 2026) untuk AP dan AR
            $apQ1 = MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q1',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'steel_category' => 'HRC',
                'coil_price' => 1.2146,
                'sheet_price' => 1.2396,
                'exchange_rate' => 15433.0,
                'price_type' => 'AP',
            ]);

            $arQ1 = MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q1',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'steel_category' => 'HRC',
                'coil_price' => 1.2500,
                'sheet_price' => 1.2800,
                'exchange_rate' => 15433.0,
                'price_type' => 'AR',
            ]);

            // Setup Q2 2026 untuk AP dan AR
            $apQ2 = MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q2',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'steel_category' => 'HRC',
                'coil_price' => 1.2146,
                'sheet_price' => 1.2396,
                'exchange_rate' => 15433.0,
                'price_type' => 'AP',
            ]);

            $arQ2 = MaterialSteel::create([
                'year' => 2026,
                'quarter' => 'Q2',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'steel_category' => 'HRC',
                'coil_price' => 1.2500,
                'sheet_price' => 1.2800,
                'exchange_rate' => 15433.0,
                'price_type' => 'AR',
            ]);

            // Update Kurs USD menjadi 15556, Gap AP = 70 ($/MT), Gap AR = 50 ($/MT)
            $currencyQ2 = Currency::create([
                'year' => 2026,
                'quarter' => 'Q2',
                'code' => 'USD',
                'exchange_rate_to_idr' => 15556.0,
                'gap_ap_usd' => 70.0,
                'gap_ar_usd' => 50.0,
            ]);

            CurrencyRecalculationService::syncQuarterlyBenchmarkToRecords($currencyQ2, true);

            $apQ2->refresh();
            $arQ2->refresh();

            // Rumus AP: 70 $/MT = 0.07 $/kg
            // Coil AP ($): 1.2146 + 0.07 = 1.2846
            // Sheet AP ($): 1.2396 + 0.07 = 1.3096
            // Coil AP (Rp): 1.2846 * 15556 = 19983.24
            // Sheet AP (Rp): 1.3096 * 15556 = 20372.14
            $this->assertEquals(70.0, (float) $apQ2->gap_steel_usd);
            $this->assertEquals(0.07, (float) $apQ2->gap_steel_kg_usd);
            $this->assertEquals(1.2846, (float) $apQ2->coil_price);
            $this->assertEquals(1.3096, (float) $apQ2->sheet_price);
            $this->assertEquals(19983.24, (float) $apQ2->coil_price_idr);
            $this->assertEquals(20372.14, (float) $apQ2->sheet_price_idr);

            // Rumus AR (persis sama): 50 $/MT = 0.05 $/kg
            // Coil AR ($): 1.2500 + 0.05 = 1.3000
            // Sheet AR ($): 1.2800 + 0.05 = 1.3300
            // Coil AR (Rp): 1.3000 * 15556 = 20222.80
            // Sheet AR (Rp): 1.3300 * 15556 = 20689.48
            $this->assertEquals(50.0, (float) $arQ2->gap_steel_usd);
            $this->assertEquals(0.05, (float) $arQ2->gap_steel_kg_usd);
            $this->assertEquals(1.3000, (float) $arQ2->coil_price);
            $this->assertEquals(1.3300, (float) $arQ2->sheet_price);
            $this->assertEquals(20222.80, (float) $arQ2->coil_price_idr);
            $this->assertEquals(20689.48, (float) $arQ2->sheet_price_idr);
        } finally {
            MaterialSteel::$disableApArSync = false;
        }
    }

    public function test_filament_material_steel_form_category_dropdown_and_reactive_excel_calculations(): void
    {
        // Setup record kuartal sebelumnya Q4 2023 sebagai benchmark dasar
        MaterialSteel::create([
            'year' => 2023,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'steel_spec' => 'SPH270C-OD',
            'steel_category' => 'HRC',
            'double_thickness' => '1.6',
            'coil_price' => 1.2146,
            'sheet_price' => 1.2396,
            'exchange_rate' => 14917.0,
            'price_type' => 'AP',
        ]);

        // Uji form Create Material Steel AP dengan Livewire
        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\MaterialSteelApResource\Pages\CreateMaterialSteelAp::class)
            ->fillForm([
                'year' => 2024,
                'quarter' => 'Q2',
                'supplier_id' => $this->supplier->id,
                'steel_spec' => 'SPH270C-OD',
                'steel_category' => 'HRC',
                'double_thickness' => '1.6',
                'exchange_rate' => 15556,
                'hr_steel_usd' => 70.0,
                'coil_price' => 1.2846,
                'sheet_price' => 1.3096,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $savedRecord = MaterialSteel::where('year', 2024)->where('quarter', 'Q2')->where('price_type', 'AP')->first();
        $this->assertNotNull($savedRecord);
        $this->assertEquals('HRC', $savedRecord->steel_category);
        $this->assertEquals(70.0, (float) $savedRecord->hr_steel_usd);
        $this->assertEquals(0.0, (float) $savedRecord->cr_steel_usd);
        $this->assertEquals(1.2846, (float) $savedRecord->coil_price);
        $this->assertEquals(19983.24, (float) $savedRecord->coil_price_idr);
    }
}
