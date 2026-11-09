<?php

namespace App\Http\Requests\Owner;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $room = $this->route('room');

        return $room
            ? $this->user()->can('update', $room)
            : $this->user()->can('create', Room::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $room = $this->route('room');
        $propertyId = $this->input('property_id') ?: $room?->property_id;

        return [
            'property_id' => ['required', 'integer', TenantRules::exists('properties', softDeletes: true)],
            'room_type_id' => ['nullable', 'integer', TenantRules::exists('room_types')],
            'number' => ['required', 'string', 'max:20', TenantRules::unique('rooms', 'number', ['property_id' => $propertyId], softDeletes: true, ignoreId: $room?->id)],
            'floor' => ['nullable', 'integer', 'min:0', 'max:255'],
            'price' => ['nullable', 'integer', 'min:0', 'max:4294967295', 'required_without:room_type_id'],
            // `occupied` ditentukan kontrak aktif (Fase 4), tidak boleh dipilih manual.
            'status' => ['sometimes', Rule::in(array_map(fn (RoomStatus $s) => $s->value, RoomStatus::manual()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'room_type_id' => $this->input('room_type_id') ?: null,
            'price' => $this->filled('price') ? $this->input('price') : null,
            'number' => trim((string) $this->input('number')),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'number.unique' => 'Nomor kamar sudah dipakai di properti ini.',
            'price.required_without' => 'Isi harga atau pilih tipe kamar.',
        ];
    }
}
