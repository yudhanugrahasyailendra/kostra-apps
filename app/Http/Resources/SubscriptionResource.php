<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Langganan berjalan. Relasi `plan` dimuat pemanggil.
 *
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_key' => $this->plan->key,
            'plan_name' => $this->plan->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'billing_period' => $this->billing_period->value,
            'billing_period_label' => $this->billing_period->label(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_start' => $this->current_period_start->toDateString(),
            'current_period_end' => $this->current_period_end->toDateString(),
            'grace_ends_at' => $this->grace_ends_at?->toIso8601String(),
        ];
    }
}
