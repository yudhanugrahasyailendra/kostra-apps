<?php

namespace App\Actions;

use App\Contracts\OutstandingBalance;
use App\Enums\DepositStatus;
use App\Enums\LeaseEndReason;
use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Models\Resident;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckOutResident
{
    public function __construct(private OutstandingBalance $outstanding) {}

    /**
     * Check-out: hitung potongan dan pengembalian deposit, tutup kontrak, kosongkan
     * `active_room_id`, dan kembalikan kamar ke kosong (atau perawatan). Kostra hanya
     * mencatat; uang deposit dikembalikan di luar sistem.
     *
     * Kunci `data`: ended_reason, checkout_notes, deductions (list label + amount),
     * deduct_outstanding (potong sisa tagihan dari deposit), room_status (vacant atau maintenance).
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Lease $lease, array $data = []): Lease
    {
        return DB::transaction(function () use ($lease, $data) {
            $lease = Lease::query()->lockForUpdate()->findOrFail($lease->id);

            if ($lease->status !== LeaseStatus::Active) {
                throw ValidationException::withMessages(['lease' => 'Kontrak ini sudah tidak aktif.']);
            }

            $this->settleDeposit($lease, $data);

            $lease->update([
                'status' => LeaseStatus::Ended,
                'active_room_id' => null,
                'checked_out_at' => now(),
                'checkout_notes' => $this->text($data['checkout_notes'] ?? null),
                'ended_reason' => $data['ended_reason'] ?? LeaseEndReason::MovedOut,
            ]);

            Room::query()->lockForUpdate()->findOrFail($lease->room_id)
                ->update(['status' => $data['room_status'] ?? RoomStatus::Vacant]);

            // Penghuni tanpa kontrak aktif lain menjadi alumni.
            $residentIds = $lease->leaseResidents()->pluck('resident_id');
            $stillActive = LeaseResident::query()
                ->whereIn('resident_id', $residentIds)
                ->whereHas('lease', fn ($q) => $q->active())
                ->pluck('resident_id');

            Resident::query()->whereIn('id', $residentIds->diff($stillActive))->update(['is_active' => false]);

            return $lease;
        });
    }

    /** Hitung potongan dan pengembalian; hasilnya disimpan di lease_deposits. */
    private function settleDeposit(Lease $lease, array $data): void
    {
        $deposit = LeaseDeposit::query()->where('lease_id', $lease->id)->lockForUpdate()->first();

        $items = collect($data['deductions'] ?? [])
            ->map(fn ($item) => ['label' => trim((string) ($item['label'] ?? '')), 'amount' => (int) ($item['amount'] ?? 0)])
            ->filter(fn ($item) => $item['amount'] > 0)
            ->values();

        if ($deposit === null) {
            if ($items->isNotEmpty()) {
                throw ValidationException::withMessages(['deductions' => 'Kontrak ini tidak punya deposit untuk dipotong.']);
            }

            return;
        }

        if ($deposit->status === DepositStatus::Pending) {
            // Deposit yang tidak pernah diterima tidak punya apa pun untuk dipotong atau dikembalikan.
            if ($items->isNotEmpty()) {
                throw ValidationException::withMessages(['deductions' => 'Deposit belum diterima, jadi tidak ada yang bisa dipotong.']);
            }

            return;
        }

        $outstanding = ($data['deduct_outstanding'] ?? false) ? $this->outstanding->forLease($lease) : 0;
        $total = $items->sum('amount') + $outstanding;

        if ($total > $deposit->amount) {
            throw ValidationException::withMessages(['deductions' => 'Total potongan melebihi jumlah deposit.']);
        }

        $refund = $deposit->amount - $total;

        $notes = $items->map(fn ($item) => ($item['label'] !== '' ? $item['label'] : 'Potongan').': Rp '.number_format($item['amount'], 0, ',', '.'));

        if ($outstanding > 0) {
            $notes->push('Sisa tagihan: Rp '.number_format($outstanding, 0, ',', '.'));
        }

        $deposit->update([
            'status' => match (true) {
                $total === 0 => DepositStatus::Refunded,
                $refund === 0 => DepositStatus::Forfeited,
                default => DepositStatus::PartiallyRefunded,
            },
            'deduction_amount' => $total,
            'deduction_note' => $notes->isEmpty() ? null : $notes->implode("\n"),
            'refund_amount' => $refund,
            'refunded_at' => now()->toDateString(),
        ]);
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
