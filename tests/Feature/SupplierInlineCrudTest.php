<?php

namespace Tests\Feature;

use App\Filament\Resources\FastenerResource\Pages\CreateFastener;
use App\Filament\Resources\MaterialResource\Pages\CreateMaterial;
use App\Filament\Resources\MaterialSteelResource\Pages\CreateMaterialSteel;
use App\Filament\Resources\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Resources\StampingPartResource\Pages\CreateStampingPart;
use App\Filament\Resources\SubAssemblyPartResource\Pages\CreateSubAssemblyPart;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Models\Currency;
use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\PurchaseOrder;
use App\Models\StampingPart;
use App\Models\Supplier;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierInlineCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->currency = Currency::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate_to_idr' => 17000.0,
        ]);
    }

    public function test_add_supplier_modal_only_asks_supplier_name_and_isolates_material_steel_from_stamping_and_fastener(): void
    {
        $this->actingAs($this->admin);

        // Supplier di Material Steel
        $steelTest = Livewire::test(CreateMaterialSteel::class);
        $form = $steelTest->instance()->getForm('form');
        /** @var Select $steelSelect */
        $steelSelect = $form->getFlatFields(withHidden: true)['supplier_id'];
        $modalFields = $steelSelect->getCreateOptionActionForm(\Filament\Forms\Form::make($steelTest->instance()));
        $modalFieldNames = array_map(static fn ($field) => $field->getName(), $modalFields);

        // Pastikan modal tambah supplier HANYA memiliki field 'name' saja
        $this->assertSame(['name'], $modalFieldNames);

        $steelTest
            ->mountFormComponentAction('supplier_id', 'createOption')
            ->assertDontSee('Kode Vendor')
            ->assertDontSee('Contact Person')
            ->setFormComponentActionData(['name' => 'PT Baja Khusus Material'])
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors();

        $steelSup = Supplier::where('name', 'PT Baja Khusus Material')->first();
        $this->assertNotNull($steelSup);
        $this->assertSame('Steel', $steelSup->category);

        // Supplier Stamping Part & Fastener masing-masing berbeda dan TIDAK tercampur
        $stampingSup = Supplier::create([
            'code' => 'VND-POSMI',
            'name' => 'PT. POSMI STEEL INDONESIA',
            'category' => 'Stamping & Steel',
            'is_active' => true,
        ]);

        $fastenerSup = Supplier::create([
            'code' => 'VND-NITTO',
            'name' => 'PT. NITTO ALAM INDONESIA',
            'category' => 'Fastener',
            'is_active' => true,
        ]);

        // Cek opsi dropdown Material Steel hanya memuat supplier Steel
        $steelOptions = $steelTest->instance()->getForm('form')->getFlatFields(withHidden: true)['supplier_id']->getOptions();
        $this->assertArrayHasKey($steelSup->id, $steelOptions);
        $this->assertArrayNotHasKey($stampingSup->id, $steelOptions);
        $this->assertArrayNotHasKey($fastenerSup->id, $steelOptions);

        // Cek opsi dropdown Stamping Part TIDAK memuat supplier Material Steel maupun Fastener
        $stampingOptions = Livewire::test(CreateStampingPart::class)
            ->instance()->getForm('form')->getFlatFields(withHidden: true)['supplier_id']->getOptions();
        $this->assertArrayHasKey($stampingSup->id, $stampingOptions);
        $this->assertArrayNotHasKey($steelSup->id, $stampingOptions);
        $this->assertArrayNotHasKey($fastenerSup->id, $stampingOptions);

        // Cek opsi dropdown Fastener TIDAK memuat supplier Material Steel maupun Stamping Part
        $fastenerOptions = Livewire::test(CreateFastener::class)
            ->instance()->getForm('form')->getFlatFields(withHidden: true)['supplier_id']->getOptions();
        $this->assertArrayHasKey($fastenerSup->id, $fastenerOptions);
        $this->assertArrayNotHasKey($steelSup->id, $fastenerOptions);
        $this->assertArrayNotHasKey($stampingSup->id, $fastenerOptions);
    }

    public function test_stamping_and_fastener_never_auto_copy_supplier_from_material_steel(): void
    {
        $this->actingAs($this->admin);

        $steelSup = Supplier::create([
            'code' => 'VND-HSSI',
            'name' => 'PT HSSI',
            'category' => 'Steel',
            'is_active' => true,
        ]);

        MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $steelSup->id,
            'vendor_code' => $steelSup->code,
            'supplier_name' => $steelSup->name,
            'steel_spec' => 'SPH440-OD',
            'double_thickness' => '2.6',
            'currency_id' => $this->currency->id,
            'exchange_rate' => 17000.0,
            'coil_price' => 1.5000,
            'sheet_price' => 1.6000,
        ]);

        // Memilih Spec di Stamping Part menarik harga Material Steel tetapi TIDAK mengisi supplier_id dari Material Steel
        Livewire::test(CreateStampingPart::class)
            ->set('data.year', 2026)
            ->set('data.quarter', 'Q4')
            ->set('data.steel_spec', 'SPH440-OD')
            ->assertSet('data.sheet_price', 1.6)
            ->assertSet('data.supplier_id', null)
            ->assertSet('data.supplier_name', null);

        // Memilih Spec di Fastener menarik harga Material Steel tetapi TIDAK mengisi supplier_id dari Material Steel
        Livewire::test(CreateFastener::class)
            ->set('data.year', 2026)
            ->set('data.quarter', 'Q4')
            ->set('data.steel_spec', 'SPH440-OD')
            ->assertSet('data.coil_price', 1.5)
            ->assertSet('data.supplier_id', null)
            ->assertSet('data.supplier_name', null);

        // Fallback findPreviousQuarterData() dari MaterialSteel tidak memindahkan supplier MaterialSteel ke Stamping/Fastener
        $prevStamping = StampingPart::findPreviousQuarterData('Q1', 2027, null, 'SPH440-OD');
        $this->assertNotNull($prevStamping);
        $this->assertNull($prevStamping['supplier_id']);

        $prevFastener = Fastener::findPreviousQuarterData('Q1', 2027, null, 'SPH440-OD');
        $this->assertNull($prevFastener['supplier_id'] ?? null);
    }

    public function test_deleting_supplier_in_material_steel_never_deletes_or_deactivates_stamping_and_fastener_suppliers(): void
    {
        $this->actingAs($this->admin);

        // Simulasikan skenario data terlanjur memakai supplier legacy yang sama di Material Steel, Stamping Part, & Fastener
        $sharedSupplier = Supplier::create([
            'code' => 'VND-SHARED',
            'name' => 'PT Shared Legacy',
            'category' => 'Steel',
            'is_active' => true,
        ]);

        $steel = MaterialSteel::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $sharedSupplier->id,
            'vendor_code' => $sharedSupplier->code,
            'supplier_name' => $sharedSupplier->name,
            'steel_spec' => 'SPCC-SD',
            'currency_id' => $this->currency->id,
            'exchange_rate' => 17000.0,
            'coil_price' => 1.5,
            'sheet_price' => 1.6,
        ]);

        $stamping = StampingPart::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $sharedSupplier->id,
            'vendor_code' => $sharedSupplier->code,
            'supplier_name' => $sharedSupplier->name,
            'part_number' => '72388-ISO-01',
            'steel_spec' => 'SPCC-SD',
        ]);

        $fastener = Fastener::create([
            'year' => 2026,
            'quarter' => 'Q4',
            'supplier_id' => $sharedSupplier->id,
            'vendor_code' => $sharedSupplier->code,
            'supplier_name' => $sharedSupplier->name,
            'part_number' => '90041-ISO-01',
            'steel_spec' => 'SWCH-45K',
        ]);

        // Hapus supplier pada Material Steel
        Livewire::test(CreateMaterialSteel::class)
            ->set('data.supplier_id', $sharedSupplier->id)
            ->callFormComponentAction('supplier_id', 'deleteOption')
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.supplier_id', null)
            ->assertSet('data.vendor_code', null)
            ->assertSet('data.supplier_name', null);

        // Material Steel historis utuh, namun Stamping Part & Fastener dipisahkan ke supplier masing-masing dan tetap aktif
        $this->assertDatabaseHas('material_steels', ['id' => $steel->id, 'supplier_id' => $sharedSupplier->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $sharedSupplier->id, 'is_active' => false]);

        $stampingFresh = $stamping->fresh();
        $fastenerFresh = $fastener->fresh();
        $this->assertNotNull($stampingFresh->supplier_id);
        $this->assertNotEquals($sharedSupplier->id, $stampingFresh->supplier_id);
        $this->assertTrue($stampingFresh->supplier->is_active);
        $this->assertSame('Stamping & Steel', $stampingFresh->supplier->category);

        $this->assertNotNull($fastenerFresh->supplier_id);
        $this->assertNotEquals($sharedSupplier->id, $fastenerFresh->supplier_id);
        $this->assertTrue($fastenerFresh->supplier->is_active);
        $this->assertSame('Fastener', $fastenerFresh->supplier->category);
    }

    public function test_can_edit_and_delete_unused_supplier_inline_using_name_only(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(CreateStampingPart::class)
            ->mountFormComponentAction('supplier_id', 'createOption')
            ->setFormComponentActionData(['name' => 'PT Sumber Stamping Uji'])
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors();

        $supplier = Supplier::where('name', 'PT Sumber Stamping Uji')->firstOrFail();
        $this->assertSame('Stamping & Steel', $supplier->category);

        // Ubah & Simpan hanya nama supplier langsung lewat tombol Edit
        $component
            ->mountFormComponentAction('supplier_id', 'editOption')
            ->setFormComponentActionData(['name' => 'PT Sumber Stamping Revisi'])
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.vendor_code', $supplier->code)
            ->assertSet('data.supplier_name', 'PT Sumber Stamping Revisi');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'PT Sumber Stamping Revisi',
        ]);

        // Hapus supplier yang belum dipakai transaksi -> terhapus permanen dan form dikosongkan
        $component
            ->callFormComponentAction('supplier_id', 'deleteOption')
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.supplier_id', null)
            ->assertSet('data.vendor_code', null)
            ->assertSet('data.supplier_name', null);

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_inline_supplier_save_and_delete_works_across_fastener_sub_assy_po_and_material(): void
    {
        $this->actingAs($this->admin);

        foreach ([
            [CreateFastener::class, 'PT Baut Inline'],
            [CreateSubAssemblyPart::class, 'PT SubAssy Inline'],
            [CreatePurchaseOrder::class, 'PT PO Inline'],
            [CreateMaterial::class, 'PT Material Inline'],
        ] as [$pageClass, $supplierName]) {
            $component = Livewire::test($pageClass)
                ->mountFormComponentAction('supplier_id', 'createOption')
                ->setFormComponentActionData(['name' => $supplierName])
                ->callMountedFormComponentAction()
                ->assertHasNoFormComponentActionErrors();

            $created = Supplier::where('name', $supplierName)->first();
            $this->assertNotNull($created, "Failed creating inline supplier on {$pageClass}");
            $component->assertSet('data.supplier_id', $created->id);

            $component
                ->callFormComponentAction('supplier_id', 'deleteOption')
                ->assertHasNoFormComponentActionErrors()
                ->assertSet('data.supplier_id', null);

            $this->assertDatabaseMissing('suppliers', ['id' => $created->id]);
        }
    }

    public function test_clearing_supplier_resets_hidden_fields_and_table_delete_guards_po_reference(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::create([
            'code' => 'VND-CLR-01',
            'name' => 'PT Clear Test',
            'category' => 'Stamping & Steel',
            'is_active' => true,
        ]);

        Livewire::test(CreateStampingPart::class)
            ->set('data.supplier_id', $supplier->id)
            ->assertSet('data.vendor_code', 'VND-CLR-01')
            ->assertSet('data.supplier_name', 'PT Clear Test')
            ->set('data.supplier_id', null)
            ->assertSet('data.vendor_code', null)
            ->assertSet('data.supplier_name', null);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now(),
            'currency_id' => $this->currency->id,
        ]);

        Livewire::test(ListSuppliers::class)
            ->callTableAction(DeleteAction::class, $supplier);

        // Supplier yang masih punya PO tetap tersimpan aman
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);
    }

    public function test_excel_suppliers_for_stamping_and_fasteners_are_active_and_properly_scoped(): void
    {
        $this->actingAs($this->admin);

        // Jalankan seeder Stamping Part & Fasteners yang memuat daftar supplier resmi Excel
        $this->seed(\Database\Seeders\StampingPartSeeder::class);
        $this->seed(\Database\Seeders\FastenerSeeder::class);

        $posmi = Supplier::where('code', 'VND-POSMI')->first();
        $tcf = Supplier::where('code', 'VND-TCF')->first();
        $nitto = Supplier::where('code', 'VND-NITTO')->first();
        $meidoh = Supplier::where('code', 'VND-MEIDOH')->first();
        $hssi = Supplier::where('code', 'VND-HSSI')->first();
        $usci = Supplier::where('code', 'VND-USCI')->first();

        $this->assertNotNull($posmi);
        $this->assertNotNull($tcf);
        $this->assertNotNull($nitto);
        $this->assertNotNull($meidoh);
        $this->assertNotNull($hssi);
        $this->assertNotNull($usci);
        $this->assertTrue($posmi->is_active);
        $this->assertTrue($tcf->is_active);
        $this->assertTrue($nitto->is_active);
        $this->assertTrue($meidoh->is_active);

        // Stamping Part memuat PT. POSMI STEEL INDONESIA & PT. TRI CENTRUM FORTUNA, bukan Steel/Fastener
        $stampingOptions = Livewire::test(CreateStampingPart::class)
            ->set('data.supplier_id', null)
            ->instance()->getForm('form')->getFlatFields(withHidden: true)['supplier_id']->getOptions();
        $this->assertArrayHasKey($posmi->id, $stampingOptions);
        $this->assertArrayHasKey($tcf->id, $stampingOptions);
        $this->assertArrayNotHasKey($hssi->id, $stampingOptions);
        $this->assertArrayNotHasKey($usci->id, $stampingOptions);
        $this->assertArrayNotHasKey($nitto->id, $stampingOptions);
        $this->assertArrayNotHasKey($meidoh->id, $stampingOptions);

        // Fastener memuat PT. NITTO ALAM INDONESIA & PT. MEIDOH INDONESIA, bukan Steel/Stamping
        $fastenerOptions = Livewire::test(CreateFastener::class)
            ->set('data.supplier_id', null)
            ->instance()->getForm('form')->getFlatFields(withHidden: true)['supplier_id']->getOptions();
        $this->assertArrayHasKey($nitto->id, $fastenerOptions);
        $this->assertArrayHasKey($meidoh->id, $fastenerOptions);
        $this->assertArrayNotHasKey($hssi->id, $fastenerOptions);
        $this->assertArrayNotHasKey($usci->id, $fastenerOptions);
        $this->assertArrayNotHasKey($posmi->id, $fastenerOptions);
        $this->assertArrayNotHasKey($tcf->id, $fastenerOptions);
    }
}
