<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'occupation',
        'emergency_name',
        'emergency_relation',
        'emergency_phone',
        'notes',
        'whatsapp_opt_out_at',
        'consented_at',
        'is_active',
    ];

    /** @var list<string> Data pribadi: tercatat berubah di audit, tetapi nilainya disamarkan. */
    protected array $auditMasked = [
        'name', 'phone', 'email', 'occupation',
        'emergency_name', 'emergency_relation', 'emergency_phone', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_opt_out_at' => 'datetime',
            'consented_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Apakah nomor ini juga dipakai penghuni lain di tenant yang sama.
     */
    public function sharesPhoneWithOthers(): bool
    {
        return static::query()->where('phone', $this->phone)->whereKeyNot($this->getKey())->exists();
    }
}
