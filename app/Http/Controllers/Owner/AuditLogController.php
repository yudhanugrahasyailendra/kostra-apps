<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /** Aksi yang dikenal, dipakai untuk filter dan label. */
    private const EVENTS = ['created', 'updated', 'deleted', 'invited_staff', 'exported_data'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'event' => ['nullable', 'in:'.implode(',', self::EVENTS)],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($filters['month'] ?? null, function ($q, $month) {
                [$year, $mon] = explode('-', $month);

                return $q->whereYear('created_at', $year)->whereMonth('created_at', $mon);
            });

        $logs = $query->latest('id')->paginate(15)->withQueryString();

        return Inertia::render('Owner/AuditLog/Index', [
            'logs' => AuditLogResource::collection($logs->items())->resolve(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
                'prev_url' => $logs->previousPageUrl(),
                'next_url' => $logs->nextPageUrl(),
            ],
            'stats' => [
                'total' => AuditLog::query()->count(),
                'changes' => AuditLog::query()->where('event', 'updated')->count(),
            ],
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'event' => $filters['event'] ?? null,
                'month' => $filters['month'] ?? null,
            ],
            // Daftar pengguna terscope tenant: hanya id dan nama.
            'users' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all(),
        ]);
    }
}
