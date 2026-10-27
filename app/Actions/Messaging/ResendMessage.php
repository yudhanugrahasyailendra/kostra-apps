<?php

namespace App\Actions\Messaging;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\MessageStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\BillingSetting;
use App\Models\MessageLog;
use App\Services\Messaging\SendWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResendMessage
{
    use ResolvesTenant;

    public function __construct(private readonly SendWindow $window) {}

    /**
     * Mengirim ulang pesan yang gagal dari log: baris yang sama diantrekan lagi (bukan baris baru), dengan
     * hitungan percobaan dari nol. Kuota dipakai lagi saat pengiriman, karena kuota pesan gagal sudah dikembalikan.
     */
    public function __invoke(MessageLog $log): MessageLog
    {
        $tenant = $this->currentTenant();

        $locked = DB::transaction(function () use ($log, $tenant) {
            $locked = MessageLog::query()->lockForUpdate()->findOrFail($log->getKey());

            if (! $locked->canResend()) {
                throw ValidationException::withMessages(['general' => 'Hanya pesan gagal yang punya nomor penerima yang bisa dikirim ulang.']);
            }

            $settings = BillingSetting::current();

            $locked->update([
                'status' => MessageStatus::Queued,
                'attempts' => 0,
                'error_code' => null,
                'error_message' => null,
                'failed_at' => null,
                'counted_in_quota' => false,
                'scheduled_for' => $this->window->nextAllowed($tenant, $settings->send_window_start, $settings->send_window_end, now()->toImmutable()),
            ]);

            return $locked;
        });

        $job = SendWhatsAppMessage::dispatch($locked->getKey(), $tenant->getKey())->afterCommit();

        if ($locked->scheduled_for->greaterThan(now())) {
            $job->delay($locked->scheduled_for);
        }

        return $locked;
    }
}
