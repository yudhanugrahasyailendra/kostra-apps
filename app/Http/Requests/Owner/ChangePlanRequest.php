<?php

namespace App\Http\Requests\Owner;

use App\Enums\SubscriptionPeriod;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Subscription::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan_key' => ['required', 'string', Rule::exists('plans', 'key')->where('is_active', true)],
            'billing_period' => ['required', Rule::enum(SubscriptionPeriod::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'plan_key.required' => 'Pilih paket.',
            'plan_key.exists' => 'Paket tidak tersedia.',
            'billing_period.required' => 'Pilih periode tagihan.',
            'billing_period.enum' => 'Periode tagihan tidak dikenal.',
        ];
    }
}
