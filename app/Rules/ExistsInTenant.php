<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Memastikan id merujuk baris milik tenant aktif (lewat model, jadi global scope dan
 * soft delete berlaku). Id tenant lain diperlakukan seperti tidak ada.
 */
class ExistsInTenant implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(private readonly string $model, private readonly string $label = 'Data') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! $this->model::query()->whereKey($value)->exists()) {
            $fail("{$this->label} tidak ditemukan.");
        }
    }
}
