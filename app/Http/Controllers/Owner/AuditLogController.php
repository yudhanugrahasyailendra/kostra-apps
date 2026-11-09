<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()->with('user:id,name')->latest('id')->limit(100)->get();

        return Inertia::render('Owner/AuditLog/Index', [
            'logs' => AuditLogResource::collection($logs)->resolve(),
        ]);
    }
}
