<?php

namespace App\Http\Requests\Owner;

use App\Models\Property;
use App\Models\Room;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class ImportRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Room::class);
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', new ExistsInTenant(Property::class, 'Properti')],
            // Batas ukuran sama dengan lampiran lain (5 MB). Excel (.xlsx) belum didukung.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'Berkas'];
    }
}
