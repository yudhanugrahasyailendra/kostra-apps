<?php

namespace App\Http\Requests\Owner;

use App\Models\RoomType;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class RoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('roomType');

        return $type
            ? $this->user()->can('update', $type)
            : $this->user()->can('create', RoomType::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $propertyId = $this->input('property_id') ?: null;

        return [
            'property_id' => ['nullable', 'integer', TenantRules::exists('properties', softDeletes: true)],
            'name' => ['required', 'string', 'max:80', TenantRules::unique('room_types', 'name', ['property_id' => $propertyId], ignoreId: $this->route('roomType')?->id)],
            'base_price' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'size_m2' => ['nullable', 'numeric', 'min:0', 'max:9999.9'],
            'facilities' => ['nullable', 'array', 'max:30'],
            'facilities.*' => ['string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['property_id' => $this->input('property_id') ?: null]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'Nama tipe sudah dipakai.'];
    }
}
