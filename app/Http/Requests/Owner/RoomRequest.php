<?php

namespace App\Http\Requests\Owner;

use App\Actions\Rooms\ImportRooms;
use App\Enums\RoomStatus;
use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dipakai untuk membuat dan mengubah satu kamar. Keunikan nomor dan batas paket
 * diputuskan Action di dalam transaksi (di sini hanya bentuk datanya).
 */
class RoomRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        $room = $this->route('room');

        return $room
            ? $this->user()->can('update', $room)
            : $this->user()->can('create', Room::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['room_type_id', 'floor', 'status', 'notes']);
        $this->normalizeMoney('price');
        $this->normalizeList('facilities');

        if (is_string($this->input('number'))) {
            $this->merge(['number' => trim($this->input('number'))]);
        }
    }

    public function rules(): array
    {
        $creating = $this->route('room') === null;

        return [
            'property_id' => [$creating ? 'required' : 'prohibited', new ExistsInTenant(Property::class, 'Properti')],
            'room_type_id' => ['nullable', new ExistsInTenant(RoomType::class, 'Tipe kamar')],
            'number' => ['required', 'string', 'max:20'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:99'],
            'price' => ['nullable', 'integer', 'min:1', 'max:'.ImportRooms::MAX_PRICE],
            'status' => ['nullable', Rule::in(RoomStatus::manualValues())],
            'facilities' => ['nullable', 'array', 'max:20'],
            'facilities.*' => ['string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
