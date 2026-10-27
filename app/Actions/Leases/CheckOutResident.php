<?php

namespace App\Actions\Leases;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\DepositStatus;
use App\Enums\LeaseEndReason;
use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Models\PortalToken;
use App\Models\Resident;
use App\Models\Room;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckOutResident
{
    use ResolvesTenant;

    /**
     * Check-out: menutup kontrak, mengosongkan active_room_id, mengembalikan kamar ke kosong,
     * menyelesaikan deposit (pengembalian = deposit - potongan), dan menonaktifkan penghuni
     * yang tidak punya kontrak aktif lain. Deposit hanya dicatat; Kostra tidak menahan dananya.
     *
     * @param  array<string, mixed>  $data  checkout_date, ended_reason, [checkout_notes, deduction_amount, deduction_note]
     */
    public function __invoke(Lease $lease, array $data): Lease
    {
        $tenant = $this->currentTenant();
        $today = $tenant->today();

        $out = Dates::date($data['checkout_date'] ?? null, $tenant->timezone);

        if ($out === null) {
            throw ValidationException::withMessages(['checkout_date' => 'Format tanggal harus YYYY-MM-DD.']);
        }

        $out = $out->startOfDay();

        if ($out->gt($today)) {
            throw ValidationException::withMessages(['checkout_date' => 'Tanggal check-out tidak boleh di masa depan.']);
        }

        $reason = LeaseEndReason::tryFrom((string) ($data['ended_reason'] ?? ''));

        if ($reason === null || ! in_array($reason->value, LeaseEndReason::checkOutValues(), true)) {
            throw ValidationException::withMessages(['ended_reason' => 'Alasan check-out tidak valid.']);
        }

        $deduction = (int) ($data['deduction_amount'] ?? 0);

        return DB::transaction(function () use ($lease, $data, $out, $reason, $deduction) {
            $lease = Lease::query()->lockForUpdate()->findOrFail($lease->getKey());

            if ($lease->status !== LeaseStatus::Active) {
                throw ValidationException::withMessages(['general' => 'Kontrak ini sudah tidak aktif.']);
            }

            if ($out->lt($lease->start_date)) {
                throw ValidationException::withMessages(['checkout_date' => 'Tanggal check-out tidak boleh sebelum tanggal mulai kontrak.']);
            }

            $room = Room::query()->lockForUpdate()->findOrFail($lease->room_id);
            $deposit = LeaseDeposit::query()->lockForUpdate()->where('lease_id', $lease->getKey())->firstOrFail();

            $this->settleDeposit($deposit, $deduction, $data['deduction_note'] ?? null, $out);

            $lease->update([
                'status' => LeaseStatus::Ended,
                'active_room_id' => null,
                'checked_out_at' => $out->utc(),
                'checkout_notes' => $data['checkout_notes'] ?? null,
                'ended_reason' => $reason,
            ]);

            $room->update(['status' => RoomStatus::Vacant]);

            $residentIds = LeaseResident::query()->where('lease_id', $lease->getKey())->pluck('resident_id');

            foreach ($residentIds as $residentId) {
                $stillLeasing = LeaseResident::query()
                    ->where('resident_id', $residentId)
                    ->whereHas('lease', fn ($query) => $query->where('status', LeaseStatus::Active->value)->whereKeyNot($lease->getKey()))
                    ->exists();

                if (! $stillLeasing) {
                    Resident::query()->find($residentId)?->update(['is_active' => false]);

                    // Mantan penghuni tidak lagi punya akses portal (tagihan, rekening, unggah bukti).
                    PortalToken::query()->where('resident_id', $residentId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                }
            }

            return $lease;
        });
    }

    private function settleDeposit(LeaseDeposit $deposit, int $deduction, ?string $note, CarbonImmutable $on): void
    {
        if ($deduction < 0) {
            throw ValidationException::withMessages(['deduction_amount' => 'Potongan tidak valid.']);
        }

        // Deposit yang belum pernah diterima tidak bisa dipotong maupun dikembalikan.
        if ($deposit->status === DepositStatus::Pending) {
            if ($deduction > 0) {
                throw ValidationException::withMessages(['deduction_amount' => 'Deposit belum diterima, jadi tidak ada yang bisa dipotong.']);
            }

            return;
        }

        if ($deduction > $deposit->amount) {
            throw ValidationException::withMessages(['deduction_amount' => 'Potongan tidak boleh melebihi nominal deposit.']);
        }

        $refund = $deposit->amount - $deduction;

        $deposit->update([
            'deduction_amount' => $deduction,
            'deduction_note' => $deduction > 0 ? $note : null,
            'refund_amount' => $refund,
            'refunded_at' => $refund > 0 ? $on->toDateString() : null,
            'status' => match (true) {
                $deduction === 0 => DepositStatus::Refunded,
                $refund === 0 => DepositStatus::Forfeited,
                default => DepositStatus::PartiallyRefunded,
            },
        ]);
    }
}
