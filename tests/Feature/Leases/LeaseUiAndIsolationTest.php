<?php

namespace Tests\Feature\Leases;

use App\Models\Attachment;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class LeaseUiAndIsolationTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->tenantWithOwner('pro');
        $this->property = $this->propertyFor($this->tenant, ['name' => 'Kos Melati']);
    }

    private function occupiedRoom(string $number, ?Tenant $tenant = null, ?Property $property = null, string $name = 'Siti Nur Aisyah'): Lease
    {
        $tenant ??= $this->tenant;
        $property ??= $this->property;
        $room = Room::factory()->for($tenant)->for($property)->create(['number' => $number]);
        $resident = Resident::factory()->for($tenant)->create(['name' => $name]);

        return $this->checkIn($tenant, $room, $resident);
    }

    public function test_all_lease_pages_render_for_owner_and_staff(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();
        $active = $this->occupiedRoom('101');
        $ended = $this->occupiedRoom('102', name: 'Bima Pratama');
        $this->actingAs($this->owner)->post(route('owner.leases.check-out', $ended), [
            'checkout_date' => $this->tenant->today()->toDateString(), 'ended_reason' => 'moved_out',
        ])->assertSessionHasNoErrors();
        Room::factory()->for($this->tenant)->for($this->property)->create(['number' => '103']);

        foreach ([$this->owner, $staff] as $user) {
            foreach (['active', 'expiring', 'ended', 'all'] as $tab) {
                $this->actingAs($user)->get(route('owner.leases.index', ['tab' => $tab]))->assertOk();
            }
            $this->actingAs($user)->get(route('owner.leases.index', ['q' => 'Siti']))->assertOk()->assertSee('Siti Nur Aisyah');
            $this->actingAs($user)->get(route('owner.leases.create'))->assertOk()->assertSee('Kamar 103');
            $this->actingAs($user)->get(route('owner.leases.show', $active))->assertOk()->assertSee('Check-out')->assertSee('Pindah kamar');
            $this->actingAs($user)->get(route('owner.leases.show', $ended))->assertOk()->assertDontSee('Pindah kamar');
        }

        $this->actingAs($this->owner)->get(route('owner.leases.index', ['tab' => 'ended']))->assertSee('Bima Pratama')->assertDontSee('Siti Nur Aisyah');
        $this->actingAs($this->owner)->get(route('owner.leases.index', ['tab' => 'bukan']))->assertSessionHasErrors('tab');
        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertSee('Kontrak');
    }

    public function test_money_decisions_are_hidden_from_staff_in_the_forms(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();
        $lease = $this->occupiedRoom('101');

        $this->actingAs($this->owner)->get(route('owner.leases.show', $lease))->assertSee('Potongan deposit')->assertSee('Harga sewa baru');
        $this->actingAs($staff)->get(route('owner.leases.show', $lease))->assertDontSee('Potongan deposit')->assertDontSee('Harga sewa baru');
    }

    public function test_expiring_tab_and_badges_show_text_not_only_color(): void
    {
        $soon = $this->occupiedRoom('101');
        $this->asTenant($this->tenant, fn () => Lease::findOrFail($soon->id)->update(['end_date' => $this->tenant->today()->addDays(10)->toDateString()]));
        $late = $this->occupiedRoom('102', name: 'Terlambat Keluar');
        $this->asTenant($this->tenant, fn () => Lease::findOrFail($late->id)->update(['end_date' => $this->tenant->today()->subDays(4)->toDateString()]));
        $far = $this->occupiedRoom('103', name: 'Masih Lama');

        $this->actingAs($this->owner)->get(route('owner.leases.index', ['tab' => 'expiring']))
            ->assertOk()->assertSee('Segera berakhir')->assertSee('10 hari lagi')->assertDontSee('Masih Lama')->assertDontSee('Terlambat Keluar');

        $this->actingAs($this->owner)->get(route('owner.leases.index', ['tab' => 'active']))
            ->assertSee('Lewat masa sewa')->assertSee('Lewat 4 hari');

        $this->actingAs($this->owner)->get(route('owner.leases.show', $late))->assertSee('Lewat masa sewa');
        $this->assertNotNull($far);
    }

    public function test_rooms_and_dashboard_show_the_resident_of_occupied_rooms(): void
    {
        $this->occupiedRoom('101');
        Room::factory()->for($this->tenant)->for($this->property)->create(['number' => '102']);

        foreach ([route('owner.rooms.index'), route('owner.dashboard')] as $url) {
            $this->actingAs($this->owner)->get($url)->assertOk()->assertSee('Siti Nur Aisyah');
        }

        $this->actingAs($this->owner)->get(route('owner.rooms.edit', Room::where('number', '102')->withoutGlobalScopes()->firstOrFail()))
            ->assertOk()->assertSee('Check-in penghuni');
        $occupied = $this->withoutTenant(fn () => Room::where('number', '101')->firstOrFail());
        $this->actingAs($this->owner)->get(route('owner.rooms.edit', $occupied))->assertOk()->assertDontSee('Kamar ini kosong');
    }

    public function test_residents_list_shows_rooms_search_by_room_number_and_history(): void
    {
        $lease = $this->occupiedRoom('207');
        Resident::factory()->for($this->tenant)->create(['name' => 'Tanpa Kamar']);

        $this->actingAs($this->owner)->get(route('owner.residents.index', ['q' => '207']))
            ->assertOk()->assertSee('Siti Nur Aisyah')->assertDontSee('Tanpa Kamar');
        $this->actingAs($this->owner)->get(route('owner.residents.index'))->assertSee('207');

        $this->actingAs($this->owner)->get(route('owner.residents.show', $lease->primary_resident_id))
            ->assertOk()->assertSee('Kos Melati')->assertSee('Aktif');
    }

    public function test_lease_pages_do_not_have_n_plus_one_queries(): void
    {
        $count = function (): int {
            DB::enableQueryLog();
            $this->actingAs($this->owner)->get(route('owner.leases.index', ['tab' => 'all']))->assertOk();
            $this->actingAs($this->owner)->get(route('owner.residents.index'))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();

            return $n;
        };

        foreach (range(1, 3) as $i) {
            $this->occupiedRoom('A'.$i, name: 'Penghuni '.$i);
        }
        $small = $count();

        foreach (range(4, 40) as $i) {
            $this->occupiedRoom('A'.$i, name: 'Penghuni '.$i);
        }
        $this->assertSame($small, $count(), 'Daftar kontrak atau penghuni terkena N+1.');
    }

    public function test_room_condition_photos_can_be_attached_viewed_and_deleted(): void
    {
        Storage::fake('private');
        $lease = $this->occupiedRoom('101');

        $this->actingAs($this->owner)->post(route('owner.leases.attachments.store', $lease), ['file' => UploadedFile::fake()->image('kamar.jpg')])
            ->assertSessionHasNoErrors();

        $photo = $this->asTenant($this->tenant, fn () => Attachment::firstOrFail());
        $this->assertSame('room_condition', $photo->category->value);
        $this->assertSame('lease', $photo->attachable_type);
        $this->assertFalse($photo->is_sensitive);

        $this->actingAs($this->owner)->get(route('owner.leases.show', $lease))->assertSee('Foto kondisi kamar')->assertSee('Lihat');
        $this->actingAs($this->owner)->get($photo->temporaryUrl())->assertOk();

        $this->actingAs($this->owner)->delete(route('owner.attachments.destroy', $photo))->assertRedirect(route('owner.leases.show', $lease));
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Attachment::count()));
    }

    public function test_tenant_isolation_for_every_lease_table(): void
    {
        $other = Tenant::factory()->create();
        $otherProperty = $this->propertyFor($other);
        $theirs = $this->occupiedRoom('B-101', $other, $otherProperty, 'Warga Tenant B');
        $mine = $this->occupiedRoom('A-101');

        $this->asTenant($this->tenant, function () use ($theirs, $mine) {
            $this->assertNull(Lease::find($theirs->id));
            $this->assertSame([$mine->id], Lease::pluck('id')->all());
            $this->assertSame(0, RoomTransfer::count());
        });

        $a = $this->actingAs($this->owner);
        $a->get(route('owner.leases.show', $theirs))->assertNotFound();
        $a->post(route('owner.leases.check-out', $theirs), ['checkout_date' => $this->tenant->today()->toDateString(), 'ended_reason' => 'moved_out'])->assertNotFound();
        $a->post(route('owner.leases.renew', $theirs), ['end_date' => '2030-01-01'])->assertNotFound();
        $a->post(route('owner.leases.transfer', $theirs), ['to_room_id' => 1, 'transferred_on' => $this->tenant->today()->toDateString()])->assertNotFound();
        $a->post(route('owner.leases.attachments.store', $theirs), ['file' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();

        $a->get(route('owner.leases.index', ['tab' => 'all']))->assertSee('A-101')->assertDontSee('B-101')->assertDontSee('Warga Tenant B');

        // Kontrak tenant B tidak tersentuh.
        $this->assertSame('active', $this->withoutTenant(fn () => Lease::findOrFail($theirs->id)->status->value));
        $this->assertSame(0, $this->withoutTenant(fn () => RoomTransfer::count()));
    }
}
