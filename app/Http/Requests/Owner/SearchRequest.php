<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->tenant_id !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.required' => 'Ketik kata kunci pencarian.',
            'q.max' => 'Kata kunci paling banyak 100 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => trim((string) $this->input('q', ''))]);
    }
}
