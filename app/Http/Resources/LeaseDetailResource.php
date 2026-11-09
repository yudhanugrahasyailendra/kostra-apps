<?php

namespace App\Http\Resources;

use App\Models\Lease;
use Illuminate\Http\Request;

/**
 * Detail satu kontrak: tambahan penghuni kontrak (dengan nomor WhatsApp untuk tautan),
 * catatan check-in dan check-out, rincian deposit, riwayat pindah kamar, dan rantai perpanjangan.
 * Relasi `leaseResidents.resident`, `deposit`, `transfers.fromRoom|toRoom` dimuat pemanggil.
 *
 * @mixin Lease
 */
class LeaseDetailResource extends LeaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'billing_cycle_label' => $this->billing_cycle->label(),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checkin_notes' => $this->checkin_notes,
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'checkout_notes' => $this->checkout_notes,
            'notes' => $this->notes,
            'renewed_from_lease_id' => $this->renewed_from_lease_id,
            'residents' => $this->whenLoaded('leaseResidents', fn () => $this->leaseResidents->map(fn ($member) => [
                'id' => $member->resident_id,
                'name' => $member->resident->name,
                'phone' => $member->resident->phone,
                'is_primary' => $member->is_primary,
            ])->values()),
            'deposit_detail' => $this->whenLoaded('deposit', fn () => $this->deposit ? [
                'amount' => $this->deposit->amount,
                'received_at' => $this->deposit->received_at?->toDateString(),
                'deduction_amount' => $this->deposit->deduction_amount,
                'deduction_note' => $this->deposit->deduction_note,
                'refund_amount' => $this->deposit->refund_amount,
                'refunded_at' => $this->deposit->refunded_at?->toDateString(),
            ] : null),
            'transfers' => $this->whenLoaded('transfers', fn () => $this->transfers->map(fn ($transfer) => [
                'id' => $transfer->id,
                'from_room_number' => $transfer->fromRoom->number,
                'to_room_number' => $transfer->toRoom->number,
                'transferred_on' => $transfer->transferred_on->toDateString(),
                'new_rent_amount' => $transfer->new_rent_amount,
                'reason' => $transfer->reason,
            ])->values()),
        ];
    }
}
