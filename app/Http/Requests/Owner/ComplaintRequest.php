<?php

namespace App\Http\Requests\Owner;

use App\Enums\ComplaintCategory;
use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Staf membuat tiket atas nama penghuni. Kamar dan penghuni harus milik tenant yang sama. */
class ComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Complaint::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:5'],
            // mimes memeriksa tipe dari isi berkas, bukan hanya ekstensi.
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Pilih kamar.',
            'room_id.exists' => 'Kamar tidak ditemukan.',
            'resident_id.required' => 'Pilih penghuni pengaju.',
            'resident_id.exists' => 'Penghuni tidak ditemukan.',
            'category.required' => 'Pilih kategori.',
            'category.enum' => 'Kategori tidak dikenal.',
            'title.required' => 'Judul wajib diisi.',
            'title.max' => 'Judul maksimal 150 karakter.',
            'description.required' => 'Deskripsi wajib diisi.',
            'description.max' => 'Deskripsi maksimal 5.000 karakter.',
            'photos.max' => 'Foto maksimal 5 berkas.',
            'photos.*.mimes' => 'Foto harus berformat JPG atau PNG.',
            'photos.*.max' => 'Ukuran tiap foto maksimal 5 MB.',
            'photos.*.uploaded' => 'Foto gagal diunggah. Periksa ukuran maksimal 5 MB.',
        ];
    }
}
