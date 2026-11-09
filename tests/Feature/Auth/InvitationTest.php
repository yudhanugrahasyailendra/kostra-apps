<?php

namespace Tests\Feature\Auth;

use App\Actions\InviteStaff;
use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->owner()->create();
    }

    /** @return array{0: Invitation, 1: string} */
    private function invite(array $data = ['email' => 'staf@contoh.id', 'permissions' => ['finance.view']]): array
    {
        $result = TenantContext::instance()->runAs($this->tenant, fn () => app(InviteStaff::class)->handle($this->owner, $data));

        return [$result['invitation'], $result['token']];
    }

    private function accept(string $token, array $override = [])
    {
        return $this->post(route('invitation.accept', $token), $override + [
            'name' => 'Budi', 'email' => 'staf@contoh.id',
            'password' => 'rahasia-banget', 'password_confirmation' => 'rahasia-banget',
        ]);
    }

    public function test_pemilik_mengundang_dan_hanya_hash_token_yang_disimpan(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('owner.staff.invite'), ['email' => 'staf@contoh.id', 'permissions' => ['finance.view']]);

        $response->assertRedirect()->assertSessionHas('invitation_link');

        $link = session('invitation_link');
        $token = basename(parse_url($link, PHP_URL_PATH));

        $this->assertDatabaseMissing('invitations', ['token_hash' => $token]);
        $this->assertDatabaseHas('invitations', ['token_hash' => hash('sha256', $token), 'tenant_id' => $this->tenant->id]);
    }

    public function test_undangan_diterima_membuat_staf_di_tenant_yang_sama(): void
    {
        [$invitation, $token] = $this->invite();

        $this->accept($token)->assertRedirect(route('owner.dashboard'));

        $user = TenantContext::instance()->unscoped(fn () => User::query()->where('email', 'staf@contoh.id')->firstOrFail());
        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertSame(UserRole::Staff, $user->role);
        $this->assertSame(['finance.view'], $user->permissions);
        $this->assertNotNull(TenantContext::instance()->unscoped(fn () => Invitation::find($invitation->id))->accepted_at);
    }

    public function test_undangan_sekali_pakai(): void
    {
        [, $token] = $this->invite();

        $this->accept($token)->assertRedirect();
        auth()->logout();

        $this->accept($token, ['email' => 'lain@contoh.id'])->assertSessionHasErrors('token');
    }

    public function test_undangan_kedaluwarsa_setelah_7_hari_ditolak(): void
    {
        [, $token] = $this->invite();

        $this->travel(8)->days();

        $this->accept($token)->assertSessionHasErrors('token');
        $this->assertSame(0, TenantContext::instance()->unscoped(fn () => User::query()->where('email', 'staf@contoh.id')->count()));
    }

    public function test_token_palsu_ditolak(): void
    {
        $this->accept('token-yang-tidak-ada')->assertSessionHasErrors('token');
    }

    public function test_halaman_undangan_tidak_membocorkan_data_tenant_atau_hash(): void
    {
        [, $token] = $this->invite();

        $this->get(route('invitation.show', $token))
            ->assertInertia(fn ($page) => $page
                ->component('Auth/AcceptInvitation')
                ->where('usable', true)
                ->missing('invitation')
                ->missing('tenant_id')
                ->missing('token_hash'));
    }

    public function test_staf_tidak_bisa_mengundang(): void
    {
        $staff = User::factory()->forTenant($this->tenant)->staff()->create();

        $this->actingAs($staff)
            ->post(route('owner.staff.invite'), ['email' => 'x@contoh.id'])
            ->assertForbidden();

        $this->actingAs($staff)->get(route('owner.staff.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('owner.audit.index'))->assertForbidden();
    }
}
