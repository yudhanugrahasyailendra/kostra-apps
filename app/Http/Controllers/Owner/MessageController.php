<?php

namespace App\Http\Controllers\Owner;

use App\Actions\ResendWhatsAppMessage;
use App\Actions\SaveMessageTemplate;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\MessageTemplateRequest;
use App\Http\Resources\MessageLogResource;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\WhatsApp\MessageQuota;
use App\Services\WhatsApp\MessageTemplateRenderer;
use App\Services\WhatsApp\WhatsAppProvider;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Template, log pengiriman, dan sisa kuota WhatsApp. Log tidak dihapus; pesan gagal dapat dikirim ulang. */
class MessageController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request, MessageQuota $quota, WhatsAppProvider $provider): Response
    {
        $this->authorize('viewAny', MessageTemplate::class);
        $this->authorize('viewAny', MessageLog::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', ...array_map(fn (MessageStatus $s) => $s->value, MessageStatus::cases())])],
        ]);
        $status = $filters['status'] ?? 'all';
        $tenant = TenantContext::instance()->tenant();

        $logs = MessageLog::query()
            ->with('resident')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        // Alasan unscoped: nama template sistem (tenant_id null) tidak terlihat TenantScope; dibatasi ke baris sistem atau milik tenant ini.
        $names = TenantContext::instance()->unscoped(fn () => MessageTemplate::query()
            ->whereIn('id', collect($logs->items())->pluck('template_id')->filter()->unique())
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->pluck('name', 'id'));

        foreach ($logs->items() as $log) {
            $log->setAttribute('template_name', $names[$log->template_id] ?? null);
        }

        $limit = $quota->limit($tenant);
        $used = $quota->used($tenant);

        return Inertia::render('Owner/Messages/Index', [
            'quota' => [
                'period' => $quota->period($tenant),
                'limit' => $limit,
                'used' => $used,
                'remaining' => max(0, $limit - $used),
            ],
            'provider' => $provider->name(),
            'templates' => $this->templates(),
            'variables' => MessageTemplateRenderer::VARIABLES,
            'logs' => MessageLogResource::collection($logs->items())->resolve(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
                'prev_url' => $logs->previousPageUrl(),
                'next_url' => $logs->nextPageUrl(),
            ],
            'filters' => ['status' => $status],
            'statuses' => array_map(fn (MessageStatus $s) => ['value' => $s->value, 'label' => $s->label()], MessageStatus::cases()),
        ]);
    }

    public function updateTemplate(MessageTemplateRequest $request, MessageTemplateKey $key, SaveMessageTemplate $save): RedirectResponse
    {
        $save->handle($key, $request->validated('body'));

        return back()->with('success', 'Templat pesan disimpan.');
    }

    public function resetTemplate(Request $request, MessageTemplateKey $key, SaveMessageTemplate $save): RedirectResponse
    {
        $this->authorize('update', [MessageTemplate::class]);

        $save->reset($key);

        return back()->with('success', 'Templat dikembalikan ke bawaan.');
    }

    public function resend(MessageLog $log, ResendWhatsAppMessage $resend): RedirectResponse
    {
        $this->authorize('resend', $log);

        $resend->handle($log);

        return back()->with('success', 'Pesan diantrekan untuk dikirim ulang.');
    }

    /** @return list<array<string, mixed>> Template yang berlaku per key: salinan tenant bila aktif, selain itu bawaan sistem. */
    private function templates(): array
    {
        return array_map(function (MessageTemplateKey $key) {
            $template = MessageTemplate::effective($key);

            return [
                'key' => $key->value,
                'label' => $key->label(),
                'body' => $template?->body ?? '',
                'is_custom' => $template !== null && ! $template->isSystem(),
                'available' => $template !== null,
            ];
        }, MessageTemplateKey::cases());
    }
}
