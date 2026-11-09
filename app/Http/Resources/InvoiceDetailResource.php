<?php

namespace App\Http\Resources;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Detail satu tagihan: item, penyesuaian, rentang periode, dan catatan pembatalan.
 * Relasi `items`, `adjustments.creator`, `property`, `lease.room`, `resident` dimuat pemanggil.
 *
 * @mixin Invoice
 */
class InvoiceDetailResource extends InvoiceResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isVoid = $this->status === InvoiceStatus::Void;

        return parent::toArray($request) + [
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'subtotal' => $this->subtotal,
            'adjustment_total' => $this->adjustment_total,
            'notes' => $this->notes,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'type_label' => $item->type->label(),
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount,
            ])->values()),
            'adjustments' => $this->whenLoaded('adjustments', fn () => $this->adjustments->map(fn ($adjustment) => [
                'id' => $adjustment->id,
                'amount' => $adjustment->amount,
                'reason' => $adjustment->reason,
                'created_by_name' => $adjustment->creator?->name,
                'created_at' => $adjustment->created_at?->toIso8601String(),
            ])->values()),
            // Dapat dibatalkan hanya bila belum lunas, belum ada pembayaran, dan belum batal.
            'can_void_now' => ! $isVoid && $this->status !== InvoiceStatus::Paid && $this->paid_total === 0,
            'can_adjust_now' => ! $isVoid && $this->status !== InvoiceStatus::Draft,
        ];
    }
}
