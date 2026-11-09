<?php

namespace App\Http\Resources;

use App\Models\Lease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kontrak di daftar dan di riwayat penghuni. Hanya nama penghuni dan nomor kamar; nomor
 * telepon dan catatan ada di LeaseDetailResource. Relasi `room`, `primaryResident`, dan
 * `deposit` dimuat pemanggil. Uang selalu integer rupiah.
 *
 * @mixin Lease
 */
class LeaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $days = $this->daysRemaining();
        $user = $request->user();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn () => $this->room->number),
            'property_name' => $this->whenLoaded('property', fn () => $this->property->name),
            'primary_resident_id' => $this->primary_resident_id,
            'primary_resident_name' => $this->whenLoaded('primaryResident', fn () => $this->primaryResident->name),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days_remaining' => $days,
            'is_overdue' => $this->isOverdue(),
            'expires_soon' => $this->status->value === 'active' && $days !== null && $days >= 0 && $days <= max(Lease::EXPIRY_REMINDER_DAYS),
            'rent_amount' => $this->rent_amount,
            'due_day' => $this->due_day,
            'ended_reason_label' => $this->ended_reason?->label(),
            'deposit' => $this->whenLoaded('deposit', fn () => $this->deposit ? [
                'amount' => $this->deposit->amount,
                'status' => $this->deposit->status->value,
                'status_label' => $this->deposit->status->label(),
                'status_tone' => $this->deposit->status->tone(),
            ] : null),
            'can' => [
                'check_out' => $user?->can('checkOut', $this->resource) ?? false,
                'renew' => $user?->can('renew', $this->resource) ?? false,
                'transfer' => $user?->can('transfer', $this->resource) ?? false,
            ],
        ];
    }
}
