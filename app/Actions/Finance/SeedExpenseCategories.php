<?php

namespace App\Actions\Finance;

use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;

class SeedExpenseCategories
{
    /**
     * Membuat kategori bawaan tenant aktif bila belum ada. Aman dipanggil berulang: kategori yang sudah
     * ada (termasuk yang namanya sama buatan pemilik) dibiarkan, tanpa duplikat.
     */
    public function __invoke(): void
    {
        DB::transaction(function () {
            $existing = ExpenseCategory::query()->pluck('name')->map(fn (string $name) => mb_strtolower($name))->all();

            foreach (ExpenseCategory::DEFAULTS as $order => $name) {
                if (in_array(mb_strtolower($name), $existing, true)) {
                    continue;
                }

                ExpenseCategory::create(['name' => $name, 'is_system' => true, 'sort_order' => $order + 1]);
            }
        });
    }
}
