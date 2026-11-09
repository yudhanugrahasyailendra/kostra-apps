<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model implements TenantExportable
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = ['name', 'is_system', 'sort_order'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public static function exportName(): string
    {
        return 'kategori_pengeluaran';
    }

    public static function exportColumns(): array
    {
        return ['id', 'name', 'is_system', 'sort_order', 'created_at'];
    }
}
