<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class CommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('portal_resident');
    }

    protected function getRedirectUrl(): string
    {
        return route('portal.complaints.show', ['token' => $this->route('token'), 'complaint' => $this->route('complaint')]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['body' => 'Komentar'];
    }
}
