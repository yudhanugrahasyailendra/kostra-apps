<?php

namespace App\Actions;

use App\Enums\BillingCycle;
use App\Enums\DepositStatus;
use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Models\Resident;
use App\Models\Room;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInResident
{
    /**
     * Check-in: dalam satu transaksi membuat kontrak, penghuni kontrak (satu penanggung jawab),
     * deposit, dan mengubah kamar menjadi terisi. Bila dua permintaan memilih kamar yang sama
     * hampir bersamaan, unique `leases.active_room_id` menolak yang kedua.
     *
     * Kunci `data`: room_id, primary_resident_id, resident_ids (penghuni tambahan), start_date,
     * duration_months (null = tanpa tanggal akhir), rent_amount (null = harga kamar), due_day,
     * deposit_amount, deposit_received_at, checkin_notes, notes.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Lease
    {
        try {
            return DB::transaction(fn () => $this->create($data));
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'active_room_id')) {
                throw ValidationException::withMessages(['room_id' => 'Kamar ini sudah memiliki kontrak aktif.']);
            }

            throw $e;
        }
    }

    /** @param  array<string, mixed>  $data */
    private function create(array $data): Lease
    {
        $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);

        if ($room->status === RoomStatus::Occupied) {
            throw ValidationException::withMessages(['room_id' => 'Kamar ini sudah terisi dan memiliki kontrak aktif.']);
        }

        if ($room->status === RoomStatus::Maintenance) {
            throw ValidationException::withMessages(['room_id' => 'Kamar sedang dalam perawatan. Ubah status menjadi kosong dulu.']);
        }

        $primaryId = (int) $data['primary_resident_id'];
        $residentIds = collect([$primaryId, ...($data['resident_ids'] ?? [])])->map(fn ($id) => (int) $id)->unique()->values();

        // Scope tenant aktif: id penghuni milik tenant lain tidak ditemukan.
        $residents = Resident::query()->whereIn('id', $residentIds)->get()->keyBy('id');

        if ($residents->count() !== $residentIds->count()) {
            throw ValidationException::withMessages(['primary_resident_id' => 'Penghuni tidak ditemukan.']);
        }

        $busy = LeaseResident::query()
            ->whereIn('resident_id', $residentIds)
            ->whereHas('lease', fn ($q) => $q->active())
            ->pluck('resident_id');

        if ($busy->isNotEmpty()) {
            $names = $busy->map(fn ($id) => $residents[$id]->name)->implode(', ');

            throw ValidationException::withMessages(['primary_resident_id' => "Sudah memiliki kontrak aktif: {$names}."]);
        }

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $months = isset($data['duration_months']) ? (int) $data['duration_months'] : null;
        $depositAmount = (int) ($data['deposit_amount'] ?? 0);
        $receivedAt = $data['deposit_received_at'] ?? null;

        $lease = Lease::create([
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'primary_resident_id' => $primaryId,
            'status' => LeaseStatus::Active,
            'active_room_id' => $room->id,
            'start_date' => $start,
            // Tanggal akhir inklusif: sewa 12 bulan dari 1 Jan berakhir 31 Des.
            'end_date' => $months ? $start->copy()->addMonthsNoOverflow($months)->subDay() : null,
            'billing_cycle' => BillingCycle::Monthly,
            'rent_amount' => (int) ($data['rent_amount'] ?? $room->price),
            'due_day' => $data['due_day'] ?? null,
            'checked_in_at' => now(),
            'checkin_notes' => $this->text($data['checkin_notes'] ?? null),
            'notes' => $this->text($data['notes'] ?? null),
        ]);

        foreach ($residentIds as $id) {
            LeaseResident::create(['lease_id' => $lease->id, 'resident_id' => $id, 'is_primary' => $id === $primaryId]);
        }

        LeaseDeposit::create([
            'lease_id' => $lease->id,
            'amount' => $depositAmount,
            'received_at' => $receivedAt,
            'status' => $receivedAt ? DepositStatus::Held : DepositStatus::Pending,
        ]);

        $room->update(['status' => RoomStatus::Occupied]);

        // Orang yang masuk kos adalah penghuni aktif (daftar alumni berisi yang sudah keluar).
        Resident::query()->whereIn('id', $residentIds)->where('is_active', false)->update(['is_active' => true]);

        return $lease;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
