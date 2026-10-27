<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Kategori bawaan tiap tenant, sesuai urutan tampil. */
    public const DEFAULTS = ['Listrik', 'Air', 'Gaji penjaga', 'Perbaikan', 'Kebersihan', 'Lainnya'];

    protected $fillable = [
        'tenant_id',
        'name',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
