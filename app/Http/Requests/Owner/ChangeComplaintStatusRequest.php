<?php

namespace App\Http\Requests\Owner;

use App\Enums\ComplaintStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeComplaintStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complaint'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ComplaintStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'Status'];
    }
}
