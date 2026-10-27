<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class LeaseAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate', $this->route('lease'));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'Berkas'];
    }
}
