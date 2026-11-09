<?php

namespace App\Http\Requests\Portal;

use App\Enums\ComplaintCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Pengajuan komplain oleh penghuni. Kamar dan pengaju tidak diterima dari klien: diambil dari token dan kontrak aktif. */
class PortalComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('portal_resident') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:3'],
            // mimes memeriksa tipe dari isi berkas, bukan hanya ekstensi.
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category.required' => 'Pilih kategori masalah.',
            'category.enum' => 'Kategori tidak dikenal.',
            'title.required' => 'Judul singkat wajib diisi.',
            'title.max' => 'Judul maksimal 150 karakter.',
            'description.required' => 'Jelaskan masalahnya.',
            'description.max' => 'Deskripsi maksimal 5.000 karakter.',
            'photos.max' => 'Foto maksimal 3 berkas.',
            'photos.*.mimes' => 'Foto harus berformat JPG atau PNG.',
            'photos.*.max' => 'Ukuran tiap foto maksimal 5 MB.',
            'photos.*.uploaded' => 'Foto gagal diunggah. Periksa ukuran maksimal 5 MB.',
        ];
    }
}
