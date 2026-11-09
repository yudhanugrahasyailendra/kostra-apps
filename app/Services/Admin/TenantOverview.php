<?php

namespace App\Services\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\WhatsApp\MessageQuota;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * Ringkasan lintas tenant untuk panel Super Admin: langganan dan pemakaian. Pembacaan lintas tenant ini
 * disengaja (hanya Super Admin yang sampai ke sini) dan dibungkus unscoped() dengan filter tenant_id eksplisit.
 */
class TenantOverview
{
    public function __construct(private MessageQuota $quota) {}

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<int, array<string, mixed>> baris per tenant, kunci id tenant
     */
    public function rows(Collection $tenants): array
    {
        $ids = $tenants->pluck('id')->all();

        // Alasan unscoped: panel Super Admin membaca banyak tenant sekaligus; setiap query dibatasi whereIn(tenant_id).
        [$properties, $rooms, $users, $subscriptions, $usedMessages] = TenantContext::instance()->unscoped(fn () => [
            Property::query()->whereIn('tenant_id', $ids)->selectRaw('tenant_id, count(*) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id'),
            Room::query()->whereIn('tenant_id', $ids)->selectRaw('tenant_id, count(*) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id'),
            User::query()->whereIn('tenant_id', $ids)->selectRaw('tenant_id, count(*) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id'),
            Subscription::query()->whereIn('tenant_id', $ids)
                ->whereIn('status', array_map(fn (SubscriptionStatus $s) => $s->value, SubscriptionStatus::current()))
                ->with('plan')->orderBy('id')->get()->keyBy('tenant_id'),
            UsageCounter::query()->whereIn('tenant_id', $ids)->where('metric', MessageQuota::METRIC)
                ->get()->groupBy('tenant_id'),
        ]);

        $rows = [];

        foreach ($tenants as $tenant) {
            $subscription = $subscriptions->get($tenant->id);
            $period = $this->quota->period($tenant);
            $messages = $usedMessages->get($tenant->id)?->firstWhere('period', $period)?->used ?? 0;

            $rows[$tenant->id] = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status->value,
                'status_label' => $tenant->status->label(),
                'created_at' => $tenant->created_at?->toIso8601String(),
                'plan_name' => $tenant->plan->name,
                'subscription_status' => $subscription?->status->value,
                'subscription_status_label' => $subscription?->status->label(),
                'period_end' => $subscription?->current_period_end->toDateString(),
                'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
                'grace_ends_at' => $subscription?->grace_ends_at?->toIso8601String(),
                'usage' => [
                    'properties' => (int) ($properties[$tenant->id] ?? 0),
                    'max_properties' => $tenant->plan->max_properties,
                    'rooms' => (int) ($rooms[$tenant->id] ?? 0),
                    'max_rooms' => $tenant->plan->max_rooms,
                    'users' => (int) ($users[$tenant->id] ?? 0),
                    'max_users' => $tenant->plan->max_users,
                    'messages' => (int) $messages,
                    'message_quota' => $this->quota->limit($tenant),
                ],
            ];
        }

        return $rows;
    }
}
