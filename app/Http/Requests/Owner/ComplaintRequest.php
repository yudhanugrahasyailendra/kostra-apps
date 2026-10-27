<?php

namespace App\Http\Requests\Owner;

use App\Enums\ComplaintCategory;
use App\Models\Complaint;
use App\Models\Resident;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Complaint::class);
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', new ExistsInTenant(Resident::class, 'Penghuni')],
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['required', 'string', 'min:3', 'max:5000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function attributes(): array
    {
        return [
            'resident_id' => 'Penghuni',
            'category' => 'Kategori',
            'title' => 'Judul',
            'description' => 'Deskripsi',
            'photo' => 'Foto',
        ];
    }
}
