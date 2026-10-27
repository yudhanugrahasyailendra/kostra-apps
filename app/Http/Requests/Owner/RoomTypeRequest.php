<?php

namespace App\Http\Requests\Owner;

use App\Actions\Rooms\ImportRooms;
use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Property;
use App\Models\RoomType;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class RoomTypeRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        $type = $this->route('roomType');

        return $type
            ? $this->user()->can('update', $type)
            : $this->user()->can('create', RoomType::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['property_id', 'size_m2', 'description']);
        $this->normalizeMoney('base_price');
        $this->normalizeList('facilities');

        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    public function rules(): array
    {
        return [
            // Properti hanya dipakai saat membuat; tipe yang ada tidak pindah properti.
            'property_id' => ['nullable', new ExistsInTenant(Property::class, 'Properti')],
            'name' => ['required', 'string', 'max:80'],
            'base_price' => ['required', 'integer', 'min:1', 'max:'.ImportRooms::MAX_PRICE],
            'size_m2' => ['nullable', 'numeric', 'min:0.1', 'max:999.9'],
            'facilities' => ['nullable', 'array', 'max:20'],
            'facilities.*' => ['string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
