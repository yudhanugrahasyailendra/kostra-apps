<?php

namespace App\Actions\Messaging;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\MessageTemplateKey;
use App\Models\Lease;
use App\Models\MessageLog;
use App\Services\Messaging\MessageSubjects;

class QueueLeaseExpiringReminders
{
    use ResolvesTenant;

    public function __construct(
        private readonly QueueWhatsAppMessage $queue,
        private readonly MessageSubjects $subjects,
    ) {}

    /**
     * Pengingat kontrak segera habis: tepat 30, 14, dan 7 hari sebelum end_date (config kostra.lease_expiring_days).
     * Tidak dikirim dua kali pada hari yang sama untuk kontrak yang sama.
     *
     * @return int jumlah pesan yang dicatat
     */
    public function __invoke(): int
    {
        $today = $this->currentTenant()->today();
        $count = 0;

        foreach ((array) config('kostra.lease_expiring_days') as $days) {
            $leases = Lease::query()->active()
                ->where('end_date', $today->addDays((int) $days)->toDateString())
                ->with('primaryResident')
                ->get();

            foreach ($leases as $lease) {
                if ($lease->primaryResident === null || $this->alreadySentToday($lease)) {
                    continue;
                }

                $context = $this->subjects->lease($lease);

                if (($this->queue)(MessageTemplateKey::LeaseExpiring, $lease->primaryResident, $lease, $context['values']) !== null) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function alreadySentToday(Lease $lease): bool
    {
        $start = $this->currentTenant()->today()->utc();

        return MessageLog::query()
            ->where('subject_type', $lease->getMorphClass())
            ->where('subject_id', $lease->getKey())
            ->whereHas('template', fn ($query) => $query->where('key', MessageTemplateKey::LeaseExpiring->value))
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->addDay())
            ->exists();
    }
}
