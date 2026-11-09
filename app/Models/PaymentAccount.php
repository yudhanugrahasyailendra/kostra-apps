<?php

namespace App\Models;

use App\Enums\PaymentAccountType;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rekening bank, QRIS statis, atau dompet digital milik pemilik. Hanya ditampilkan; Kostra tidak menyentuh dananya. */
class PaymentAccount extends Model implements TenantExportable
{
    /** @use HasFactory<PaymentAccountFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'property_id', 'type', 'bank_name', 'account_number', 'account_name', 'instructions', 'is_default', 'is_active',
    ];

    protected $attributes = ['is_default' => false, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'type' => PaymentAccountType::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @param  Builder<PaymentAccount>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Nama pendek untuk pilihan di formulir dan kuitansi. */
    public function displayName(): string
    {
        $name = $this->bank_name ?: $this->type->label();

        return $this->account_number ? $name.' '.$this->account_number : $name;
    }

    public static function exportName(): string
    {
        return 'rekening_pembayaran';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'property_id', 'type', 'bank_name', 'account_number', 'account_name', 'instructions',
            'is_default', 'is_active', 'created_at',
        ];
    }
}
