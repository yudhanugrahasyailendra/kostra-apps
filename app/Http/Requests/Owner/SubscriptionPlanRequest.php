<?php

namespace App\Http\Requests\Owner;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('subscription.manage');
    }

    public function rules(): array
    {
        return [
            // Paket milik platform (tidak terscope tenant); hanya yang aktif yang bisa dipilih.
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')->where('is_active', true)],
            'billing_period' => ['nullable', Rule::enum(BillingPeriod::class)],
        ];
    }

    public function plan(): Plan
    {
        return Plan::query()->findOrFail($this->validated('plan_id'));
    }

    public function period(): BillingPeriod
    {
        return BillingPeriod::from($this->validated('billing_period') ?? BillingPeriod::Monthly->value);
    }
}
