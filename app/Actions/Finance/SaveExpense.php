<?php

namespace App\Actions\Finance;

use App\Actions\Attachments\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SaveExpense
{
    public function __construct(private readonly StoreAttachment $store) {}

    /**
     * Membuat atau memperbarui pengeluaran beserta nota (opsional) dalam satu transaksi. Properti dan kategori
     * dicari lewat model ber-scope tenant, jadi id milik tenant lain gagal sebagai tidak ditemukan.
     *
     * @param  array{property_id?: ?int, expense_category_id: int, amount: int, spent_on: string, description: string, vendor_name?: ?string}  $data
     */
    public function __invoke(array $data, User $by, ?UploadedFile $receipt = null, ?Expense $expense = null): Expense
    {
        if (($data['property_id'] ?? null) !== null) {
            Property::query()->findOrFail($data['property_id']);
        }

        ExpenseCategory::query()->findOrFail($data['expense_category_id']);

        return DB::transaction(function () use ($data, $by, $receipt, $expense) {
            $fields = [
                'property_id' => $data['property_id'] ?? null,
                'expense_category_id' => $data['expense_category_id'],
                'amount' => $data['amount'],
                'spent_on' => $data['spent_on'],
                'description' => $data['description'],
                'vendor_name' => $data['vendor_name'] ?? null,
            ];

            if ($expense === null) {
                $expense = Expense::create($fields + ['created_by' => $by->getKey()]);
            } else {
                $expense->update($fields);
            }

            if ($receipt !== null) {
                ($this->store)($expense, $receipt, AttachmentCategory::ExpenseReceipt, $by);
            }

            return $expense;
        });
    }
}
