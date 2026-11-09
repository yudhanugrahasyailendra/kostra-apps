<?php

namespace App\Actions;

use App\Enums\MessageStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\MessageLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResendWhatsAppMessage
{
    public function __construct(private IssuePortalToken $portal) {}

    /**
     * Kirim ulang pesan yang gagal dari log (NTF-05). Baris yang sama diantrekan lagi dengan penghitung
     * percobaan nol; opt-out, jam kirim, dan kuota diperiksa ulang seperti pesan baru.
     */
    public function handle(MessageLog $log): MessageLog
    {
        $log = DB::transaction(function () use ($log) {
            $locked = MessageLog::query()->lockForUpdate()->findOrFail($log->id);

            if ($locked->status !== MessageStatus::Failed) {
                throw ValidationException::withMessages(['message' => 'Hanya pesan yang gagal yang dapat dikirim ulang.']);
            }

            // Pesan gagal menyimpan tautan yang sudah disamarkan; terbitkan token baru supaya kirim ulang tetap berguna.
            if (str_contains($locked->body, '/p/[tautan-rahasia]') && $locked->resident !== null && ! $locked->resident->trashed()) {
                $locked->body = str_replace(
                    '/p/[tautan-rahasia]',
                    parse_url($this->portal->url($locked->resident), PHP_URL_PATH),
                    $locked->body,
                );
            }

            $locked->forceFill([
                'status' => MessageStatus::Queued,
                'skip_reason' => null,
                'attempts' => 0,
                'error_code' => null,
                'error_message' => null,
                'failed_at' => null,
                'scheduled_for' => now(),
            ])->save();

            return $locked;
        });

        SendWhatsAppMessage::dispatch(TenantContext::instance()->id(), $log->id)->afterCommit();

        return $log;
    }
}
