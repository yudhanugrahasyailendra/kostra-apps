<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_respond(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk');
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
    }

    public function test_root_sends_guests_to_login_and_users_to_their_panel(): void
    {
        $this->get('/')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/owner');
        $this->actingAs(User::factory()->superAdmin()->create())->get('/')->assertRedirect('/admin');
    }

    public function test_login_page_shows_the_method_tabs_and_the_product_panel(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('Kata Sandi')
            ->assertSee('WhatsApp OTP')
            ->assertSee('Segera')
            ->assertSee('Email atau nomor WhatsApp')
            ->assertSee('Lupa kata sandi?')
            ->assertSee('Daftar gratis')
            ->assertSee('Kelola kos tanpa rekap manual.');
    }
}
