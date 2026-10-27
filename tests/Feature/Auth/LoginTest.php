<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_logs_in_with_email(): void
    {
        $user = User::factory()->create(['email' => 'sari@example.com']);

        $this->post(route('login'), ['login' => 'Sari@Example.com', 'password' => 'password'])
            ->assertRedirect('/owner');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($this->withoutTenant(fn () => User::find($user->id))->last_login_at);
    }

    public function test_owner_logs_in_with_phone_in_any_local_format(): void
    {
        $user = User::factory()->withPhone('+6281234567890')->create();

        $this->post(route('login'), ['login' => '0812 3456 7890', 'password' => 'password'])
            ->assertRedirect('/owner');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_and_inactive_users_are_rejected(): void
    {
        $user = User::factory()->create(['email' => 'sari@example.com']);

        $this->post(route('login'), ['login' => 'sari@example.com', 'password' => 'salah'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $user->forceFill(['is_active' => false])->save();

        $this->post(route('login'), ['login' => 'sari@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'sari@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['login' => 'sari@example.com', 'password' => 'salah']);
        }

        $this->post(route('login'), ['login' => 'sari@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('login'));
    }

    public function test_super_admin_goes_to_admin_panel(): void
    {
        User::factory()->superAdmin()->create(['email' => 'admin@example.com']);

        $this->post(route('login'), ['login' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect('/admin');
    }

    public function test_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/owner')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
    }
}
