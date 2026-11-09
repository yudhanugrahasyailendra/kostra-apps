<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class PortalCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('portal_resident') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Tulis pesan Anda.',
            'body.max' => 'Pesan maksimal 2.000 karakter.',
        ];
    }
}
