<?php

namespace App\Http\Requests\Owner;

use App\Enums\PaymentAccountType;
use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\PaymentAccount;
use App\Models\Property;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentAccountRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        return $this->user()->can('manage', [PaymentAccount::class, $this->route('account')]);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['property_id', 'bank_name', 'account_number', 'account_name', 'instructions']);

        $this->merge([
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    public function rules(): array
    {
        $needsNumber = Rule::requiredIf(fn () => in_array($this->input('type'), [PaymentAccountType::Bank->value, PaymentAccountType::Ewallet->value], true));

        return [
            'type' => ['required', Rule::enum(PaymentAccountType::class)],
            'property_id' => ['nullable', new ExistsInTenant(Property::class, 'Properti')],
            'bank_name' => [$needsNumber, 'nullable', 'string', 'max:80'],
            'account_number' => [$needsNumber, 'nullable', 'regex:/^[0-9A-Za-z\- ]{3,50}$/'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.regex' => 'Nomor rekening hanya boleh huruf, angka, spasi, dan tanda hubung (3 sampai 50 karakter).',
            'bank_name.required' => 'Nama bank atau dompet digital wajib diisi.',
            'account_number.required' => 'Nomor rekening wajib diisi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'Jenis',
            'bank_name' => 'Nama bank',
            'account_number' => 'Nomor rekening',
            'account_name' => 'Atas nama',
        ];
    }
}
