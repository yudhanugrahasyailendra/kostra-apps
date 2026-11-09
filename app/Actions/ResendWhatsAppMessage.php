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
