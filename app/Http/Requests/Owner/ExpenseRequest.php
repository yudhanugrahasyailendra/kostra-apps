<?php

namespace App\Http\Requests\Owner;

use App\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dipakai untuk tambah dan ubah pengeluaran. Kategori dan properti harus milik tenant yang sama. */
class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? $this->user()->can('update', $expense)
            : $this->user()->can('create', Expense::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now()->addDay()->toDateString()],
            'description' => ['required', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            // mimes memeriksa tipe dari isi berkas, bukan hanya ekstensi.
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Pilih kategori pengeluaran.',
            'expense_category_id.exists' => 'Kategori tidak ditemukan.',
            'property_id.exists' => 'Properti tidak ditemukan.',
            'amount.required' => 'Jumlah wajib diisi.',
            'amount.integer' => 'Jumlah harus berupa angka bulat rupiah.',
            'amount.min' => 'Jumlah harus lebih dari Rp 0.',
            'amount.max' => 'Jumlah terlalu besar.',
            'spent_on.required' => 'Tanggal pengeluaran wajib diisi.',
            'spent_on.date_format' => 'Tanggal tidak valid.',
            'spent_on.before_or_equal' => 'Tanggal pengeluaran tidak boleh di masa depan.',
            'description.required' => 'Keterangan wajib diisi.',
            'description.max' => 'Keterangan maksimal 255 karakter.',
            'vendor_name.max' => 'Nama penyedia maksimal 120 karakter.',
            'receipt.mimes' => 'Nota harus berformat JPG, PNG, atau PDF.',
            'receipt.max' => 'Ukuran nota maksimal 5 MB.',
            'receipt.uploaded' => 'Nota gagal diunggah. Periksa ukuran maksimal 5 MB.',
        ];
    }
}
