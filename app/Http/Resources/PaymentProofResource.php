<?php

namespace App\Http\Resources;

use App\Enums\ProofStatus;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bukti transfer di antrean verifikasi. Relasi `invoice.lease.room`, `resident`, `attachments`,
 * dan `payment` dimuat pemanggil. Tautan berkas bertanda tangan berumur pendek, tanpa path.
 *
 * @mixin PaymentProof
 */
class PaymentProofResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $pending = $this->status === ProofStatus::Pending;
        $ageDays = (int) $this->created_at->diffInDays(now());

        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice->number),
            'invoice_outstanding' => $this->whenLoaded('invoice', fn () => $this->invoice->outstanding()),
            'room_number' => $this->whenLoaded('invoice', fn () => $this->invoice->lease?->room?->number),
            'resident_name' => $this->whenLoaded('resident', fn () => $this->resident->name),
            'amount' => $this->amount,
            'paid_on' => $this->paid_on?->toDateString(),
            'sender_note' => $this->sender_note,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'created_at' => $this->created_at->toIso8601String(),
            'age_days' => $ageDays,
            'is_stale' => $pending && $ageDays >= PaymentProof::REMINDER_AFTER_DAYS,
            'reject_reason' => $this->reject_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'payment_id' => $this->payment_id,
            'file' => $this->whenLoaded('attachments', fn () => ($file = $this->attachments->first()) ? (new AttachmentResource($file))->resolve($request) : null),
            'can' => ['review' => $pending && ($request->user()?->can('review', $this->resource) ?? false)],
        ];
    }
}
