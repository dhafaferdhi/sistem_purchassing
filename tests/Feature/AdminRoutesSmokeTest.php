<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_all_admin_navigation_pages_render_without_errors(): void
    {
        $this->actingAs($this->admin);

        $routes = [
            '/admin',
            '/admin/currencies',
            '/admin/currencies/create',
            '/admin/material-steel-aps',
            '/admin/material-steel-aps/create',
            '/admin/material-steel-ars',
            '/admin/material-steel-ars/create',
            '/admin/stamping-parts',
            '/admin/stamping-parts/create',
            '/admin/fasteners',
            '/admin/fasteners/create',
            '/admin/sub-assembly-parts',
            '/admin/sub-assembly-parts/create',
            '/admin/monthly-rollover',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertSuccessful();
        }
    }
}
