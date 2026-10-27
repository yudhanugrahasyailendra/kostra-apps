<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Messaging\SaveMessageTemplate;
use App\Enums\MessageTemplateKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\MessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Services\Messaging\MessageTemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MessageTemplateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', MessageTemplate::class);

        return view('owner.messages.templates', [
            'rows' => collect(MessageTemplateKey::cases())->map(fn (MessageTemplateKey $key) => [
                'key' => $key,
                'template' => MessageTemplate::resolve($key),
            ]),
        ]);
    }

    public function edit(string $key): View
    {
        Gate::authorize('update', MessageTemplate::class);

        $templateKey = $this->key($key);

        return view('owner.messages.template-edit', [
            'key' => $templateKey,
            'template' => MessageTemplate::resolve($templateKey),
            'placeholders' => array_map(fn (string $variable) => '{{ '.$variable.' }}', MessageTemplateRenderer::VARIABLES),
            'default' => $templateKey->defaultBody(),
        ]);
    }

    public function update(MessageTemplateRequest $request, string $key, SaveMessageTemplate $save): RedirectResponse
    {
        $save($this->key($key), $request->validated());

        return redirect()->route('owner.message-templates.index')->with('status', 'Template disimpan.');
    }

    private function key(string $key): MessageTemplateKey
    {
        return MessageTemplateKey::tryFrom($key) ?? abort(404);
    }
}
