<?php

namespace App\Actions;

use App\Enums\AttachmentCategory;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SaveExpense
{
    public function __construct(private StoreAttachment $store) {}

    /**
     * Membuat atau mengubah pengeluaran, beserta nota bila diunggah. Satu transaksi supaya pengeluaran
     * tidak tersimpan tanpa notanya saat penyimpanan berkas gagal.
     *
     * @param  array{expense_category_id: int, property_id?: int|null, amount: int, spent_on: string, description: string, vendor_name?: string|null}  $data
     */
    public function handle(array $data, User $by, ?Expense $expense = null, ?UploadedFile $receipt = null): Expense
    {
        return DB::transaction(function () use ($data, $by, $expense, $receipt) {
            $attributes = [
                'expense_category_id' => (int) $data['expense_category_id'],
                'property_id' => ($data['property_id'] ?? null) ?: null,
                'amount' => (int) $data['amount'],
                'spent_on' => $data['spent_on'],
                'description' => trim($data['description']),
                'vendor_name' => trim((string) ($data['vendor_name'] ?? '')) ?: null,
            ];

            if ($expense === null) {
                $expense = new Expense($attributes);
                $expense->created_by = $by->id;
                $expense->save();
            } else {
                $expense->update($attributes);
            }

            if ($receipt !== null) {
                $this->store->handle($expense, $receipt, AttachmentCategory::ExpenseReceipt, $by);
            }

            return $expense;
        });
    }
}
