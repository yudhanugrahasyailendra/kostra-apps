<?php

namespace App\Services\Reports;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentProof;
use App\Models\Room;
use App\Models\Tenant;
use App\Services\WhatsApp\MessageQuota;
use Carbon\CarbonImmutable;

/**
 * Bagian dashboard yang berupa daftar ringkas (tagihan terdekat, antrean bukti, komplain, denah kamar,
 * kuota). Semua query lewat model bertenant, jadi global scope `tenant_id` berlaku. Hanya kolom yang
 * dipakai layar yang dikembalikan.
 */
class DashboardWidgets
{
    public function __construct(private MessageQuota $quota) {}

    /**
     * Tagihan terbuka yang masih bersisa, urut jatuh tempo terdekat. `days_late` negatif berarti belum jatuh tempo.
     *
     * @return array{items: list<array<string, scalar|null>>, overdue_count: int}
     */
    public function dueSoon(CarbonImmutable $today, int $limit = 5): array
    {
        $query = Invoice::query()->open()->whereColumn('total', '>', 'paid_total');

        $items = (clone $query)
            ->with(['resident', 'lease.room.type'])
            ->orderBy('due_date')->orderBy('id')
            ->limit($limit)->get()
            ->map(fn (Invoice $invoice) => [
                'invoice_id' => $invoice->id,
                'resident_name' => $invoice->resident->name,
                'room_number' => $invoice->lease?->room?->number,
                'room_type' => $invoice->lease?->room?->type?->name,
                'floor' => $invoice->lease?->room?->floor,
                'due_date' => $invoice->due_date->toDateString(),
                'days_late' => (int) $invoice->due_date->startOfDay()->diffInDays($today->startOfDay(), false),
                'outstanding' => $invoice->outstanding(),
            ])->values()->all();

        return [
            'items' => $items,
            'overdue_count' => (clone $query)->where('due_date', '<', $today->toDateString())->count(),
        ];
    }

    /** @return array{count: int, items: list<array<string, scalar|null>>} */
    public function pendingProofs(int $limit = 3): array
    {
        $query = PaymentProof::query()->pending();

        return [
            'count' => (clone $query)->count(),
            'items' => (clone $query)->with(['resident', 'invoice.lease.room'])
                ->oldest()->limit($limit)->get()
                ->map(fn (PaymentProof $proof) => [
                    'proof_id' => $proof->id,
                    'resident_name' => $proof->resident->name,
                    'room_number' => $proof->invoice?->lease?->room?->number,
                    'amount' => $proof->amount,
                ])->values()->all(),
        ];
    }

    /** @return array{count: int, items: list<array<string, scalar|null>>} */
    public function openComplaints(int $limit = 3): array
    {
        $query = Complaint::query()->whereIn('status', array_map(fn (ComplaintStatus $s) => $s->value, ComplaintStatus::open()));

        return [
            'count' => (clone $query)->count(),
            'items' => (clone $query)->with('room')->latest()->limit($limit)->get()
                ->map(fn (Complaint $complaint) => [
                    'id' => $complaint->id,
                    'title' => $complaint->title,
                    'room_number' => $complaint->room?->number,
                    'status_label' => $complaint->status->label(),
                    'status_tone' => $complaint->status->tone(),
                    'created_at' => $complaint->created_at->toIso8601String(),
                ])->values()->all(),
        ];
    }

    /**
     * Denah kamar per lantai. Status mengikuti kolom status kamar (terisi ditentukan kontrak aktif).
     *
     * @return list<array{floor: int|null, rooms: list<array<string, scalar|null>>}>
     */
    public function roomMap(): array
    {
        $residents = Lease::query()->active()->with('primaryResident')->get()
            ->mapWithKeys(fn (Lease $lease) => [$lease->room_id => $lease->primaryResident->name]);

        return Room::query()->orderBy('floor')->orderBy('number')->get()
            ->groupBy(fn (Room $room) => $room->floor ?? 0)
            ->map(fn ($rooms, $floor) => [
                'floor' => $floor === 0 ? null : (int) $floor,
                'rooms' => $rooms->map(fn (Room $room) => [
                    'id' => $room->id,
                    'number' => $room->number,
                    'status' => $room->status->value,
                    'status_label' => $room->status->label(),
                    'resident_name' => $residents[$room->id] ?? null,
                ])->values()->all(),
            ])->values()->all();
    }

    /** @return array{used: int, limit: int, remaining: int} */
    public function quota(Tenant $tenant): array
    {
        $limit = $this->quota->limit($tenant);
        $used = $this->quota->used($tenant);

        return ['used' => $used, 'limit' => $limit, 'remaining' => max(0, $limit - $used)];
    }
}
