<?php

namespace App\Actions;

use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferRoom
{
    /**
     * Pindah kamar di tengah kontrak: catat room_transfers, perbarui room_id dan active_room_id,
     * kamar lama kosong, kamar baru terisi. Harga baru (bila ada) berlaku untuk tagihan berikutnya.
     *
     * Kunci `data`: to_room_id, transferred_on, new_rent_amount (null = harga tetap), reason.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Lease $lease, array $data, User $by): RoomTransfer
    {
        try {
            return DB::transaction(fn () => $this->transfer($lease, $data, $by));
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'active_room_id')) {
                throw ValidationException::withMessages(['to_room_id' => 'Kamar tujuan sudah memiliki kontrak aktif.']);
            }

            throw $e;
        }
    }

    /** @param  array<string, mixed>  $data */
    private function transfer(Lease $lease, array $data, User $by): RoomTransfer
    {
        $lease = Lease::query()->lockForUpdate()->findOrFail($lease->id);

        if ($lease->status !== LeaseStatus::Active) {
            throw ValidationException::withMessages(['lease' => 'Hanya kontrak aktif yang bisa pindah kamar.']);
        }

        $toId = (int) $data['to_room_id'];

        if ($toId === $lease->room_id) {
            throw ValidationException::withMessages(['to_room_id' => 'Pilih kamar yang berbeda dari kamar sekarang.']);
        }

        // Kunci berurutan menurut id agar dua pemindahan silang tidak saling menunggu (deadlock).
        $rooms = Room::query()->whereIn('id', [$lease->room_id, $toId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $from = $rooms[$lease->room_id];
        $to = $rooms[$toId] ?? throw ValidationException::withMessages(['to_room_id' => 'Kamar tujuan tidak ditemukan.']);

        if ($to->status !== RoomStatus::Vacant) {
            throw ValidationException::withMessages(['to_room_id' => 'Kamar tujuan tidak kosong.']);
        }

        $newRent = isset($data['new_rent_amount']) ? (int) $data['new_rent_amount'] : null;
        $reason = trim((string) ($data['reason'] ?? ''));

        $transfer = RoomTransfer::create([
            'lease_id' => $lease->id,
            'from_room_id' => $from->id,
            'to_room_id' => $to->id,
            'transferred_on' => $data['transferred_on'] ?? now()->toDateString(),
            'new_rent_amount' => $newRent,
            'reason' => $reason === '' ? null : $reason,
            'created_by' => $by->id,
        ]);

        $lease->update([
            'room_id' => $to->id,
            'property_id' => $to->property_id,
            'active_room_id' => $to->id,
            'rent_amount' => $newRent ?? $lease->rent_amount,
        ]);

        $from->update(['status' => RoomStatus::Vacant]);
        $to->update(['status' => RoomStatus::Occupied]);

        return $transfer;
    }
}
