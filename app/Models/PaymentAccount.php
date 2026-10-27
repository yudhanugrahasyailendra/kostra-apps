<?php

namespace App\Models;

use App\Enums\PaymentAccountType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PaymentAccount extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'property_id',
        'type',
        'bank_name',
        'account_number',
        'account_name',
        'instructions',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentAccountType::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function qrisImage(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('category', 'qris_image');
    }

    public function label(): string
    {
        return match ($this->type) {
            PaymentAccountType::Qris => 'QRIS'.($this->account_name ? ' '.$this->account_name : ''),
            default => trim(($this->bank_name ?? '').' '.($this->account_number ?? '')),
        };
    }
}
