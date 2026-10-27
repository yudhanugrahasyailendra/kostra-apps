<?php

namespace App\Http\Requests\Portal;

use App\Enums\ComplaintCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplaintRequest extends FormRequest
{
    /**
     * Otorisasi adalah token di URL (ResolvePortalToken). Penghuni dan kamar tidak pernah datang dari input:
     * tiket selalu dibuat atas nama penghuni pemilik token, di kamar kontrak aktifnya.
     */
    public function authorize(): bool
    {
        return $this->attributes->has('portal_resident');
    }

    protected function getRedirectUrl(): string
    {
        return route('portal.complaints.create', ['token' => $this->route('token')]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['required', 'string', 'min:3', 'max:5000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
            'photo.mimes' => 'Foto harus berupa JPG atau PNG.',
        ];
    }

    public function attributes(): array
    {
        return ['category' => 'Kategori', 'title' => 'Judul', 'description' => 'Deskripsi', 'photo' => 'Foto'];
    }
}
