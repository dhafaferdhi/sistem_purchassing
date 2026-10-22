<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_price_update_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get(route('purchasing.price-update'));

        $response->assertStatus(200);
        $response->assertSee('Penyesuaian Harga Material Baja');
    }

    public function test_validation_fails_when_parameters_are_missing(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('purchasing.price-update.submit'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['kurs_idr', 'hr_adj_mt_usd', 'cr_adj_mt_usd']);
    }

    public function test_validation_fails_when_kurs_idr_is_zero_or_negative(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('purchasing.price-update.submit'), [
            'kurs_idr' => 0,
            'hr_adj_mt_usd' => -20,
            'cr_adj_mt_usd' => -20,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['kurs_idr']);
    }

    public function test_successfully_calculates_and_updates_prices_for_hr_and_cr_materials(): void
    {
        // 1. Setup Material HR (SPHC) dan Material CR (SPCC)
        $hrMaterial = Material::create([
            'spec' => 'SPHC-P',
            'category' => 'Presspart',
            'main_category' => 'Steel',
            'basic_rate' => 1.8500,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $crMaterial = Material::create([
            'spec' => 'SPCC-SD',
            'category' => 'Presspart',
            'main_category' => 'Steel',
            'basic_rate' => 2.0000,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        // Input data:
        // kurs_idr = 17796
        // hr_adj_mt_usd = -20 -> hr_adj_kg_usd = -0.02
        // cr_adj_mt_usd = 30 -> cr_adj_kg_usd = 0.03
        $payload = [
            'kurs_idr' => 17796,
            'hr_adj_mt_usd' => -20,
            'cr_adj_mt_usd' => 30,
        ];

        $response = $this->actingAs($this->user)->postJson(route('purchasing.price-update.submit'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'parameters' => [
                'kurs_idr' => 17796,
                'hr_adj_mt_usd' => -20,
                'hr_adj_kg_usd' => -0.02,
                'cr_adj_mt_usd' => 30,
                'cr_adj_kg_usd' => 0.03,
            ],
            'total_updated' => 2,
            'hr_updated_count' => 1,
            'cr_updated_count' => 1,
        ]);

        // Ekspektasi HR: 1.8500 + (-0.02) = 1.8300; IDR = 1.8300 * 17796 = 32566.68
        $hrMaterial->refresh();
        $this->assertEquals(1.8300, (float) $hrMaterial->basic_rate);
        $this->assertEquals(32566.68, (float) $hrMaterial->price_idr);

        // Ekspektasi CR: 2.0000 + 0.03 = 2.0300; IDR = 2.0300 * 17796 = 36125.88
        $crMaterial->refresh();
        $this->assertEquals(2.0300, (float) $crMaterial->basic_rate);
        $this->assertEquals(36125.88, (float) $crMaterial->price_idr);
    }

    public function test_preview_calculates_without_persisting_to_database(): void
    {
        $hrMaterial = Material::create([
            'spec' => 'SPH270D',
            'category' => 'Presspart',
            'main_category' => 'Steel',
            'basic_rate' => 1.9000,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $payload = [
            'kurs_idr' => 17500,
            'hr_adj_mt_usd' => 10, // +0.01 $/kg
            'cr_adj_mt_usd' => 20,
        ];

        $response = $this->actingAs($this->user)->postJson(route('purchasing.price-update.preview'), $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'spec' => 'SPH270D',
            'harga_dasar_usd' => 1.9,
            'penyesuaian_kg_usd' => 0.01,
            'harga_baru_usd' => 1.91,
            'harga_baru_idr' => 33425.0,
        ]);

        // Pastikan database TIDAK berubah
        $hrMaterial->refresh();
        $this->assertEquals(1.9000, (float) $hrMaterial->basic_rate);
        $this->assertNull($hrMaterial->price_idr);
    }
}
