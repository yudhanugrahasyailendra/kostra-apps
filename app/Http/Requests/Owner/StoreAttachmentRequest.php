<?php

namespace App\Http\Requests\Owner;

use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resident = $this->route('resident');
        $category = AttachmentCategory::tryFrom((string) $this->input('category'));

        if (! $resident instanceof Resident || ! $this->user()->can('update', $resident)) {
            return false;
        }

        // Kategori tidak dikenal dibiarkan lolos ke validasi supaya pesannya jelas.
        return $category === null || $this->user()->can('create', [Attachment::class, $category]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_map(fn (AttachmentCategory $c) => $c->value, AttachmentCategory::forResident()))],
            // mimes memeriksa tipe dari isi berkas, bukan hanya ekstensi.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category.required' => 'Pilih jenis dokumen.',
            'category.in' => 'Jenis dokumen tidak dikenal.',
            'file.required' => 'Pilih berkas yang akan diunggah.',
            'file.mimes' => 'Berkas harus berformat JPG, PNG, atau PDF.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
            'file.uploaded' => 'Berkas gagal diunggah. Periksa ukuran maksimal 5 MB.',
        ];
    }
}
