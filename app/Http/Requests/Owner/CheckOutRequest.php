<?php

namespace App\Http\Requests\Owner;

use App\Actions\Leases\CheckInResident;
use App\Enums\LeaseEndReason;
use App\Http\Requests\Concerns\ParsesRoomInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckOutRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        $lease = $this->route('lease');

        if (! $this->user()->can('operate', $lease)) {
            return false;
        }

        // Potongan deposit menyangkut uang: hanya pemilik. Nilai sudah dinormalisasi (prepareForValidation).
        $raw = $this->input('deduction_amount');
        $deducting = filled($raw) && $raw !== 0 && $raw !== '0';

        return ! ($deducting && ! $this->user()->can('decideMoney', $lease));
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['checkout_notes', 'deduction_note']);
        $this->normalizeMoney('deduction_amount');
    }

    public function rules(): array
    {
        return [
            'checkout_date' => ['required', 'date_format:Y-m-d'],
            'ended_reason' => ['required', Rule::in(LeaseEndReason::checkOutValues())],
            'checkout_notes' => ['nullable', 'string', 'max:2000'],
            'deduction_amount' => ['nullable', 'integer', 'min:0', 'max:'.CheckInResident::MAX_DEPOSIT],
            'deduction_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ((int) $this->input('deduction_amount') > 0 && blank($this->input('deduction_note'))) {
                    $validator->errors()->add('deduction_note', 'Tulis alasan potongan deposit.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'checkout_date' => 'Tanggal check-out',
            'ended_reason' => 'Alasan',
            'deduction_amount' => 'Potongan',
            'deduction_note' => 'Alasan potongan',
        ];
    }
}
