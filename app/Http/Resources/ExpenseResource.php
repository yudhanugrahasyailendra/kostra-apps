<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pengeluaran di daftar. Relasi `category`, `property`, `creator`, dan `attachments` dimuat pemanggil.
 * Uang integer rupiah; lampiran lewat AttachmentResource (tautan bertanda tangan, tanpa jalur berkas).
 *
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_category_id' => $this->expense_category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category->name),
            'property_id' => $this->property_id,
            'property_name' => $this->whenLoaded('property', fn () => $this->property?->name),
            'amount' => $this->amount,
            'spent_on' => $this->spent_on->toDateString(),
            'description' => $this->description,
            'vendor_name' => $this->vendor_name,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'receipts' => $this->whenLoaded('attachments', fn () => AttachmentResource::collection($this->attachments)->resolve()),
        ];
    }
}
