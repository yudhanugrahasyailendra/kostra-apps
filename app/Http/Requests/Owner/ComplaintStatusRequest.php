<?php

namespace App\Http\Requests\Owner;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplaintStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $complaint = $this->route('complaint');

        return $complaint instanceof Complaint && $this->user()->can('update', $complaint);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ComplaintStatus::class)]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status.',
            'status.enum' => 'Status tidak dikenal.',
        ];
    }
}
