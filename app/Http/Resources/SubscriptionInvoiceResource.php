<?php

namespace App\Http\Resources;

use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tagihan langganan. Jumlah integer rupiah.
 *
 * @mixin SubscriptionInvoice
 */
class SubscriptionInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'amount' => $this->amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'due_date' => $this->due_date->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
        ];
    }
}
