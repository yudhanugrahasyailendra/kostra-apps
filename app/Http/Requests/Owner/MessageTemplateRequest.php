<?php

namespace App\Http\Requests\Owner;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;

/** Hanya isi (body) yang bisa diubah; category selalu utility dan tidak diterima dari klien. */
class MessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [MessageTemplate::class]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Isi pesan tidak boleh kosong.',
            'body.max' => 'Isi pesan maksimal 1.000 karakter.',
        ];
    }
}
