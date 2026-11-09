<?php

namespace App\Http\Requests\Owner;

use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        $complaint = $this->route('complaint');

        return $complaint instanceof Complaint && $this->user()->can('update', $complaint);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assigned_to' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)->where('is_active', true)->whereNull('deleted_at'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'assigned_to.exists' => 'Penanggung jawab harus pengguna aktif di kos ini.',
            'assigned_to.integer' => 'Penanggung jawab tidak valid.',
        ];
    }
}
