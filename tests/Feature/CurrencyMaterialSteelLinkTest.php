<?php

namespace Tests\Feature;

use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\CurrencyResource\Pages\CreateCurrency;
use App\Filament\Resources\CurrencyResource\Pages\EditCurrency;
use App\Filament\Resources\CurrencyResource\Pages\ListCurrencies;
use App\Filament\Resources\CurrencyResource\RelationManagers\MaterialSteelsRelationManager;
use App\Filament\Resources\CurrencyResource\RelationManagers\StampingPartsRelationManager;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CurrencyRecalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurrencyMaterialSteelLinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->supplier = Supplier::create([
            'code' => 'VND-POSCO',
            'name' => 'PT Posco Steel Indonesia',
            'category' => 'Steel',
        ]);
    }

    public function test_currency_has_year_attribute_and_defaults_to_2026(): void
    {
        $currency = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
        ]);

        $this->assertEquals(2026, $currency->year);
    }

    public function test_currency_supports_multi_year_rates_for_same_code(): void
    {
        $usd2025 = Currency::create([
            'year' => 2025,
            'code' => 'USD',
            'name' => 'US Dollar (2025)',
            'symbol' => '$',
            'exchange_rate_to_idr' => 15500.0,
        ]);

        $usd2026 = Currency::create([
            'year' => 2026,
            'code' => 'USD',
            'name' => 'US Dollar (2026)',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16200.0,
        ]);

        $this->assertDatabaseHas('currencies', [
            'id' => $usd2025->id,
            'year' => 2025,
            'exchange_rate_to_idr' => 15500.0,
        ]);

        $this->assertDatabaseHas('currencies', [
            'id' => $usd2026->id,
            'year' => 2026,
            'exchange_rate_to_idr' => 16200.0,
        ]);

        // Helper rate lookup for year
        $this->assertEquals(15500.0, Currency::getRateForYear('USD', 2025));
        $this->assertEquals(16200.0, Currency::getRateForYear('USD', 2026));
    }

    public function test_currency_has_many_material_steels_relation(): void
    {
        $currency = Currency::create([
            'year' => 2026,
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
        ]);

        $steel1 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC 1.2mm',
            'currency_id' => $currency->id,
            'currency_code' => 'USD',
            'steel_price' => 1.8000,
            'exchange_rate' => 16000.0,
            'steel_price_idr' => 28800.0,
        ]);

        $steel2 = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPHC 2.0mm',
            'currency_id' => $currency->id,
            'currency_code' => 'USD',
            'steel_price' => 2.0000,
            'exchange_rate' => 16000.0,
            'steel_price_idr' => 32000.0,
        ]);

        $this->assertCount(2, $currency->materialSteels);
        $this->assertTrue($currency->materialSteels->contains($steel1));
        $this->assertTrue($currency->materialSteels->contains($steel2));
    }

    public function test_currency_recalculation_service_syncs_rate_to_material_steels(): void
    {
        $currency = Currency::create([
            'year' => 2026,
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
        ]);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC 1.2mm',
            'currency_id' => $currency->id,
            'currency_code' => 'USD',
            'steel_price' => 2.0000,
            'exchange_rate' => 16000.0,
            'steel_price_idr' => 32000.0,
        ]);

        // Perbarui kurs dari 16.000 ke 16.500
        $currency->update(['exchange_rate_to_idr' => 16500.0]);

        $updatedCount = CurrencyRecalculationService::recalculateMaterialSteelsForCurrency($currency);

        $this->assertEquals(1, $updatedCount);

        $steel->refresh();
        $this->assertEquals(16500.0, (float) $steel->exchange_rate);
        $this->assertEquals(33000.0, (float) $steel->steel_price_idr);
    }

    public function test_currency_filament_table_lists_year_and_filter(): void
    {
        Currency::create([
            'year' => 2025,
            'code' => 'USD-25',
            'name' => 'US Dollar 2025',
            'exchange_rate_to_idr' => 15500.0,
        ]);

        Currency::create([
            'year' => 2026,
            'code' => 'USD-26',
            'name' => 'US Dollar 2026',
            'exchange_rate_to_idr' => 16200.0,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListCurrencies::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Currency::all());
    }

    public function test_currency_edit_page_renders_material_steels_relation(): void
    {
        $currency = Currency::create([
            'year' => 2026,
            'code' => 'USD',
            'name' => 'US Dollar',
            'exchange_rate_to_idr' => 16000.0,
        ]);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-Special-Spec',
            'currency_id' => $currency->id,
            'currency_code' => 'USD',
            'steel_price' => 2.5000,
            'exchange_rate' => 16000.0,
            'steel_price_idr' => 40000.0,
        ]);

        $this->assertContains(
            MaterialSteelsRelationManager::class,
            CurrencyResource::getRelations()
        );

        Livewire::actingAs($this->admin)
            ->test(MaterialSteelsRelationManager::class, [
                'ownerRecord' => $currency,
                'pageClass' => EditCurrency::class,
            ])
            ->assertSuccessful()
            ->assertSee('SPCC-Special-Spec');
    }

    public function test_currency_update_automatically_recalculates_both_material_steel_and_stamping_part(): void
    {
        $currency = Currency::create([
            'year' => 2026,
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 15000.0,
        ]);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-AUTO-RECALC',
            'currency_id' => $currency->id,
            'currency_code' => 'USD',
            'steel_price' => 1.8000,
            'coil_price' => 1.8000,
            'sheet_price' => 1.9000,
            'gap_steel_usd' => 50.0,
            'exchange_rate' => 15000.0,
            'steel_price_idr' => 27000.0,
            'coil_price_idr' => 27000.0,
            'sheet_price_idr' => 28500.0,
            'gap_steel_idr' => 750000.0,
        ]);

        $stampingPart = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-AUTO-RECALC',
            'currency_id' => $currency->id,
            'currency_code' => 'USD',
            'double_thickness' => '1.2',
            'coil_price' => 1.8000,
            'sheet_price' => 1.9000,
            'gap_steel_usd' => 50.0,
            'exchange_rate' => 15000.0,
            'coil_price_idr' => 27000.0,
            'sheet_price_idr' => 28500.0,
        ]);

        // Ubah angka kurs dolar di model Currency dari 15.000 ke 16.000
        $currency->update([
            'exchange_rate_to_idr' => 16000.0,
        ]);

        // Cek data Material Steel otomatis ter-update nilainya
        $steel->refresh();
        $this->assertEquals(16000.0, (float) $steel->exchange_rate);
        $this->assertEquals(28800.0, (float) $steel->coil_price_idr);
        $this->assertEquals(30400.0, (float) $steel->sheet_price_idr);
        $this->assertEquals(800000.0, (float) $steel->gap_steel_idr);

        // Cek data Stamping Part otomatis ter-update nilainya
        $stampingPart->refresh();
        $this->assertEquals(16000.0, (float) $stampingPart->exchange_rate);
        $this->assertEquals(28800.0, (float) $stampingPart->coil_price_idr);
        $this->assertEquals(30400.0, (float) $stampingPart->sheet_price_idr);
    }

    public function test_currency_filament_table_has_quick_update_rate_action(): void
    {
        $currency = Currency::create([
            'year' => 2026,
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 15000.0,
        ]);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-QUICK-RATE',
            'currency_id' => $currency->id,
            'currency_code' => 'USD',
            'coil_price' => 2.0000,
            'sheet_price' => 2.1000,
            'exchange_rate' => 15000.0,
        ]);

        $stampingPart = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q2',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-QUICK-RATE',
            'currency_id' => $currency->id,
            'currency_code' => 'USD',
            'coil_price' => 2.0000,
            'sheet_price' => 2.1000,
            'exchange_rate' => 15000.0,
        ]);

        // Simulasikan ubah kurs melalui action tabel di menu Currency
        Livewire::actingAs($this->admin)
            ->test(ListCurrencies::class)
            ->callTableAction('quick_update_rate', $currency, data: [
                'exchange_rate_to_idr' => 17000.0,
            ])
            ->assertHasNoTableActionErrors();

        $currency->refresh();
        $this->assertEquals(17000.0, (float) $currency->exchange_rate_to_idr);

        $steel->refresh();
        $this->assertEquals(17000.0, (float) $steel->exchange_rate);
        $this->assertEquals(34000.0, (float) $steel->coil_price_idr);
        $this->assertEquals(35700.0, (float) $steel->sheet_price_idr);

        $stampingPart->refresh();
        $this->assertEquals(17000.0, (float) $stampingPart->exchange_rate);
        $this->assertEquals(34000.0, (float) $stampingPart->coil_price_idr);
        $this->assertEquals(35700.0, (float) $stampingPart->sheet_price_idr);
    }

    public function test_stamping_parts_relation_manager_renders_on_currency_resource(): void
    {
        $currency = Currency::create([
            'year' => 2026,
            'code' => 'USD',
            'name' => 'US Dollar',
            'exchange_rate_to_idr' => 16000.0,
        ]);

        StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'STAMPING-RELATION-TEST',
            'currency_id' => $currency->id,
            'currency_code' => 'USD',
            'coil_price' => 1.5000,
            'sheet_price' => 1.6000,
            'exchange_rate' => 16000.0,
        ]);

        $this->assertContains(
            StampingPartsRelationManager::class,
            CurrencyResource::getRelations()
        );

        Livewire::actingAs($this->admin)
            ->test(StampingPartsRelationManager::class, [
                'ownerRecord' => $currency,
                'pageClass' => EditCurrency::class,
            ])
            ->assertSuccessful()
            ->assertSee('STAMPING-RELATION-TEST');
    }

    public function test_currency_has_year_and_month_attributes(): void
    {
        $currency = Currency::create([
            'year' => 2026,
            'month' => 11,
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16200.0,
        ]);

        $this->assertEquals(2026, $currency->year);
        $this->assertEquals(11, $currency->month);
        $this->assertEquals('November', $currency->month_name);

        // Pastikan format tampilan tidak mengandung ,00
        $this->assertStringNotContainsString(',00', $currency->display_with_year);
        $this->assertStringContainsString('Rp 16.200', $currency->display_with_year);
    }

    public function test_updating_jpy_yen_currency_automatically_recalculates_material_steel_and_stamping_part(): void
    {
        $jpy = Currency::create([
            'year' => 2026,
            'month' => 10,
            'code' => 'JPY',
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'exchange_rate_to_idr' => 105.0,
        ]);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-JPY-TEST',
            'currency_id' => $jpy->id,
            'currency_code' => 'JPY',
            'coil_price' => 200.0,
            'sheet_price' => 220.0,
            'gap_steel_usd' => 20.0,
            'exchange_rate' => 105.0,
        ]);

        $stampingPart = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-JPY-TEST',
            'currency_id' => $jpy->id,
            'currency_code' => 'JPY',
            'coil_price' => 120.0,
            'sheet_price' => 130.0,
            'exchange_rate' => 105.0,
        ]);

        // Ubah kurs Yen dari 105 ke 110
        $jpy->update([
            'exchange_rate_to_idr' => 110.0,
        ]);

        $steel->refresh();
        $this->assertEquals(110.0, (float) $steel->exchange_rate);
        $this->assertEquals(22000.0, (float) $steel->coil_price_idr); // 200 * 110
        $this->assertEquals(24200.0, (float) $steel->sheet_price_idr); // 220 * 110

        $stampingPart->refresh();
        $this->assertEquals(110.0, (float) $stampingPart->exchange_rate);
        $this->assertEquals(13200.0, (float) $stampingPart->coil_price_idr); // 120 * 110
        $this->assertEquals(14300.0, (float) $stampingPart->sheet_price_idr); // 130 * 110
    }

    public function test_updating_currency_via_quick_update_rate_automatically_recalculates_material_steel_stamping_part_and_fastener(): void
    {
        $usd = Currency::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'month' => 10,
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
        ]);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-SD 1.2mm',
            'currency_id' => $usd->id,
            'currency_code' => 'USD',
            'coil_price' => 1.95,
            'sheet_price' => 2.05,
            'gap_steel_usd' => 100.0,
            'exchange_rate' => 16000.0,
        ]);

        $stampingPart = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-SD 1.2mm',
            'currency_id' => $usd->id,
            'currency_code' => 'USD',
            'coil_price' => 1.95,
            'sheet_price' => 2.05,
            'exchange_rate' => 16000.0,
        ]);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC-SD 1.2mm',
            'currency_id' => $usd->id,
            'currency_code' => 'USD',
            'coil_price' => 1.95,
            'sheet_price' => 2.05,
            'exchange_rate' => 16000.0,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListCurrencies::class)
            ->callTableAction('quick_update_rate', $usd, [
                'exchange_rate_to_idr' => 16500.0,
            ])
            ->assertHasNoTableActionErrors();

        $usd->refresh();
        $this->assertEquals(16500.0, (float) $usd->exchange_rate_to_idr);

        $steel->refresh();
        $this->assertEquals(16500.0, (float) $steel->exchange_rate);
        $this->assertEquals(32175.0, (float) $steel->coil_price_idr); // 1.95 * 16500
        $this->assertEquals(33825.0, (float) $steel->sheet_price_idr); // 2.05 * 16500

        $stampingPart->refresh();
        $this->assertEquals(16500.0, (float) $stampingPart->exchange_rate);
        $this->assertEquals(32175.0, (float) $stampingPart->coil_price_idr);
        $this->assertEquals(33825.0, (float) $stampingPart->sheet_price_idr);

        $fastener->refresh();
        $this->assertEquals(16500.0, (float) $fastener->exchange_rate);
        $this->assertEquals(32175.0, (float) $fastener->coil_price_idr);
        $this->assertEquals(33825.0, (float) $fastener->sheet_price_idr);
    }

    public function test_can_create_currency_with_only_year_quarter_usd_and_jpy(): void
    {
        $initialUsd = Currency::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 16000.0,
        ]);

        $initialJpy = Currency::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'code' => 'JPY',
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'exchange_rate_to_idr' => 105.0,
        ]);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'SPCC',
            'currency_id' => $initialUsd->id,
            'currency_code' => 'USD',
            'steel_price' => 2.0,
            'coil_price' => 1.8,
            'sheet_price' => 1.9,
        ]);

        $stamping = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'part_name' => 'BRACKET TEST',
            'steel_spec' => 'SPCC',
            'currency_id' => $initialUsd->id,
            'currency_code' => 'USD',
            'coil_price' => 1.5,
            'sheet_price' => 1.6,
        ]);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q1',
            'supplier_id' => $this->supplier->id,
            'vendor_code' => $this->supplier->code,
            'supplier_name' => $this->supplier->name,
            'steel_spec' => 'BOLT M6',
            'currency_id' => $initialUsd->id,
            'currency_code' => 'USD',
            'coil_price' => 0.5,
            'sheet_price' => 0.6,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CreateCurrency::class)
            ->fillForm([
                'year' => 2026,
                'quarter' => 'Q1',
                'kurs_usd' => 16500,
                'kurs_jpy' => 110,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('currencies', [
            'code' => 'USD',
            'year' => 2026,
            'quarter' => 'Q1',
            'exchange_rate_to_idr' => 16500,
        ]);

        $this->assertDatabaseHas('currencies', [
            'code' => 'JPY',
            'year' => 2026,
            'quarter' => 'Q1',
            'exchange_rate_to_idr' => 110,
        ]);

        $steel->refresh();
        $this->assertEquals(16500, (float) $steel->exchange_rate);
        $this->assertEquals(33000, (float) $steel->steel_price_idr); // 2.0 * 16500

        $stamping->refresh();
        $this->assertEquals(16500, (float) $stamping->exchange_rate);
        $this->assertEquals(24750, (float) $stamping->coil_price_idr); // 1.5 * 16500

        $fastener->refresh();
        $this->assertEquals(16500, (float) $fastener->exchange_rate);
        $this->assertEquals(8250, (float) $fastener->coil_price_idr); // 0.5 * 16500
    }
}
