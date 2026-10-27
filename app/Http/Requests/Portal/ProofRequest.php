<?php

namespace App\Http\Requests\Portal;

use App\Http\Requests\Concerns\ParsesRoomInput;
use Illuminate\Foundation\Http\FormRequest;

class ProofRequest extends FormRequest
{
    use ParsesRoomInput;

    /**
     * Otorisasi adalah token di URL (ResolvePortalToken); penghuni tidak punya akun.
     */
    public function authorize(): bool
    {
        return $this->attributes->has('portal_resident');
    }

    protected function getRedirectUrl(): string
    {
        return route('portal.invoices.show', ['token' => $this->route('token'), 'invoice' => $this->route('invoice')]);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['paid_on', 'sender_note']);
        $this->normalizeMoney('amount');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
            'sender_note' => ['nullable', 'string', 'max:255'],
            // Isi berkas diperiksa ulang oleh StoreAttachment (bukan hanya ekstensi).
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.integer' => 'Nominal harus rupiah bulat (mis. 1.500.000).',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
            'file.mimes' => 'Berkas harus berupa JPG, PNG, atau PDF.',
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'Nominal', 'paid_on' => 'Tanggal transfer', 'sender_note' => 'Catatan', 'file' => 'Bukti transfer'];
    }
}
