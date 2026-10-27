<?php

namespace App\Actions\Leases;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferRoom
{
    use ResolvesTenant;

    /**
     * Pindah kamar di tengah kontrak: kontrak berpindah ke kamar tujuan (room_id, active_room_id, property_id),
     * kamar lama kosong, kamar baru terisi, dan riwayat tersimpan di room_transfers. Harga baru (opsional)
     * berlaku untuk tagihan berikutnya.
     *
     * @param  array<string, mixed>  $data  to_room_id, transferred_on, [new_rent_amount, reason]
     */
    public function __invoke(Lease $lease, array $data, User $actor): Lease
    {
        $tenant = $this->currentTenant();
        $today = $tenant->today();

        $on = is_string($data['transferred_on'] ?? null)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $data['transferred_on'], $tenant->timezone)
            : false;

        if ($on === false) {
            throw ValidationException::withMessages(['transferred_on' => 'Format tanggal harus YYYY-MM-DD.']);
        }

        $on = $on->startOfDay();

        if ($on->gt($today)) {
            throw ValidationException::withMessages(['transferred_on' => 'Tanggal pindah tidak boleh di masa depan.']);
        }

        $newRent = $data['new_rent_amount'] ?? null;

        if ($newRent !== null && (! is_int($newRent) || $newRent < 1 || $newRent > CheckInResident::MAX_RENT)) {
            throw ValidationException::withMessages(['new_rent_amount' => 'Harga sewa baru harus rupiah bulat antara 1 dan 100.000.000.']);
        }

        try {
            return DB::transaction(function () use ($lease, $data, $on, $newRent, $actor) {
                $lease = Lease::query()->lockForUpdate()->findOrFail($lease->getKey());

                if ($lease->status !== LeaseStatus::Active) {
                    throw ValidationException::withMessages(['general' => 'Hanya kontrak aktif yang bisa dipindahkan.']);
                }

                if ($on->lt($lease->start_date)) {
                    throw ValidationException::withMessages(['transferred_on' => 'Tanggal pindah tidak boleh sebelum tanggal mulai kontrak.']);
                }

                $fromId = $lease->room_id;
                $toId = (int) $data['to_room_id'];

                if ($fromId === $toId) {
                    throw ValidationException::withMessages(['to_room_id' => 'Pilih kamar tujuan yang berbeda.']);
                }

                // Kunci kedua kamar dengan urutan id tetap supaya dua pemindahan bersamaan tidak saling menunggu.
                $rooms = Room::query()->whereIn('id', [$fromId, $toId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $from = $rooms->get($fromId);
                $to = $rooms->get($toId);

                if ($to === null) {
                    throw ValidationException::withMessages(['to_room_id' => 'Kamar tujuan tidak ditemukan.']);
                }

                if ($to->status !== RoomStatus::Vacant || Lease::query()->where('active_room_id', $to->getKey())->exists()) {
                    throw ValidationException::withMessages(['to_room_id' => 'Kamar tujuan tidak kosong.']);
                }

                $lease->update([
                    'room_id' => $to->getKey(),
                    'active_room_id' => $to->getKey(),
                    'property_id' => $to->property_id,
                    'rent_amount' => $newRent ?? $lease->rent_amount,
                ]);

                $from->update(['status' => RoomStatus::Vacant]);
                $to->update(['status' => RoomStatus::Occupied]);

                RoomTransfer::create([
                    'lease_id' => $lease->getKey(),
                    'from_room_id' => $fromId,
                    'to_room_id' => $to->getKey(),
                    'transferred_on' => $on->toDateString(),
                    'new_rent_amount' => $newRent,
                    'reason' => $data['reason'] ?? null,
                    'created_by' => $actor->getKey(),
                ]);

                return $lease;
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'active_room_id')) {
                throw ValidationException::withMessages(['to_room_id' => 'Kamar tujuan baru saja terisi.']);
            }

            throw $e;
        }
    }
}
