<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\Material;
use App\Models\Part;
use App\Models\PartBom;
use App\Models\PartQuotation;
use App\Models\Supplier;
use App\Services\CurrencyRecalculationService;
use App\Services\MaterialMassUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterprisePurchasingTest extends TestCase
{
    use RefreshDatabase;

    public function test_bom_tree_view_hierarchy(): void
    {
        // Level 0
        $seat = Part::create([
            'part_number' => 'SEAT-TOP',
            'part_name' => 'Top Seat Assembly',
            'type' => 'Assembly',
            'main_category' => 'Assembly',
        ]);

        // Level 1
        $frame = Part::create([
            'part_number' => 'FR-ASM',
            'part_name' => 'Frame Assembly',
            'type' => 'Assembly',
            'main_category' => 'Steel',
        ]);

        // Level 2
        $bracket = Part::create([
            'part_number' => 'BRKT-01',
            'part_name' => 'Steel Bracket',
            'type' => 'Component',
            'main_category' => 'Steel',
        ]);

        PartBom::create(['parent_part_id' => $seat->id, 'component_part_id' => $frame->id, 'quantity' => 1, 'level' => 1]);
        PartBom::create(['parent_part_id' => $frame->id, 'component_part_id' => $bracket->id, 'quantity' => 2, 'level' => 2]);

        $tree = $seat->getBomTreeArray();

        $this->assertEquals('SEAT-TOP', $tree['part_number']);
        $this->assertCount(1, $tree['children']);
        $this->assertEquals('FR-ASM', $tree['children'][0]['part_number']);
        $this->assertCount(1, $tree['children'][0]['children']);
        $this->assertEquals('BRKT-01', $tree['children'][0]['children'][0]['part_number']);
        $this->assertEquals(2, $tree['children'][0]['children'][0]['quantity']);
    }

    public function test_currency_quarterly_recalculation(): void
    {
        $usd = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'exchange_rate_to_idr' => 15000.0,
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-TEST',
            'name' => 'Test Supplier',
        ]);

        $part = Part::create([
            'part_number' => 'PART-USD',
            'part_name' => 'USD Part',
            'type' => 'Component',
        ]);

        $quote = PartQuotation::create([
            'part_id' => $part->id,
            'supplier_id' => $supplier->id,
            'currency_id' => $usd->id,
            'exchange_rate_to_idr' => 15000.0,
            'material_cost_original' => 2.0, // 2 * 15000 = 30000
            'machine_cost_original' => 1.0,  // 1 * 15000 = 15000
            'manpower_cost_idr' => 5000.0,
        ]);

        $this->assertEquals(30000.0, $quote->material_cost_idr);
        $this->assertEquals(50000.0, $quote->total_cost_idr); // 30000 + 15000 + 5000

        // Kuartalan: Kurs naik dari 15.000 menjadi 16.000
        $usd->update(['exchange_rate_to_idr' => 16000.0]);
        $recalculated = CurrencyRecalculationService::recalculateForCurrency($usd);

        $this->assertEquals(1, $recalculated);

        $quote->refresh();
        $this->assertEquals(32000.0, $quote->material_cost_idr); // 2 * 16000 = 32000
        $this->assertEquals(16000.0, $quote->machine_cost_idr);  // 1 * 16000 = 16000
        $this->assertEquals(53000.0, $quote->total_cost_idr);    // 32000 + 16000 + 5000
    }

    public function test_material_where_used_and_mass_update(): void
    {
        $material = Material::create([
            'spec' => 'PP-RESIN-TEST',
            'category' => 'Presspart',
            'main_category' => 'Non-Steel',
            'sub_category' => 'Plastik',
            'basic_rate' => 2.0,
            'unit' => 'kg',
        ]);

        $part = Part::create([
            'part_number' => 'PART-PLASTIC',
            'part_name' => 'Plastic Part',
            'type' => 'Component',
            'material_id' => $material->id,
        ]);

        $idr = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'exchange_rate_to_idr' => 1.0]);
        $sup = Supplier::create(['code' => 'SUP-POLY', 'name' => 'Polymer Supplier']);

        $quote = PartQuotation::create([
            'part_id' => $part->id,
            'supplier_id' => $sup->id,
            'currency_id' => $idr->id,
            'exchange_rate_to_idr' => 1.0,
            'material_cost_original' => 20000.0,
            'machine_cost_original' => 10000.0,
            'status' => 'active',
        ]);

        $this->assertEquals(30000.0, $quote->total_cost_idr);

        // Kenaikan harga material +10%
        $result = MaterialMassUpdateService::massUpdatePrice($material, 10.0);

        $this->assertEquals(2.2, $result['new_basic_rate']);
        $this->assertEquals(1, $result['affected_parts_count']);

        $quote->refresh();
        $this->assertEquals(22000.0, $quote->material_cost_original); // 20000 + 10%
        $this->assertEquals(32000.0, $quote->total_cost_idr);         // 22000 + 10000
    }
}

