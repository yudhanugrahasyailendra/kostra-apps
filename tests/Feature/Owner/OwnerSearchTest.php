<?php

namespace Tests\Feature\Owner;

use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerSearchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $staffA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->staffA = User::factory()->forTenant($this->tenantA)->staff()->create();
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    private function search(User $user, string $q)
    {
        return $this->actingAs($user)->getJson(route('owner.search', ['q' => $q]));
    }

    public function test_mencari_kamar_berdasarkan_nomor_dan_penghuni_berdasarkan_nama(): void
    {
        $this->inTenant($this->tenantA, function () {
            $property = Property::factory()->create(['name' => 'Kos Melati']);
            $room = Room::factory()->create(['property_id' => $property->id, 'number' => 'A12', 'status' => RoomStatus::Occupied]);
            Room::factory()->create(['property_id' => $property->id, 'number' => 'B07']);
            $resident = Resident::factory()->create(['name' => 'Bagus Arif']);
            Resident::factory()->create(['name' => 'Siti Aminah']);
            Lease::factory()->create(['property_id' => $property->id, 'room_id' => $room->id, 'primary_resident_id' => $resident->id]);
        });

        $this->search($this->ownerA, 'a12')
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.number', 'A12')
            ->assertJsonPath('rooms.0.property_name', 'Kos Melati')
            ->assertJsonPath('rooms.0.status_label', 'Terisi')
            ->assertJsonPath('rooms.0.resident_name', 'Bagus Arif');

        $this->search($this->staffA, 'bagus')
            ->assertOk()
            ->assertJsonCount(0, 'rooms')
            ->assertJsonCount(1, 'residents')
            ->assertJsonPath('residents.0.name', 'Bagus Arif')
            ->assertJsonPath('residents.0.room_number', 'A12');
    }

    public function test_hasil_tidak_memuat_data_pribadi_yang_tidak_dipakai(): void
    {
        $this->inTenant($this->tenantA, fn () => Resident::factory()->create(['name' => 'Bagus Arif', 'phone' => '+6281234567890']));

        $response = $this->search($this->ownerA, 'Bagus')->assertOk();

        $this->assertSame(['id', 'name', 'is_active', 'status_label', 'room_number', 'href'], array_keys($response->json('residents.0')));
        $this->assertStringNotContainsString('6281234567890', $response->getContent());
        $this->assertStringNotContainsString('tenant_id', $response->getContent());
    }

    public function test_pencarian_tidak_menampilkan_data_tenant_lain(): void
    {
        $this->inTenant($this->tenantB, function () {
            $property = Property::factory()->create();
            Room::factory()->create(['property_id' => $property->id, 'number' => 'A12']);
            Resident::factory()->create(['name' => 'Bagus Arif']);
        });

        $this->search($this->ownerA, 'A12')->assertOk()->assertJsonCount(0, 'rooms')->assertJsonCount(0, 'residents');
        $this->search($this->ownerA, 'Bagus')->assertOk()->assertJsonCount(0, 'rooms')->assertJsonCount(0, 'residents');
    }

    public function test_hasil_dibatasi_lima_per_jenis(): void
    {
        $this->inTenant($this->tenantA, function () {
            $property = Property::factory()->create();
            foreach (range(1, 8) as $n) {
                Room::factory()->create(['property_id' => $property->id, 'number' => "K{$n}"]);
                Resident::factory()->create(['name' => "Kurnia {$n}"]);
            }
        });

        $this->search($this->ownerA, 'K')->assertOk()->assertJsonCount(5, 'rooms')->assertJsonCount(5, 'residents');
    }

    public function test_kata_kunci_wajib_dan_dibatasi(): void
    {
        $this->search($this->ownerA, '')->assertStatus(422)->assertJsonValidationErrors('q');
        $this->search($this->ownerA, str_repeat('a', 101))->assertStatus(422)->assertJsonValidationErrors('q');
    }

    public function test_tamu_tidak_bisa_mencari(): void
    {
        $this->getJson(route('owner.search', ['q' => 'A12']))->assertUnauthorized();
    }
}
