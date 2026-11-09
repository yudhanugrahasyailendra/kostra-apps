<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->forTenant(Tenant::factory()->create())->owner()->create(['email' => 'lupa@contoh.id']);
    }

    public function test_tautan_reset_dikirim_dan_kata_sandi_bisa_diganti(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'lupa@contoh.id'])->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($this->user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'lupa@contoh.id',
            'password' => 'sandi-baru-1', 'password_confirmation' => 'sandi-baru-1',
        ])->assertRedirect(route('login'));

        $this->post(route('login'), ['login' => 'lupa@contoh.id', 'password' => 'sandi-baru-1'])
            ->assertRedirect(route('owner.dashboard'));
    }

    public function test_email_tak_terdaftar_mendapat_jawaban_yang_sama(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'tidakada@contoh.id'])->assertSessionHas('success');
        Notification::assertNothingSent();
    }

    public function test_tautan_kedaluwarsa_setelah_60_menit(): void
    {
        $token = Password::createToken($this->user);

        $this->travel(61)->minutes();

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'lupa@contoh.id',
            'password' => 'sandi-baru-1', 'password_confirmation' => 'sandi-baru-1',
        ])->assertSessionHasErrors('email');
    }

    public function test_tautan_masih_berlaku_sebelum_60_menit(): void
    {
        $token = Password::createToken($this->user);

        $this->travel(59)->minutes();

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'lupa@contoh.id',
            'password' => 'sandi-baru-1', 'password_confirmation' => 'sandi-baru-1',
        ])->assertRedirect(route('login'));
    }
}
