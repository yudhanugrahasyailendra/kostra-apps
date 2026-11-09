<?php

namespace App\Http\Resources\Portal;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tagihan untuk penghuni. Hanya kolom yang dipakai layar portal: tanpa catatan internal, pembuat, alasan
 * pembatalan, atau id kontrak. `items` dimuat pemanggil bila halaman membutuhkannya.
 *
 * @mixin Invoice
 */
class PortalInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'period' => $this->period,
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'total' => $this->total,
            'paid_total' => $this->paid_total,
            'outstanding' => $this->outstanding(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'description' => $item->description,
                'amount' => $item->amount,
            ])->values()),
            'has_pending_proof' => (bool) ($this->resource->getAttribute('has_pending_proof') ?? false),
        ];
    }
}
