<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pembayaran di daftar dan riwayat tagihan. Hanya nama penghuni; tidak ada nomor telepon.
 * Relasi `resident`, `account`, `recorder`, dan `allocations.invoice` dimuat pemanggil.
 * Uang selalu integer rupiah.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'resident_id' => $this->resident_id,
            'resident_name' => $this->whenLoaded('resident', fn () => $this->resident->name),
            'amount' => $this->amount,
            'paid_on' => $this->paid_on->toDateString(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'account_label' => $this->whenLoaded('account', fn () => $this->account?->displayName()),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'void_reason' => $this->void_reason,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'recorded_by_name' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'invoices' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'id' => $allocation->invoice_id,
                'number' => $allocation->invoice?->number,
                'amount' => $allocation->amount,
            ])->values()),
            'can' => ['void' => $this->isActive() && ($request->user()?->can('void', $this->resource) ?? false)],
        ];
    }
}
