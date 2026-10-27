<?php

namespace App\Http\Requests\Owner;

use App\Enums\ComplaintCategory;
use App\Models\Complaint;
use App\Models\Room;
use App\Models\User;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplaintIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Complaint::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['room_id', 'category', 'assigned_to', 'status'] as $key) {
            if (! filled($this->input($key))) {
                $this->merge([$key => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            // "open" = baru + diproses (bawaan), "all" = semuanya.
            'status' => ['nullable', Rule::in(['open', 'all', 'new', 'in_progress', 'done', 'rejected'])],
            'room_id' => ['nullable', new ExistsInTenant(Room::class, 'Kamar')],
            'category' => ['nullable', Rule::enum(ComplaintCategory::class)],
            // "me" = tiket saya, "none" = belum ditugaskan, atau id pengguna.
            'assigned_to' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if (in_array($value, ['me', 'none'], true)) {
                    return;
                }

                (new ExistsInTenant(User::class, 'Penanggung jawab'))->validate($attribute, $value, $fail);
            }],
        ];
    }
}
