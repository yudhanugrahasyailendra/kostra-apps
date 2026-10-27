<?php

namespace App\Http\Requests\Owner;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Expense::class);
    }

    public function rules(): array
    {
        return [
            // YYYY-MM menurut tanggal pengeluaran, atau "all".
            'period' => ['nullable', 'regex:/^(all|\d{4}-(0[1-9]|1[0-2]))$/'],
            'property_id' => ['nullable', new ExistsInTenant(Property::class, 'Properti')],
            'expense_category_id' => ['nullable', new ExistsInTenant(ExpenseCategory::class, 'Kategori')],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
