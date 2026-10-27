<?php

namespace App\Http\Requests\Owner;

use App\Actions\Rooms\CreateRoomsInBulk;
use App\Actions\Rooms\ImportRooms;
use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class BulkRoomsRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Room::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['room_type_id', 'floor', 'prefix']);
        $this->normalizeMoney('price');
        $this->normalizeList('facilities');
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', new ExistsInTenant(Property::class, 'Properti')],
            'room_type_id' => ['nullable', new ExistsInTenant(RoomType::class, 'Tipe kamar')],
            'prefix' => ['nullable', 'string', 'max:10'],
            'from' => ['required', 'integer', 'min:0', 'max:99999'],
            'to' => ['required', 'integer', 'gte:from', 'max:99999'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:99'],
            'price' => ['nullable', 'integer', 'min:1', 'max:'.ImportRooms::MAX_PRICE],
            'facilities' => ['nullable', 'array', 'max:20'],
            'facilities.*' => ['string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'to.gte' => 'Nomor akhir harus sama atau lebih besar dari nomor awal.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $from = (int) $this->input('from');
                $to = (int) $this->input('to');

                if ($to >= $from && ($to - $from + 1) > CreateRoomsInBulk::MAX_PER_BATCH) {
                    $validator->errors()->add('to', 'Maksimal '.CreateRoomsInBulk::MAX_PER_BATCH.' kamar per langkah.');
                }
            },
        ];
    }
}
