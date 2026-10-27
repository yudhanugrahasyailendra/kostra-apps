<?php

namespace App\Actions\Leases;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\BillingCycle;
use App\Enums\DepositStatus;
use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Models\Resident;
use App\Models\Room;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInResident
{
    use ResolvesTenant;

    public const MAX_RESIDENTS = 6;

    public const MAX_RENT = 100_000_000;

    public const MAX_DEPOSIT = 1_000_000_000;

    /**
     * Check-in: dalam satu transaksi membuat kontrak, daftar penghuni, deposit, mengisi
     * active_room_id, dan mengubah kamar menjadi terisi. Satu kontrak aktif per kamar dijaga
     * pemeriksaan di sini dan, sebagai pagar terakhir, unique(active_room_id) di database.
     *
     * @param  array<string, mixed>  $data  room_id, primary_resident_id, [other_resident_ids, start_date, end_date,
     *                                      rent_amount, due_day, deposit_amount, deposit_received_on, checkin_notes, notes]
     */
    public function __invoke(array $data): Lease
    {
        $tenant = $this->currentTenant();
        $today = $tenant->today();

        $start = $this->date($data['start_date'] ?? $today->toDateString(), $tenant->timezone, 'start_date');
        $end = isset($data['end_date']) && $data['end_date'] !== '' ? $this->date($data['end_date'], $tenant->timezone, 'end_date') : null;

        if ($start->gt($today)) {
            throw ValidationException::withMessages(['start_date' => 'Tanggal mulai tidak boleh di masa depan. Booking untuk tanggal mendatang belum tersedia.']);
        }

        if ($start->lt($today->subYears(5))) {
            throw ValidationException::withMessages(['start_date' => 'Tanggal mulai tidak boleh lebih dari 5 tahun yang lalu.']);
        }

        if ($end !== null && $end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.']);
        }

        $deposit = (int) ($data['deposit_amount'] ?? 0);

        if ($deposit < 0 || $deposit > self::MAX_DEPOSIT) {
            throw ValidationException::withMessages(['deposit_amount' => 'Nominal deposit tidak valid.']);
        }

        $receivedOn = isset($data['deposit_received_on']) && $data['deposit_received_on'] !== ''
            ? $this->date($data['deposit_received_on'], $tenant->timezone, 'deposit_received_on')
            : null;

        if ($receivedOn?->gt($today)) {
            throw ValidationException::withMessages(['deposit_received_on' => 'Tanggal deposit diterima tidak boleh di masa depan.']);
        }

        $residentIds = array_values(array_unique(array_map('intval', [
            $data['primary_resident_id'],
            ...($data['other_resident_ids'] ?? []),
        ])));

        if (count($residentIds) > self::MAX_RESIDENTS) {
            throw ValidationException::withMessages(['other_resident_ids' => 'Maksimal '.self::MAX_RESIDENTS.' penghuni per kontrak.']);
        }

        try {
            return DB::transaction(function () use ($data, $start, $end, $deposit, $receivedOn, $residentIds) {
                $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);

                if ($room->status === RoomStatus::Maintenance) {
                    throw ValidationException::withMessages(['room_id' => 'Kamar sedang dalam perawatan.']);
                }

                if ($room->status !== RoomStatus::Vacant || Lease::query()->where('active_room_id', $room->getKey())->exists()) {
                    throw ValidationException::withMessages(['room_id' => 'Kamar ini sudah terisi oleh kontrak aktif.']);
                }

                $residents = Resident::query()->whereIn('id', $residentIds)->get();

                if ($residents->count() !== count($residentIds)) {
                    throw ValidationException::withMessages(['primary_resident_id' => 'Penghuni tidak ditemukan.']);
                }

                $rent = $data['rent_amount'] ?? $room->price;

                if (! is_int($rent) || $rent < 1 || $rent > self::MAX_RENT) {
                    throw ValidationException::withMessages(['rent_amount' => 'Harga sewa harus rupiah bulat antara 1 dan 100.000.000.']);
                }

                $lease = Lease::create([
                    'property_id' => $room->property_id,
                    'room_id' => $room->getKey(),
                    'primary_resident_id' => (int) $data['primary_resident_id'],
                    'status' => LeaseStatus::Active,
                    'active_room_id' => $room->getKey(),
                    'start_date' => $start->toDateString(),
                    'end_date' => $end?->toDateString(),
                    'billing_cycle' => BillingCycle::Monthly,
                    'rent_amount' => $rent,
                    'due_day' => $data['due_day'] ?? null,
                    'checked_in_at' => $start->utc(),
                    'checkin_notes' => $data['checkin_notes'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($residentIds as $residentId) {
                    LeaseResident::create([
                        'lease_id' => $lease->getKey(),
                        'resident_id' => $residentId,
                        'is_primary' => $residentId === (int) $data['primary_resident_id'],
                    ]);
                }

                LeaseDeposit::create([
                    'lease_id' => $lease->getKey(),
                    'amount' => $deposit,
                    'received_at' => $receivedOn?->toDateString(),
                    'status' => $receivedOn === null ? DepositStatus::Pending : DepositStatus::Held,
                ]);

                $room->update(['status' => RoomStatus::Occupied]);

                foreach ($residents as $resident) {
                    if (! $resident->is_active) {
                        $resident->update(['is_active' => true]);
                    }
                }

                return $lease;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Dua check-in nyaris bersamaan: yang kalah ditolak batasan unik active_room_id.
            if (str_contains($e->getMessage(), 'active_room_id')) {
                throw ValidationException::withMessages(['room_id' => 'Kamar ini baru saja terisi oleh kontrak lain.']);
            }

            throw $e;
        }
    }

    private function date(mixed $value, string $timezone, string $field): CarbonImmutable
    {
        $parsed = Dates::date($value, $timezone);

        if ($parsed === null) {
            throw ValidationException::withMessages([$field => 'Format tanggal harus YYYY-MM-DD.']);
        }

        return $parsed;
    }
}
