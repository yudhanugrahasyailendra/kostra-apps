<?php

namespace Tests\Feature\Leases;

use App\Actions\CheckInResident;
use App\Actions\CheckOutResident;
use App\Actions\RenewLease;
use App\Actions\TransferRoom;
use App\Contracts\OutstandingBalance;
use App\Enums\DepositStatus;
use App\Enums\LeaseEndReason;
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
use App\Services\Billing\ZeroOutstandingBalance;
use App\Tenancy\TenantContext;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->owner()->create();
        $this->property = $this->inTenant(fn () => Property::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function inTenant(callable $callback): mixed
    {
        return TenantContext::instance()->runAs($this->tenant, $callback);
    }

    private function room(array $attributes = []): Room
    {
        return $this->inTenant(fn () => Room::factory()->create($attributes + ['property_id' => $this->property->id]));
    }

    private function resident(array $attributes = []): Resident
    {
        return $this->inTenant(fn () => Resident::factory()->create($attributes));
    }

    /** @param  array<string, mixed>  $data */
    private function checkIn(Room $room, Resident $primary, array $data = []): Lease
    {
        return $this->inTenant(fn () => app(CheckInResident::class)->handle($data + [
            'room_id' => $room->id,
            'primary_resident_id' => $primary->id,
            'start_date' => '2026-11-01',
            'duration_months' => 12,
            'deposit_amount' => 1000000,
            'deposit_received_at' => '2026-11-01',
        ]));
    }

    // Check-in

    public function test_check_in_membuat_kontrak_deposit_dan_mengisi_kamar(): void
    {
        $room = $this->room(['price' => 1850000]);
        $resident = $this->resident();

        $lease = $this->checkIn($room, $resident, ['due_day' => 5, 'checkin_notes' => 'AC dingin, cat bersih']);

        $this->inTenant(function () use ($lease, $room, $resident) {
            $lease = Lease::query()->with('deposit')->findOrFail($lease->id);

            $this->assertSame(LeaseStatus::Active, $lease->status);
            $this->assertSame($room->id, $lease->active_room_id);
            $this->assertSame($this->property->id, $lease->property_id);
            $this->assertSame($resident->id, $lease->primary_resident_id);
            $this->assertSame(1850000, $lease->rent_amount, 'harga kamar dipakai bila harga sewa tidak diisi');
            $this->assertSame('2026-11-01', $lease->start_date->toDateString());
            $this->assertSame('2027-10-31', $lease->end_date->toDateString(), 'tanggal akhir inklusif');
            $this->assertSame(5, $lease->due_day);
            $this->assertNotNull($lease->checked_in_at);
            $this->assertSame('AC dingin, cat bersih', $lease->checkin_notes);

            $this->assertSame(1000000, $lease->deposit->amount);
            $this->assertSame(DepositStatus::Held, $lease->deposit->status);
            $this->assertSame(RoomStatus::Occupied, $room->fresh()->status);
        });
    }

    public function test_deposit_yang_belum_diterima_berstatus_pending(): void
    {
        $lease = $this->checkIn($this->room(), $this->resident(), ['deposit_received_at' => null]);

        $this->inTenant(fn () => $this->assertSame(DepositStatus::Pending, LeaseDeposit::query()->where('lease_id', $lease->id)->firstOrFail()->status));
    }

    public function test_check_in_kedua_pada_kamar_yang_sama_ditolak_pesan_jelas(): void
    {
        $room = $this->room();
        $this->checkIn($room, $this->resident());

        try {
            $this->checkIn($room, $this->resident());
            $this->fail('Check-in kedua seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Kamar ini sudah terisi dan memiliki kontrak aktif.', $e->errors()['room_id'][0]);
        }

        $this->inTenant(fn () => $this->assertSame(1, Lease::query()->count()));
    }

    public function test_batasan_unik_database_menolak_dua_kontrak_aktif_di_satu_kamar(): void
    {
        $room = $this->room();
        $first = $this->resident();
        $second = $this->resident();

        $this->inTenant(function () use ($room, $first) {
            Lease::factory()->create(['property_id' => $this->property->id, 'room_id' => $room->id, 'primary_resident_id' => $first->id]);
        });

        $this->expectException(UniqueConstraintViolationException::class);

        $this->inTenant(function () use ($room, $second) {
            Lease::factory()->create(['property_id' => $this->property->id, 'room_id' => $room->id, 'primary_resident_id' => $second->id]);
        });
    }

    public function test_permintaan_hampir_bersamaan_ditolak_oleh_unique_walau_status_kamar_masih_kosong(): void
    {
        // Mensimulasikan permintaan lain yang sudah menyisipkan kontrak, tetapi status kamar belum
        // terlihat berubah: hanya batasan unik di database yang tersisa sebagai pelindung.
        $room = $this->room();
        $other = $this->resident();

        $this->inTenant(function () use ($room, $other) {
            Lease::factory()->create(['property_id' => $this->property->id, 'room_id' => $room->id, 'primary_resident_id' => $other->id]);
        });
        $this->assertSame(RoomStatus::Vacant, $room->fresh()->status);

        try {
            $this->checkIn($room, $this->resident());
            $this->fail('Seharusnya ditolak oleh batasan unik.');
        } catch (ValidationException $e) {
            $this->assertSame('Kamar ini sudah memiliki kontrak aktif.', $e->errors()['room_id'][0]);
        }

        $this->inTenant(function () use ($room) {
            $this->assertSame(1, Lease::query()->where('room_id', $room->id)->count());
            $this->assertSame(0, LeaseDeposit::query()->count(), 'transaksi yang gagal tidak meninggalkan deposit');
        });
    }

    public function test_kegagalan_membuat_deposit_membatalkan_semuanya(): void
    {
        $room = $this->room();
        $resident = $this->resident();

        try {
            // Tanggal terima yang tidak bisa diparse membuat pembuatan deposit gagal setelah kontrak dan penghuni kontrak dibuat.
            $this->checkIn($room, $resident, ['deposit_received_at' => 'bukan-tanggal']);
            $this->fail('Seharusnya melempar galat.');
        } catch (InvalidFormatException) {
            // diharapkan
        }

        $this->inTenant(function () use ($room) {
            $this->assertSame(0, Lease::query()->count());
            $this->assertSame(0, LeaseResident::query()->count());
            $this->assertSame(0, LeaseDeposit::query()->count());
            $this->assertSame(RoomStatus::Vacant, $room->fresh()->status);
        });
    }

    public function test_kamar_perawatan_tidak_bisa_dihuni(): void
    {
        $room = $this->room(['status' => RoomStatus::Maintenance]);

        $this->expectException(ValidationException::class);

        $this->checkIn($room, $this->resident());
    }

    public function test_dua_penghuni_dalam_satu_kontrak_tepat_satu_primary(): void
    {
        $primary = $this->resident();
        $partner = $this->resident();

        $lease = $this->checkIn($this->room(), $primary, ['resident_ids' => [$partner->id, $primary->id]]);

        $this->inTenant(function () use ($lease, $primary, $partner) {
            $members = LeaseResident::query()->where('lease_id', $lease->id)->get();

            $this->assertCount(2, $members);
            $this->assertSame(1, $members->where('is_primary', true)->count());
            $this->assertSame($primary->id, $members->firstWhere('is_primary', true)->resident_id);
            $this->assertSame($primary->id, Lease::query()->findOrFail($lease->id)->primary_resident_id);
            $this->assertEqualsCanonicalizing([$primary->id, $partner->id], $members->pluck('resident_id')->all());
        });
    }

    public function test_penghuni_dengan_kontrak_aktif_tidak_bisa_check_in_lagi(): void
    {
        $resident = $this->resident();
        $this->checkIn($this->room(), $resident);

        try {
            $this->checkIn($this->room(), $resident);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('primary_resident_id', $e->errors());
        }
    }

    public function test_check_in_mengaktifkan_kembali_penghuni_alumni(): void
    {
        $alumnus = $this->resident(['is_active' => false]);

        $this->checkIn($this->room(), $alumnus);

        $this->inTenant(fn () => $this->assertTrue(Resident::query()->findOrFail($alumnus->id)->is_active));
    }

    // Check-out

    public function test_check_out_mengosongkan_kamar_dan_menghitung_pengembalian_deposit(): void
    {
        $room = $this->room();
        $resident = $this->resident();
        $lease = $this->checkIn($room, $resident);

        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, [
            'ended_reason' => LeaseEndReason::MovedOut,
            'checkout_notes' => 'Dinding barat tergores paku',
            'deductions' => [
                ['label' => 'Cat ulang dinding', 'amount' => 150000],
                ['label' => 'Deep cleaning', 'amount' => 50000],
            ],
        ]));

        $this->inTenant(function () use ($lease, $room, $resident) {
            $lease = Lease::query()->with('deposit')->findOrFail($lease->id);

            $this->assertSame(LeaseStatus::Ended, $lease->status);
            $this->assertNull($lease->active_room_id);
            $this->assertNotNull($lease->checked_out_at);
            $this->assertSame(LeaseEndReason::MovedOut, $lease->ended_reason);
            $this->assertSame('Dinding barat tergores paku', $lease->checkout_notes);
            $this->assertSame(RoomStatus::Vacant, $room->fresh()->status);

            $deposit = $lease->deposit;
            $this->assertSame(1000000, $deposit->amount);
            $this->assertSame(200000, $deposit->deduction_amount);
            $this->assertSame(800000, $deposit->refund_amount, 'pengembalian = deposit dikurangi potongan');
            $this->assertSame(DepositStatus::PartiallyRefunded, $deposit->status);
            $this->assertSame('2026-11-09', $deposit->refunded_at->toDateString());
            $this->assertStringContainsString('Cat ulang dinding', $deposit->deduction_note);

            $this->assertFalse(Resident::query()->findOrFail($resident->id)->is_active, 'penghuni tanpa kontrak aktif menjadi alumni');
        });
    }

    public function test_check_out_tanpa_potongan_mengembalikan_deposit_penuh(): void
    {
        $lease = $this->checkIn($this->room(), $this->resident());

        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, []));

        $this->inTenant(function () use ($lease) {
            $deposit = LeaseDeposit::query()->where('lease_id', $lease->id)->firstOrFail();

            $this->assertSame(1000000, $deposit->refund_amount);
            $this->assertSame(0, $deposit->deduction_amount);
            $this->assertSame(DepositStatus::Refunded, $deposit->status);
        });
    }

    public function test_potongan_sama_dengan_deposit_membuat_deposit_hangus(): void
    {
        $lease = $this->checkIn($this->room(), $this->resident());

        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, ['deductions' => [['label' => 'Kerusakan', 'amount' => 1000000]]]));

        $this->inTenant(function () use ($lease) {
            $deposit = LeaseDeposit::query()->where('lease_id', $lease->id)->firstOrFail();

            $this->assertSame(0, $deposit->refund_amount);
            $this->assertSame(DepositStatus::Forfeited, $deposit->status);
        });
    }

    public function test_potongan_melebihi_deposit_ditolak_dan_tidak_menutup_kontrak(): void
    {
        $room = $this->room();
        $lease = $this->checkIn($room, $this->resident());

        try {
            $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, ['deductions' => [['label' => 'Kerusakan', 'amount' => 1000001]]]));
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Total potongan melebihi jumlah deposit.', $e->errors()['deductions'][0]);
        }

        $this->inTenant(function () use ($lease, $room) {
            $this->assertSame(LeaseStatus::Active, Lease::query()->findOrFail($lease->id)->status);
            $this->assertSame(RoomStatus::Occupied, $room->fresh()->status);
            $this->assertSame(DepositStatus::Held, LeaseDeposit::query()->where('lease_id', $lease->id)->firstOrFail()->status);
        });
    }

    public function test_sisa_tagihan_dari_outstanding_balance_ikut_memotong_deposit(): void
    {
        $this->app->bind(OutstandingBalance::class, fn () => new class implements OutstandingBalance
        {
            public function forLease(Lease $lease): int
            {
                return 300000;
            }
        });

        $lease = $this->checkIn($this->room(), $this->resident());

        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, [
            'deductions' => [['label' => 'Cat', 'amount' => 200000]],
            'deduct_outstanding' => true,
        ]));

        $this->inTenant(function () use ($lease) {
            $deposit = LeaseDeposit::query()->where('lease_id', $lease->id)->firstOrFail();

            $this->assertSame(500000, $deposit->deduction_amount);
            $this->assertSame(500000, $deposit->refund_amount);
        });
    }

    public function test_implementasi_sementara_outstanding_balance_mengembalikan_nol(): void
    {
        $this->assertInstanceOf(ZeroOutstandingBalance::class, app(OutstandingBalance::class));

        $lease = $this->checkIn($this->room(), $this->resident());

        $this->assertSame(0, app(OutstandingBalance::class)->forLease($lease));
    }

    public function test_deposit_belum_diterima_tidak_bisa_dipotong(): void
    {
        $lease = $this->checkIn($this->room(), $this->resident(), ['deposit_received_at' => null]);

        $this->expectException(ValidationException::class);

        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, ['deductions' => [['label' => 'Cat', 'amount' => 1000]]]));
    }

    public function test_check_out_ke_status_perawatan(): void
    {
        $room = $this->room();
        $lease = $this->checkIn($room, $this->resident());

        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, ['room_status' => RoomStatus::Maintenance]));

        $this->assertSame(RoomStatus::Maintenance, $room->fresh()->status);
    }

    public function test_check_out_dua_kali_ditolak(): void
    {
        $lease = $this->checkIn($this->room(), $this->resident());
        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, []));

        $this->expectException(ValidationException::class);

        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, []));
    }

    public function test_kamar_bisa_dihuni_lagi_setelah_check_out(): void
    {
        $room = $this->room();
        $lease = $this->checkIn($room, $this->resident());
        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, []));

        $next = $this->checkIn($room, $this->resident());

        $this->inTenant(fn () => $this->assertSame($room->id, Lease::query()->findOrFail($next->id)->active_room_id));
    }

    public function test_penghuni_dengan_kontrak_aktif_lain_tetap_aktif_setelah_satu_kontrak_ditutup(): void
    {
        $shared = $this->resident();
        $this->checkIn($this->room(), $this->resident(), ['resident_ids' => [$shared->id]]);

        // Penghuni berbagi kontrak lain: tidak boleh ada dua kontrak aktif untuk orang yang sama.
        $this->expectException(ValidationException::class);

        $this->checkIn($this->room(), $shared);
    }

    // Perpanjangan

    public function test_perpanjangan_menyambung_tanggal_dan_menyimpan_rantai(): void
    {
        $room = $this->room();
        $primary = $this->resident();
        $partner = $this->resident();
        $old = $this->checkIn($room, $primary, ['resident_ids' => [$partner->id], 'due_day' => 7]);

        $new = $this->inTenant(fn () => app(RenewLease::class)->handle($old, ['duration_months' => 6]));

        $this->inTenant(function () use ($old, $new, $room, $primary) {
            $old = Lease::query()->findOrFail($old->id);
            $new = Lease::query()->with('deposit')->findOrFail($new->id);

            $this->assertSame('2027-10-31', $old->end_date->toDateString());
            $this->assertSame('2027-11-01', $new->start_date->toDateString(), 'mulai sehari setelah akhir kontrak lama');
            $this->assertSame('2028-04-30', $new->end_date->toDateString());
            $this->assertSame($old->id, $new->renewed_from_lease_id);

            $this->assertSame(LeaseStatus::Ended, $old->status);
            $this->assertNull($old->active_room_id);
            $this->assertSame(LeaseEndReason::Expired, $old->ended_reason);

            $this->assertSame(LeaseStatus::Active, $new->status);
            $this->assertSame($room->id, $new->active_room_id);
            $this->assertSame($primary->id, $new->primary_resident_id);
            $this->assertSame(7, $new->due_day);
            $this->assertSame($old->rent_amount, $new->rent_amount, 'harga lama diwarisi');
            $this->assertSame(RoomStatus::Occupied, $room->fresh()->status);

            $members = LeaseResident::query()->where('lease_id', $new->id)->get();
            $this->assertCount(2, $members);
            $this->assertSame(1, $members->where('is_primary', true)->count());

            $this->assertSame(1000000, $new->deposit->amount, 'deposit ikut pindah ke kontrak baru');
            $this->assertNull(LeaseDeposit::query()->where('lease_id', $old->id)->first());
            $this->assertSame(1, Lease::query()->active()->where('room_id', $room->id)->count());
        });
    }

    public function test_perpanjangan_boleh_dengan_harga_baru_dan_rantai_berlapis(): void
    {
        $first = $this->checkIn($this->room(), $this->resident());
        $second = $this->inTenant(fn () => app(RenewLease::class)->handle($first, ['duration_months' => 12, 'rent_amount' => 1750000]));
        $third = $this->inTenant(fn () => app(RenewLease::class)->handle($second, ['duration_months' => 12]));

        $this->inTenant(function () use ($first, $second, $third) {
            $this->assertSame(1750000, Lease::query()->findOrFail($second->id)->rent_amount);
            $this->assertSame(1750000, Lease::query()->findOrFail($third->id)->rent_amount);
            $this->assertSame($second->id, Lease::query()->findOrFail($third->id)->renewed_from_lease_id);
            $this->assertSame($first->id, Lease::query()->findOrFail($second->id)->renewed_from_lease_id);
        });
    }

    public function test_perpanjangan_kontrak_berakhir_atau_tanpa_tanggal_akhir_ditolak(): void
    {
        $open = $this->checkIn($this->room(), $this->resident(), ['duration_months' => null]);

        try {
            $this->inTenant(fn () => app(RenewLease::class)->handle($open, []));
            $this->fail('Kontrak tanpa tanggal akhir tidak bisa diperpanjang.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lease', $e->errors());
        }

        $closed = $this->checkIn($this->room(), $this->resident());
        $this->inTenant(fn () => app(CheckOutResident::class)->handle($closed, []));

        $this->expectException(ValidationException::class);

        $this->inTenant(fn () => app(RenewLease::class)->handle($closed, []));
    }

    // Pindah kamar

    public function test_pindah_kamar_menyimpan_riwayat_dan_memakai_harga_baru(): void
    {
        $from = $this->room(['price' => 1500000]);
        $to = $this->room(['price' => 2000000]);
        $lease = $this->checkIn($from, $this->resident());

        $this->inTenant(fn () => app(TransferRoom::class)->handle($lease, [
            'to_room_id' => $to->id,
            'transferred_on' => '2026-11-20',
            'new_rent_amount' => 2000000,
            'reason' => 'Pindah ke kamar lebih besar',
        ], $this->owner));

        $this->inTenant(function () use ($lease, $from, $to) {
            $lease = Lease::query()->findOrFail($lease->id);

            $this->assertSame($to->id, $lease->room_id);
            $this->assertSame($to->id, $lease->active_room_id);
            $this->assertSame(2000000, $lease->rent_amount, 'harga kamar baru dipakai kontrak');
            $this->assertSame(RoomStatus::Vacant, $from->fresh()->status);
            $this->assertSame(RoomStatus::Occupied, $to->fresh()->status);

            $transfer = RoomTransfer::query()->where('lease_id', $lease->id)->firstOrFail();
            $this->assertSame($from->id, $transfer->from_room_id);
            $this->assertSame($to->id, $transfer->to_room_id);
            $this->assertSame('2026-11-20', $transfer->transferred_on->toDateString());
            $this->assertSame(2000000, $transfer->new_rent_amount);
            $this->assertSame($this->owner->id, $transfer->created_by);
        });
    }

    public function test_pindah_kamar_tanpa_harga_baru_mempertahankan_harga(): void
    {
        $lease = $this->checkIn($this->room(['price' => 1500000]), $this->resident(), ['rent_amount' => 1400000]);
        $to = $this->room(['price' => 2000000]);

        $this->inTenant(fn () => app(TransferRoom::class)->handle($lease, ['to_room_id' => $to->id], $this->owner));

        $this->inTenant(fn () => $this->assertSame(1400000, Lease::query()->findOrFail($lease->id)->rent_amount));
    }

    public function test_pindah_ke_kamar_terisi_atau_sama_ditolak(): void
    {
        $roomA = $this->room();
        $roomB = $this->room();
        $leaseA = $this->checkIn($roomA, $this->resident());
        $this->checkIn($roomB, $this->resident());

        try {
            $this->inTenant(fn () => app(TransferRoom::class)->handle($leaseA, ['to_room_id' => $roomB->id], $this->owner));
            $this->fail('Kamar tujuan terisi.');
        } catch (ValidationException $e) {
            $this->assertSame('Kamar tujuan tidak kosong.', $e->errors()['to_room_id'][0]);
        }

        try {
            $this->inTenant(fn () => app(TransferRoom::class)->handle($leaseA, ['to_room_id' => $roomA->id], $this->owner));
            $this->fail('Kamar sama.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to_room_id', $e->errors());
        }

        $this->inTenant(function () use ($leaseA, $roomA) {
            $this->assertSame($roomA->id, Lease::query()->findOrFail($leaseA->id)->room_id);
            $this->assertSame(0, RoomTransfer::query()->count());
        });
    }

    // Pengingat dan lewat masa sewa

    public function test_expiring_within_benar_di_batas_30_14_dan_7_hari(): void
    {
        $today = Carbon::today();
        $ends = [
            'hari ini' => $today->copy(),
            'tujuh' => $today->copy()->addDays(7),
            'delapan' => $today->copy()->addDays(8),
            'empat belas' => $today->copy()->addDays(14),
            'lima belas' => $today->copy()->addDays(15),
            'tiga puluh' => $today->copy()->addDays(30),
            'tiga puluh satu' => $today->copy()->addDays(31),
            'kemarin' => $today->copy()->subDay(),
        ];

        $ids = [];

        foreach ($ends as $name => $end) {
            $ids[$name] = $this->inTenant(fn () => Lease::factory()->create([
                'property_id' => $this->property->id,
                'room_id' => $this->room()->id,
                'primary_resident_id' => $this->resident()->id,
                'end_date' => $end->toDateString(),
            ]))->id;
        }

        // Kontrak tanpa tanggal akhir dan kontrak yang sudah berakhir tidak pernah masuk.
        $this->inTenant(fn () => Lease::factory()->create([
            'property_id' => $this->property->id, 'room_id' => $this->room()->id, 'primary_resident_id' => $this->resident()->id, 'end_date' => null,
        ]));
        $this->inTenant(fn () => Lease::factory()->ended()->create([
            'property_id' => $this->property->id, 'room_id' => $this->room()->id, 'primary_resident_id' => $this->resident()->id,
            'end_date' => $today->copy()->addDays(3)->toDateString(),
        ]));

        $within = fn (int $days) => $this->inTenant(fn () => Lease::query()->expiringWithin($days)->pluck('id')->all());

        $this->assertEqualsCanonicalizing([$ids['hari ini'], $ids['tujuh']], $within(7));
        $this->assertEqualsCanonicalizing([$ids['hari ini'], $ids['tujuh'], $ids['delapan'], $ids['empat belas']], $within(14));
        $this->assertEqualsCanonicalizing(
            [$ids['hari ini'], $ids['tujuh'], $ids['delapan'], $ids['empat belas'], $ids['lima belas'], $ids['tiga puluh']],
            $within(30),
        );
    }

    public function test_kontrak_lewat_end_date_tanpa_check_out_ditandai_lewat_masa_sewa(): void
    {
        $make = fn (?string $end, array $extra = []) => $this->inTenant(fn () => Lease::factory()->create($extra + [
            'property_id' => $this->property->id,
            'room_id' => $this->room()->id,
            'primary_resident_id' => $this->resident()->id,
            'end_date' => $end,
        ]));

        $overdue = $make('2026-11-08');
        $today = $make('2026-11-09');
        $open = $make(null);
        $ended = $make('2026-11-01', ['status' => LeaseStatus::Ended, 'active_room_id' => null]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($today->isOverdue(), 'berakhir hari ini belum lewat');
        $this->assertFalse($open->isOverdue());
        $this->assertFalse($ended->isOverdue(), 'sudah check-out');
        $this->assertSame(-1, $overdue->daysRemaining());
        $this->assertNull($open->daysRemaining());

        $this->inTenant(fn () => $this->assertSame([$overdue->id], Lease::query()->overdue()->pluck('id')->all()));
    }
}
