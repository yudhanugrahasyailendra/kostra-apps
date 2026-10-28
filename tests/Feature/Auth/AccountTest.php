<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_account_page(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
    }

    public function test_user_updates_own_profile_without_changing_password(): void
    {
        $user = User::factory()->create(['email' => 'sari@example.com']);
        $hash = $user->password;

        $this->actingAs($user)->get(route('account.edit'))->assertOk()->assertSee('Edit akun');

        $this->actingAs($user)->put(route('account.update'), [
            'name' => 'Sari Baru',
            'email' => 'Sari.Baru@Example.com',
            'phone' => '0812 3456 7890',
        ])->assertRedirect(route('account.edit'));

        $fresh = $this->withoutTenant(fn () => User::find($user->id));
        $this->assertSame('Sari Baru', $fresh->name);
        $this->assertSame('sari.baru@example.com', $fresh->email);
        $this->assertSame('+6281234567890', $fresh->phone);
        $this->assertSame($hash, $fresh->password);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['email' => 'sari@example.com']);

        $this->actingAs($user)->put(route('account.update'), [
            'name' => $user->name,
            'email' => 'sari@example.com',
            'current_password' => 'salah',
            'password' => 'kata-sandi-baru',
            'password_confirmation' => 'kata-sandi-baru',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('account.update'), [
            'name' => $user->name,
            'email' => 'sari@example.com',
            'current_password' => 'password',
            'password' => 'kata-sandi-baru',
            'password_confirmation' => 'kata-sandi-baru',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('kata-sandi-baru', $this->withoutTenant(fn () => User::find($user->id))->password));
    }

    public function test_email_taken_by_another_tenant_is_rejected_and_only_own_row_changes(): void
    {
        $mine = User::factory()->create(['email' => 'a@example.com']);
        $other = User::factory()->create(['email' => 'b@example.com']);

        $this->actingAs($mine)->put(route('account.update'), [
            'name' => 'Ganti',
            'email' => 'b@example.com',
        ])->assertSessionHasErrors('email');

        $this->actingAs($mine)->put(route('account.update'), ['name' => 'Ganti', 'email' => 'a@example.com']);

        $this->assertSame('Ganti', $this->withoutTenant(fn () => User::find($mine->id))->name);
        $this->assertNotSame('Ganti', $this->withoutTenant(fn () => User::find($other->id))->name);
    }
}
