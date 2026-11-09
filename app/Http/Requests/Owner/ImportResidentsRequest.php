<?php

namespace App\Http\Requests\Owner;

use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;

class ImportResidentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Resident::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas CSV.',
            'file.mimes' => 'Berkas harus berformat CSV.',
            'file.max' => 'Ukuran berkas maksimal 1 MB.',
        ];
    }
}
