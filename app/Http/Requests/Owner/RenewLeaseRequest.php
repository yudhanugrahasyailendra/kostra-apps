<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\ParsesRoomInput;
use Illuminate\Foundation\Http\FormRequest;

class RenewLeaseRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        return $this->user()->can('operate', $this->route('lease'));
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['notes']);
    }

    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['end_date' => 'Tanggal akhir baru'];
    }
}
