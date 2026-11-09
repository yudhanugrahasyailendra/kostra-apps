<?php

namespace App\Models;

use App\Enums\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'name', 'price_monthly', 'price_yearly', 'max_properties', 'max_rooms',
        'max_users', 'monthly_message_quota', 'features', 'is_active', 'sort_order',
    ];

    /** Semua kunci fitur yang dikenal; plans.features berisi himpunan bagiannya. */
    public const FEATURES = [
        'rooms.manual_billing', 'billing.auto', 'whatsapp.reminders', 'reports.basic',
        'reports.advanced', 'users.multi', 'support.priority', 'export.advanced',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function isFree(): bool
    {
        return (int) $this->price_monthly === 0;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    /** Harga satu periode dalam rupiah; null bila periode itu tidak dijual. */
    public function priceFor(SubscriptionPeriod $period): ?int
    {
        return match ($period) {
            SubscriptionPeriod::Monthly => $this->price_monthly,
            SubscriptionPeriod::Yearly => $this->price_yearly,
        };
    }
}
