<?php

namespace App\Http\Requests\Owner;

use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class TransferRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transfer', $this->route('lease'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to_room_id' => ['required', 'integer', TenantRules::exists('rooms', softDeletes: true)],
            'transferred_on' => ['nullable', 'date_format:Y-m-d'],
            'new_rent_amount' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['new_rent_amount', 'transferred_on'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to_room_id.required' => 'Pilih kamar tujuan.',
            'to_room_id.exists' => 'Kamar tujuan tidak ditemukan.',
            'transferred_on.date_format' => 'Format tanggal pindah tidak valid.',
            'new_rent_amount.integer' => 'Harga sewa baru harus berupa angka bulat rupiah.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }
}
