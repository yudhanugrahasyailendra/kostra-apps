<?php

namespace App\Actions\Leases;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\LeaseEndReason;
use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Support\Dates;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RenewLease
{
    use ResolvesTenant;

    /**
     * Perpanjangan: kontrak lama ditutup dan kontrak baru dibuat dalam satu transaksi, sehingga kamar
     * tidak pernah kosong di tengah. Tanggal mulai baru menyambung (akhir lama + 1 hari); harga,
     * penghuni, dan jatuh tempo diwarisi; deposit dipindahkan ke kontrak baru.
     *
     * @param  array<string, mixed>  $data  end_date, [notes]
     */
    public function __invoke(Lease $lease, array $data): Lease
    {
        $tenant = $this->currentTenant();

        $newEnd = Dates::date($data['end_date'] ?? null, $tenant->timezone);

        if ($newEnd === null) {
            throw ValidationException::withMessages(['end_date' => 'Format tanggal harus YYYY-MM-DD.']);
        }

        try {
            return DB::transaction(function () use ($lease, $data, $newEnd) {
                $old = Lease::query()->lockForUpdate()->findOrFail($lease->getKey());

                if ($old->status !== LeaseStatus::Active) {
                    throw ValidationException::withMessages(['general' => 'Hanya kontrak aktif yang bisa diperpanjang.']);
                }

                if ($old->end_date === null) {
                    throw ValidationException::withMessages(['general' => 'Kontrak tanpa tanggal akhir tidak perlu diperpanjang.']);
                }

                $newStart = $old->end_date->toImmutable()->addDay()->startOfDay();

                if ($newEnd->startOfDay()->lt($newStart)) {
                    throw ValidationException::withMessages(['end_date' => 'Tanggal akhir baru harus setelah akhir kontrak lama.']);
                }

                $roomId = $old->room_id;

                // Lepas kamar dari kontrak lama lebih dulu supaya unique(active_room_id) bisa dipakai kontrak baru.
                $old->update([
                    'status' => LeaseStatus::Ended,
                    'active_room_id' => null,
                    'ended_reason' => LeaseEndReason::Expired,
                ]);

                $new = Lease::create([
                    'property_id' => $old->property_id,
                    'room_id' => $roomId,
                    'primary_resident_id' => $old->primary_resident_id,
                    'status' => LeaseStatus::Active,
                    'active_room_id' => $roomId,
                    'start_date' => $newStart->toDateString(),
                    'end_date' => $newEnd->toDateString(),
                    'billing_cycle' => $old->billing_cycle,
                    'rent_amount' => $old->rent_amount,
                    'due_day' => $old->due_day,
                    'renewed_from_lease_id' => $old->getKey(),
                    'checked_in_at' => $old->checked_in_at,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach (LeaseResident::query()->where('lease_id', $old->getKey())->get() as $member) {
                    LeaseResident::create([
                        'lease_id' => $new->getKey(),
                        'resident_id' => $member->resident_id,
                        'is_primary' => $member->is_primary,
                    ]);
                }

                // Satu deposit per kontrak: deposit ikut pindah ke kontrak baru (tercatat di audit).
                LeaseDeposit::query()->where('lease_id', $old->getKey())->firstOrFail()->update(['lease_id' => $new->getKey()]);

                return $new;
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'active_room_id')) {
                throw ValidationException::withMessages(['general' => 'Kamar ini baru saja diubah oleh proses lain. Muat ulang halaman.']);
            }

            throw $e;
        }
    }
}
