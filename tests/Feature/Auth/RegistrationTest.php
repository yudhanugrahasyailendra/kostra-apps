<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\RegisterOwner;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Kos Melati',
            'name' => 'Bu Sari',
            'email' => 'sari@example.com',
            'phone' => '',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ], $overrides);
    }

    public function test_owner_registers_with_email_and_gets_free_plan_tenant(): void
    {
        $this->post(route('register'), $this->payload())->assertRedirect('/owner');

        $this->assertAuthenticated();

        $user = $this->withoutTenant(fn () => User::where('email', 'sari@example.com')->firstOrFail());
        $this->assertSame(UserRole::Owner, $user->role);

        $tenant = Tenant::findOrFail($user->tenant_id);
        $this->assertSame('free', $tenant->plan->key);
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame('Asia/Makassar', $tenant->timezone);

        $this->get('/owner')->assertOk()->assertSee('Selamat datang');
    }

    public function test_owner_registers_with_phone_only_in_international_format(): void
    {
        $this->post(route('register'), $this->payload(['email' => '', 'phone' => '0812-3456-7890']))
            ->assertRedirect('/owner');

        $this->assertSame(1, $this->withoutTenant(fn () => User::where('phone', '+6281234567890')->count()));
    }

    public function test_email_or_phone_is_required(): void
    {
        $this->post(route('register'), $this->payload(['email' => '', 'phone' => '']))
            ->assertSessionHasErrors(['email', 'phone']);

        $this->assertSame(0, Tenant::count());
    }

    public function test_invalid_phone_and_duplicates_are_rejected(): void
    {
        $this->post(route('register'), $this->payload(['email' => '', 'phone' => '123']))
            ->assertSessionHasErrors('phone');

        User::factory()->create(['email' => 'sari@example.com']);

        $this->post(route('register'), $this->payload())->assertSessionHasErrors('email');
    }

    public function test_registration_is_atomic_when_user_creation_fails(): void
    {
        User::factory()->create(['email' => 'sari@example.com']);
        $tenantsBefore = Tenant::count();

        try {
            app(RegisterOwner::class)([
                'name' => 'Bu Sari',
                'business_name' => 'Kos Melati',
                'email' => 'sari@example.com',
                'password' => 'rahasia-123',
            ]);
            $this->fail('Seharusnya gagal karena email ganda.');
        } catch (QueryException) {
            // diharapkan
        }

        $this->assertSame($tenantsBefore, Tenant::count());
        $this->assertSame(0, Tenant::where('name', 'Kos Melati')->count());
    }
}
