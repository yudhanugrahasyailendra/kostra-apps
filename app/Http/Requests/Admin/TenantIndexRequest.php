<?php

namespace App\Http\Requests\Admin;

use App\Enums\TenantStatus;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.manage');
    }

    protected function prepareForValidation(): void
    {
        foreach (['status', 'plan_id'] as $key) {
            if (! filled($this->input($key))) {
                $this->merge([$key => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TenantStatus::class)],
            'plan_id' => ['nullable', 'integer', Rule::exists(Plan::class, 'id')],
        ];
    }
}
