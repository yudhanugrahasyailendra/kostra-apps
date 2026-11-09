<?php

namespace Tests\Feature\Leases;

use App\Enums\DepositStatus;
use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LeaseHttpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $staffA;

    private User $ownerB;

    private Property $propertyA;

    private Property $propertyB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->staffA = User::factory()->forTenant($this->tenantA)->staff()->create();
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();
        $this->propertyA = $this->inTenant($this->tenantA, fn () => Property::factory()->create());
        $this->propertyB = $this->inTenant($this->tenantB, fn () => Property::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    private function room(Tenant $tenant, array $attributes = []): Room
    {
        $property = $tenant->is($this->tenantA) ? $this->propertyA : $this->propertyB;

        return $this->inTenant($tenant, fn () => Room::factory()->create($attributes + ['property_id' => $property->id]));
    }

    private function resident(Tenant $tenant): Resident
    {
        return $this->inTenant($tenant, fn () => Resident::factory()->create());
    }

    /** Check-in lewat UI (route), seperti pengguna sebenarnya. */
    private function checkInVia(User $user, Room $room, Resident $resident, array $extra = []): Lease
    {
        $this->actingAs($user)->post(route('owner.leases.store'), $extra + [
            'room_id' => $room->id,
            'primary_resident_id' => $resident->id,
            'start_date' => '2026-11-01',
            'duration_months' => 12,
            'deposit_amount' => 1000000,
            'deposit_received_at' => '2026-11-01',
        ])->assertSessionHasNoErrors();

        return $this->inTenant($user->tenant, fn () => Lease::query()->where('room_id', $room->id)->latest('id')->firstOrFail());
    }

    public function test_simulasi_check_in_sampai_check_out_lewat_ui_tanpa_mengedit_data_langsung(): void
    {
        $room = $this->room($this->tenantA, ['price' => 1850000]);
        $resident = $this->resident($this->tenantA);

        // Formulir check-in menawarkan kamar kosong dan penghuni yang belum punya kontrak aktif.
        $this->actingAs($this->staffA)->get(route('owner.leases.create', ['room_id' => $room->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Leases/Create')
                ->has('rooms', 1)
                ->where('rooms.0.id', $room->id)
                ->where('rooms.0.price', 1850000)
                ->has('residents', 1)
                ->where('defaults.room_id', $room->id));

        $lease = $this->checkInVia($this->staffA, $room, $resident, ['rent_amount' => '', 'checkin_notes' => 'Kunci 2 buah']);

        $this->actingAs($this->staffA)->get(route('owner.leases.show', $lease))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Leases/Show')
                ->where('lease.status', 'active')
                ->where('lease.rent_amount', 1850000)
                ->where('lease.deposit.amount', 1000000)
                ->where('lease.deposit.status', 'held')
                ->where('lease.end_date', '2027-10-31')
                ->where('lease.checkin_notes', 'Kunci 2 buah')
                ->where('lease.can.check_out', true)
                ->where('lease.can.renew', false, 'staf tidak boleh memperpanjang')
                ->has('lease.residents', 1));

        $this->actingAs($this->staffA)->get(route('owner.leases.checkout.create', $lease))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Owner/Leases/CheckOut')->where('outstanding', 0));

        $this->actingAs($this->staffA)->post(route('owner.leases.checkout.store', $lease), [
            'ended_reason' => 'moved_out',
            'room_status' => 'vacant',
            'checkout_notes' => 'Kamar bersih',
            'deductions' => [['label' => 'Cat ulang', 'amount' => 150000]],
        ])->assertRedirect(route('owner.leases.show', $lease))->assertSessionHasNoErrors();

        $this->inTenant($this->tenantA, function () use ($lease, $room) {
            $this->assertSame(LeaseStatus::Ended, Lease::query()->findOrFail($lease->id)->status);
            $this->assertSame(RoomStatus::Vacant, $room->fresh()->status);
            $this->assertSame(850000, LeaseDeposit::query()->where('lease_id', $lease->id)->firstOrFail()->refund_amount);
        });

        // Riwayat kontrak tampil di profil penghuni.
        $resident = $this->inTenant($this->tenantA, fn () => Resident::query()->firstOrFail());
        $this->actingAs($this->ownerA)->get(route('owner.residents.show', $resident))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Residents/Show')
                ->has('leases', 1)
                ->where('leases.0.id', $lease->id)
                ->where('leases.0.status', 'ended'));
    }

    public function test_pemilik_memperpanjang_dan_memindahkan_kamar_lewat_ui(): void
    {
        $roomA = $this->room($this->tenantA);
        $roomB = $this->room($this->tenantA, ['price' => 2100000]);
        $lease = $this->checkInVia($this->ownerA, $roomA, $this->resident($this->tenantA));

        $this->actingAs($this->ownerA)->post(route('owner.leases.transfer', $lease), [
            'to_room_id' => $roomB->id, 'new_rent_amount' => 2100000, 'transferred_on' => '2026-11-15', 'reason' => 'Naik kelas',
        ])->assertRedirect(route('owner.leases.show', $lease))->assertSessionHasNoErrors();

        $this->actingAs($this->ownerA)->post(route('owner.leases.renew', $lease), ['duration_months' => 12])
            ->assertSessionHasNoErrors();

        $this->inTenant($this->tenantA, function () use ($lease, $roomA, $roomB) {
            $old = Lease::query()->findOrFail($lease->id);
            $new = Lease::query()->where('renewed_from_lease_id', $old->id)->firstOrFail();

            $this->assertSame($roomB->id, $new->room_id);
            $this->assertSame(2100000, $new->rent_amount);
            $this->assertSame('2027-11-01', $new->start_date->toDateString());
            $this->assertSame(RoomStatus::Vacant, $roomA->fresh()->status);
            $this->assertSame(RoomStatus::Occupied, $roomB->fresh()->status);
            $this->assertSame(1, RoomTransfer::query()->count());
        });
    }

    public function test_staf_tidak_boleh_memperpanjang_atau_memindahkan_kamar(): void
    {
        $lease = $this->checkInVia($this->staffA, $this->room($this->tenantA), $this->resident($this->tenantA));
        $other = $this->room($this->tenantA);

        $this->actingAs($this->staffA)->post(route('owner.leases.renew', $lease), ['duration_months' => 12])->assertForbidden();
        $this->actingAs($this->staffA)->post(route('owner.leases.transfer', $lease), ['to_room_id' => $other->id])->assertForbidden();

        $this->inTenant($this->tenantA, fn () => $this->assertSame(1, Lease::query()->count()));
    }

    public function test_check_in_tanpa_login_dialihkan_ke_masuk(): void
    {
        $this->post(route('owner.leases.store'), [])->assertRedirect();
        $this->get(route('owner.leases.index'))->assertRedirect();
    }

    public function test_validasi_check_in_memakai_pesan_indonesia(): void
    {
        $this->actingAs($this->ownerA)->post(route('owner.leases.store'), ['start_date' => 'bukan-tanggal', 'due_day' => 31, 'rent_amount' => 'abc'])
            ->assertSessionHasErrors([
                'room_id' => 'Pilih kamar.',
                'primary_resident_id' => 'Pilih penghuni penanggung jawab.',
                'start_date' => 'Format tanggal mulai tidak valid.',
                'due_day' => 'Tanggal jatuh tempo antara 1 dan 28.',
                'rent_amount' => 'Harga sewa harus berupa angka bulat rupiah.',
            ]);
    }

    public function test_check_in_pada_kamar_terisi_menampilkan_galat_di_field_kamar(): void
    {
        $room = $this->room($this->tenantA);
        $this->checkInVia($this->ownerA, $room, $this->resident($this->tenantA));

        $this->actingAs($this->ownerA)->post(route('owner.leases.store'), [
            'room_id' => $room->id, 'primary_resident_id' => $this->resident($this->tenantA)->id, 'start_date' => '2026-11-01',
        ])->assertSessionHasErrors(['room_id' => 'Kamar ini sudah terisi dan memiliki kontrak aktif.']);
    }

    public function test_kamar_terisi_tidak_bisa_dihapus_dan_statusnya_tidak_diubah_manual(): void
    {
        $room = $this->room($this->tenantA);
        $this->checkInVia($this->ownerA, $room, $this->resident($this->tenantA));

        $this->actingAs($this->ownerA)->delete(route('owner.rooms.destroy', $room))
            ->assertSessionHasErrors(['room' => 'Kamar masih punya kontrak aktif. Check-out penghuni lebih dulu.']);

        // Formulir ubah kamar mengirim status "vacant" untuk kamar terisi; server harus mengabaikannya.
        $this->actingAs($this->ownerA)->patch(route('owner.rooms.update', $room), [
            'property_id' => $this->propertyA->id, 'number' => $room->number, 'price' => 1500000, 'status' => 'vacant',
        ])->assertSessionHasNoErrors();

        $this->assertSame(RoomStatus::Occupied, $room->fresh()->status);
        $this->inTenant($this->tenantA, fn () => $this->assertNull(Room::withTrashed()->findOrFail($room->id)->deleted_at));
    }

    public function test_penghuni_dengan_kontrak_aktif_tidak_bisa_dihapus(): void
    {
        $resident = $this->resident($this->tenantA);
        $this->checkInVia($this->ownerA, $this->room($this->tenantA), $resident);

        $this->actingAs($this->ownerA)->delete(route('owner.residents.destroy', $resident))
            ->assertSessionHasErrors(['resident' => 'Penghuni masih punya kontrak aktif. Check-out lebih dulu.']);

        $this->inTenant($this->tenantA, fn () => $this->assertNotNull(Resident::query()->find($resident->id)));
    }

    public function test_daftar_kontrak_tab_filter_dan_pencarian(): void
    {
        $roomExpiring = $this->room($this->tenantA, ['number' => '101']);
        $roomLate = $this->room($this->tenantA, ['number' => '102']);
        $roomLong = $this->room($this->tenantA, ['number' => '103']);

        $this->inTenant($this->tenantA, function () use ($roomExpiring, $roomLate, $roomLong) {
            $make = fn (Room $room, string $name, ?string $end) => Lease::factory()->create([
                'property_id' => $this->propertyA->id,
                'room_id' => $room->id,
                'primary_resident_id' => Resident::factory()->create(['name' => $name])->id,
                'end_date' => $end,
            ]);

            $make($roomExpiring, 'Agus Soon', '2026-11-20');
            $make($roomLate, 'Bima Late', '2026-11-01');
            $make($roomLong, 'Citra Long', '2027-06-30');
        });

        $this->actingAs($this->ownerA)->get(route('owner.leases.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Owner/Leases/Index')
            ->has('leases', 3)
            ->where('counts', ['active' => 3, 'expiring' => 1, 'overdue' => 1, 'history' => 0])
            ->where('leases.0.primary_resident_name', 'Bima Late'));

        $this->actingAs($this->ownerA)->get(route('owner.leases.index', ['tab' => 'expiring']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('leases', 1)->where('leases.0.primary_resident_name', 'Agus Soon')->where('leases.0.expires_soon', true));

        $this->actingAs($this->ownerA)->get(route('owner.leases.index', ['tab' => 'overdue']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('leases', 1)->where('leases.0.is_overdue', true)->where('leases.0.room_number', '102'));

        $this->actingAs($this->ownerA)->get(route('owner.leases.index', ['q' => '103']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('leases', 1)->where('leases.0.primary_resident_name', 'Citra Long'));

        $this->actingAs($this->ownerA)->get(route('owner.leases.index', ['q' => 'bima']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('leases', 1)->where('leases.0.room_number', '102'));
    }

    public function test_props_kontrak_tidak_memuat_kolom_terlarang(): void
    {
        $lease = $this->checkInVia($this->ownerA, $this->room($this->tenantA), $this->resident($this->tenantA));

        $this->actingAs($this->ownerA)->get(route('owner.leases.show', $lease))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('lease', fn ($l) => $l->missing('tenant_id')->missing('active_room_id')->missing('property_id')->etc())
            ->has('lease.residents.0', fn ($r) => $r->missing('tenant_id')->missing('email')->missing('notes')->etc()));

        $this->actingAs($this->ownerA)->get(route('owner.leases.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('leases.0', fn ($l) => $l->missing('tenant_id')->missing('primary_resident_phone')->missing('checkin_notes')->etc()));
    }

    // Isolasi tenant

    public function test_model_kontrak_tenant_lain_tidak_terbaca_dari_tenant_a(): void
    {
        $roomB = $this->room($this->tenantB);
        $residentB = $this->resident($this->tenantB);
        $leaseB = $this->checkInVia($this->ownerB, $roomB, $residentB);
        $toB = $this->room($this->tenantB);
        $this->actingAs($this->ownerB)->post(route('owner.leases.transfer', $leaseB), ['to_room_id' => $toB->id])->assertSessionHasNoErrors();

        $this->inTenant($this->tenantA, function () use ($leaseB) {
            $this->assertSame(0, Lease::query()->count());
            $this->assertNull(Lease::query()->find($leaseB->id));
            $this->assertSame(0, LeaseResident::query()->count());
            $this->assertSame(0, LeaseDeposit::query()->count());
            $this->assertSame(0, RoomTransfer::query()->count());
        });

        $this->inTenant($this->tenantB, function () {
            $this->assertSame(1, Lease::query()->count());
            $this->assertSame(1, LeaseResident::query()->count());
            $this->assertSame(1, LeaseDeposit::query()->count());
            $this->assertSame(1, RoomTransfer::query()->count());
        });
    }

    public function test_pengguna_tenant_a_tidak_bisa_membaca_atau_mengubah_kontrak_tenant_b_lewat_url(): void
    {
        $roomB = $this->room($this->tenantB);
        $leaseB = $this->checkInVia($this->ownerB, $roomB, $this->resident($this->tenantB));
        $freeA = $this->room($this->tenantA);

        $this->actingAs($this->ownerA)->get(route('owner.leases.show', $leaseB))->assertNotFound();
        $this->actingAs($this->ownerA)->get(route('owner.leases.checkout.create', $leaseB))->assertNotFound();
        $this->actingAs($this->ownerA)->post(route('owner.leases.checkout.store', $leaseB), ['ended_reason' => 'moved_out', 'room_status' => 'vacant'])->assertNotFound();
        $this->actingAs($this->ownerA)->post(route('owner.leases.renew', $leaseB), ['duration_months' => 12])->assertNotFound();
        $this->actingAs($this->ownerA)->post(route('owner.leases.transfer', $leaseB), ['to_room_id' => $freeA->id])->assertNotFound();

        $this->inTenant($this->tenantB, function () use ($leaseB, $roomB) {
            $this->assertSame(LeaseStatus::Active, Lease::query()->findOrFail($leaseB->id)->status);
            $this->assertSame(RoomStatus::Occupied, $roomB->fresh()->status);
            $this->assertSame(DepositStatus::Held, LeaseDeposit::query()->where('lease_id', $leaseB->id)->firstOrFail()->status);
        });

        $this->actingAs($this->ownerA)->get(route('owner.leases.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('leases', 0)->where('counts.active', 0));
    }

    public function test_check_in_dengan_kamar_atau_penghuni_tenant_lain_ditolak(): void
    {
        $roomB = $this->room($this->tenantB);
        $residentB = $this->resident($this->tenantB);
        $roomA = $this->room($this->tenantA);
        $residentA = $this->resident($this->tenantA);

        $this->actingAs($this->ownerA)->post(route('owner.leases.store'), [
            'room_id' => $roomB->id, 'primary_resident_id' => $residentA->id, 'start_date' => '2026-11-01',
        ])->assertSessionHasErrors(['room_id' => 'Kamar tidak ditemukan.']);

        $this->actingAs($this->ownerA)->post(route('owner.leases.store'), [
            'room_id' => $roomA->id, 'primary_resident_id' => $residentB->id, 'start_date' => '2026-11-01',
        ])->assertSessionHasErrors(['primary_resident_id' => 'Penghuni tidak ditemukan.']);

        $this->actingAs($this->ownerA)->post(route('owner.leases.store'), [
            'room_id' => $roomA->id, 'primary_resident_id' => $residentA->id, 'resident_ids' => [$residentB->id], 'start_date' => '2026-11-01',
        ])->assertSessionHasErrors(['resident_ids.0' => 'Penghuni tambahan tidak ditemukan.']);

        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, Lease::query()->count()));
        $this->assertSame(RoomStatus::Vacant, $roomB->fresh()->status);
    }

    public function test_pindah_kamar_ke_kamar_tenant_lain_ditolak(): void
    {
        $lease = $this->checkInVia($this->ownerA, $this->room($this->tenantA), $this->resident($this->tenantA));
        $roomB = $this->room($this->tenantB);

        $this->actingAs($this->ownerA)->post(route('owner.leases.transfer', $lease), ['to_room_id' => $roomB->id])
            ->assertSessionHasErrors(['to_room_id' => 'Kamar tujuan tidak ditemukan.']);

        $this->assertSame(RoomStatus::Vacant, $roomB->fresh()->status);
    }

    public function test_form_check_in_hanya_menawarkan_kamar_dan_penghuni_tenant_sendiri(): void
    {
        $roomA = $this->room($this->tenantA);
        $this->room($this->tenantB);
        $residentA = $this->resident($this->tenantA);
        $this->resident($this->tenantB);

        $this->actingAs($this->ownerA)->get(route('owner.leases.create'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rooms', 1)->where('rooms.0.id', $roomA->id)
            ->has('residents', 1)->where('residents.0.id', $residentA->id));
    }
}
