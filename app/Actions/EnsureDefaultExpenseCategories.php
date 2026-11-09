<?php

namespace App\Actions;

use App\Models\ExpenseCategory;

/** Membuat kategori pengeluaran bawaan untuk tenant aktif. Aman dijalankan berulang (tidak menggandakan). */
class EnsureDefaultExpenseCategories
{
    public const DEFAULTS = ['Listrik', 'Air', 'Gaji penjaga', 'Perbaikan', 'Kebersihan', 'Lainnya'];

    public function handle(): void
    {
        foreach (self::DEFAULTS as $index => $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name], ['is_system' => true, 'sort_order' => $index + 1]);
        }
    }
}
