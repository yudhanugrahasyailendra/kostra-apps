<?php

namespace App\Http\Requests\Owner;

use App\Enums\LeaseEndReason;
use App\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('checkOut', $this->route('lease'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ended_reason' => ['required', Rule::enum(LeaseEndReason::class)],
            'checkout_notes' => ['nullable', 'string', 'max:2000'],
            'deductions' => ['nullable', 'array', 'max:20'],
            'deductions.*.label' => ['nullable', 'string', 'max:100'],
            'deductions.*.amount' => ['nullable', 'required_with:deductions.*.label', 'integer', 'min:0', 'max:999999999999'],
            'deduct_outstanding' => ['nullable', 'boolean'],
            'room_status' => ['required', Rule::in([RoomStatus::Vacant->value, RoomStatus::Maintenance->value])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ended_reason.required' => 'Pilih alasan kontrak berakhir.',
            'checkout_notes.max' => 'Catatan maksimal 2000 karakter.',
            'deductions.max' => 'Maksimal 20 pos potongan.',
            'deductions.*.label.max' => 'Nama potongan maksimal 100 karakter.',
            'deductions.*.amount.integer' => 'Jumlah potongan harus berupa angka bulat rupiah.',
            'deductions.*.amount.min' => 'Jumlah potongan tidak boleh negatif.',
            'deductions.*.amount.required_with' => 'Isi jumlah potongan.',
            'room_status.required' => 'Pilih status kamar setelah check-out.',
        ];
    }
}
