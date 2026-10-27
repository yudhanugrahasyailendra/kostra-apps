<?php

namespace App\Http\Requests\Owner;

use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = AttachmentCategory::tryFrom((string) $this->input('category'));

        // Kategori tidak dikenal ditolak validasi; izin sensitif diperiksa Policy.
        return $category === null
            || ($this->user()->can('update', $this->route('resident'))
                && $this->user()->can('upload', [Attachment::class, $category]));
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(AttachmentCategory::residentValues())],
            // Isi berkas diperiksa ulang oleh StoreAttachment (bukan hanya ekstensi).
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'Berkas', 'category' => 'Jenis dokumen'];
    }
}
