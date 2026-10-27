<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.manage');
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')],
            'billing_period' => ['nullable', Rule::enum(BillingPeriod::class)],
            'ignore_limits' => ['nullable', 'boolean'],
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
