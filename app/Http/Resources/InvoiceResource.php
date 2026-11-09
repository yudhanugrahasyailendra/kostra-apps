<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tagihan di daftar. Hanya nama penghuni dan nomor kamar; tidak ada nomor telepon.
 * Relasi `property`, `lease.room`, dan `resident` dimuat pemanggil. Uang selalu integer rupiah.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'property_name' => $this->whenLoaded('property', fn () => $this->property->name),
            'room_number' => $this->whenLoaded('lease', fn () => $this->lease->room?->number),
            'lease_id' => $this->lease_id,
            'resident_id' => $this->resident_id,
            'resident_name' => $this->whenLoaded('resident', fn () => $this->resident->name),
            'period' => $this->period,
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'total' => $this->total,
            'paid_total' => $this->paid_total,
            'outstanding' => $this->outstanding(),
            'can' => [
                'adjust' => $user?->can('adjust', $this->resource) ?? false,
                'void' => $user?->can('void', $this->resource) ?? false,
            ],
        ];
    }
}
