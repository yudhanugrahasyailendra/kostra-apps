<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Mail\StaffInvitationMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StaffInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->for($this->tenant)->create();
    }

    /**
     * @return array{0: Invitation, 1: string}
     */
    private function invite(array $data = []): array
    {
        Mail::fake();

        $response = $this->actingAs($this->owner)->post(route('owner.staff.invite'), array_merge([
            'email' => 'staf@example.com',
            'permissions' => ['finance.view'],
        ], $data));

        $response->assertRedirect(route('owner.staff.index'));

        $link = session('invite_link');
        $this->assertNotNull($link);
        $token = basename($link);

        return [$this->withoutTenant(fn () => Invitation::firstOrFail()), $token];
    }

    public function test_owner_invites_staff_and_only_the_hash_is_stored(): void
    {
        [$invitation, $token] = $this->invite();

        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame($this->tenant->id, $invitation->tenant_id);
        $this->assertSame(['finance.view'], $invitation->permissions);
        $this->assertEqualsWithDelta(7 * 86400, now()->diffInSeconds($invitation->expires_at), 5);

        Mail::assertSent(StaffInvitationMail::class, fn ($mail) => $mail->hasTo('staf@example.com') && str_contains($mail->url, $token));
    }

    public function test_invitation_can_be_accepted_once(): void
    {
        [, $token] = $this->invite();
        auth()->logout();
        $this->flushSession();

        $this->get(route('invitation.show', $token))->assertOk()->assertSee('staf@example.com');

        $this->post(route('invitation.accept', $token), [
            'name' => 'Pak Budi',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ])->assertRedirect('/owner');

        $staff = $this->withoutTenant(fn () => User::where('email', 'staf@example.com')->firstOrFail());
        $this->assertSame(UserRole::Staff, $staff->role);
        $this->assertSame($this->tenant->id, $staff->tenant_id);
        $this->assertSame(['finance.view'], $staff->permissions);
        $this->assertAuthenticatedAs($staff);

        auth()->logout();
        $this->flushSession();

        $this->post(route('invitation.accept', $token), [
            'name' => 'Orang Lain',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ])->assertStatus(410);

        $this->get(route('invitation.show', $token))->assertStatus(410);
        $this->assertSame(1, $this->withoutTenant(fn () => User::where('email', 'staf@example.com')->count()));
    }

    public function test_expired_invitation_is_rejected(): void
    {
        [, $token] = $this->invite();
        auth()->logout();
        $this->flushSession();

        $this->travel(8)->days();

        $this->get(route('invitation.show', $token))->assertStatus(410);
        $this->post(route('invitation.accept', $token), [
            'name' => 'Pak Budi',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ])->assertStatus(410);

        $this->assertSame(0, $this->withoutTenant(fn () => User::where('email', 'staf@example.com')->count()));
    }

    public function test_revoked_invitation_is_rejected(): void
    {
        [$invitation, $token] = $this->invite();

        $this->actingAs($this->owner)
            ->delete(route('owner.staff.invitations.revoke', $invitation))
            ->assertRedirect(route('owner.staff.index'));

        $this->get(route('invitation.show', $token))->assertStatus(410);
    }

    public function test_unknown_token_is_rejected_the_same_way(): void
    {
        $this->get(route('invitation.show', str_repeat('a', 64)))->assertStatus(410);
    }

    public function test_cannot_invite_an_already_registered_email(): void
    {
        User::factory()->create(['email' => 'staf@example.com']);

        $this->actingAs($this->owner)
            ->post(route('owner.staff.invite'), ['email' => 'staf@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_staff_cannot_invite_or_manage_staff(): void
    {
        $staff = User::factory()->for($this->tenant)->staff(['settings.manage'])->create();

        $this->actingAs($staff)->post(route('owner.staff.invite'), ['email' => 'x@example.com'])->assertForbidden();
        $this->actingAs($staff)->get(route('owner.staff.index'))->assertForbidden();
    }

    public function test_owner_updates_staff_permissions_and_unknown_permissions_are_dropped(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($this->owner)
            ->put(route('owner.staff.update', $staff), ['permissions' => ['finance.view']])
            ->assertRedirect(route('owner.staff.index'));

        $this->assertSame(['finance.view'], $this->withoutTenant(fn () => User::find($staff->id))->permissions);

        $this->actingAs($this->owner)
            ->put(route('owner.staff.update', $staff), ['permissions' => ['hack.all']])
            ->assertSessionHasErrors('permissions.0');
    }
}
