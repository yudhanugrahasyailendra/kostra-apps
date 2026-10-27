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

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('kostra.attachments.max_kb')],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'Centang bahwa seluruh penghuni di berkas sudah menyetujui datanya dikumpulkan.',
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'Berkas'];
    }
}
