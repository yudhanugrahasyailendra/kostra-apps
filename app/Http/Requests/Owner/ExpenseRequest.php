<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? $this->user()->can('update', $expense)
            : $this->user()->can('create', Expense::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['property_id', 'vendor_name']);
        $this->normalizeMoney('amount');
    }

    public function rules(): array
    {
        return [
            'property_id' => ['nullable', new ExistsInTenant(Property::class, 'Properti')],
            'expense_category_id' => ['required', new ExistsInTenant(ExpenseCategory::class, 'Kategori')],
            'amount' => ['required', 'integer', 'min:1'],
            // Pengeluaran dicatat setelah terjadi: tidak boleh bertanggal setelah hari ini (zona waktu tenant).
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->user()->tenant->today()->toDateString()],
            'description' => ['required', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.integer' => 'Jumlah harus rupiah bulat (mis. 250.000).',
            'amount.min' => 'Jumlah harus lebih dari 0.',
            'spent_on.before_or_equal' => 'Tanggal pengeluaran tidak boleh setelah hari ini.',
        ];
    }

    public function attributes(): array
    {
        return [
            'expense_category_id' => 'Kategori',
            'amount' => 'Jumlah',
            'spent_on' => 'Tanggal',
            'description' => 'Keterangan',
            'vendor_name' => 'Penerima atau vendor',
            'file' => 'Nota',
        ];
    }
}
