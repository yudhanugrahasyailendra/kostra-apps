<?php

namespace App\Http\Requests\Owner;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;

class MessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', MessageTemplate::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        // Variabel yang diizinkan diperiksa oleh SaveMessageTemplate (MessageTemplateRenderer); category tidak
        // bisa diubah dari input.
        return [
            'name' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Nama', 'body' => 'Isi template'];
    }
}
