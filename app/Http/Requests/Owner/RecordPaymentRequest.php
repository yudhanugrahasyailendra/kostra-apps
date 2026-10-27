<?php

namespace App\Http\Requests\Owner;

use App\Actions\Payments\RecordPayment;
use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Resident;
use App\Rules\ExistsInTenant;
use App\Support\Rupiah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        return $this->user()->can('record', Payment::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['payment_account_id', 'reference', 'notes']);

        // Formulir mengirim allocations[id_tagihan] = nominal. Kolom kosong diabaikan; nominal dibaca sebagai rupiah bulat.
        $list = [];

        foreach ((array) $this->input('allocations', []) as $invoiceId => $raw) {
            if (is_array($raw)) {
                $list[] = $raw;

                continue;
            }

            if (! filled($raw)) {
                continue;
            }

            $list[] = [
                'invoice_id' => ctype_digit((string) $invoiceId) ? (int) $invoiceId : $invoiceId,
                'amount' => Rupiah::parse($raw) ?? $raw,
            ];
        }

        $this->merge(['allocations' => $list]);
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', new ExistsInTenant(Resident::class, 'Penghuni')],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))],
            'payment_account_id' => ['nullable', new ExistsInTenant(PaymentAccount::class, 'Rekening')],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1', 'max:'.RecordPayment::MAX_ALLOCATIONS],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'allocations.required' => 'Isi nominal pembayaran untuk sekurangnya satu tagihan.',
            'allocations.min' => 'Isi nominal pembayaran untuk sekurangnya satu tagihan.',
            'allocations.*.amount.integer' => 'Nominal harus rupiah bulat (mis. 1.500.000).',
            'allocations.*.amount.min' => 'Nominal harus lebih dari 0.',
        ];
    }

    public function attributes(): array
    {
        return [
            'resident_id' => 'Penghuni',
            'paid_on' => 'Tanggal bayar',
            'method' => 'Metode',
            'payment_account_id' => 'Rekening',
        ];
    }
}
