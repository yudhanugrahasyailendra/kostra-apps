<?php

namespace App\Actions;

use App\Enums\LeaseEndReason;
use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RenewLease
{
    /**
     * Perpanjang: kontrak lama ditutup (berakhir karena masa sewa habis) dan kontrak baru
     * menyambung sehari setelah tanggal akhir lama, dengan penghuni, kamar, jatuh tempo, dan
     * deposit yang sama. Deposit dipindah ke kontrak baru karena itu satu kewajiban yang sama.
     * Kamar tetap terisi.
     *
     * Kunci `data`: duration_months (null = tanpa tanggal akhir), rent_amount (null = harga lama), notes.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Lease $lease, array $data = []): Lease
    {
        try {
            return DB::transaction(fn () => $this->renew($lease, $data));
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'active_room_id')) {
                throw ValidationException::withMessages(['lease' => 'Kamar ini sudah memiliki kontrak aktif lain.']);
            }

            throw $e;
        }
    }

    /** @param  array<string, mixed>  $data */
    private function renew(Lease $lease, array $data): Lease
    {
        $old = Lease::query()->lockForUpdate()->findOrFail($lease->id);

        if ($old->status !== LeaseStatus::Active) {
            throw ValidationException::withMessages(['lease' => 'Hanya kontrak aktif yang bisa diperpanjang.']);
        }

        if ($old->end_date === null) {
            throw ValidationException::withMessages(['lease' => 'Kontrak tanpa tanggal akhir tidak perlu diperpanjang.']);
        }

        // Harus dikosongkan lebih dulu: active_room_id unik, jadi dua kontrak aktif tidak boleh ada bersamaan.
        $old->update(['status' => LeaseStatus::Ended, 'active_room_id' => null, 'ended_reason' => LeaseEndReason::Expired]);

        $start = $old->end_date->copy()->addDay();
        $months = isset($data['duration_months']) ? (int) $data['duration_months'] : null;
        $notes = trim((string) ($data['notes'] ?? ''));

        $new = Lease::create([
            'property_id' => $old->property_id,
            'room_id' => $old->room_id,
            'primary_resident_id' => $old->primary_resident_id,
            'status' => LeaseStatus::Active,
            'active_room_id' => $old->room_id,
            'start_date' => $start,
            'end_date' => $months ? $start->copy()->addMonthsNoOverflow($months)->subDay() : null,
            'billing_cycle' => $old->billing_cycle,
            'rent_amount' => (int) ($data['rent_amount'] ?? $old->rent_amount),
            'due_day' => $old->due_day,
            'renewed_from_lease_id' => $old->id,
            'notes' => $notes === '' ? null : $notes,
        ]);

        $old->leaseResidents()->get()->each(fn (LeaseResident $member) => LeaseResident::create([
            'lease_id' => $new->id,
            'resident_id' => $member->resident_id,
            'is_primary' => $member->is_primary,
        ]));

        LeaseDeposit::query()->where('lease_id', $old->id)->lockForUpdate()->first()?->update(['lease_id' => $new->id]);

        return $new;
    }
}
