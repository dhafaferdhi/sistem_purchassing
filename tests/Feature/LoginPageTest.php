<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_displays_pud_portal_and_subheading(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('PUD PORTAL');
        $response->assertSee('Purchasing Division Monitoring System');
    }

    public function test_user_can_authenticate_via_custom_login_page(): void
    {
        $user = User::factory()->create([
            'email' => 'purchasing@fujiseat.co.id',
            'password' => bcrypt('password'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'purchasing@fujiseat.co.id',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(filament()->getUrl());

        $this->assertAuthenticatedAs($user);
    }
}
