<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Pengeluaran operasional. property_id null berarti pengeluaran umum semua properti. Boleh soft delete. */
class Expense extends Model implements TenantExportable
{
    /** @use HasFactory<ExpenseFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id', 'expense_category_id', 'amount', 'spent_on', 'description', 'vendor_name', 'complaint_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'spent_on' => 'date'];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public static function exportName(): string
    {
        return 'pengeluaran';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'property_id', 'expense_category_id', 'amount', 'spent_on', 'description', 'vendor_name',
            'complaint_id', 'created_by', 'created_at', 'deleted_at',
        ];
    }
}
