<?php

namespace Tests\Feature;

use App\Filament\Pages\ComparativeQuotationSheet;
use App\Models\Part;
use App\Models\User;
use App\Services\ComparativeQuotationService;
use Database\Seeders\ComparativeQuotationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComparativeQuotationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->seed(ComparativeQuotationSeeder::class);
    }

    public function test_comparative_quotation_data_loaded_correctly(): void
    {
        // 3 Parts dari Excel harus terdaftar
        $this->assertDatabaseHas('parts', ['part_number' => '73012-BZ010']);
        $this->assertDatabaseHas('parts', ['part_number' => '72388-X7V00-A']);
        $this->assertDatabaseHas('parts', ['part_number' => '90041-19960']);

        // BOM Sub-Assy harus menghubungkan ke Stamping Part
        $subAssy = Part::where('part_number', '73012-BZ010')->first();
        $this->assertNotNull($subAssy);
        $this->assertGreaterThanOrEqual(3, $subAssy->bomComponents()->count());
    }

    public function test_stamping_part_posmi_matches_excel_values_and_differences(): void
    {
        $matrix = ComparativeQuotationService::getComparativeMatrix('72388-X7V00-A');
        $periods = $matrix['periods'];

        $this->assertCount(12, $periods);

        // Q2 '22: Posmi part cost = 10583.5293631104
        $q2 = $periods[0];
        $this->assertEquals("Periode Q2 April-June '22", $q2->period_label);
        $this->assertEqualsWithDelta(10583.5294, (float) $q2->part_cost, 0.001);

        // Q3 '22: Posmi part cost = 10793.244760205758, Diff = +209.7155
        $q3 = $periods[1];
        $this->assertEqualsWithDelta(10793.2448, (float) $q3->part_cost, 0.001);
        $this->assertEqualsWithDelta(209.7155, (float) $q3->cost_difference, 0.001);

        // Q4 '22: Diff = -66.5210
        $q4 = $periods[2];
        $this->assertEqualsWithDelta(-66.5210, (float) $q4->cost_difference, 0.001);

        // Q1 '23: Diff = +137.6946
        $q1_23 = $periods[3];
        $this->assertEqualsWithDelta(137.6946, (float) $q1_23->cost_difference, 0.001);

        // Q2 '23: Diff = +786.1883
        $q2_23 = $periods[4];
        $this->assertEqualsWithDelta(786.1883, (float) $q2_23->cost_difference, 0.001);
    }

    public function test_sub_assembly_tcf_matches_excel_values(): void
    {
        $matrix = ComparativeQuotationService::getComparativeMatrix('73012-BZ010');
        $periods = $matrix['periods'];

        $this->assertCount(12, $periods);

        // Q2 '22 TCF Part Cost: 15429.9094
        $p1 = $periods[0];
        $this->assertEqualsWithDelta(15429.9094, (float) $p1->part_cost, 0.001);
        $this->assertEquals(4258.0000, round((float) $p1->process_total, 4));

        // Periode akhir 2026 (saat baut Meidoh masuk): 19838.7886
        $last = $periods->last();
        $this->assertEqualsWithDelta(19838.7886, (float) $last->part_cost, 0.001);
        $this->assertEqualsWithDelta(14865.2686, (float) $last->material_total, 0.001);
        $this->assertEqualsWithDelta(313.6481, (float) $last->cost_difference, 0.001);
    }

    public function test_fastener_nitto_alam_matches_excel_values(): void
    {
        $matrix = ComparativeQuotationService::getComparativeMatrix('90041-19960');
        $periods = $matrix['periods'];

        $this->assertCount(5, $periods);

        // Q1-2026: 630.0846
        $p1 = $periods[0];
        $this->assertEqualsWithDelta(630.0846, (float) $p1->part_cost, 0.001);
        $this->assertEquals(225.4500, round((float) $p1->process_total, 4));

        // Q2-2026: 677.1975
        $p2 = $periods[1];
        $this->assertEqualsWithDelta(677.1975, (float) $p2->part_cost, 0.001);

        // Next-2026: 692.8150
        $pLast = $periods->last();
        $this->assertEqualsWithDelta(692.8150, (float) $pLast->part_cost, 0.001);
    }

    public function test_comparative_quotation_sheet_page_renders(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ComparativeQuotationSheet::class)
            ->set('selectedPartNumber', '73012-BZ010')
            ->assertSuccessful()
            ->assertSee('BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH')
            ->assertSee('PT. TRI CENTRUM FORTUNA')
            ->assertSee('15.430');
    }

    public function test_export_to_csv_works(): void
    {
        $csv = ComparativeQuotationService::exportToCsv('73012-BZ010');

        $this->assertStringContainsString('73012-BZ010', $csv);
        $this->assertStringContainsString('BRACKET SUB-ASSY, BEALT INNER ANCHOR, LH', $csv);
        $this->assertStringContainsString('PT. TRI CENTRUM FORTUNA', $csv);
        $this->assertStringContainsString('MATERIAL BREAKDOWN', $csv);
        $this->assertStringContainsString('PROCESS BREAKDOWN', $csv);
    }
}
