<?php

namespace Tests\Feature\Residents;

use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class ResidentCrudTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rizky Dwi Saputra',
            'phone' => '0812-3456-7890',
            'email' => 'Rizky@Example.com',
            'occupation' => 'Mahasiswa Unhas',
            'emergency_name' => 'Ibu Rizky',
            'emergency_relation' => 'Ibu',
            'emergency_phone' => '081311112222',
            'consent' => '1',
        ], $overrides);
    }

    public function test_owner_creates_resident_with_normalized_phone_and_recorded_consent(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $response = $this->actingAs($owner)->post(route('owner.residents.store'), $this->payload());

        $resident = $this->asTenant($tenant, fn () => Resident::firstOrFail());
        $response->assertRedirect(route('owner.residents.show', $resident));

        $this->assertSame('+6281234567890', $resident->phone);
        $this->assertSame('+6281311112222', $resident->emergency_phone);
        $this->assertSame('rizky@example.com', $resident->email);
        $this->assertNotNull($resident->consented_at);
        $this->assertTrue($resident->is_active);
        $this->assertSame($tenant->id, $resident->tenant_id);
    }

    #[DataProvider('equivalentPhones')]
    public function test_all_phone_formats_are_stored_identically(string $input): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post(route('owner.residents.store'), $this->payload(['phone' => $input]))
            ->assertSessionHasNoErrors();

        $this->assertSame('+6281234567890', $this->asTenant($tenant, fn () => Resident::firstOrFail()->phone));
    }

    public static function equivalentPhones(): array
    {
        return [['081234567890'], ['+6281234567890'], ['6281234567890']];
    }

    public function test_consent_is_required_and_invalid_phone_is_rejected(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post(route('owner.residents.store'), $this->payload(['consent' => null]))
            ->assertSessionHasErrors('consent');

        $this->actingAs($owner)->post(route('owner.residents.store'), $this->payload(['phone' => '12345']))
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, $this->asTenant($tenant, fn () => Resident::count()));
    }

    public function test_duplicate_phone_is_allowed_but_warned_and_flagged(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        Resident::factory()->for($tenant)->create(['phone' => '+6281234567890']);

        $this->actingAs($owner)->post(route('owner.residents.store'), $this->payload())
            ->assertSessionHas('warning');

        $this->assertSame(2, $this->asTenant($tenant, fn () => Resident::count()));

        $this->actingAs($owner)->get(route('owner.residents.index'))->assertOk()->assertSee('Nomor ganda');
    }

    public function test_update_changes_data_but_activity_follows_leases_not_the_form(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $resident = Resident::factory()->for($tenant)->create();

        $this->actingAs($owner)->put(route('owner.residents.update', $resident), [
            'name' => 'Nama Baru', 'phone' => '0821 0000 1111', 'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $fresh = $this->asTenant($tenant, fn () => Resident::findOrFail($resident->id));
        $this->assertSame('Nama Baru', $fresh->name);
        $this->assertSame('+6282100001111', $fresh->phone);
        // Status aktif dikelola check-in dan check-out; isian formulir diabaikan.
        $this->assertTrue($fresh->is_active);
    }

    public function test_consent_cannot_be_sent_on_update(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $resident = Resident::factory()->for($tenant)->create();

        $this->actingAs($owner)->put(route('owner.residents.update', $resident), [
            'name' => 'X', 'phone' => '081234567890', 'consent' => '1',
        ])->assertSessionHasErrors('consent');
    }

    public function test_search_and_status_filter(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        Resident::factory()->for($tenant)->create(['name' => 'Siti Nur Aisyah', 'phone' => '+6281200000001']);
        Resident::factory()->for($tenant)->create(['name' => 'Bima Pratama', 'phone' => '+6281200000002']);
        Resident::factory()->inactive()->for($tenant)->create(['name' => 'Dina Lama', 'phone' => '+6281200000003']);

        $get = fn (array $query) => $this->actingAs($owner)->get(route('owner.residents.index', $query));

        $get(['q' => 'siti'])->assertSee('Siti Nur Aisyah')->assertDontSee('Bima Pratama');
        $get(['q' => '0812 0000 0002'])->assertSee('Bima Pratama')->assertDontSee('Siti Nur Aisyah');
        $get(['q' => '+62812'])->assertSee('Siti Nur Aisyah')->assertSee('Bima Pratama');
        $get(['status' => 'inactive'])->assertSee('Dina Lama')->assertDontSee('Siti Nur Aisyah');
        $get(['status' => 'active'])->assertDontSee('Dina Lama')->assertSee('Bima Pratama');
        $get(['q' => '%'])->assertOk()->assertDontSee('Siti Nur Aisyah');
        $get(['status' => 'salah'])->assertSessionHasErrors('status');
    }

    public function test_staff_can_create_and_edit_but_not_delete(): void
    {
        [$tenant] = $this->tenantWithOwner();
        $staff = User::factory()->for($tenant)->staff()->create();
        $resident = Resident::factory()->for($tenant)->create();

        $this->actingAs($staff)->post(route('owner.residents.store'), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($staff)->put(route('owner.residents.update', $resident), ['name' => 'Diubah Staf', 'phone' => '081234500000'])
            ->assertSessionHasNoErrors();
        $this->actingAs($staff)->delete(route('owner.residents.destroy', $resident))->assertForbidden();

        $this->assertSame(2, $this->asTenant($tenant, fn () => Resident::count()));
    }

    public function test_suspended_tenant_cannot_write_but_can_read(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->suspended()->create();
        $owner = User::factory()->for($tenant)->create();
        $resident = Resident::factory()->for($tenant)->create();

        $this->actingAs($owner)->get(route('owner.residents.index'))->assertOk();
        $this->actingAs($owner)->get(route('owner.residents.show', $resident))->assertOk();
        $this->actingAs($owner)->post(route('owner.residents.store'), $this->payload())->assertForbidden();
        $this->actingAs($owner)->delete(route('owner.residents.destroy', $resident))->assertForbidden();
    }

    public function test_every_resident_page_renders_for_owner_and_staff(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $staff = User::factory()->for($tenant)->staff()->create();
        $resident = Resident::factory()->for($tenant)->create(['name' => 'Siti Nur Aisyah', 'notes' => 'Catatan rahasia']);

        foreach ([$owner, $staff] as $user) {
            foreach ([
                route('owner.residents.index'), route('owner.residents.create'),
                route('owner.residents.show', $resident), route('owner.residents.edit', $resident),
            ] as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }

        $this->actingAs($owner)->get(route('owner.residents.show', $resident))
            ->assertSee('Siti Nur Aisyah')->assertSee('Anonimkan dan hapus')->assertSee('Riwayat kontrak');
        $this->actingAs($staff)->get(route('owner.residents.show', $resident))->assertDontSee('Anonimkan dan hapus');
        $this->actingAs($owner)->get(route('owner.dashboard'))->assertSee('Penghuni');
    }
}
