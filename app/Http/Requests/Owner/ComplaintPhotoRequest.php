<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class ComplaintPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complaint'));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'Foto'];
    }
}
