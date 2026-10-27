<?php

namespace App\Http\Requests\Owner;

use App\Models\MessageLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessageIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', MessageLog::class);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['all', 'queued', 'sent', 'delivered', 'failed', 'skipped'])],
        ];
    }
}
