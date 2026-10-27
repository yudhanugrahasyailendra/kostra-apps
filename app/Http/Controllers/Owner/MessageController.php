<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Messaging\ResendMessage;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\MessageIndexRequest;
use App\Models\MessageLog;
use App\Services\Messaging\MessageQuota;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(MessageIndexRequest $request, TenantContext $context, MessageQuota $quota): View
    {
        $tenant = $context->current();
        $status = $request->validated('status') ?? 'all';

        $counts = MessageLog::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('owner.messages.index', [
            'logs' => MessageLog::query()
                ->with(['resident:id,name', 'template:id,key,name'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(25)->withQueryString(),
            'status' => $status,
            'statuses' => MessageStatus::cases(),
            'counts' => collect(MessageStatus::cases())->mapWithKeys(fn (MessageStatus $case) => [$case->value => (int) ($counts[$case->value] ?? 0)])->all(),
            'quota' => [
                'limit' => $quota->limit($tenant),
                'used' => $quota->used($tenant),
                'remaining' => $quota->remaining($tenant),
                'period' => $tenant->today()->locale('id')->translatedFormat('F Y'),
            ],
        ]);
    }

    public function resend(MessageLog $log, ResendMessage $resend): RedirectResponse
    {
        Gate::authorize('resend', $log);

        $resend($log);

        return redirect()->route('owner.messages.index')->with('status', 'Pesan diantrekan untuk dikirim ulang.');
    }
}
