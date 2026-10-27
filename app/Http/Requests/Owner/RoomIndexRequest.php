<?php

namespace App\Http\Requests\Owner;

use App\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(RoomStatus::class)],
            'room_type_id' => ['nullable', 'integer'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:99'],
        ];
    }
}
