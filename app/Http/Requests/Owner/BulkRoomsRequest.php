<?php

namespace App\Http\Requests\Owner;

use App\Models\Room;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class BulkRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Room::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', TenantRules::exists('properties', softDeletes: true)],
            'prefix' => ['nullable', 'string', 'max:5'],
            'start' => ['required', 'integer', 'min:0', 'max:99999'],
            'end' => ['required', 'integer', 'gte:start', 'max:99999'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:255'],
            'room_type_id' => ['nullable', 'integer', TenantRules::exists('room_types')],
            'price' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'room_type_id' => $this->input('room_type_id') ?: null,
            'price' => $this->filled('price') ? $this->input('price') : null,
        ]);
    }
}
