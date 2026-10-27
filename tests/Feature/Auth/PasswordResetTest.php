<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function requestToken(User $user): string
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        return $token;
    }

    public function test_password_can_be_reset_with_a_fresh_link(): void
    {
        $user = User::factory()->create(['email' => 'sari@example.com']);
        $token = $this->requestToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'sari@example.com',
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('sandi-baru-123', $this->withoutTenant(fn () => User::find($user->id))->password));
    }

    public function test_reset_link_expires_after_60_minutes(): void
    {
        $user = User::factory()->create(['email' => 'sari@example.com']);
        $token = $this->requestToken($user);

        $this->travel(61)->minutes();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'sari@example.com',
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $this->withoutTenant(fn () => User::find($user->id))->password));
    }

    public function test_unknown_email_gets_the_same_answer(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'tidak-ada@example.com'])->assertSessionHas('status');

        Notification::assertNothingSent();
    }
}
