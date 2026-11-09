<?php

namespace App\Http\Requests\Owner;

use App\Models\PaymentProof;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PaymentProof::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
            'sender_note' => ['nullable', 'string', 'max:255'],
            // mimes memeriksa tipe dari isi berkas, bukan hanya ekstensi.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required' => 'Jumlah transfer wajib diisi.',
            'amount.integer' => 'Jumlah transfer harus berupa angka bulat rupiah.',
            'amount.min' => 'Jumlah transfer minimal Rp 1.',
            'paid_on.date_format' => 'Tanggal transfer tidak valid.',
            'sender_note.max' => 'Catatan pengirim maksimal 255 karakter.',
            'file.required' => 'Pilih berkas bukti transfer.',
            'file.mimes' => 'Berkas harus berformat JPG, PNG, atau PDF.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
            'file.uploaded' => 'Berkas gagal diunggah. Periksa ukuran maksimal 5 MB.',
        ];
    }
}
