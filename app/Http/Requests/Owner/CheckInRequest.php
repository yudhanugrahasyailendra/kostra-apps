<?php

namespace App\Http\Requests\Owner;

use App\Models\Lease;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Lease::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', TenantRules::exists('rooms', softDeletes: true)],
            'primary_resident_id' => ['required', 'integer', TenantRules::exists('residents', softDeletes: true)],
            'resident_ids' => ['nullable', 'array', 'max:5'],
            'resident_ids.*' => ['integer', 'distinct', TenantRules::exists('residents', softDeletes: true)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'rent_amount' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'deposit_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'deposit_received_at' => ['nullable', 'date_format:Y-m-d'],
            'checkin_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Input kosong dari form berarti "tidak diisi", bukan 0.
        foreach (['duration_months', 'rent_amount', 'due_day', 'deposit_amount', 'deposit_received_at'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === null) {
                $this->merge([$key => null]);
            }
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Pilih kamar.',
            'room_id.exists' => 'Kamar tidak ditemukan.',
            'primary_resident_id.required' => 'Pilih penghuni penanggung jawab.',
            'primary_resident_id.exists' => 'Penghuni tidak ditemukan.',
            'resident_ids.max' => 'Penghuni tambahan maksimal 5 orang.',
            'resident_ids.*.exists' => 'Penghuni tambahan tidak ditemukan.',
            'resident_ids.*.distinct' => 'Penghuni tambahan tidak boleh ganda.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date_format' => 'Format tanggal mulai tidak valid.',
            'duration_months.min' => 'Durasi minimal 1 bulan.',
            'duration_months.max' => 'Durasi maksimal 120 bulan.',
            'rent_amount.integer' => 'Harga sewa harus berupa angka bulat rupiah.',
            'due_day.min' => 'Tanggal jatuh tempo antara 1 dan 28.',
            'due_day.max' => 'Tanggal jatuh tempo antara 1 dan 28.',
            'deposit_amount.integer' => 'Deposit harus berupa angka bulat rupiah.',
            'deposit_received_at.date_format' => 'Format tanggal terima deposit tidak valid.',
        ];
    }
}
