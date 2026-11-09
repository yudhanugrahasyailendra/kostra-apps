<?php

namespace App\Http\Resources\Portal;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pembayaran (kuitansi) untuk penghuni. Tanpa pencatat, rekening internal, atau catatan pemilik.
 * Relasi `allocations.invoice` dimuat pemanggil.
 *
 * @mixin Payment
 */
class PortalPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'amount' => $this->amount,
            'paid_on' => $this->paid_on->toDateString(),
            'method_label' => $this->method->label(),
            'periods' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($a) => $a->invoice?->period)->filter()->values()),
        ];
    }
}
