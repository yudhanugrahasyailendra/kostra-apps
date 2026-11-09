<?php

namespace App\Http\Requests\Owner;

use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;

class ComplaintCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $complaint = $this->route('complaint');

        return $complaint instanceof Complaint && $this->user()->can('update', $complaint);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Komentar tidak boleh kosong.',
            'body.max' => 'Komentar maksimal 2.000 karakter.',
        ];
    }
}
