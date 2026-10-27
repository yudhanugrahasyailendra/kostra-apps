<?php

namespace App\Http\Requests\Owner;

use App\Models\PaymentAccount;
use Illuminate\Foundation\Http\FormRequest;

class QrisImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', [PaymentAccount::class, $this->route('account')]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'Gambar QRIS'];
    }
}
