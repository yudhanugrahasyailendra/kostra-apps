<?php

namespace App\Http\Resources;

use App\Models\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rekening untuk pengaturan dan pilihan formulir. Relasi `property` dimuat pemanggil.
 *
 * @mixin PaymentAccount
 */
class PaymentAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'property_id' => $this->property_id,
            'property_name' => $this->whenLoaded('property', fn () => $this->property?->name),
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'instructions' => $this->instructions,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'label' => $this->displayName(),
        ];
    }
}
