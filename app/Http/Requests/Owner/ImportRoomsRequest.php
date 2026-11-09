<?php

namespace App\Http\Requests\Owner;

use App\Models\Room;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class ImportRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Room::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', TenantRules::exists('properties', softDeletes: true)],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Berkas harus berformat CSV.',
            'file.max' => 'Ukuran berkas maksimal 1 MB.',
        ];
    }
}
