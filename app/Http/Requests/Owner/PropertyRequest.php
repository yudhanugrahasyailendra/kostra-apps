<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;

class PropertyRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property
            ? $this->user()->can('update', $property)
            : $this->user()->can('create', Property::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['address', 'city', 'floors', 'notes']);

        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:80'],
            'floors' => ['nullable', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
